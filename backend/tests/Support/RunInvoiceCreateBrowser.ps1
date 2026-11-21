# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param(
    [switch]$Provision,
    [switch]$KeepAlive,
    [ValidateRange(1, 120)][int]$MaxMinutes = 30,
    [ValidateSet('None', 'Prepare', 'Ready')][string]$InjectFailureAfter = 'None'
)
#Requires -Version 7.0
if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$Scenario = 'InvoiceCreate'
$development = $null
$childTerminationFailed = $false
if ($KeepAlive -and !$env:STAYHUB_TV3_AC_BROWSER_PASSWORD) { throw 'KeepAlive requires an owner-supplied ephemeral password in STAYHUB_TV3_AC_BROWSER_PASSWORD.' }
$schema = "stayhub_tv3_ac_test_$runId"
$testUser = 'tv3ac_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testAppKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$fixturePassword = if ($env:STAYHUB_TV3_AC_BROWSER_PASSWORD) { $env:STAYHUB_TV3_AC_BROWSER_PASSWORD } else { [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24)) }
if ($fixturePassword.Length -lt 24) { throw 'Ephemeral fixture password must contain at least 24 characters.' }
$evidence = Join-Path $repo ".agent-workspace/tv3/inv-create-ui-01a/$runId"
$null = New-Item -ItemType Directory -Path $evidence
$php = (Get-Command php -ErrorAction Stop).Source
$docker = (Get-Command docker -ErrorAction Stop).Source
$container = $null
$manifest = $null
$schemaCreated = $false
$userCreated = $false
$before = $null
$protectedBefore = $null
$failure = $null
$cleanupFailure = $null
$servers = @()

function Protect-Text([string]$Value) {
    return $Value.Replace($testPassword, '[REDACTED]').Replace($testAppKey, '[REDACTED]').Replace($fixturePassword, '[REDACTED]')
}

function Save-Json([string]$Name, $Value) {
    ConvertTo-Json -InputObject $Value -Depth 12 | Set-Content -LiteralPath (Join-Path $evidence $Name) -Encoding utf8
}

function Invoke-Child([string]$Exe, [string[]]$Arguments, [string]$InputText = '', [hashtable]$Environment = @{}, [switch]$AllowFailure, [string]$WorkingDirectory = $repo, [int]$TimeoutSeconds = 180) {
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $Exe
    $start.WorkingDirectory = $WorkingDirectory
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardInputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    $started = $false
    try {
        $started = $process.Start()
        $outTask = $process.StandardOutput.ReadToEndAsync()
        $errTask = $process.StandardError.ReadToEndAsync()
        $process.StandardInput.Write($InputText)
        $process.StandardInput.Close()
        if (!$process.WaitForExit($TimeoutSeconds * 1000)) {
            throw 'Owned child process exceeded its timeout.'
        }
        $result = @{ exit = $process.ExitCode; stdout = $outTask.GetAwaiter().GetResult(); stderr = $errTask.GetAwaiter().GetResult() }
    } finally {
        try {
            if ($started -and !$process.HasExited) {
                $process.Kill($true)
                if (!$process.WaitForExit(10000)) { throw 'Owned child did not exit after termination.' }
            }
        } catch {
            $script:childTerminationFailed = $true
            throw
        } finally {
            $process.Dispose()
        }
    }
    if ($result.exit -ne 0 -and !$AllowFailure) {
        throw "Child process failed (exit $($result.exit)): $(Protect-Text $result.stderr)"
    }
    return $result
}

function Admin-Sql([string]$Sql) {
    # The root secret remains inside the container environment, never in argv or evidence.
    $argsList = @('exec', '-i', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --database=information_schema --batch --raw --skip-column-names')
    return (Invoke-Child $docker $argsList ($Sql + "`n")).stdout.Trim()
}

function Hash-Text([string]$Value) {
    return [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($Value)))
}

function Development-Fingerprint {
    $tables = @( (Admin-Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$development' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`r?`n" | Where-Object { $_ })
    if ($tables.Count -eq 0) { throw 'Cannot establish development database fingerprint.' }
    $counts = [ordered]@{}
    foreach ($table in $tables) {
        if ($table -notmatch '^[a-zA-Z0-9_]+$') { throw 'Unexpected table name in development fingerprint.' }
        $counts[$table] = Admin-Sql ('SELECT COUNT(*) FROM `' + $development + '`.`' + $table + '`;')
    }
    # Raw dump stays in memory only. Persist only SHA256 and row counts, never records.
    $dump = Invoke-Child $docker @('exec', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --skip-comments --compact --skip-add-locks --skip-disable-keys --hex-blob --order-by-primary --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"')
    return @{ tables = $counts; sha256 = Hash-Text $dump.stdout }
}

function Assert-AdminIdentity {
    $observed = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid, CURRENT_USER();') -split "`t"
    if ($observed.Count -ne 4 -or $observed[0] -ne 'information_schema' -or
        $observed[1] -ne $manifest.version -or $observed[2] -ne $manifest.server_uuid -or $observed[3] -ne $manifest.admin_account) {
        throw 'Administrative connection instance changed; refuse writes or cleanup.'
    }
}

function Protected-Fingerprint {
    $paths = @((Invoke-Child 'git' @('ls-files')).stdout -split "`r?`n" | Where-Object { $_ })
    # Protect all nonignored untracked work, including the accepted UC48 work and TV3 matrix.
    $paths += @((Invoke-Child 'git' @('ls-files', '--others', '--exclude-standard')).stdout -split "`r?`n" | Where-Object { $_ })
    $paths += @('.env', 'backend/.env', 'frontend/.env')
    $hashes = [ordered]@{}
    foreach ($path in ($paths | Sort-Object -Unique)) {
        $absolute = Join-Path $repo $path
        $hashes[$path] = if (Test-Path -LiteralPath $absolute -PathType Leaf) { (Get-FileHash -LiteralPath $absolute -Algorithm SHA256).Hash } else { 'ABSENT' }
    }
    return $hashes
}

try {
    if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }
    if (Test-Path -LiteralPath (Join-Path $backend 'bootstrap/cache/config.php')) { throw 'Refuse cached Laravel configuration.' }
    if ($env:APP_CONFIG_CACHE -or $env:APP_BASE_PATH -or (Test-Path -LiteralPath (Join-Path $repo '.env.testing'))) { throw 'Refuse alternate cache/env/base path.' }
    $protectedBefore = Protected-Fingerprint
    Save-Json 'protected-before.json' $protectedBefore
    $phpProbe = Invoke-Child $php @('-r', 'if (PHP_VERSION_ID < 80200 || !extension_loaded("pdo_mysql")) {exit(1);} echo PHP_VERSION;')
    $ids = (Invoke-Child $docker @('compose', 'ps', '-q', 'mysql')).stdout.Trim()
    if ($ids -notmatch '^[a-f0-9]{12,64}$') { throw 'Expected exactly one existing Compose MySQL container; none created or started by runner.' }
    $container = $ids
    $info = @((Invoke-Child $docker @('inspect', $container)).stdout | ConvertFrom-Json)[0]
    if ($info.Config.Image -ne 'mysql:8.4' -or !$info.State.Running -or $info.State.Health.Status -ne 'healthy') {
        throw 'Expected healthy existing mysql:8.4 container.'
    }
    if ($info.Config.Labels.'com.docker.compose.service' -ne 'mysql' -or
        [IO.Path]::GetFullPath($info.Config.Labels.'com.docker.compose.project.working_dir') -ne $repo) {
        throw 'Compose container does not belong to this checkout.'
    }
    $bindings = @($info.NetworkSettings.Ports.'3306/tcp')
    if ($bindings.Count -ne 1 -or $bindings[0].HostIp -ne '127.0.0.1') { throw 'Expected one loopback TCP binding.' }
    $port = [string]$bindings[0].HostPort
    # Parse selectors only. Root credentials never leave memory or enter evidence.
    $selectorProbe = @'
require 'backend/vendor/autoload.php';
$v = Dotenv\Dotenv::parse(file_get_contents('.env'));
if (!empty($v['APP_CONFIG_CACHE']) || !empty($v['DB_URL']) || !empty($v['DB_SOCKET'])) {exit(2);}
$keys = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE'];
foreach ($keys as $key) {
    if (getenv($key) !== false && getenv($key) !== ($v[$key] ?? null)) {exit(3);}
}
echo json_encode(array_intersect_key($v, array_flip($keys)), JSON_THROW_ON_ERROR);
'@
    $selectors = (Invoke-Child $php @('-r', $selectorProbe)).stdout | ConvertFrom-Json
    $developmentEntry = @($info.Config.Env | Where-Object { $_.StartsWith('MYSQL_DATABASE=') })
    if ($developmentEntry.Count -ne 1) { throw 'Missing unambiguous Compose development schema.' }
    $development = $developmentEntry[0].Substring('MYSQL_DATABASE='.Length)
    if ($development -notmatch '^[a-zA-Z0-9_]{1,64}$' -or $development.StartsWith('stayhub_tv3_ac_test_') -or
        $selectors.DB_CONNECTION -ne 'mysql' -or $selectors.DB_HOST -ne '127.0.0.1' -or
        [string]$selectors.DB_PORT -ne $port -or $selectors.DB_DATABASE -cne $development) {
        throw 'Root development target does not match the observed Compose instance.'
    }
    $identity = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid, CURRENT_USER();') -split "`t"
    if ($identity.Count -ne 4 -or $identity[0] -ne 'information_schema' -or $identity[1] -notmatch '^8\.4\.' -or $identity[2] -notmatch '^[a-f0-9-]{36}$' -or $identity[3] -ne 'root@localhost') {
        throw 'Unexpected administrative connection identity.'
    }
    function Free-Port {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        try { return $listener.LocalEndpoint.Port } finally { $listener.Stop() }
    }
    $backendPort = Free-Port
    do { $frontendPort = Free-Port } while ($frontendPort -eq $backendPort)
    $manifest = @{ scenario = $Scenario; backend_url = "http://localhost:$backendPort"; frontend_url = "http://localhost:$frontendPort"; run_id = $runId; database = $schema; username = $testUser; host = '127.0.0.1'; port = $port;
        development_database = $development; admin_account = $identity[3]; server_uuid = $identity[2]; version = $identity[1]; container = $container; php = $phpProbe.stdout;
        head = (Invoke-Child 'git' @('rev-parse', 'HEAD')).stdout.Trim(); created_utc = [DateTime]::UtcNow.ToString('o') }
    Save-Json 'manifest.json' $manifest
    $before = Development-Fingerprint
    Save-Json 'development-before.json' $before
    if ((Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';") -ne '0' -or
        (Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';") -ne '0') {
        throw 'Owned names already exist; refuse to adopt or overwrite resources.'
    }
    Assert-AdminIdentity
    $null = Admin-Sql ('CREATE DATABASE `' + $schema + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    $schemaCreated = $true
    Assert-AdminIdentity
    $null = Admin-Sql "CREATE USER '$testUser'@'%' IDENTIFIED BY '$testPassword';"
    $userCreated = $true
    $literalGrantSchema = $schema.Replace('_', '\_')
    Assert-AdminIdentity
    $null = Admin-Sql ('GRANT ALL PRIVILEGES ON `' + $literalGrantSchema + '`.* TO ' + "'$testUser'@'%';")
    $envMap = @{ APP_ENV = 'local'; APP_KEY = $testAppKey;
        DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = $port; DB_DATABASE = $schema;
        DB_USERNAME = $testUser; DB_PASSWORD = $testPassword; DB_URL = ''; DB_SOCKET = '';
        DB_CHARSET = 'utf8mb4'; DB_COLLATION = 'utf8mb4_unicode_ci'; MYSQL_ATTR_SSL_CA = '';
        BCRYPT_ROUNDS = '4'; MAIL_MAILER = 'array'; BROADCAST_CONNECTION = 'null';
        CACHE_STORE = 'array'; SESSION_DRIVER = 'database'; QUEUE_CONNECTION = 'sync'; LOG_CHANNEL = 'null';
        STAYHUB_TV3_AC_BROWSER_OPT_IN = '1'; STAYHUB_TV3_AC_OPT_IN = '1'; STAYHUB_TV3_AC_MANIFEST = (Join-Path $evidence 'manifest.json') }
    $envMap.STAYHUB_TV3_AC_CREATE_BROWSER_OPT_IN = '1'
    $envMap.APP_URL = $manifest.backend_url
    $envMap.APP_DEBUG = 'false'
    $envMap.STAYHUB_TV3_AC_BROWSER_PASSWORD = $fixturePassword
    $prepared = Invoke-Child $php @('tests/Support/InvoiceCreateBrowserServer.php', 'prepare') '' $envMap -WorkingDirectory $backend
    $fixture = $prepared.stdout | ConvertFrom-Json -AsHashtable
    Save-Json 'fixture.json' $fixture
    if ($InjectFailureAfter -eq 'Prepare') { throw 'INV_CREATE_UI_EXPECTED_FAILURE_AFTER_PREPARE' }
    $envMap.Remove('STAYHUB_TV3_AC_BROWSER_PASSWORD')
    function Start-Owned([string]$Exe, [string[]]$Arguments, [hashtable]$Environment, [string]$Directory) {
        $start = [Diagnostics.ProcessStartInfo]::new()
        $start.FileName = $Exe; $start.WorkingDirectory = $Directory
        $start.UseShellExecute = $false; $start.CreateNoWindow = $true
        $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true
        $start.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
        $start.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
        foreach ($arg in $Arguments) { $start.ArgumentList.Add($arg) }
        foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
        # Do not forward the fixture login password to long-running servers.
        $start.Environment.Remove('STAYHUB_TV3_AC_BROWSER_PASSWORD') | Out-Null
        $process = [Diagnostics.Process]::new(); $process.StartInfo = $start
        $null = $process.Start()
        return @{ process = $process; output = $process.StandardOutput.ReadToEndAsync(); error = $process.StandardError.ReadToEndAsync() }
    }
    $servers += Start-Owned $php @('-S', "127.0.0.1:$backendPort", '-t', 'public', 'tests/Support/InvoiceCreateBrowserServer.php') $envMap $backend
    $frontendEnv = @{ VITE_BACKEND_URL = $manifest.backend_url; VITE_ENABLE_BOOKING_MOCKS = 'false'; VITE_ENABLE_PROPERTY_MOCKS = 'false' }
    $servers += Start-Owned (Get-Command node).Source @('node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', [string]$frontendPort, '--strictPort') $frontendEnv (Join-Path $repo 'frontend')
    $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $headers = @{ Accept = 'application/json'; Origin = $manifest.frontend_url; Referer = $manifest.frontend_url + '/' }
    $ready = $false
    for ($i = 0; $i -lt 50; $i++) {
        foreach ($server in $servers) { if ($server.process.HasExited) { throw 'Owned server exited before readiness.' } }
        try {
            $csrf = Invoke-WebRequest ($manifest.backend_url + '/sanctum/csrf-cookie') -WebSession $session -Headers $headers -SkipHttpErrorCheck -TimeoutSec 2
            $front = Invoke-WebRequest $manifest.frontend_url -SkipHttpErrorCheck -TimeoutSec 2
            if ($csrf.StatusCode -eq 204 -and $front.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 200
    }
    if (!$ready) { throw 'Owned server readiness timed out.' }

    if ($InjectFailureAfter -eq 'Ready') { throw 'INV_CREATE_UI_EXPECTED_FAILURE_AFTER_READY' }
    function Server-Command([string]$Mode, [string]$InputText = '') {
        $result = Invoke-Child $php @('tests/Support/InvoiceCreateBrowserServer.php', $Mode) $InputText $envMap -WorkingDirectory $backend
        try { return $result.stdout | ConvertFrom-Json -AsHashtable }
        catch {
            Save-Json 'command-error.json' @{mode = $Mode; output = (Protect-Text $result.stdout); error = (Protect-Text $result.stderr)}
            throw
        }
    }
    function Assert-Status($Response, [int]$Expected, [string]$Name) {
        if ([int]$Response.StatusCode -ne $Expected) { throw "$Name expected HTTP $Expected, observed $($Response.StatusCode)." }
        $script:probes.Add(@{ name = $Name; status = [int]$Response.StatusCode })
        Save-Json 'http-probes.json' @($script:probes)
    }
    function Request-QA([string]$Method, [string]$Path, $Jar, [hashtable]$Headers, [string]$Body = '') {
        # PowerShell sessions retain request headers; absence must mean absence on the wire.
        $Jar.Headers.Remove('X-XSRF-TOKEN') | Out-Null
        $argsMap = @{ Uri = $manifest.backend_url + $Path; Method = $Method; WebSession = $Jar; Headers = $Headers;
            SkipHttpErrorCheck = $true; TimeoutSec = 10 }
        if ($Method -eq 'POST') { $argsMap.ContentType = 'application/json'; $argsMap.Body = $Body }
        return Invoke-WebRequest @argsMap
    }
    function Fresh-Xsrf($Jar, [hashtable]$Headers) {
        $cookie = $Jar.Cookies.GetCookies([uri]$manifest.backend_url)['XSRF-TOKEN']
        if (!$cookie) { throw 'Missing XSRF cookie.' }
        $Headers['X-XSRF-TOKEN'] = [uri]::UnescapeDataString($cookie.Value)
    }
    $script:probes = [Collections.Generic.List[object]]::new()
    $beforeBusiness = Server-Command 'audit-probes'
    Save-Json 'business-before.json' $beforeBusiness
    foreach ($path in @('/src/pages/host/HostInvoiceCreationPage.jsx', '/src/components/invoice/CreateInvoiceButton.jsx', '/src/components/invoice/InvoiceDetail.jsx', '/src/services/invoiceService.js', '/src/api/invoiceApi.js')) {
        $source = Invoke-WebRequest ($manifest.frontend_url + $path) -SkipHttpErrorCheck -TimeoutSec 10
        if ($source.StatusCode -ne 200 -or $source.Content -notmatch 'HostInvoiceCreationPage|CreateInvoiceButton|InvoiceDetail|createInvoiceService|createInvoiceApi') { throw 'Vite did not serve current Invoice source.' }
    }
    $oracleResults = [Collections.Generic.List[object]]::new()
    function Compare-Invoice($Response, [int]$BookingId, [string]$Name) {
        # Preserve ISO date/null/decimal JSON exactly; PS 7.5+ otherwise deserializes dates.
        [IO.File]::WriteAllText((Join-Path $evidence ('response-' + $oracleResults.Count + '.json')), $Response.Content, [Text.UTF8Encoding]::new($false))
        $payload = '{"response":' + $Response.Content + ',"booking_id":' + [string]$BookingId + '}'
        $comparison = Server-Command 'verify' $payload
        if (!$comparison.matched) { throw 'Independent persisted Invoice oracle did not match.' }
        $oracleResults.Add(@{ name = $Name; matched = $true; booking_id = $BookingId })
    }
    $createdIds = [ordered]@{}
    foreach ($actor in @('H_A', 'H_B', 'C_A', 'ADMIN')) {
        $spec = $fixture.fixture.actors[$actor]
        $jar = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
        $actorHeaders = @{ Accept = 'application/json'; Origin = $manifest.frontend_url; Referer = $manifest.frontend_url + '/' }
        $mainId = [int]$fixture.fixture.bookings['P-VND-SERVICES'].id
        Assert-Status (Request-QA 'GET' '/sanctum/csrf-cookie' $jar $actorHeaders) 204 "$actor csrf-cookie"
        Fresh-Xsrf $jar $actorHeaders
        Assert-Status (Request-QA 'POST' "/api/host/bookings/$mainId/invoice" $jar $actorHeaders '{}') 401 "$actor guest create"
        $payload = @{email = $spec.email; password = $fixturePassword; expected_role = $spec.role} | ConvertTo-Json -Compress
        if ($actor -eq 'H_A') {
            $actorHeaders.Remove('X-XSRF-TOKEN')
            Assert-Status (Request-QA 'POST' '/login' $jar $actorHeaders $payload) 419 'missing XSRF login'
        }
        Fresh-Xsrf $jar $actorHeaders
        $login = Request-QA 'POST' '/login' $jar $actorHeaders $payload
        $payload = $null
        Assert-Status $login 200 "$actor login"
        if (($login.Content | ConvertFrom-Json).user.id -ne $spec.id) { throw 'Login restored different actor.' }
        if (!$jar.Cookies.GetCookies([uri]$manifest.backend_url)['tv3ac_ui_' + $runId + '_session']) { throw 'Owned session cookie missing.' }
        Fresh-Xsrf $jar $actorHeaders
        $me = Request-QA 'GET' '/api/me' $jar $actorHeaders
        Assert-Status $me 200 "$actor persisted session"
        if (($me.Content | ConvertFrom-Json).user.id -ne $spec.id) { throw 'Session actor differs.' }
        $sessionAudit = Server-Command 'audit-probes'
        if (@($sessionAudit.session_users | Where-Object { $_.user_id -eq $spec.id -and $_.count -gt 0 }).Count -ne 1) { throw 'Database session missing.' }
        if ($actor -eq 'H_A') {
            $valid = $actorHeaders['X-XSRF-TOKEN']
            $actorHeaders.Remove('X-XSRF-TOKEN')
            Assert-Status (Request-QA 'POST' "/api/host/bookings/$mainId/invoice" $jar $actorHeaders '{}') 419 'missing XSRF create'
            $actorHeaders['X-XSRF-TOKEN'] = 'wrong-xsrf-token'
            Assert-Status (Request-QA 'POST' "/api/host/bookings/$mainId/invoice" $jar $actorHeaders '{}') 419 'wrong XSRF create'
            $actorHeaders['X-XSRF-TOKEN'] = $valid
            foreach ($entry in @(@('P-DUPLICATE',409), @('P-NOT-COMPLETED',422), @('P-FOREIGN',403))) {
                $id = [int]$fixture.fixture.bookings[$entry[0]].id
                Assert-Status (Request-QA 'POST' "/api/host/bookings/$id/invoice" $jar $actorHeaders '{}') ([int]$entry[1]) $entry[0]
            }
            Assert-Status (Request-QA 'POST' ('/api/host/bookings/' + $fixture.fixture.missing_id + '/invoice') $jar $actorHeaders '{}') 404 'missing Booking'
            foreach ($kind in @('VND-SERVICES','ROOM-ONLY','ZERO','ROUND','MAX','DOUBLE','UNKNOWN')) {
                $id = [int]$fixture.fixture.bookings["P-$kind"].id
                $body = '{"subtotal":"1.00","total_amount":"1.00","extra_fee":"99.00","items":[]}'
                $post = Request-QA 'POST' "/api/host/bookings/$id/invoice" $jar $actorHeaders $body
                if ($post.Headers['Access-Control-Allow-Origin'] -ne $manifest.frontend_url -or $post.Headers['Access-Control-Allow-Credentials'] -ne 'true') { throw 'Creation CORS headers differ, including injected response failure.' }
                if ($kind -eq 'UNKNOWN') {
                    Assert-Status $post 500 'synthetic after-commit response failure'
                    if ($post.Headers['X-TV3-QA-Fault'] -ne 'after-commit-response') { throw 'Missing explicit QA fault attribution.' }
                    $unknown = $post.Content | ConvertFrom-Json
                    if ($unknown.code -ne 'INTERNAL_SERVER_ERROR' -or $unknown.message -ne 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.') { throw 'Unsafe injected response.' }
                    # Discover committed result with GET only; never replay the UNKNOWN POST.
                    $list = Request-QA 'GET' '/api/host/invoices?per_page=50' $jar $actorHeaders
                    Assert-Status $list 200 'unknown outcome list recovery'
                    $matches = @(($list.Content | ConvertFrom-Json).data | Where-Object { $_.booking_id -eq $id })
                    if ($matches.Count -ne 1) { throw 'Expected one committed Invoice despite response failure.' }
                    $invoiceId = [int]$matches[0].id
                } else {
                    Assert-Status $post 201 "create P-$kind"
                    Compare-Invoice $post $id "POST P-$kind"
                    $invoiceId = [int](($post.Content | ConvertFrom-Json).data.id)
                }
                $createdIds[$kind] = $invoiceId
                $detail = Request-QA 'GET' "/api/host/invoices/$invoiceId" $jar $actorHeaders
                Assert-Status $detail 200 "host readback P-$kind"
                Compare-Invoice $detail $id "GET P-$kind"
            }
            $doubleId = [int]$fixture.fixture.bookings['P-DOUBLE'].id
            Assert-Status (Request-QA 'POST' "/api/host/bookings/$doubleId/invoice" $jar $actorHeaders '{}') 409 'sequential duplicate (not browser double click)'
            Assert-Status (Request-QA 'POST' '/register' $jar $actorHeaders '{}') 403 'harness refuses unrelated mutation'
            $audit = Server-Command 'audit-probes'
            if ($audit.delta.new_invoices -ne 7 -or $audit.delta.new_items -ne 15) { throw 'Unexpected probe mutation inventory.' }
        } elseif ($actor -eq 'C_A') {
            Assert-Status (Request-QA 'POST' "/api/host/bookings/$mainId/invoice" $jar $actorHeaders '{}') 403 'Customer cannot create'
            foreach ($kind in $createdIds.Keys) {
                $id = [int]$fixture.fixture.bookings["P-$kind"].id
                $detail = Request-QA 'GET' ('/api/customer/invoices/' + $createdIds[$kind]) $jar $actorHeaders
                Assert-Status $detail 200 "customer readback P-$kind"
                Compare-Invoice $detail $id "Customer GET P-$kind"
            }
        } else {
            Assert-Status (Request-QA 'POST' "/api/host/bookings/$mainId/invoice" $jar $actorHeaders '{}') 403 "$actor cannot create for H_A"
            if ($actor -eq 'H_B') {
                Assert-Status (Request-QA 'GET' ('/api/host/invoices/' + $createdIds['VND-SERVICES']) $jar $actorHeaders) 404 'foreign Host cannot read created Invoice'
            }
        }
        Fresh-Xsrf $jar $actorHeaders
        Assert-Status (Request-QA 'POST' '/logout' $jar $actorHeaders '{}') 200 "$actor logout"
        Assert-Status (Request-QA 'GET' '/api/me' $jar $actorHeaders) 401 "$actor guest after logout"
    }
    Save-Json 'oracle.json' @($oracleResults)
    $afterBusiness = Server-Command 'audit-probes'
    Save-Json 'business-after.json' $afterBusiness
    Save-Json 'sessions-after.json' @{authenticated_sessions = @($afterBusiness.session_users).Count}
    if (@($afterBusiness.session_users).Count -ne 0) { throw 'Authenticated sessions remain after HTTP probes.' }
    if ($afterBusiness.delta.new_invoices -ne 7 -or $afterBusiness.delta.new_items -ne 15) { throw 'Probe inventory mismatch.' }
    $seal = Server-Command 'seal'
    if (!$seal.sealed) { throw 'Browser checkpoint not sealed.' }
    Save-Json 'browser-checkpoint.json' $seal
    Save-Json 'ready.json' @{ run_id = $runId; scenario = $Scenario; backend_url = $manifest.backend_url; frontend_url = $manifest.frontend_url;
        processes = @($servers | ForEach-Object { $_.process.Id }); stop_file = (Join-Path $evidence 'stop.request'); mode = 'real HTTP cookie jar; no browser UI observation' }
    if ($KeepAlive) {
        Write-Output "INV_CREATE_UI browser harness ready: $evidence"
        $deadline = [DateTime]::UtcNow.AddMinutes($MaxMinutes)
        while (!(Test-Path -LiteralPath (Join-Path $evidence 'stop.request'))) {
            if ([DateTime]::UtcNow -ge $deadline) { throw 'Browser QA lease expired; owned cleanup required.' }
            foreach ($server in $servers) { if ($server.process.HasExited) { throw 'Owned QA server exited unexpectedly.' } }
            Start-Sleep -Milliseconds 500
        }
        $browserAfter = Server-Command 'audit-browser'
        Save-Json 'browser-business-after.json' $browserAfter
        Save-Json 'browser-sessions-after.json' @{ authenticated_sessions = @($browserAfter.session_users).Count }
        if (!$browserAfter.delta.matched) { throw 'Browser mutation audit failed.' }
        if (@($browserAfter.session_users).Count -ne 0) { throw 'Authenticated browser session remained at stop; logout before stopping the run.' }
    }
} catch {
    $failure = Protect-Text $_.Exception.Message
} finally {
    try {
        $stopped = @()
        foreach ($server in $servers) {
            $pidOwned = $server.process.Id
            if (!$server.process.HasExited) { $server.process.Kill($true); $server.process.WaitForExit(10000) | Out-Null }
            $stopped += @{ pid = $pidOwned; exited = $server.process.HasExited }
            $null = $server.output.GetAwaiter().GetResult()
            $null = $server.error.GetAwaiter().GetResult()
            $server.process.Dispose()
        }
        Save-Json 'process-cleanup.json' $stopped
        if (@($stopped | Where-Object { !$_.exited }).Count -gt 0) { throw 'Owned process remained after termination.' }
        $portChecks = @()
        if ($null -ne $manifest) {
            foreach ($origin in @($manifest.backend_url, $manifest.frontend_url)) {
                $released = @([Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners() | Where-Object { $_.Port -eq ([uri]$origin).Port }).Count -eq 0
                $portChecks += @{ port = ([uri]$origin).Port; released = $released }
            }
        }
        Save-Json 'port-cleanup.json' $portChecks
        if (@($portChecks | Where-Object { !$_.released }).Count -gt 0) { throw 'QA listener port was not released.' }
        if ($schemaCreated -or $userCreated) {
            if ($childTerminationFailed) { throw 'Owned child termination unconfirmed; refuse resource cleanup.' }
            if ($schema -cne "stayhub_tv3_ac_test_$runId" -or $testUser -cne ('tv3ac_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
            Assert-AdminIdentity
            $connections = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            if ($connections -ne '0') { throw 'Owned user still has active connections; refuse drop and preserve evidence.' }
            if ($schemaCreated) { $null = Admin-Sql ('DROP DATABASE `' + $schema + '`;') }
            if ($userCreated) { $null = Admin-Sql "DROP USER '$testUser'@'%';" }
            $schemaRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';"
            $userRemaining = Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';"
            $connectionsRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            Save-Json 'cleanup.json' @{ schema_remaining = $schemaRemaining; user_remaining = $userRemaining; connections_before_drop = $connections; connections_remaining = $connectionsRemaining }
            if ($schemaRemaining -ne '0' -or $userRemaining -ne '0' -or $connectionsRemaining -ne '0') { throw 'Owned resources remain after cleanup.' }
        }
        if ($null -ne $before) {
            $after = Development-Fingerprint
            Save-Json 'development-after.json' $after
            if ($before.sha256 -ne $after.sha256 -or
                (($before.tables | ConvertTo-Json -Compress) -cne ($after.tables | ConvertTo-Json -Compress))) {
                throw 'Development fingerprint changed; do not restore or overwrite development data.'
            }
        }
    } catch { $cleanupFailure = Protect-Text $_.Exception.Message }
    if ($null -ne $protectedBefore) {
        try {
            $protectedAfter = Protected-Fingerprint
            Save-Json 'protected-after.json' $protectedAfter
            if (($protectedBefore | ConvertTo-Json -Compress) -cne ($protectedAfter | ConvertTo-Json -Compress)) {
                throw 'Protected source/config fingerprints changed; no files restored automatically.'
            }
        } catch { $cleanupFailure = "$cleanupFailure $($_.Exception.Message)".Trim() }
    }
    foreach ($artifact in (Get-ChildItem -LiteralPath $evidence -File)) {
        $artifactText = [IO.File]::ReadAllText($artifact.FullName)
        if ($artifactText.Contains($testPassword) -or $artifactText.Contains($testAppKey) -or $artifactText.Contains($fixturePassword)) {
            [IO.File]::WriteAllText($artifact.FullName, (Protect-Text $artifactText))
            $cleanupFailure = "$cleanupFailure Secret detected and redacted.".Trim()
        }
    }
    Save-Json 'result.json' @{ run_id = $runId; scenario = $Scenario; injected_failure_after = $InjectFailureAfter; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null; $testAppKey = $null; $fixturePassword = $null
}
Write-Output "INV_CREATE_UI evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "INV_CREATE_UI failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'INV_CREATE_UI browser environment and cleanup verified.'
