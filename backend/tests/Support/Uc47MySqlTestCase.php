<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class Uc47MySqlTestCase extends TestCase
{
    protected array $uc47Manifest;

    public function createApplication()
    {
        if (getenv('STAYHUB_UC47_OPT_IN') === false) {
            $this->markTestSkipped('UC47 MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->uc47Manifest = Uc47MySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc47MySqlEnvironment::assertApplication($app, $this->uc47Manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Uc47MySqlEnvironment::assertApplication($this->app, $this->uc47Manifest);
        if (!$db->getSchemaBuilder()->hasTable('migrations')) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('UC47 refuses to migrate a nonempty uninitialized schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
        }
        $db->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app)) {
                $db = $this->app['db']->connection();
                while ($db->transactionLevel() > 0) {
                    $db->rollBack();
                }
                $this->app['db']->purge('mysql');
            }
        } finally {
            parent::tearDown();
        }
    }
}
