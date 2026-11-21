# Run from PowerShell 7. No env/config changes in the caller or development checkout.
[CmdletBinding()]
param(
    [switch]$Provision,
    # Negative control: the smoke test fails after writing to its owned transaction.
    [switch]$InjectTestFailure,
    [ValidateSet('None', 'AfterSchema', 'Collision')]
    [string]$InjectFailureAfter = 'None'
)

#Requires -Version 7.0
if (!$Provision) { throw 'Explicit -Provision is required; no database connection was opened.' }

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$repo = (Resolve-Path (Join-Path $backend '..')).Path
$runId = [guid]::NewGuid().ToString('N')
$schema = "stayhub_uc48_test_$runId"
$testUser = 'uc48_' + $runId.Substring(0, 16)
$testPassword = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testAppKey = 'base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$evidence = Join-Path $repo ".agent-workspace/tv3/uc48-env-01/$runId"
$null = New-Item -ItemType Directory -Path $evidence
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
    # Include all nonignored untracked files, especially the accepted UC48 contract.
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
    if ($env:APP_CONFIG_CACHE) { throw 'Refuse inherited configuration cache override.' }
    if ($env:APP_BASE_PATH -or (Test-Path -LiteralPath (Join-Path $repo '.env.testing'))) { throw 'Refuse alternate application base path or testing environment file.' }
    if (Test-Path -LiteralPath (Join-Path $backend 'bootstrap/cache/config.php')) { throw 'Refuse cached Laravel configuration.' }
    if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php'))) { throw 'Missing Composer dependencies.' }
    $protectedBefore = Protected-Fingerprint
    Save-Json 'protected-before.json' $protectedBefore
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
    if ($development -notmatch '^[a-zA-Z0-9_]{1,64}$' -or $development.StartsWith('stayhub_uc48_test_') -or
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
        created_utc = [DateTime]::UtcNow.ToString('o') }
    Save-Json 'manifest.json' $manifest
    $before = Development-Fingerprint
    Save-Json 'development-before.json' $before
    function Assert-NamesAvailable {
        $counts = (Admin-Sql "SELECT (SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$schema'), (SELECT COUNT(*) FROM mysql.user WHERE User='$testUser');") -split "`t"
        if ($counts.Count -ne 2) { throw 'Invalid resource availability response.' }
        $code = 'require "backend/vendor/autoload.php"; Tests\Support\Uc48MySqlEnvironment::assertNamesAvailable((int)$argv[1], (int)$argv[2]);'
        $check = Invoke-Child $php @('-r', $code, $counts[0], $counts[1]) -AllowFailure
        if ($check.exit -ne 0) { throw 'UC48 owned names already exist; refuse adoption.' }
    }
    Assert-NamesAvailable
    Assert-AdminIdentity
    $null = Admin-Sql ('CREATE DATABASE `' + $schema + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    $schemaCreated = $true
    if ($InjectFailureAfter -eq 'AfterSchema') { throw 'UC48_EXPECTED_FAILURE_AFTER_SCHEMA' }
    if ($InjectFailureAfter -eq 'Collision') {
        $collisionHash = Hash-Text (Admin-Sql ('SHOW CREATE DATABASE `' + $schema + '`;'))
        try { Assert-NamesAvailable; throw 'Collision unexpectedly accepted.' }
        catch {
            if ($_.Exception.Message -ne 'UC48 owned names already exist; refuse adoption.') { throw }
            if ($collisionHash -cne (Hash-Text (Admin-Sql ('SHOW CREATE DATABASE `' + $schema + '`;')))) { throw 'Collision changed the existing owned schema.' }
            Save-Json 'collision.json' @{ rejected = $true; existing_schema_unchanged = $true }
            throw 'UC48_EXPECTED_COLLISION_REJECTED'
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
        STAYHUB_UC48_INJECT_TEST_FAILURE = $(if ($InjectTestFailure) { '1' } else { '0' });
        STAYHUB_UC48_OPT_IN = '1'; STAYHUB_UC48_MANIFEST = (Join-Path $evidence 'manifest.json') }
    # Build a non-Laravel PDO probe. The secret is read from child environment only.
    $pdoProbe = @'
require 'backend/vendor/autoload.php';
$m = Tests\Support\Uc48MySqlEnvironment::beforeBoot('backend');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
Tests\Support\Uc48MySqlEnvironment::assertIdentity($identity, $m);
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
Tests\Support\Uc48MySqlEnvironment::assertGrants($grants, $m['database']);
try {$pdo->query('SELECT 1 FROM `'.$m['development_database'].'`.invoices LIMIT 0'); throw new RuntimeException('Development access unexpectedly allowed.');}
catch (PDOException $e) {if (($e->errorInfo[1] ?? null) !== 1142) {throw $e;}}
$settings = $pdo->query('SELECT @@session.time_zone AS session_timezone, @@system_time_zone AS system_timezone, @@session.sql_mode AS sql_mode')->fetch(PDO::FETCH_ASSOC);
echo json_encode(['identity'=>$identity, 'grants'=>$grants, 'settings'=>$settings, 'development_read_denied'=>true], JSON_THROW_ON_ERROR);
'@
    $probe = Invoke-Child $php @('-r', $pdoProbe) '' $envMap
    Save-Json 'connection.json' ($probe.stdout | ConvertFrom-Json)
    # Never discover or execute unrelated SQLite-only suites under MySQL overrides.
    $testArguments = @('artisan', 'test', '--no-ansi', '--do-not-cache-result',
        'tests/Unit/Uc48MySqlEnvironmentTest.php',
        'tests/Unit/AdminStatisticsValidatorTest.php',
        'tests/Unit/AdminStatisticsPeriodTest.php',
        'tests/Unit/AdminStatisticsContractTest.php',
        'tests/Feature/Statistics/Uc48EnvironmentMySqlTest.php',
        'tests/Feature/Statistics/Uc48StatisticsQueryMySqlTest.php',
        'tests/Feature/Statistics/Uc48AdminStatisticsHttpMySqlTest.php',
        'tests/Feature/Statistics/Uc48StatisticsDataAcceptanceMySqlTest.php')
    $tests = Invoke-Child $php $testArguments '' $envMap -AllowFailure -WorkingDirectory $backend -TimeoutSeconds 300
    (Protect-Text ($tests.stdout + $tests.stderr)) | Set-Content -LiteralPath (Join-Path $evidence 'tests.txt')
    Save-Json 'test-result.json' @{ exit = $tests.exit; command = 'php ' + ($testArguments -join ' '); engine = 'MySQL Docker' }
    if (($tests.stdout + $tests.stderr) -notmatch 'migrations and owned transaction' -or
        ($tests.stdout + $tests.stderr) -match '(?im)^\s*(?:WARN(?:ING)?|SKIP(?:PED)?)(?:\s|$)|^\s*Tests:.*\b(?:skipped|warnings?)\b') { throw 'MySQL smoke did not execute without skips/warnings.' }
    if ($tests.exit -ne 0) { throw 'UC48 test run failed; see sanitized tests.txt.' }
} catch {
    $failure = Protect-Text $_.Exception.Message
} finally {
    $cleanupErrors = [Collections.Generic.List[string]]::new()
    if ($schemaCreated -or $userCreated) {
        $safeCleanup = $false
        try {
            if ($schema -cne "stayhub_uc48_test_$runId" -or $testUser -cne ('uc48_' + $runId.Substring(0, 16))) { throw 'Ownership mismatch; refuse cleanup.' }
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
    foreach ($artifact in (Get-ChildItem -LiteralPath $evidence -File)) {
        $artifactText = [IO.File]::ReadAllText($artifact.FullName)
        if ($artifactText.Contains($testPassword) -or $artifactText.Contains($testAppKey)) {
            [IO.File]::WriteAllText($artifact.FullName, (Protect-Text $artifactText))
            $cleanupFailure = "$cleanupFailure Secret detected and redacted in evidence.".Trim()
        }
    }
    Save-Json 'result.json' @{ run_id = $runId; injected_test_failure = [bool]$InjectTestFailure; injected_failure_after = $InjectFailureAfter; failure = $failure; cleanup_failure = $cleanupFailure;
        passed = ($null -eq $failure -and $null -eq $cleanupFailure); finished_utc = [DateTime]::UtcNow.ToString('o') }
    $testPassword = $null
    $testAppKey = $null
}
Write-Output "UC48 evidence: $evidence"
if ($failure -or $cleanupFailure) { throw "UC48 failed: $failure Cleanup: $cleanupFailure" }
Write-Output 'UC48 MySQL environment and cleanup verified.'
