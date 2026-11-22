<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Uc21MySqlEnvironment;

class Uc21MySqlEnvironmentTest extends TestCase
{
    public function test_raw_target_requires_owned_schema_credentials_and_opt_in(): void
    {
        $run = str_repeat('a', 32);
        $manifest = ['run_id' => $run, 'database' => 'stayhub_uc21_'.$run.'_test', 'username' => 'uc21_'.substr($run, 0, 16), 'port' => '3306', 'version' => '10.11.16-MariaDB'];
        $env = ['STAYHUB_UC21_OPT_IN' => '1', 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'ALLOW_MYSQL_TESTS' => 'true',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => $manifest['database'], 'DB_USERNAME' => $manifest['username'],
            'DB_PASSWORD' => str_repeat('b', 64), 'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        Uc21MySqlEnvironment::assertTarget($env, $manifest);
        $this->addToAssertionCount(1);
        foreach (['STAYHUB_UC21_OPT_IN' => '', 'APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'ALLOW_MYSQL_TESTS' => '',
            'DB_HOST' => 'elsewhere', 'DB_DATABASE' => 'stayhub', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '',
            'DB_URL' => 'mysql://foreign', 'DATABASE_URL' => 'mysql://foreign', 'DB_SOCKET' => '/tmp/mysql.sock', 'SESSION_DRIVER' => 'database'] as $key => $value) {
            try { Uc21MySqlEnvironment::assertTarget(array_replace($env, [$key => $value]), $manifest); $this->fail('Unsafe '.$key); }
            catch (RuntimeException) { $this->addToAssertionCount(1); }
        }
        $this->expectException(RuntimeException::class);
        Uc21MySqlEnvironment::assertTarget($env, array_replace($manifest, ['database' => 'stayhub']));
    }
}
