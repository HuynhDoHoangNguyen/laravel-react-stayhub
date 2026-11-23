<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class Tv3AcceptanceMySqlTestCase extends TestCase
{
    protected array $tv3acManifest;
    private static array $migratedSchemas = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_TV3_AC_OPT_IN') === false) {
            $this->markTestSkipped('TV3_AC MySQL requires the explicit owned-schema runner; no database booted.');
        }
        $this->tv3acManifest = Tv3AcceptanceMySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Tv3AcceptanceMySqlEnvironment::assertApplication($app, $this->tv3acManifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Tv3AcceptanceMySqlEnvironment::assertApplication($this->app, $this->tv3acManifest);
        if (!isset(self::$migratedSchemas[$this->tv3acManifest['database']])) {
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('TV3_AC refuses to migrate or adopt a nonempty schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migratedSchemas[$this->tv3acManifest['database']] = true;
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
