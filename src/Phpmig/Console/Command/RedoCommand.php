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
 * Redo command
 *
 * @author      nagodon <nagodon@gmail.com>
 */
class RedoCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('redo')
             ->addArgument('version', InputArgument::REQUIRED, 'The version number for the migration')
             ->setDescription('Redo a specific migration')
             ->setHelp(<<<EOT
The <info>redo</info> command redo a specific migration

<info>start redo 20111018185412</info>

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

        if (!in_array($version, $versions)) {
            return;
        }

        if (!isset($migrations[$version])) {
            return;
        }

        $container = $this->getContainer();
        $container['start.migrator']->down($migrations[$version]);
        $container['start.migrator']->up($migrations[$version]);
    }
}



