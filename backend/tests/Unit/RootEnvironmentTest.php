<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class RootEnvironmentTest extends TestCase
{
    public static function rootEnvironments(): array
    {
        $docker = 'MYSQL_PORT=3307'."\n".'DB_PORT=${MYSQL_PORT}'."\n";

        return [
            'XAMPP without legacy env' => ["DB_PORT=3306\n", '3306', null, false],
            'XAMPP with legacy env' => ["DB_PORT=3306\n", '3306', null, true],
            'Docker without legacy env' => [$docker, '3307', '3307', false],
            'Docker with legacy env' => [$docker, '3307', '3307', true],
        ];
    }

    #[DataProvider('rootEnvironments')]
    public function test_root_loader_keeps_backend_base_path_and_process_overrides(
        string $portConfiguration, string $expectedPort, ?string $expectedMysqlPort, bool $legacyEnvironment,
    ): void
    {
        $backend = dirname(__DIR__, 2);
        $temporary = $this->temporaryProject();
        try {
            mkdir($temporary.'/backend/bootstrap', 0700);
            mkdir($temporary.'/backend/config', 0700);
            // Exercise the actual bootstrap and configuration in an isolated project tree.
            copy($backend.'/bootstrap/app.php', $temporary.'/backend/bootstrap/app.php');
            foreach (['app', 'database', 'session'] as $configuration) {
                copy($backend.'/config/'.$configuration.'.php', $temporary.'/backend/config/'.$configuration.'.php');
            }
            file_put_contents($temporary.'/.env', "STAYHUB_E2_ENV_MARKER=root_fixture\n"
                ."APP_KEY=file-key\nDB_DATABASE=file_database\nDB_URL=file-url\nDB_SOCKET=file-socket\nSESSION_DRIVER=file\n"
                .$portConfiguration);
            if ($legacyEnvironment) {
                file_put_contents($temporary.'/backend/.env', "STAYHUB_E2_ENV_MARKER=legacy_fixture\nDB_PORT=9999\n");
            }

            $probe = <<<'PHP'
require $argv[1];
$app = require 'bootstrap/app.php';
(new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
(new Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
echo json_encode([
    'base' => realpath($app->basePath()),
    'environment' => realpath($app->environmentPath()),
    'key' => config('app.key') === getenv('APP_KEY'),
    'database' => config('database.connections.mysql.database'),
    'url' => config('database.connections.mysql.url'),
    'socket' => config('database.connections.mysql.unix_socket'),
    'session' => config('session.driver'),
    'marker' => Illuminate\Support\Env::get('STAYHUB_E2_ENV_MARKER'),
    'port' => config('database.connections.mysql.port'),
    'mysql_port' => Illuminate\Support\Env::get('MYSQL_PORT'),
    'cached' => $app->configurationIsCached(),
]);
PHP;
            $process = new Process([PHP_BINARY, '-r', $probe, $backend.'/vendor/autoload.php'], $temporary.'/backend', [
                'APP_ENV' => 'testing', 'APP_KEY' => 'root-env-test-key',
                'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'root_env_probe_no_connection',
                'DB_URL' => '', 'DB_SOCKET' => '', 'SESSION_DRIVER' => 'array',
                'APP_CONFIG_CACHE' => 'bootstrap/cache/config.php',
                // The file must supply these facts, independently of the invoking shell.
                'MYSQL_PORT' => false, 'DB_PORT' => false, 'STAYHUB_E2_ENV_MARKER' => false,
            ]);
            $process->mustRun();
            $actual = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(realpath($temporary.'/backend'), $actual['base']);
            $this->assertSame(realpath($temporary), $actual['environment']);
            $this->assertTrue($actual['key']);
            $this->assertSame('root_env_probe_no_connection', $actual['database']);
            $this->assertSame('', $actual['url']);
            $this->assertSame('', $actual['socket']);
            $this->assertSame('array', $actual['session']);
            $this->assertSame('root_fixture', $actual['marker']);
            $this->assertSame($expectedPort, $actual['port']);
            $this->assertSame($expectedMysqlPort, $actual['mysql_port']);
            $this->assertFalse($actual['cached']);
        } finally {
            $this->removeTemporaryProject($temporary);
        }
    }

    public function test_composer_hooks_copy_only_the_missing_root_environment(): void
    {
        $scripts = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR)['scripts'];
        $hook = '@php -r "file_exists(\'../.env\') || copy(\'../.env.example\', \'../.env\');"';
        $this->assertContains($hook, $scripts['setup']);
        $this->assertSame([$hook], $scripts['post-root-package-install']);
        $temporary = $this->temporaryProject();
        try {
            file_put_contents($temporary.'/.env.example', "APP_NAME=template\n");
            $command = substr($scripts['post-root-package-install'][0], strlen('@php -r "'), -1);
            (new Process([PHP_BINARY, '-r', $command], $temporary.'/backend'))->mustRun();
            $this->assertSame("APP_NAME=template\n", file_get_contents($temporary.'/.env'));
            file_put_contents($temporary.'/.env', "APP_NAME=existing\n");
            (new Process([PHP_BINARY, '-r', $command], $temporary.'/backend'))->mustRun();
            $this->assertSame("APP_NAME=existing\n", file_get_contents($temporary.'/.env'));
            $this->assertFileDoesNotExist($temporary.'/backend/.env');
            $this->assertFileDoesNotExist($temporary.'/backend/.env.example');
        } finally {
            $this->removeTemporaryProject($temporary);
        }
    }

    public function test_only_the_root_environment_template_is_tracked(): void
    {
        $process = new Process(['git', 'ls-files', '--', '.env', '.env.example',
            'backend/.env', 'backend/.env.*', 'frontend/.env', 'frontend/.env.*'], dirname(__DIR__, 3));
        $process->mustRun();

        $this->assertSame('.env.example', trim($process->getOutput()));
    }

    private function temporaryProject(): string
    {
        $temporary = sys_get_temp_dir().'/stayhub-root-env-'.bin2hex(random_bytes(8));
        mkdir($temporary.'/backend', 0700, true);

        return $temporary;
    }

    private function removeTemporaryProject(string $temporary): void
    {
        foreach (['backend/bootstrap/app.php', 'backend/config/app.php', 'backend/config/database.php',
            'backend/config/session.php', 'backend/.env', 'backend/.env.example', '.env', '.env.example'] as $file) {
            if (is_file($temporary.'/'.$file)) unlink($temporary.'/'.$file);
        }
        foreach (['backend/config', 'backend/bootstrap', 'backend', ''] as $directory) {
            if (is_dir($temporary.'/'.$directory)) rmdir($temporary.'/'.$directory);
        }
    }
}
