<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class Uc48MySqlTestCase extends TestCase
{
    protected array $uc48Manifest;
    private static array $migratedSchemas = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_UC48_OPT_IN') === false) {
            $this->markTestSkipped('UC48 MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->uc48Manifest = Uc48MySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc48MySqlEnvironment::assertApplication($app, $this->uc48Manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Uc48MySqlEnvironment::assertApplication($this->app, $this->uc48Manifest);
        if (!isset(self::$migratedSchemas[$this->uc48Manifest['database']])) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('UC48 refuses to migrate or adopt a nonempty schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migratedSchemas[$this->uc48Manifest['database']] = true;
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
