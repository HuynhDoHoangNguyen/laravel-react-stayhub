<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class Uc45MySqlTestCase extends TestCase
{
    protected array $uc45Manifest;

    public function createApplication()
    {
        if (!getenv('STAYHUB_UC45_OPT_IN')) {
            $this->markTestSkipped('UC45 MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->uc45Manifest = Uc45MySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc45MySqlEnvironment::assertApplication($app, $this->uc45Manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Uc45MySqlEnvironment::assertApplication($this->app, $this->uc45Manifest);
        if (!$db->getSchemaBuilder()->hasTable('migrations')) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('UC45 refuses to migrate a nonempty uninitialized schema.');
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
