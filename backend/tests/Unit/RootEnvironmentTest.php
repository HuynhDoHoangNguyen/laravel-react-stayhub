<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class RootEnvironmentTest extends TestCase
{
    public function test_root_loader_keeps_backend_base_path_and_process_overrides(): void
    {
        $backend = dirname(__DIR__, 2);
        $probe = <<<'PHP'
require 'vendor/autoload.php';
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
    'root_loaded' => Illuminate\Support\Env::get('MYSQL_PORT') !== null,
]);
PHP;
        $process = new Process([PHP_BINARY, '-r', $probe], $backend, [
            'APP_ENV' => 'testing', 'APP_KEY' => 'root-env-test-key',
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'root_env_probe_no_connection',
            'DB_URL' => '', 'DB_SOCKET' => '', 'SESSION_DRIVER' => 'array',
            // Remove inherited values so root loading is actually exercised.
            'MYSQL_PORT' => false,
        ]);
        $process->mustRun();
        $actual = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(realpath($backend), $actual['base']);
        $this->assertSame(realpath(dirname($backend)), $actual['environment']);
        $this->assertTrue($actual['key']);
        $this->assertSame('root_env_probe_no_connection', $actual['database']);
        $this->assertSame('', $actual['url']);
        $this->assertSame('', $actual['socket']);
        $this->assertSame('array', $actual['session']);
        $this->assertTrue($actual['root_loaded']);
    }

    public function test_composer_hooks_copy_only_the_missing_root_environment(): void
    {
        $scripts = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR)['scripts'];
        $hook = '@php -r "file_exists(\'../.env\') || copy(\'../.env.example\', \'../.env\');"';
        $this->assertContains($hook, $scripts['setup']);
        $this->assertSame([$hook], $scripts['post-root-package-install']);
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/.env');
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/.env.example');

        $temporary = sys_get_temp_dir().'/stayhub-composer-env-'.bin2hex(random_bytes(8));
        mkdir($temporary.'/backend', 0700, true);
        try {
            file_put_contents($temporary.'/.env.example', "APP_NAME=template\n");
            $command = substr($scripts['post-root-package-install'][0], strlen('@php -r "'), -1);
            (new Process([PHP_BINARY, '-r', $command], $temporary.'/backend'))->mustRun();
            $this->assertSame("APP_NAME=template\n", file_get_contents($temporary.'/.env'));
            file_put_contents($temporary.'/.env', "APP_NAME=existing\n");
            (new Process([PHP_BINARY, '-r', $command], $temporary.'/backend'))->mustRun();
            $this->assertSame("APP_NAME=existing\n", file_get_contents($temporary.'/.env'));
            $this->assertFileDoesNotExist($temporary.'/backend/.env');
        } finally {
            foreach (['.env', '.env.example'] as $file) {
                if (is_file($temporary.'/'.$file)) unlink($temporary.'/'.$file);
            }
            rmdir($temporary.'/backend');
            rmdir($temporary);
        }
    }
}
