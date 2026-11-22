# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param(
    [switch]$Provision,
    # Negative control: the smoke test fails after writing to its owned transaction.
    [switch]$InjectTestFailure,
    [ValidateSet('Functional', 'InvoiceCreateRace', 'ReviewRead', 'ReviewCreate', 'ReviewCreateRace', 'ChatData', 'ChatHttp', 'ChatRecovery', 'ChatBrowser', 'GuestChat', 'GuestChatBrowser', 'ChatLiveDryRun', 'ChatLive', 'ChatContextLiveDryRun', 'ChatContextLive')]
    [string]$Suite = 'Functional',
    [switch]$AllowLiveGemini,
    [string]$VerifiedDryRun,
    [ValidateSet('None', 'AfterSchema', 'Collision')]
    [string]$InjectFailureAfter = 'None'
)

#Requires -Version 7.0
if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }
if ($Suite -ne 'Functional' -and $InjectTestFailure) { throw 'Smoke failure injection requires the Functional suite.' }
$liveNetwork = $Suite -in @('ChatLive', 'ChatContextLive')
if (($liveNetwork -and (!$AllowLiveGemini -or $VerifiedDryRun -cnotmatch '^[a-f0-9]{32}$')) -or
    (!$liveNetwork -and ($AllowLiveGemini -or $VerifiedDryRun))) { throw 'Live QA requires explicit opt-in and a verified dry-run ID.' }

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$schema = "stayhub_tv3_ac_test_$runId"
$testUser = 'tv3ac_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testAppKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$liveGeminiKey = $null
$contextQaKey = $env:STAYHUB_UC61_LIVE_GEMINI_API_KEY
Remove-Item Env:STAYHUB_UC61_LIVE_GEMINI_API_KEY -ErrorAction SilentlyContinue
$browserSuites = @('ChatBrowser', 'GuestChatBrowser', 'ChatLiveDryRun', 'ChatLive', 'ChatContextLiveDryRun', 'ChatContextLive')
$evidence = Join-Path $repo ".agent-workspace/tv3/ac-env-01/$runId"
$null = New-Item -ItemType Directory -Path $evidence
$lifecycleArtifacts = Join-Path $evidence 'lifecycle'
if ($Suite -in @('InvoiceCreateRace', 'ReviewCreateRace', 'ChatRecovery')) { $null = New-Item -ItemType Directory -Path $lifecycleArtifacts }
$php = (Get-Command php -ErrorAction Stop).Source
$docker = (Get-Command docker -ErrorAction Stop).Source
$container = $null
$development = $null
$manifest = $null
$schemaCreated = $false
$userCreated = $false
$before = $null
$protectedBefore = $null
$failure = $null
$cleanupFailure = $null
$childTerminationFailed = $false
$browserServices = [Collections.Generic.List[hashtable]]::new()
$edgeProfile = Join-Path $evidence 'edge-profile'

function Protect-Text([string]$Value) {
    $safe = $Value.Replace($testPassword, '[REDACTED]').Replace($testAppKey, '[REDACTED]')
    if ($liveGeminiKey) { $safe = $safe.Replace($liveGeminiKey, '[REDACTED]') }
    return $safe
}

function Save-Json([string]$Name, $Value) {
    $Value | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath (Join-Path $evidence $Name) -Encoding utf8
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

function New-BrowserPorts {
    $listeners = [Collections.Generic.List[Net.Sockets.TcpListener]]::new()
    try {
        foreach ($index in 1..2) {
            $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
            $listeners.Add($listener)
            $listener.Start()
            $listener.LocalEndpoint.Port
        }
    } finally {
        foreach ($listener in $listeners) { $listener.Stop() }
    }
}

function Start-BrowserService([string]$Label, [string]$Exe, [string[]]$Arguments, [hashtable]$Environment, [string]$Directory) {
    $start = [Diagnostics.ProcessStartInfo]::new($Exe)
    $start.WorkingDirectory = $Directory
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    foreach ($argument in $Arguments) { $start.ArgumentList.Add($argument) }
    foreach ($entry in $Environment.GetEnumerator()) { $start.Environment[$entry.Key] = [string]$entry.Value }
    $process = [Diagnostics.Process]::Start($start)
    $browserServices.Add(@{ label = $Label; process = $process; output = $process.StandardOutput.ReadToEndAsync(); errors = $process.StandardError.ReadToEndAsync() })
}

function Wait-BrowserHttp([string]$Url) {
    $deadline = [DateTime]::UtcNow.AddSeconds(25)
    do {
        if (@($browserServices | Where-Object { $_.process.HasExited }).Count -gt 0) { throw 'An owned browser service exited during startup.' }
        try {
            if ((Invoke-WebRequest -Uri $Url -TimeoutSec 2 -SkipHttpErrorCheck).StatusCode -eq 200) { return }
        } catch { }
        Start-Sleep -Milliseconds 150
    } while ([DateTime]::UtcNow -lt $deadline)
    throw "Owned browser service was not ready at $Url."
}

function Invoke-ChatBrowser([hashtable]$Environment) {
    $guestBrowser = $Suite -eq 'GuestChatBrowser'
    $liveMode = $Suite -in @('ChatLiveDryRun', 'ChatLive')
    $contextMode = $Suite -in @('ChatContextLiveDryRun', 'ChatContextLive')
    $node = (Get-Command node -ErrorAction Stop).Source
    $edge = Join-Path ${env:ProgramFiles(x86)} 'Microsoft/Edge/Application/msedge.exe'
    if (!(Test-Path -LiteralPath $edge -PathType Leaf)) { throw 'Microsoft Edge executable is missing.' }
    $frontend = Join-Path $repo 'frontend'
    $Environment.GEMINI_API_KEY = 'fake-uc17-browser-key'
    $Environment.SUPPORT_CHAT_AI_ENABLED = 'false'
    $Environment.SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS = 'false'
    $Environment.SUPPORT_CHAT_RATE_LIMIT_PER_MINUTE = '10'
    $Environment.SUPPORT_CHAT_TIMEOUT_MS = '12000'
    $Environment.SUPPORT_CHAT_MAX_CONCURRENT = '2'
    $Environment.SUPPORT_CHAT_MAX_OUTPUT_TOKENS = '500'
    $Environment.GEMINI_MODEL = 'gemini-3.5-flash-lite'
    $Environment.STAYHUB_UC17_LIVE_AUTHORIZED = '0'
    $Environment.STAYHUB_UC17_BROWSER_PASSWORD = $testPassword
    $Environment.STAYHUB_UC17_PHP = $php
    $server = 'tests/Support/Uc17BrowserServer.php'
    if ($Suite -in @('ChatLiveDryRun', 'ChatContextLiveDryRun')) {
        $guard = Invoke-Child $php @($server, 'guard-preflight') '' $Environment -WorkingDirectory $backend
        Save-Json 'guard-preflight.json' ($guard.stdout | ConvertFrom-Json)
    }
    $prepared = Invoke-Child $php @($server, 'prepare') '' $Environment -WorkingDirectory $backend
    Save-Json 'fixture-before.json' ($prepared.stdout | ConvertFrom-Json)
    $serverEnvironment = $Environment.Clone()
    if ($liveNetwork) {
        $claim = Join-Path $repo ".agent-workspace/tv3/ac-env-01/$VerifiedDryRun/live-consumed"
        $ticket = [IO.File]::Open($claim, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $ticket.Dispose()
        $serverEnvironment.GEMINI_API_KEY = $liveGeminiKey
        $serverEnvironment.STAYHUB_UC17_LIVE_AUTHORIZED = '1'
    }
    Start-BrowserService 'laravel' $php @('-S', "127.0.0.1:$backendPort", '-t', 'public', $server) $serverEnvironment $backend
    if ($liveNetwork) { $serverEnvironment.GEMINI_API_KEY = '' }
    Start-BrowserService 'vite' $node @('node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', [string]$frontendPort, '--strictPort') @{
        VITE_BACKEND_URL = $manifest.backend_url; VITE_ENABLE_BOOKING_MOCKS = 'false'; VITE_ENABLE_PROPERTY_MOCKS = 'false'
        VITE_ENABLE_GUEST_CHAT = ([string]($guestBrowser -or $liveMode)).ToLowerInvariant()
    } $frontend
    Wait-BrowserHttp "$($manifest.backend_url)/up"
    Wait-BrowserHttp "$($manifest.frontend_url)/customer"
    $null = New-Item -ItemType Directory -Path $edgeProfile
    Start-BrowserService 'edge' $edge @('--headless=new', '--disable-gpu', '--disable-background-networking', '--disable-extensions', '--no-first-run',
        '--no-default-browser-check', '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1', "--user-data-dir=$edgeProfile", 'about:blank') @{} $frontend
    $portFile = Join-Path $edgeProfile 'DevToolsActivePort'
    $deadline = [DateTime]::UtcNow.AddSeconds(20)
    while (!(Test-Path -LiteralPath $portFile) -and [DateTime]::UtcNow -lt $deadline) { Start-Sleep -Milliseconds 150 }
    $debugPort = [int](Get-Content -LiteralPath $portFile | Select-Object -First 1)
    if ($debugPort -lt 1 -or $debugPort -gt 65535) { throw 'Invalid owned Edge debug port.' }
    $Environment.STAYHUB_UC17_DEBUG_PORT = [string]$debugPort
    Save-Json 'browser-services.json' @{ frontend = $manifest.frontend_url; backend = $manifest.backend_url; edge_debug_port = $debugPort;
        pids = @($browserServices | ForEach-Object { @{ name = $_.label; pid = $_.process.Id } }) }

    $browser = Invoke-Child $node @('tests/support/uc17Browser.mjs') '' $Environment -AllowFailure -WorkingDirectory $frontend -TimeoutSeconds 300
    (Protect-Text $browser.stdout) | Set-Content -LiteralPath (Join-Path $evidence 'browser.json') -Encoding utf8
    (Protect-Text $browser.stderr) | Set-Content -LiteralPath (Join-Path $evidence 'browser-stderr.txt') -Encoding utf8
    Save-Json 'browser-exit.json' @{ exit = $browser.exit }
    $audit = Invoke-Child $php @($server, 'audit') '' $Environment -WorkingDirectory $backend
    $finalFixture = $audit.stdout | ConvertFrom-Json
    Save-Json 'fixture-after.json' $finalFixture
    if ($contextMode) {
        Save-Json 'gemini-attempts.json' @{ simulated = !$liveNetwork; model = $Environment.GEMINI_MODEL; cases = $finalFixture.gemini }
        $contextCases = if ($liveNetwork) { @('name', 'code', 'room') } else { @('name', 'code', 'room', 'invalid-intent') }
        foreach ($case in $contextCases) {
            if ($finalFixture.gemini.$case.attempts -ne 1) { throw "UC61 expected one provider attempt for $case." }
        }
    } elseif ($liveMode) {
        Save-Json 'gemini-attempts.json' @{ simulated = ($Suite -eq 'ChatLiveDryRun'); model = 'gemini-3.5-flash-lite';
            guest = $finalFixture.gemini.guest; customer = $finalFixture.gemini.customer }
        if ($finalFixture.gemini.guest.attempts -ne 1 -or $finalFixture.gemini.customer.attempts -ne 1) { throw 'UC17 expected one Gemini attempt per flow.' }
    }
    if ($browser.exit -ne 0 -or $finalFixture.outbound_attempts -ne 0) { throw 'UC17 browser acceptance failed; see browser and fixture evidence.' }
    $reported = $browser.stdout | ConvertFrom-Json
    $expectedChecks = if ($contextMode) { if ($liveNetwork) { 3 } else { 5 } } elseif ($liveMode) { 2 } elseif ($guestBrowser) { 5 } else { 7 }
    if (!$reported.passed -or @($reported.checkpoints).Count -ne $expectedChecks -or @($reported.checkpoints | Where-Object { $_.status -ne 'PASS' }).Count -gt 0) {
        throw 'UC17 browser evidence is incomplete.'
    }
}

try {
    if ($env:APP_CONFIG_CACHE) { throw 'Refuse inherited configuration cache override.' }
    if ($env:APP_BASE_PATH -or (Test-Path -LiteralPath (Join-Path $repo '.env.testing'))) { throw 'Refuse alternate application base path or testing environment file.' }
    if (Test-Path -LiteralPath (Join-Path $backend 'bootstrap/cache/config.php')) { throw 'Refuse cached Laravel configuration.' }
    if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php'))) { throw 'Missing Composer dependencies.' }
    $protectedBefore = Protected-Fingerprint
    Save-Json 'protected-before.json' $protectedBefore
    if ($liveNetwork) {
        $requiredDrySuite = if ($Suite -eq 'ChatContextLive') { 'ChatContextLiveDryRun' } else { 'ChatLiveDryRun' }
        $requiredBlocks = if ($Suite -eq 'ChatContextLive') { 7 } else { 5 }
        $dryDirectory = Join-Path $repo ".agent-workspace/tv3/ac-env-01/$VerifiedDryRun"
        $dryManifest = Get-Content -LiteralPath (Join-Path $dryDirectory 'manifest.json') -Raw | ConvertFrom-Json
        $dryResult = Get-Content -LiteralPath (Join-Path $dryDirectory 'result.json') -Raw | ConvertFrom-Json
        $dryGuard = Get-Content -LiteralPath (Join-Path $dryDirectory 'guard-preflight.json') -Raw | ConvertFrom-Json
        $dryBrowser = Get-Content -LiteralPath (Join-Path $dryDirectory 'browser.json') -Raw | ConvertFrom-Json
        $dryCleanup = Get-Content -LiteralPath (Join-Path $dryDirectory 'cleanup.json') -Raw | ConvertFrom-Json
        $dryConnection = Get-Content -LiteralPath (Join-Path $dryDirectory 'connection.json') -Raw | ConvertFrom-Json
        $dryScope = Get-Content -LiteralPath (Join-Path $dryDirectory 'protected-after.json') -Raw | ConvertFrom-Json -AsHashtable
        # An independently changing development cache is reported by the runner. The gate checks the owned target and cleanup separately.
        $onlyDevDrift = $dryResult.cleanup_failure -ceq 'Development fingerprint changed; do not restore or overwrite development data.'
        if ($dryManifest.suite -ne $requiredDrySuite -or $dryResult.run_id -cne $VerifiedDryRun -or $dryResult.failure -or
            (!$dryResult.passed -and ($Suite -eq 'ChatContextLive' -or !$onlyDevDrift)) -or $dryBrowser.passed -ne $true -or
            $dryConnection.identity.db -cne "stayhub_tv3_ac_test_$VerifiedDryRun" -or $dryConnection.development_read_denied -ne $true -or
            $dryCleanup.schema_remaining -ne '0' -or $dryCleanup.user_remaining -ne '0' -or
            $dryCleanup.connections_remaining -ne '0' -or $dryCleanup.owned_children_exited -ne $true -or
            $dryGuard.network_attempts -ne 0 -or @($dryGuard.blocked).Count -ne $requiredBlocks -or
            (($dryScope | ConvertTo-Json -Compress) -cne ($protectedBefore | ConvertTo-Json -Compress))) { throw 'ChatLive requires a successful dry run of the current files.' }
        if ($Suite -eq 'ChatContextLive') {
            if ($contextQaKey -cnotmatch '^[!-~]{20,200}$' -or $contextQaKey -eq 'fake-uc17-browser-key') {
                throw 'UC61 live requires a QA key in STAYHUB_UC61_LIVE_GEMINI_API_KEY.'
            }
            $script:liveGeminiKey = $contextQaKey
            $contextQaKey = $null
        } else {
            $keyProbe = @'
require 'backend/vendor/autoload.php';
$values = Dotenv\Dotenv::parse(file_get_contents('.env'));
$key = getenv('STAYHUB_UC17_LIVE_GEMINI_API_KEY') ?: ($values['GEMINI_API_KEY'] ?? '');
if (!preg_match('/\A[!-~]{20,200}\z/', $key)) {exit(2);}
echo $key;
'@
            $script:liveGeminiKey = (Invoke-Child $php @('-r', $keyProbe)).stdout
        }
    }
    $phpProbe = Invoke-Child $php @('-r', 'if (PHP_VERSION_ID < 80200 || PHP_INT_SIZE !== 8 || !extension_loaded("pdo_mysql")) {exit(1);} echo PHP_VERSION;')
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
    $manifest = @{ run_id = $runId; database = $schema; username = $testUser; host = '127.0.0.1'; port = $port;
        development_database = $development; admin_account = $identity[3]; server_uuid = $identity[2]; version = $identity[1]; container = $container; php = $phpProbe.stdout;
        head = (Invoke-Child 'git' @('rev-parse', 'HEAD')).stdout.Trim(); dirty_state = (Invoke-Child 'git' @('status', '--short')).stdout;
        suite = $Suite; created_utc = [DateTime]::UtcNow.ToString('o') }
    if ($Suite -in $browserSuites) {
        $backendPort, $frontendPort = New-BrowserPorts
        $manifest.backend_url = "http://127.0.0.1:$backendPort"
        $manifest.frontend_url = "http://127.0.0.1:$frontendPort"
    }
    Save-Json 'manifest.json' $manifest
    $before = Development-Fingerprint
    Save-Json 'development-before.json' $before
    function Assert-NamesAvailable {
        $counts = (Admin-Sql "SELECT (SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema'), (SELECT COUNT(*) FROM mysql.user WHERE User='$testUser');") -split "`t"
        if ($counts.Count -ne 2) { throw 'Invalid resource availability response.' }
        $code = 'require "backend/vendor/autoload.php"; Tests\Support\Tv3AcceptanceMySqlEnvironment::assertNamesAvailable((int)$argv[1], (int)$argv[2]);'
        $check = Invoke-Child $php @('-r', $code, $counts[0], $counts[1]) -AllowFailure
        if ($check.exit -ne 0) { throw 'TV3_AC owned names already exist; refuse adoption.' }
    }
    Assert-NamesAvailable
    Assert-AdminIdentity
    $null = Admin-Sql ('CREATE DATABASE `' + $schema + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    $schemaCreated = $true
    if ($InjectFailureAfter -eq 'AfterSchema') { throw 'TV3_AC_EXPECTED_FAILURE_AFTER_SCHEMA' }
    if ($InjectFailureAfter -eq 'Collision') {
        $collisionHash = Hash-Text (Admin-Sql ('SHOW CREATE DATABASE `' + $schema + '`;'))
        try { Assert-NamesAvailable; throw 'Collision unexpectedly accepted.' }
        catch {
            if ($_.Exception.Message -ne 'TV3_AC owned names already exist; refuse adoption.') { throw }
            if ($collisionHash -cne (Hash-Text (Admin-Sql ('SHOW CREATE DATABASE `' + $schema + '`;')))) { throw 'Collision changed the existing owned schema.' }
            Save-Json 'collision.json' @{ rejected = $true; existing_schema_unchanged = $true }
            throw 'TV3_AC_EXPECTED_COLLISION_REJECTED'
        }
    }
    Assert-AdminIdentity
    $null = Admin-Sql "CREATE USER '$testUser'@'%' IDENTIFIED BY '$testPassword';"
    $userCreated = $true
    $literalGrantSchema = $schema.Replace('_', '\_')
    Assert-AdminIdentity
    $null = Admin-Sql ('GRANT ALL PRIVILEGES ON `' + $literalGrantSchema + '`.* TO ' + "'$testUser'@'%';")
    $envMap = @{ APP_ENV = 'testing'; APP_KEY = $testAppKey;
        DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = $port; DB_DATABASE = $schema;
        DB_USERNAME = $testUser; DB_PASSWORD = $testPassword; DB_URL = ''; DB_SOCKET = '';
        DB_CHARSET = 'utf8mb4'; DB_COLLATION = 'utf8mb4_unicode_ci'; MYSQL_ATTR_SSL_CA = '';
        CACHE_STORE = 'array'; SESSION_DRIVER = 'array'; QUEUE_CONNECTION = 'sync'; LOG_CHANNEL = 'null';
        APP_DEBUG = 'false'; MAIL_MAILER = 'array'; BROADCAST_CONNECTION = 'null';
        STAYHUB_TV3_AC_INJECT_TEST_FAILURE = $(if ($InjectTestFailure) { '1' } else { '0' });
        STAYHUB_TV3_AC_OPT_IN = '1'; STAYHUB_TV3_AC_MANIFEST = (Join-Path $evidence 'manifest.json') }
    if ($Suite -eq 'InvoiceCreateRace') {
        $envMap.STAYHUB_TV3_AC_CREATE_LIFECYCLE = '1'
        $envMap.STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS = $lifecycleArtifacts
    } else {
        # Do not inherit lifecycle opt-ins into the transaction-wrapped suite.
        $envMap.STAYHUB_TV3_AC_CREATE_LIFECYCLE = ''
        $envMap.STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS = ''
    }
    if ($Suite -eq 'ReviewCreateRace') {
        $envMap.STAYHUB_TV3_AC_REVIEW_LIFECYCLE = '1'
        $envMap.STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS = $lifecycleArtifacts
    } else {
        $envMap.STAYHUB_TV3_AC_REVIEW_LIFECYCLE = ''
    }
    # Build a non-Laravel PDO probe. The secret is read from child environment only.
    $pdoProbe = @'
require 'backend/vendor/autoload.php';
$m = Tests\Support\Tv3AcceptanceMySqlEnvironment::beforeBoot('backend');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
Tests\Support\Tv3AcceptanceMySqlEnvironment::assertIdentity($identity, $m);
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
Tests\Support\Tv3AcceptanceMySqlEnvironment::assertGrants($grants, $m['database']);
try {$pdo->query('SELECT 1 FROM `'.$m['development_database'].'`.invoices LIMIT 0'); throw new RuntimeException('Development access unexpectedly allowed.');}
catch (PDOException $e) {if (($e->errorInfo[1] ?? null) !== 1142) {throw $e;}}
$settings = $pdo->query('SELECT @@session.time_zone AS session_timezone, @@system_time_zone AS system_timezone, @@session.sql_mode AS sql_mode')->fetch(PDO::FETCH_ASSOC);
echo json_encode(['identity'=>$identity, 'grants'=>$grants, 'settings'=>$settings, 'development_read_denied'=>true], JSON_THROW_ON_ERROR);
'@
    $probe = Invoke-Child $php @('-r', $pdoProbe) '' $envMap
    Save-Json 'connection.json' ($probe.stdout | ConvertFrom-Json)
    # Never discover or execute unrelated SQLite-only suites under MySQL overrides.
    $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
        'tests/Unit/Tv3AcceptanceMySqlEnvironmentTest.php',
        'tests/Unit/InvoiceCalculationAcceptanceTest.php',
        'tests/Feature/Acceptance/Tv3AcceptanceEnvironmentMySqlTest.php',
        'tests/Feature/Invoice/InvoiceReadAcceptanceMySqlTest.php',
        'tests/Feature/Invoice/InvoiceCreateAcceptanceMySqlTest.php')
    $requiredMarkers = @('migrations and owned transaction', 'invoice read membership and exact projection',
        'invoice calculation acceptance literal full oracle', 'invoice create acceptance snapshots')
    if ($Suite -eq 'InvoiceCreateRace') {
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Unit/Tv3AcceptanceMySqlEnvironmentTest.php',
            'tests/Feature/Invoice/InvoiceCreateCommitAcceptanceMySqlTest.php',
            'tests/Feature/Invoice/InvoiceCreateConcurrencyAcceptanceMySqlTest.php')
        $requiredMarkers = @('invoice create lifecycle actual commit', 'invoice create race waiter rechecks')
    }
    if ($Suite -eq 'ReviewRead') {
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Unit/Tv3AcceptanceMySqlEnvironmentTest.php',
            'tests/Feature/Acceptance/Tv3AcceptanceEnvironmentMySqlTest.php',
            'tests/Feature/Review/ReviewReadAcceptanceMySqlTest.php')
        $requiredMarkers = @('migrations and owned transaction', 'review read acceptance membership projection')
    }

    if ($Suite -eq 'ReviewCreate') {
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Unit/Tv3AcceptanceMySqlEnvironmentTest.php',
            'tests/Feature/Acceptance/Tv3AcceptanceEnvironmentMySqlTest.php',
            'tests/Feature/Review/ReviewCreateAcceptanceMySqlTest.php')
        $requiredMarkers = @('migrations and owned transaction', 'review create acceptance literal projection')
    }

    if ($Suite -eq 'ReviewCreateRace') {
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Unit/Tv3AcceptanceMySqlEnvironmentTest.php',
            'tests/Feature/Review/ReviewCreateCommitAcceptanceMySqlTest.php',
            'tests/Feature/Review/ReviewCreateConcurrencyAcceptanceMySqlTest.php')
        $requiredMarkers = @('review create lifecycle actual commit', 'review create race waiter rechecks')
    }

    if ($Suite -eq 'ChatData') {
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Feature/Chat/ChatTurnPersistenceMySqlTest.php')
        $requiredMarkers = @('chat migration preserves legacy rows', 'chat success commits exactly one pair',
            'chat pair rolls back', 'chat foreign keys preserve tombstones')
    }

    if ($Suite -eq 'ChatHttp') {
        $envMap.GEMINI_API_KEY = 'fake-chat-http-key'
        $envMap.SUPPORT_CHAT_AI_ENABLED = 'false'
        $envMap.SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS = 'false'
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Feature/Chat/ChatMessageHttpMySqlTest.php')
        $requiredMarkers = @('customer chat persists and replays', 'customer chat rejects requests outside the web session',
            'customer chat retry after timeout', 'customer chat limits requests', 'customer chat blocks external input')
    }

    if ($Suite -eq 'ChatRecovery') {
        $envMap.GEMINI_API_KEY = 'fake-chat-recovery-key'
        $envMap.SUPPORT_CHAT_AI_ENABLED = 'false'
        $envMap.SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS = 'false'
        $envMap.STAYHUB_CHAT_RECOVERY_ARTIFACTS = $lifecycleArtifacts
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Feature/Chat/ChatRecoveryHttpMySqlTest.php')
        $requiredMarkers = @('chat recovery concurrent duplicate', 'chat recovery replays after committed response',
            'chat recovery late worker', 'chat recovery expired worker', 'chat recovery insufficient slot budget')
    }

    if ($Suite -eq 'GuestChat') {
        $envMap.GEMINI_API_KEY = 'fake-uc17-guest-key'
        $envMap.SUPPORT_CHAT_AI_ENABLED = 'false'
        $envMap.SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS = 'false'
        $envMap.SUPPORT_GUEST_CHAT_ALLOW_PROVIDER = 'false'
        $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
            'tests/Feature/Chat/GuestChatMessageHttpMySqlTest.php')
        $requiredMarkers = @('guest chat projects only the safe topic', 'guest chat routes personal questions',
            'guest chat lists only public rooms', 'guest chat limits session ip and route',
            'guest chat shares the provider slot', 'guest chat validates input')
    }

    if ($Suite -in $browserSuites) {
        Invoke-ChatBrowser $envMap
    } else {
        $tests = Invoke-Child $php $testArguments '' $envMap -AllowFailure -WorkingDirectory $backend -TimeoutSeconds 300
        (Protect-Text ($tests.stdout + $tests.stderr)) | Set-Content -LiteralPath (Join-Path $evidence 'tests.txt')
        Save-Json 'test-result.json' @{ exit = $tests.exit; command = 'php ' + ($testArguments -join ' '); engine = 'MySQL Docker' }
        foreach ($marker in $requiredMarkers) {
            if (($tests.stdout + $tests.stderr) -notmatch [regex]::Escape($marker)) { throw 'Required MySQL suite marker missing.' }
        }
        if (($tests.stdout + $tests.stderr) -match '(?i)skipped|WARN') { throw 'MySQL suite did not execute without skips/warnings.' }
        if ($tests.exit -ne 0) { throw 'TV3_AC test run failed; see sanitized tests.txt.' }
    }
} catch {
    $failure = Protect-Text $_.Exception.Message
} finally {
    $cleanupErrors = [Collections.Generic.List[string]]::new()
    if ($Suite -in $browserSuites) {
        $serviceAudit = @()
        for ($index = $browserServices.Count - 1; $index -ge 0; $index--) {
            $service = $browserServices[$index]
            $process = $service.process
            try {
                if (!$process.HasExited) { $process.Kill($true) }
                if (!$process.WaitForExit(10000)) { throw 'Owned browser service did not exit.' }
                $serviceAudit += @{ name = $service.label; pid = $process.Id; exited = $true }
                # Keep diagnostics out of the registry of evidence until sanitized.
                foreach ($stream in @('output', 'errors')) {
                    if ($service[$stream].Wait(2000)) {
                        (Protect-Text $service[$stream].Result) | Set-Content -LiteralPath (Join-Path $evidence "$($service.label)-$stream.txt")
                    }
                }
            } catch {
                $childTerminationFailed = $true
                $cleanupErrors.Add((Protect-Text $_.Exception.Message))
            } finally { $process.Dispose() }
        }
        try {
            if (Test-Path -LiteralPath $edgeProfile) {
                $resolved = (Resolve-Path -LiteralPath $edgeProfile).Path
                if ($childTerminationFailed -or $resolved -cne [IO.Path]::GetFullPath($edgeProfile) -or
                    ((Get-Item -LiteralPath $resolved).Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Refuse unverified Edge profile cleanup.' }
                Remove-Item -LiteralPath $resolved -Recurse -Force
            }
            Save-Json 'browser-cleanup.json' @{ services = $serviceAudit; profile_remaining = (Test-Path -LiteralPath $edgeProfile) }
        } catch { $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
    }
    if ($Suite -in @('InvoiceCreateRace', 'ReviewCreateRace', 'ChatRecovery')) {
        try {
            $workerAudit = @()
            foreach ($spawnFile in (Get-ChildItem -LiteralPath $lifecycleArtifacts -Filter '*-spawn.json' -Recurse -File)) {
                $spawn = Get-Content -LiteralPath $spawnFile.FullName -Raw | ConvertFrom-Json
                if ($spawn.pid -isnot [long] -and $spawn.pid -isnot [int]) { throw 'Invalid owned worker PID evidence.' }
                $workerProcess = Get-Process -Id $spawn.pid -ErrorAction SilentlyContinue
                $workerAudit += @{ pid = $spawn.pid; exited = ($null -eq $workerProcess) }
                if ($null -ne $workerProcess) { throw 'Worker exit unconfirmed; refuse database resource drop.' }
            }
            Save-Json 'worker-cleanup.json' $workerAudit
        } catch {
            $childTerminationFailed = $true
            $cleanupErrors.Add($_.Exception.Message)
        }
    }
    if ($schemaCreated -or $userCreated) {
        $safeCleanup = $false
        try {
            if ($schema -cne "stayhub_tv3_ac_test_$runId" -or $testUser -cne ('tv3ac_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
            if ($childTerminationFailed) { throw 'Child termination unconfirmed; refuse resource drop.' }
            Assert-AdminIdentity
            $safeCleanup = $true
            $connectionIds = @((Admin-Sql "SELECT ID FROM information_schema.PROCESSLIST WHERE USER='$testUser';") -split "`r?`n" | Where-Object { $_ })
            foreach ($connectionId in $connectionIds) {
                if ($connectionId -notmatch '^[0-9]+$') { throw 'Invalid owned connection id.' }
                Assert-AdminIdentity
                $owner = Admin-Sql "SELECT USER FROM information_schema.PROCESSLIST WHERE ID=$connectionId;"
                if ($owner -ceq $testUser) { $null = Admin-Sql "KILL CONNECTION $connectionId;" }
            }
            if ((Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';") -ne '0') { throw 'Owned connections remain; refuse drop.' }
        } catch { $safeCleanup = $false; $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
        if ($safeCleanup) {
            if ($schemaCreated) {
                try { Assert-AdminIdentity; $null = Admin-Sql ('DROP DATABASE `' + $schema + '`;') }
                catch { $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
            }
            if ($userCreated) {
                try { Assert-AdminIdentity; $null = Admin-Sql "DROP USER '$testUser'@'%';" }
                catch { $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
            }
        }
        try {
            Assert-AdminIdentity
            $schemaRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';"
            $userRemaining = Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';"
            $connectionsAfter = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            Save-Json 'cleanup.json' @{ schema_remaining = $schemaRemaining; user_remaining = $userRemaining; connections_remaining = $connectionsAfter; owned_children_exited = !$childTerminationFailed }
            if ($schemaRemaining -ne '0' -or $userRemaining -ne '0' -or $connectionsAfter -ne '0') { throw 'Owned resources remain after cleanup.' }
        } catch { $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
    }
    if ($null -ne $before) {
        try {
            Assert-AdminIdentity
            $after = Development-Fingerprint
            Save-Json 'development-after.json' $after
            if ($before.sha256 -ne $after.sha256 -or
                (($before.tables | ConvertTo-Json -Compress) -cne ($after.tables | ConvertTo-Json -Compress))) {
                throw 'Development fingerprint changed; do not restore or overwrite development data.'
            }
        } catch { $cleanupErrors.Add((Protect-Text $_.Exception.Message)) }
    }
    if ($cleanupErrors.Count -gt 0) { $cleanupFailure = $cleanupErrors -join ' | ' }
    if ($null -ne $protectedBefore) {
        try {
            $protectedAfter = Protected-Fingerprint
            Save-Json 'protected-after.json' $protectedAfter
            if (($protectedBefore | ConvertTo-Json -Compress) -cne ($protectedAfter | ConvertTo-Json -Compress)) {
                throw 'Protected source/config fingerprints changed; no files restored automatically.'
            }
        } catch { $cleanupFailure = "$cleanupFailure $($_.Exception.Message)".Trim() }
    }
    foreach ($artifact in (Get-ChildItem -LiteralPath $evidence -File -Recurse)) {
        if ($Suite -in $browserSuites -and $artifact.FullName.StartsWith($edgeProfile + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { continue }
        $artifactText = [IO.File]::ReadAllText($artifact.FullName)
        if ($artifactText.Contains($testPassword) -or $artifactText.Contains($testAppKey) -or ($liveGeminiKey -and $artifactText.Contains($liveGeminiKey))) {
            [IO.File]::WriteAllText($artifact.FullName, (Protect-Text $artifactText))
            $cleanupFailure = "$cleanupFailure Secret detected and redacted in evidence.".Trim()
        }
    }
    Save-Json 'result.json' @{ run_id = $runId; injected_test_failure = [bool]$InjectTestFailure; injected_failure_after = $InjectFailureAfter; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null
    $testAppKey = $null
    $liveGeminiKey = $null
}
Write-Output "TV3_AC evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "TV3_AC failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'TV3_AC MySQL environment and cleanup verified.'
