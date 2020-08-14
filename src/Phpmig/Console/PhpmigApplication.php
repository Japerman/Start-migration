<?php
/**
 * @package    Start
 * @subpackage Console
 */
namespace Start\Console;

use Start\Console\Command;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 * This file is part of start
 *
 * Copyright (c) 2011 Jose Perez <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The main start application
 *
 * @author      Jose Perez <david.marshall@bskyb.com>
 */
class StartApplication extends Application
{
    /**
     * @param string $version
     */
    public function __construct($version = 'dev')
    {
        parent::__construct('start', $version);

        $this->addCommands(array(
            new Command\InitCommand(),
            new Command\StatusCommand(),
            new Command\CheckCommand(),
            new Command\GenerateCommand(),
            new Command\UpCommand(),
            new Command\DownCommand(),
            new Command\MigrateCommand(),
            new Command\RollbackCommand(),
            new Command\RedoCommand()
        ));
    }
}

