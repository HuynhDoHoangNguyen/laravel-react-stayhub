<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class Uc39MySqlTestCase extends TestCase
{
    protected array $uc39Manifest;
    private static array $migratedSchemas = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_UC39_OPT_IN') === false) {
            $this->markTestSkipped('UC39 MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->uc39Manifest = Uc39MySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc39MySqlEnvironment::assertApplication($app, $this->uc39Manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Uc39MySqlEnvironment::assertApplication($this->app, $this->uc39Manifest);
        if (!isset(self::$migratedSchemas[$this->uc39Manifest['database']])) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('UC39 refuses to migrate or adopt a nonempty schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migratedSchemas[$this->uc39Manifest['database']] = true;
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
