<?php

namespace Start\Console;

/**
 * @group unit
 */
class StartApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testAllCommandsRegistered()
    {
        $version = 'test';
        $commandNames = array('check', 'down', 'up', 'generate', 'init', 'migrate', 'rollback', 'status', 'up', 'redo');

        $app = new StartApplication($version);

        foreach ($commandNames as $commandName) {
            $this->assertTrue($app->has($commandName));
        }

        $this->assertSame($version, $app->getVersion());
    }
}
