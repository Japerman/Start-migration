<?php
/**
 * @package    Start
 * @subpackage Start\Console
 */
namespace Start\Console\Command;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Config\FileLocator;

/**
 * This file is part of start
 *
 * Copyright (c) 2011 Jose Perez <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Up command
 *
 * @author      Jose Perez <david.marshall@atstsolutions.co.uk>
 */
class UpCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('up')
             ->addArgument('version', InputArgument::REQUIRED, 'The version number for the migration')
             ->setDescription('Run a specific migration')
             ->setHelp(<<<EOT
The <info>up</info> command runs a specific migration

<info>start up 20111018185121</info>

EOT
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $migrations = $this->getMigrations();
        $versions   = $this->getAdapter()->fetchAll();

        $version = $input->getArgument('version');

        if (in_array($version, $versions)) {
            return;
        }

        if (!isset($migrations[$version])) {
            return;
        }

        $container = $this->getContainer();
        $container['start.migrator']->up($migrations[$version]);
    }
}



