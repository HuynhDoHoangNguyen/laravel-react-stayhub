# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param([switch]$Provision, [ValidateRange(1, 120)][int]$MaxMinutes = 30)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$schema = "stayhub_uc45_test_$runId"
$testUser = 'uc45_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$evidence = Join-Path $repo ".agent-workspace/tv3/uc45-qa-browser-01/$runId"
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

function Save-Json([string]$Name, $Value) {
    $Value | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath (Join-Path $evidence $Name) -Encoding utf8
}

function Invoke-Child([string]$Exe, [string[]]$Arguments, [string]$InputText = '', [hashtable]$Environment = @{}, [switch]$AllowFailure, [string]$WorkingDirectory = $repo) {
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $Exe
    $start.WorkingDirectory = $WorkingDirectory
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    $null = $process.Start()
    $outTask = $process.StandardOutput.ReadToEndAsync()
    $errTask = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.Write($InputText)
    $process.StandardInput.Close()
    $process.WaitForExit()
    $result = @{ exit = $process.ExitCode; stdout = $outTask.GetAwaiter().GetResult(); stderr = $errTask.GetAwaiter().GetResult() }
    $process.Dispose()
    if ($result.exit -ne 0 -and !$AllowFailure) {
        throw "Child process failed (exit $($result.exit)): $($result.stderr.Replace($testPassword, '[REDACTED]'))"
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
    $tables = @( (Admin-Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='stayhub' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`r?`n" | Where-Object { $_ })
    if ($tables.Count -eq 0) { throw 'Cannot establish development database fingerprint.' }
    $counts = [ordered]@{}
    foreach ($table in $tables) {
        if ($table -notmatch '^[a-zA-Z0-9_]+$') { throw 'Unexpected table name in development fingerprint.' }
        $counts[$table] = Admin-Sql ('SELECT COUNT(*) FROM `stayhub`.`' + $table + '`;')
    }
    # Raw dump stays in memory only. Persist only SHA256 and row counts, never records.
    $dump = Invoke-Child $docker @('exec', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --skip-comments --compact --skip-add-locks --skip-disable-keys --hex-blob --order-by-primary --no-tablespaces --set-gtid-purged=OFF stayhub')
    return @{ tables = $counts; sha256 = Hash-Text $dump.stdout }
}

function Assert-AdminIdentity {
    $observed = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid;') -split "`t"
    if ($observed.Count -ne 3 -or $observed[0] -ne 'information_schema' -or
        $observed[1] -ne $manifest.version -or $observed[2] -ne $manifest.server_uuid) {
        throw 'Administrative connection instance changed; refuse writes or cleanup.'
    }
}

function Protected-Fingerprint {
    $paths = @((Invoke-Child 'git' @('ls-files')).stdout -split "`r?`n" | Where-Object { $_ })
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
    $bindings = @($info.NetworkSettings.Ports.'3306/tcp')
    if ($bindings.Count -ne 1 -or $bindings[0].HostIp -ne '127.0.0.1') { throw 'Expected one loopback TCP binding.' }
    $port = [string]$bindings[0].HostPort
    $identity = (Admin-Sql 'SELECT DATABASE(), VERSION(), @@server_uuid;') -split "`t"
    if ($identity.Count -ne 3 -or $identity[0] -ne 'information_schema' -or $identity[1] -notmatch '^8\.4\.' -or $identity[2] -notmatch '^[a-f0-9-]{36}$') {
        throw 'Unexpected administrative connection identity.'
    }
    function Free-Port {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        try { return $listener.LocalEndpoint.Port } finally { $listener.Stop() }
    }
    $backendPort = Free-Port
    do { $frontendPort = Free-Port } while ($frontendPort -eq $backendPort)
    $manifest = @{ backend_url = "http://localhost:$backendPort"; frontend_url = "http://localhost:$frontendPort"; run_id = $runId; database = $schema; username = $testUser; host = '127.0.0.1'; port = $port;
        server_uuid = $identity[2]; version = $identity[1]; container = $container; php = $phpProbe.stdout;
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
    $null = Admin-Sql "CREATE USER '$testUser'@'%' IDENTIFIED BY '$testPassword';"
    $userCreated = $true
    $literalGrantSchema = $schema.Replace('_', '\_')
    $null = Admin-Sql ('GRANT ALL PRIVILEGES ON `' + $literalGrantSchema + '`.* TO ' + "'$testUser'@'%';")
    $envMap = @{ APP_ENV = 'local'; APP_KEY = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32));
        DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = $port; DB_DATABASE = $schema;
        DB_USERNAME = $testUser; DB_PASSWORD = $testPassword; DB_URL = ''; DB_SOCKET = '';
        DB_CHARSET = 'utf8mb4'; DB_COLLATION = 'utf8mb4_unicode_ci'; MYSQL_ATTR_SSL_CA = '';
        CACHE_STORE = 'array'; SESSION_DRIVER = 'database'; QUEUE_CONNECTION = 'sync'; LOG_CHANNEL = 'null';
        STAYHUB_UC45_BROWSER_OPT_IN = '1'; STAYHUB_UC45_OPT_IN = '1'; STAYHUB_UC45_MANIFEST = (Join-Path $evidence 'manifest.json') }
    $envMap.APP_URL = $manifest.backend_url
    $envMap.APP_DEBUG = 'false'
    $envMap.STAYHUB_UC45_BROWSER_PASSWORD = $env:STAYHUB_UC45_BROWSER_PASSWORD
    $prepared = Invoke-Child $php @('tests/Support/Uc45BrowserServer.php', 'prepare') '' $envMap -WorkingDirectory $backend
    Save-Json 'fixture.json' ($prepared.stdout | ConvertFrom-Json)
    $fixturePassword = $envMap.STAYHUB_UC45_BROWSER_PASSWORD
    $envMap.Remove('STAYHUB_UC45_BROWSER_PASSWORD')
    function Start-Owned([string]$Exe, [string[]]$Arguments, [hashtable]$Environment, [string]$Directory) {
        $start = [Diagnostics.ProcessStartInfo]::new()
        $start.FileName = $Exe; $start.WorkingDirectory = $Directory
        $start.UseShellExecute = $false; $start.CreateNoWindow = $true
        $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true
        foreach ($arg in $Arguments) { $start.ArgumentList.Add($arg) }
        foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
        # Do not forward the fixture login password to long-running servers.
        $start.Environment.Remove('STAYHUB_UC45_BROWSER_PASSWORD') | Out-Null
        $process = [Diagnostics.Process]::new(); $process.StartInfo = $start
        $null = $process.Start()
        return @{ process = $process; output = $process.StandardOutput.ReadToEndAsync(); error = $process.StandardError.ReadToEndAsync() }
    }
    $servers += Start-Owned $php @('-S', "127.0.0.1:$backendPort", '-t', 'public', 'tests/Support/Uc45BrowserServer.php') $envMap $backend
    $frontendEnv = @{ VITE_BACKEND_URL = $manifest.backend_url }
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
    $negative = Invoke-WebRequest ($manifest.backend_url + '/login') -Method Post -WebSession $session -Headers $headers -ContentType 'application/json' -Body '{}' -SkipHttpErrorCheck
    if ($negative.StatusCode -ne 419) { throw 'CSRF negative control did not return 419.' }
    $xsrf = $session.Cookies.GetCookies([uri]$manifest.backend_url)['XSRF-TOKEN']
    if (!$xsrf) { throw 'CSRF cookie was not issued.' }
    $headers['X-XSRF-TOKEN'] = [uri]::UnescapeDataString($xsrf.Value)
    $payload = @{ email = 'admin@uc45.example.test'; password = $fixturePassword; expected_role = 'ADMIN' } | ConvertTo-Json -Compress
    $login = Invoke-WebRequest ($manifest.backend_url + '/login') -Method Post -WebSession $session -Headers $headers -ContentType 'application/json' -Body $payload -SkipHttpErrorCheck
    $payload = $null; $fixturePassword = $null
    if ($login.StatusCode -ne 200) { throw "Browser runtime login failed: HTTP $($login.StatusCode)." }
    $read = Invoke-WebRequest ($manifest.backend_url + '/api/admin/invoices') -WebSession $session -Headers $headers -SkipHttpErrorCheck
    $again = Invoke-WebRequest ($manifest.backend_url + '/api/admin/invoices?page=2') -WebSession $session -Headers $headers -SkipHttpErrorCheck
    if ($read.StatusCode -ne 200 -or $again.StatusCode -ne 200 -or ($read.Content | ConvertFrom-Json).meta.total -ne 60) { throw 'Persistent HTTP session failed.' }
    $xsrf = $session.Cookies.GetCookies([uri]$manifest.backend_url)['XSRF-TOKEN']
    $headers['X-XSRF-TOKEN'] = [uri]::UnescapeDataString($xsrf.Value)
    $logout = Invoke-WebRequest ($manifest.backend_url + '/logout') -Method Post -WebSession $session -Headers $headers -ContentType 'application/json' -Body '{}' -SkipHttpErrorCheck
    $guest = Invoke-WebRequest ($manifest.backend_url + '/api/admin/invoices') -WebSession $session -Headers $headers -SkipHttpErrorCheck
    if ($logout.StatusCode -ne 200 -or $guest.StatusCode -ne 401) { throw 'Logout HTTP control failed.' }
    Save-Json 'http-probes.json' @{ csrf_cookie = $csrf.StatusCode; missing_csrf = $negative.StatusCode; login = $login.StatusCode; authenticated_read = $read.StatusCode; second_request = $again.StatusCode; total = 60; logout = $logout.StatusCode; guest_after_logout = $guest.StatusCode; mode = 'real HTTP cookie jar; browser observation separately' }
    Save-Json 'ready.json' @{ run_id = $runId; backend_url = $manifest.backend_url; frontend_url = $manifest.frontend_url; processes = @($servers | ForEach-Object { $_.process.Id }); stop_file = (Join-Path $evidence 'stop.request') }
    Write-Output "UC45 browser ready: $evidence"
    Write-Output "Frontend: $($manifest.frontend_url) Backend: $($manifest.backend_url)"
    $deadline = [DateTime]::UtcNow.AddMinutes($MaxMinutes)
    while (!(Test-Path -LiteralPath (Join-Path $evidence 'stop.request'))) {
        if ([DateTime]::UtcNow -ge $deadline) { throw 'Browser QA lease expired; automatic owned cleanup.' }
        foreach ($server in $servers) { if ($server.process.HasExited) { throw 'Owned QA server exited unexpectedly.' } }
        Start-Sleep -Milliseconds 500
    }
} catch {
    $failure = $_.Exception.Message.Replace($testPassword, '[REDACTED]')
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
        if ($schemaCreated -or $userCreated) {
            if ($schema -cne "stayhub_uc45_test_$runId" -or $testUser -cne ('uc45_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
            Assert-AdminIdentity
            $connections = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            if ($connections -ne '0') { throw 'Owned user still has active connections; refuse drop and preserve evidence.' }
            if ($schemaCreated) { $null = Admin-Sql ('DROP DATABASE `' + $schema + '`;') }
            if ($userCreated) { $null = Admin-Sql "DROP USER '$testUser'@'%';" }
            $schemaRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';"
            $userRemaining = Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';"
            Save-Json 'cleanup.json' @{ schema_remaining = $schemaRemaining; user_remaining = $userRemaining; connections_before_drop = $connections }
            if ($schemaRemaining -ne '0' -or $userRemaining -ne '0') { throw 'Owned resources remain after cleanup.' }
        }
        if ($null -ne $before) {
            $after = Development-Fingerprint
            Save-Json 'development-after.json' $after
            if ($before.sha256 -ne $after.sha256 -or
                (($before.tables | ConvertTo-Json -Compress) -cne ($after.tables | ConvertTo-Json -Compress))) {
                throw 'Development fingerprint changed; do not restore or overwrite development data.'
            }
        }
    } catch { $cleanupFailure = $_.Exception.Message.Replace($testPassword, '[REDACTED]') }
    if ($null -ne $protectedBefore) {
        try {
            $protectedAfter = Protected-Fingerprint
            Save-Json 'protected-after.json' $protectedAfter
            if (($protectedBefore | ConvertTo-Json -Compress) -cne ($protectedAfter | ConvertTo-Json -Compress)) {
                throw 'Protected source/config fingerprints changed; no files restored automatically.'
            }
        } catch { $cleanupFailure = "$cleanupFailure $($_.Exception.Message)".Trim() }
    }
    Save-Json 'result.json' @{ run_id = $runId; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null
}
Write-Output "UC45 evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "UC45 failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'UC45 browser environment and cleanup verified.'
