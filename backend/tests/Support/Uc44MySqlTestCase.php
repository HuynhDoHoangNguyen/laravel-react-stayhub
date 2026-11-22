<?php

namespace Tests\Support;

use Tests\TestCase;
use RuntimeException;

abstract class Uc44MySqlTestCase extends TestCase
{
    protected array $uc44Manifest;
    private static array $migratedSchemas = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_UC44_OPT_IN') === false) {
            $this->markTestSkipped('UC44 MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->uc44Manifest = Uc44MySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc44MySqlEnvironment::assertApplication($app, $this->uc44Manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Uc44MySqlEnvironment::assertApplication($this->app, $this->uc44Manifest);
        if (!isset(self::$migratedSchemas[$this->uc44Manifest['database']])) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('UC44 refuses to migrate or adopt a nonempty schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migratedSchemas[$this->uc44Manifest['database']] = true;
        }
        if (!$db->getSchemaBuilder()->hasTable('uc44_environment_probe')) {
            $db->getSchemaBuilder()->create('uc44_environment_probe', function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->string('marker');
            });
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
