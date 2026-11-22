<?php

namespace Tests\Support;

use RuntimeException;
use Tests\TestCase;

abstract class Uc21MySqlTestCase extends TestCase
{
    protected array $manifest;

    public function createApplication()
    {
        if (getenv('STAYHUB_UC21_OPT_IN') !== '1') $this->markTestSkipped('UC21 requires its disposable database runner; no database booted.');
        $this->manifest = Uc21MySqlEnvironment::manifest(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Uc21MySqlEnvironment::assertApplication($app, $this->manifest);
        $storage = dirname(getenv('STAYHUB_UC21_MANIFEST')).'/public';
        if (is_link($storage) || !is_dir($storage)) throw new RuntimeException('Owned UC21 storage missing.');
        $app['config']->set('filesystems.disks.public.root', $storage);
        $app['config']->set('filesystems.disks.public.url', 'http://uc21-fixture.invalid/storage');
        $app['filesystem']->forgetDisk('public');
        return $app;
    }
}
