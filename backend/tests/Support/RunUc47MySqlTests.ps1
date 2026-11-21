# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param(
    [switch]$Provision,
    # Negative control: the smoke test fails after writing to its owned transaction.
    [switch]$InjectTestFailure
)

#Requires -Version 7.0
if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$schema = "stayhub_uc47_test_$runId"
$testUser = 'uc47_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testAppKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$evidence = Join-Path $repo ".agent-workspace/tv3/uc47-env-01/$runId"
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

function Protect-Text([string]$Value) {
    return $Value.Replace($testPassword, '[REDACTED]').Replace($testAppKey, '[REDACTED]')
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
    $null = $process.Start()
    $outTask = $process.StandardOutput.ReadToEndAsync()
    $errTask = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.Write($InputText)
    $process.StandardInput.Close()
    if (!$process.WaitForExit($TimeoutSeconds * 1000)) {
        $process.Kill($true)
        $process.WaitForExit(10000) | Out-Null
        $process.Dispose()
        throw 'Owned child process exceeded its timeout and was terminated.'
    }
    $result = @{ exit = $process.ExitCode; stdout = $outTask.GetAwaiter().GetResult(); stderr = $errTask.GetAwaiter().GetResult() }
    $process.Dispose()
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
    # Include untracked scope files as well as tracked files, on both snapshots.
    $paths += @((Invoke-Child 'git' @('ls-files', '--others', '--exclude-standard', '--',
        'backend/tests/Support/Uc47*', 'backend/tests/Support/RunUc47MySqlTests.ps1',
        'backend/tests/Unit/Uc47*', 'backend/tests/Feature/Dashboard/Uc47*',
        'backend/app', 'backend/routes', 'backend/tests/Unit/AdminDashboardContractTest.php',
        'frontend/src', 'frontend/tests/adminDashboard.test.js')).stdout -split "`r?`n" | Where-Object { $_ })
    $paths += @('.env', 'backend/.env', 'frontend/.env')
    $hashes = [ordered]@{}
    foreach ($path in ($paths | Sort-Object -Unique)) {
        $absolute = Join-Path $repo $path
        $hashes[$path] = if (Test-Path -LiteralPath $absolute -PathType Leaf) { (Get-FileHash -LiteralPath $absolute -Algorithm SHA256).Hash } else { 'ABSENT' }
    }
    return $hashes
}

try {
    if (Test-Path -LiteralPath (Join-Path $backend 'bootstrap/cache/config.php')) { throw 'Refuse cached Laravel configuration.' }
    if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php'))) { throw 'Missing Composer dependencies.' }
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
    $manifest = @{ run_id = $runId; database = $schema; username = $testUser; host = '127.0.0.1'; port = $port;
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
        STAYHUB_UC47_INJECT_TEST_FAILURE = $(if ($InjectTestFailure) { '1' } else { '0' });
        STAYHUB_UC47_OPT_IN = '1'; STAYHUB_UC47_MANIFEST = (Join-Path $evidence 'manifest.json') }
    # Build a non-Laravel PDO probe. The secret is read from child environment only.
    $pdoProbe = @'
require 'backend/vendor/autoload.php';
$m = Tests\Support\Uc47MySqlEnvironment::beforeBoot('backend');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
Tests\Support\Uc47MySqlEnvironment::assertIdentity($identity, $m);
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
Tests\Support\Uc47MySqlEnvironment::assertGrants($grants, $m['database']);
try {$pdo->query('SELECT 1 FROM stayhub.invoices LIMIT 0'); throw new RuntimeException('Development access unexpectedly allowed.');}
catch (PDOException $e) {if (($e->errorInfo[1] ?? null) !== 1142) {throw $e;}}
echo json_encode(['identity'=>$identity, 'grants'=>$grants, 'development_read_denied'=>true], JSON_THROW_ON_ERROR);
'@
    $probe = Invoke-Child $php @('-r', $pdoProbe) '' $envMap
    Save-Json 'connection.json' ($probe.stdout | ConvertFrom-Json)
    # Never discover or execute unrelated SQLite-only suites under MySQL overrides.
    $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
        'tests/Unit/Uc47MySqlEnvironmentTest.php',
        'tests/Feature/Dashboard/Uc47EnvironmentMySqlTest.php',
        'tests/Unit/AdminDashboardContractTest.php',
        'tests/Feature/Dashboard/Uc47AdminDashboardMySqlTest.php')
    $tests = Invoke-Child $php $testArguments '' $envMap -AllowFailure -WorkingDirectory $backend -TimeoutSeconds 300
    (Protect-Text ($tests.stdout + $tests.stderr)) | Set-Content -LiteralPath (Join-Path $evidence 'tests.txt')
    Save-Json 'test-result.json' @{ exit = $tests.exit; command = 'php ' + ($testArguments -join ' '); engine = 'MySQL Docker' }
    if ($tests.exit -ne 0) { throw 'UC47 test run failed; see sanitized tests.txt.' }
} catch {
    $failure = Protect-Text $_.Exception.Message
} finally {
    try {
        if ($schemaCreated -or $userCreated) {
            if ($schema -cne "stayhub_uc47_test_$runId" -or $testUser -cne ('uc47_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
            Assert-AdminIdentity
            $connections = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            if ($connections -ne '0') { throw 'Owned user still has active connections; refuse drop and preserve evidence.' }
            if ($schemaCreated) { $null = Admin-Sql ('DROP DATABASE `' + $schema + '`;') }
            if ($userCreated) { $null = Admin-Sql "DROP USER '$testUser'@'%';" }
            $schemaRemaining = Admin-Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema';"
            $userRemaining = Admin-Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$testUser';"
            $connectionsAfter = Admin-Sql "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER='$testUser';"
            Save-Json 'cleanup.json' @{ schema_remaining = $schemaRemaining; user_remaining = $userRemaining; connections_before_drop = $connections; connections_remaining = $connectionsAfter }
            if ($schemaRemaining -ne '0' -or $userRemaining -ne '0' -or $connectionsAfter -ne '0') { throw 'Owned resources remain after cleanup.' }
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
        if ($artifactText.Contains($testPassword) -or $artifactText.Contains($testAppKey)) {
            [IO.File]::WriteAllText($artifact.FullName, (Protect-Text $artifactText))
            $cleanupFailure = "$cleanupFailure Secret detected and redacted in evidence.".Trim()
        }
    }
    Save-Json 'result.json' @{ run_id = $runId; injected_test_failure = [bool]$InjectTestFailure; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null
    $testAppKey = $null
}
Write-Output "UC47 evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "UC47 failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'UC47 MySQL environment and cleanup verified.'
