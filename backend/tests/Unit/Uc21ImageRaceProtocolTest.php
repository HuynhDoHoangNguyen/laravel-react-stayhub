<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Uc21ImageRaceProtocol;

class Uc21ImageRaceProtocolTest extends TestCase
{
    public function test_scoped_messages_cannot_escape_or_overwrite_and_missing_barrier_fails(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'uc21-protocol-'.bin2hex(random_bytes(16));
        mkdir($root);
        $case = 'case-'.bin2hex(random_bytes(16));
        $protocol = new Uc21ImageRaceProtocol($root, $case, true);
        try {
            $protocol->write('ready', ['id'=>1]);
            $this->assertSame(['id'=>1], $protocol->wait('ready'));
            foreach (['../escape', 'a/b', '', 'a\\b'] as $name) {
                try { $protocol->write($name, []); $this->fail('Invalid message accepted'); }
                catch (RuntimeException) { $this->addToAssertionCount(1); }
            }
            try { $protocol->write('ready', []); $this->fail('Overwrite accepted'); }
            catch (RuntimeException) { $this->addToAssertionCount(1); }
            try { $protocol->wait('missing', 0.02); $this->fail('Missing barrier accepted'); }
            catch (RuntimeException) { $this->addToAssertionCount(1); }
            try { new Uc21ImageRaceProtocol($root, '../escape'); $this->fail('Invalid case accepted'); }
            catch (RuntimeException) { $this->addToAssertionCount(1); }
        } finally {
            unlink($protocol->directory.DIRECTORY_SEPARATOR.'ready.json');
            rmdir($protocol->directory); rmdir($root);
        }
    }
}
