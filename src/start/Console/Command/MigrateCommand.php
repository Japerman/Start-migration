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
 * Migrate command
 *
 * @author      Jose Perez <david.marshall@atstsolutions.co.uk>
 */
class MigrateCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('migrate')
             ->addOption('--target', '-t', InputArgument::OPTIONAL, 'The version number to migrate to')
             ->setDescription('Run all migrations')
             ->setHelp(<<<EOT
The <info>migrate</info> command runs all available migrations, optionally up to a specific version

<info>start migrate</info>
<info>start migrate -t 20111018185412</info>

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

        $version = $input->getOption('target');

        ksort($migrations);
        sort($versions);

        if (!empty($versions)) {
            // Get the last run migration number
            $current = end($versions);
        } else {
            $current = 0;
        }

        if (null !== $version) {
            if (0 != $version && !isset($migrations[$version])) {
                return;
            }
        } else {
            $versionNumbers = array_merge($versions, array_keys($migrations));

            if (empty($versionNumbers)) {
                return;
            }

            $version = max($versionNumbers);
        }

        $direction = $version > $current ? 'up' : 'down';

        if ($direction == 'down') {
            /**
             * Run downs first
             */
            krsort($migrations);
            foreach($migrations as $migration) {
                if ($migration->getVersion() <= $version) {
                    break;
                }

                if (in_array($migration->getVersion(), $versions)) {
                    $container = $this->getContainer();
                    $container['start.migrator']->down($migration);
                }
            }
        }

        ksort($migrations);
        foreach($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                $container = $this->getContainer();
                $container['start.migrator']->up($migration);
            }
        }
    }
}
