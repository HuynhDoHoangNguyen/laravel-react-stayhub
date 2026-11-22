#Requires -Version 7.0
[CmdletBinding()]
param([switch]$Provision, [ValidateSet('Smoke','Concurrency')][string]$Suite='Smoke', [switch]$InjectTestFailure, [switch]$InjectFailureAfterSchema, [switch]$SuppressLockObservation, [switch]$InjectWorkerFailure)

$ErrorActionPreference = 'Stop'
if (!$Provision) { throw 'UC21 requires explicit -Provision; no connection opened.' }
$uc21Backend = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$uc21Repo = (Resolve-Path (Join-Path $uc21Backend '..')).Path
$uc21Run = [guid]::NewGuid().ToString('N')
$uc21Directory = Join-Path $uc21Repo ".agent-workspace/uc21/$uc21Run"
$null = New-Item -ItemType Directory -Path $uc21Directory
$uc21PhpSource = @'
<?php
declare(strict_types=1);
$backend = getenv('UC21_BACKEND');
$run = getenv('UC21_RUN_ID');
if (!preg_match('/\A[a-f0-9]{32}\z/', $run)) throw new RuntimeException('Invalid run ID.');
chdir($backend);
require 'vendor/autoload.php';
$directory = realpath($backend.'/../.agent-workspace/uc21/'.$run);
if ($directory !== realpath(dirname(__FILE__))) throw new RuntimeException('Unowned runner directory.');
if (getenv('APP_CONFIG_CACHE') || is_file('bootstrap/cache/config.php')) throw new RuntimeException('UC21 refuses config cache.');
$schema = 'stayhub_uc21_'.$run.'_test';
$user = 'uc21_'.substr($run, 0, 16);
$password = bin2hex(random_bytes(32));
$schemaCreated = $userCreated = false;
$pdo = null; $before = null; $failure = null; $cleanup = []; $processStopped = true;
function saveEvidence(string $name, array $data): void { global $directory; file_put_contents($directory.'/'.$name, json_encode($data, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); }
function identity(PDO $pdo): array { return $pdo->query('SELECT VERSION() AS version, @@hostname AS hostname, @@server_id AS server_id')->fetch(PDO::FETCH_ASSOC); }
function storageFingerprint(string $root): string {
    if (!is_dir($root)) return hash('sha256', 'absent');
    $rows = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink()) throw new RuntimeException('Refuse linked development storage fingerprint.');
        if ($file->isFile()) $rows[] = substr($file->getPathname(), strlen($root)).':'.hash_file('sha256', $file->getPathname());
    }
    sort($rows); return hash('sha256', serialize($rows));
}
function fingerprint(PDO $pdo, string $database): string {
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $database)) throw new RuntimeException('Invalid database name.');
    $tables = $pdo->query('SHOW FULL TABLES FROM `'.$database.'` WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables); $hash = hash_init('sha256');
    foreach ($tables as $table) {
        $quoted = '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
        $rows = [];
        foreach ($pdo->query('SELECT * FROM '.$quoted) as $row) $rows[] = hash('sha256', serialize($row));
        sort($rows);
        hash_update($hash, $table.serialize($rows).serialize($pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)));
    }
    return hash_final($hash);
}
try {
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $config = $app['config']->get('database.connections.mysql');
    $development = $config['database'];
    if ($app['config']->get('database.default') !== 'mysql' || $app->environment('testing')
        || $config['host'] !== '127.0.0.1' || !empty($config['url']) || !empty($config['unix_socket'])
        || !preg_match('/\A[a-zA-Z0-9_]+\z/', $development) || str_starts_with($development, 'stayhub_uc21_')) throw new RuntimeException('Unexpected local development target.');
    $pdo = new PDO('mysql:host=127.0.0.1;port='.$config['port'].';dbname=information_schema;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server = identity($pdo);
    if (!preg_match('/\A10\.11\.[0-9]+-MariaDB/', $server['version'])) throw new RuntimeException('Runner requires the audited MariaDB 10.11 server.');
    $grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    if (!array_filter($grants, fn ($grant) => str_starts_with($grant, 'GRANT ALL PRIVILEGES ON *.* TO '))) throw new RuntimeException('Provision requires an authorized administrative connection; no resources created.');
    $before = fingerprint($pdo, $development);
    $envHash = hash_file('sha256', $backend.'/../.env');
    $storageHash = storageFingerprint($backend.'/storage/app/public');
    if ($pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=".$pdo->quote($schema))->fetchColumn() != 0
        || $pdo->query('SELECT COUNT(*) FROM mysql.user WHERE User='.$pdo->quote($user))->fetchColumn() != 0) throw new RuntimeException('UC21 resource collision.');
    $pdo->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $schemaCreated = true;
    if (getenv('UC21_INJECT_SCHEMA_FAILURE') === '1') throw new RuntimeException('Injected failure after owned schema creation.');
    $account = $pdo->quote($user)."@'127.0.0.1'";
    $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password)); $userCreated = true;
    $scope = str_replace('_', '\\_', $schema);
    $pdo->exec('GRANT ALL PRIVILEGES ON `'.$scope.'`.* TO '.$account);
    $manifest = $server + ['run_id'=>$run, 'database'=>$schema, 'username'=>$user, 'port'=>(string)$config['port']];
    mkdir($directory.'/public'); saveEvidence('manifest.json', $manifest);
    $childEnv = [
        'APP_ENV'=>'testing', 'DB_CONNECTION'=>'mysql', 'DB_HOST'=>'127.0.0.1', 'DB_PORT'=>(string)$config['port'],
        'DB_DATABASE'=>$schema, 'DB_USERNAME'=>$user, 'DB_PASSWORD'=>$password, 'DB_URL'=>'', 'DATABASE_URL'=>'', 'DB_SOCKET'=>'',
        'ALLOW_MYSQL_TESTS'=>'true', 'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array', 'QUEUE_CONNECTION'=>'sync',
        'BCRYPT_ROUNDS'=>'4', 'STAYHUB_UC21_OPT_IN'=>'1', 'STAYHUB_UC21_MANIFEST'=>$directory.'/manifest.json',
        'STAYHUB_UC21_INJECT_FAILURE'=>getenv('UC21_INJECT_TEST_FAILURE') ?: '0', 'APP_CONFIG_CACHE'=>false,
        'UC21_FAIL_WORKER'=>getenv('UC21_INJECT_WORKER_FAILURE') ?: '0',
    ];
    $suite = getenv('UC21_SUITE') ?: 'Smoke';
    if (!in_array($suite, ['Smoke','Concurrency'], true)) throw new RuntimeException('Invalid suite.');
    $testFile = $suite === 'Smoke' ? 'Uc21RoomImagesDatabaseTest.php' : 'Uc21RoomImagesConcurrencyTest.php';
    $arguments=[PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '--testdox', 'tests/Feature/Catalog/'.$testFile];
    if ($suite==='Concurrency' && (getenv('UC21_SUPPRESS_OBSERVATION')==='1' || getenv('UC21_INJECT_WORKER_FAILURE')==='1' || getenv('UC21_INJECT_TEST_FAILURE')==='1')) {
        $arguments[]='--filter'; $arguments[]='upload limit';
    }
    $process = new Symfony\Component\Process\Process($arguments, $backend, $childEnv);
    $process->setTimeout(180); $processStopped = false;
    try {
        $process->start();
        while ($process->isRunning()) {
            $process->checkTimeout();
            if ($suite === 'Concurrency' && getenv('UC21_SUPPRESS_OBSERVATION') !== '1') {
                foreach (glob($directory.'/case-*/observe.json') as $request) {
                    $caseDirectory=dirname($request);
                    if (!preg_match('/\Acase-[a-f0-9]{32}\z/',basename($caseDirectory)) || is_link($caseDirectory)
                        || is_file($caseDirectory.'/observed.json')) continue;
                    $pair=json_decode(file_get_contents($request),true,flags:JSON_THROW_ON_ERROR);
                    if (!is_int($pair['waiting']??null) || !is_int($pair['blocking']??null)) throw new RuntimeException('Invalid observation pair.');
                    $query=$pdo->prepare('SELECT r.trx_mysql_thread_id AS waiting, b.trx_mysql_thread_id AS blocking FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id JOIN information_schema.PROCESSLIST pr ON pr.ID=r.trx_mysql_thread_id JOIN information_schema.PROCESSLIST pb ON pb.ID=b.trx_mysql_thread_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=? AND pr.USER=? AND pb.USER=? AND pr.DB=? AND pb.DB=?');
                    $query->execute([$pair['waiting'],$pair['blocking'],$user,$user,$schema,$schema]);
                    if ($wait=$query->fetch(PDO::FETCH_ASSOC)) {
                        $protocol=new Tests\Support\Uc21ImageRaceProtocol($directory,basename($caseDirectory));
                        $protocol->write('observed',$wait+['source'=>'INNODB_LOCK_WAITS','observed_at'=>microtime(true)]);
                    }
                }
            }
            usleep(10000);
        }
    }
    finally { if ($process->isRunning()) $process->stop(5); $processStopped = !$process->isRunning(); }
    $output = str_replace($password, '[REDACTED]', $process->getOutput().$process->getErrorOutput());
    file_put_contents($directory.'/tests.txt', $output);
    saveEvidence('tests.json', ['exit'=>$process->getExitCode(), 'engine'=>$server['version']]);
    if (!$process->isSuccessful() || !str_contains($output, $suite==='Smoke'?'OK (1 test,':'OK (8 tests,') || str_contains($output, 'skipped')) throw new RuntimeException('UC21 suite failed; see sanitized evidence.');
} catch (Throwable $error) {
    // Never publish exception strings from SQL, which may contain credentials.
    $failure = $error instanceof PDOException ? 'Database operation failed; credentials and SQL suppressed.' : str_replace($password, '[REDACTED]', $error->getMessage());
} finally {
    if ($pdo && ($schemaCreated || $userCreated)) {
        try {
            if (!$processStopped || identity($pdo) !== $server || $schema !== 'stayhub_uc21_'.$run.'_test' || $user !== 'uc21_'.substr($run, 0, 16)) throw new RuntimeException('Cleanup ownership/identity mismatch.');
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE USER=?'); $stmt->execute([$user]);
            if ($stmt->fetchColumn() != 0) throw new RuntimeException('Owned test connections remain; resources retained.');
            if ($schemaCreated) $pdo->exec('DROP DATABASE `'.$schema.'`');
            if ($userCreated) $pdo->exec('DROP USER '.$pdo->quote($user)."@'127.0.0.1'");
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?'); $stmt->execute([$schema]); $schemaRemaining=(int)$stmt->fetchColumn();
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?'); $stmt->execute([$user]); $userRemaining=(int)$stmt->fetchColumn();
            if ($schemaRemaining || $userRemaining) throw new RuntimeException('Owned resources remain.');
            saveEvidence('cleanup.json', ['schema_remaining'=>$schemaRemaining, 'user_remaining'=>$userRemaining, 'children_exited'=>$processStopped]);
        } catch (Throwable) { $cleanup[]='Cleanup failed; inspect owned resources before retry.'; }
    }
    if ($before !== null) {
        try {
            $unchanged = $before === fingerprint($pdo, $development);
            $envUnchanged = $envHash === hash_file('sha256', $backend.'/../.env');
            $storageUnchanged = $storageHash === storageFingerprint($backend.'/storage/app/public');
            saveEvidence('preservation.json', ['development_unchanged'=>$unchanged, 'env_unchanged'=>$envUnchanged, 'development_storage_unchanged'=>$storageUnchanged]);
            if (!$unchanged || !$envUnchanged || !$storageUnchanged) $cleanup[]='Development/environment changed; no restoration attempted.';
        } catch (Throwable) { $cleanup[]='Preservation verification failed.'; }
    }
    saveEvidence('result.json', ['passed'=>$failure === null && !$cleanup, 'failure'=>$failure, 'cleanup_errors'=>$cleanup]);
}
echo 'UC21 evidence: '.$directory.PHP_EOL;
exit($failure !== null || $cleanup ? 1 : 0);
'@
$uc21Script = Join-Path $uc21Directory 'runner.php'
[IO.File]::WriteAllText($uc21Script, $uc21PhpSource, [Text.UTF8Encoding]::new($false))
$uc21Start = [Diagnostics.ProcessStartInfo]::new()
$uc21Start.FileName = (Get-Command php -ErrorAction Stop).Source
$uc21Start.WorkingDirectory = $uc21Backend
$uc21Start.UseShellExecute = $false
$uc21Start.CreateNoWindow = $true
$uc21Start.ArgumentList.Add($uc21Script)
$uc21Start.Environment['UC21_BACKEND'] = $uc21Backend
$uc21Start.Environment['UC21_RUN_ID'] = $uc21Run
$uc21Start.Environment['UC21_SUITE'] = $Suite
$uc21Start.Environment['UC21_SUPPRESS_OBSERVATION'] = [string][int][bool]$SuppressLockObservation
$uc21Start.Environment['UC21_INJECT_WORKER_FAILURE'] = [string][int][bool]$InjectWorkerFailure
$uc21Start.Environment['UC21_INJECT_TEST_FAILURE'] = [string][int][bool]$InjectTestFailure
$uc21Start.Environment['UC21_INJECT_SCHEMA_FAILURE'] = [string][int][bool]$InjectFailureAfterSchema
$uc21Child = [Diagnostics.Process]::Start($uc21Start)
try {
    if (!$uc21Child.WaitForExit(240000)) { $uc21Child.Kill($true); $uc21Child.WaitForExit(); throw 'UC21 runner timed out; inspect owned-resource evidence.' }
    $uc21Exit = $uc21Child.ExitCode
} finally {
    if (!$uc21Child.HasExited) { $uc21Child.Kill($true); $uc21Child.WaitForExit() }
    $uc21Child.Dispose()
    $uc21Public = Join-Path $uc21Directory 'public'
    if (Test-Path -LiteralPath $uc21Public) {
        $uc21Resolved = (Resolve-Path -LiteralPath $uc21Public).Path
        if ($uc21Resolved -ne $uc21Public -or (Split-Path $uc21Resolved -Parent) -ne $uc21Directory) { throw 'Refuse storage cleanup outside owned run.' }
        $uc21Entries = @((Get-Item -LiteralPath $uc21Public -Force)) + @(Get-ChildItem -LiteralPath $uc21Public -Recurse -Force)
        if ($uc21Entries | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Refuse cleanup through reparse point.' }
        Remove-Item -LiteralPath $uc21Public -Recurse -Force
        [IO.File]::WriteAllText((Join-Path $uc21Directory 'storage-cleanup.json'), '{"owned_storage_removed":true}')
    }
}
if ($uc21Exit -ne 0) { throw "UC21 runner failed (exit $uc21Exit); see $uc21Directory" }
Write-Output "UC21 owned MariaDB $Suite and cleanup PASS."
