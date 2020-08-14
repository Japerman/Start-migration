<?php
/**
 * @package
 * @subpackage
 */
namespace Start\Console\Command;

use Start\Adapter\AdapterInterface;
use Start\Migration\Migration;
use Start\Migration\Migrator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This file is part of start
 *
 * Copyright (c) 2011 Jose Perez <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract command, contains bootstrapping info
 *
 * @author      Jose Perez <david.marshall@atstsolutions.co.uk>
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var \ArrayAccess
     */
    protected $container = null;

    /**
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * @var string
     */
    protected $bootstrap = null;

    /**
     * @var array
     */
    protected $migrations = array();

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption('--set', '-s', InputOption::VALUE_REQUIRED, 'The start.sets key');
        $this->addOption('--bootstrap', '-b', InputOption::VALUE_REQUIRED, 'The bootstrap file to load');
    }

    /**
     * Bootstrap start
     *
     * @return void
     */
    protected function bootstrap(InputInterface $input, OutputInterface $output)
    {
        $this->setBootstrap($this->findBootstrapFile($input->getOption('bootstrap')));

        $container = $this->bootstrapContainer();
        $this->setContainer($container);
        $this->setAdapter($this->bootstrapAdapter($input));

        $this->setMigrations($this->bootstrapMigrations($input, $output));

        $container['start.migrator'] = $this->bootstrapMigrator($output);

    }

    /**
     * @param string $filename
     * @return array|string
     */
    protected function findBootstrapFile($filename)
    {
        if (null === $filename) {
            $filename = 'start.php';
        }

        $cwd = getcwd();

        $locator = new FileLocator(array(
            $cwd . DIRECTORY_SEPARATOR . 'config',
            $cwd
        ));

        return $locator->locate($filename);
    }

    /**
     * @return \ArrayAccess The container
     * @throws \RuntimeException
     */
    protected function bootstrapContainer()
    {
        $bootstrapFile = $this->getBootstrap();

        $func = function () use ($bootstrapFile) {
            return require $bootstrapFile;
        };

        $container = $func();

        if (!($container instanceof \ArrayAccess)) {
            throw new \RuntimeException($bootstrapFile . " must return object of type \ArrayAccess");
        }

        return $container;
    }

    /**
     * @param InputInterface  $input
     * @return AdapterInterface
     * @throws \RuntimeException
     */
    protected function bootstrapAdapter(InputInterface $input)
    {
        $container = $this->getContainer();

        $validAdapter = isset($container['start.adapter']);
        $validSets = !isset($container['start.sets']) || is_array($container['start.sets']);

        if (!$validAdapter && !$validSets) {
            throw new \RuntimeException(
                $this->getBootstrap()
                . 'must return container with start.adapter or start.sets'
            );
        }

        if (isset($container['start.sets'])) {
            $set = $input->getOption('set');
            if (!isset($container['start.sets'][$set]['adapter'])) {
                throw new \RuntimeException(
                    $set . ' is undefined keys or adapter at start.sets'
                );
            }
            $adapter = $container['start.sets'][$set]['adapter'];
        }
        if (isset($container['start.adapter'])) {
            $adapter = $container['start.adapter'];
        }

        if (!($adapter instanceof AdapterInterface)) {
            throw new \RuntimeException("start.adapter or start.sets must be an instance of \\Start\\Adapter\\AdapterInterface");
        }

        if (!$adapter->hasSchema()) {
            $adapter->createSchema();
        }

        return $adapter;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function bootstrapMigrations(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $set = $input->getOption('set');

        if (!isset($container['start.migrations']) && !isset($container['start.migrations_path']) && !isset($container['start.sets'][$set]['migrations_path'])) {
            throw new \RuntimeException($this->getBootstrap() . ' must return container with array at start.migrations or migrations default path at start.migrations_path or migrations default path at start.sets');
        }

        $migrations = array();
        if (isset($container['start.migrations'])) {
            if (!is_array($container['start.migrations'])) {
                throw new \RuntimeException($this->getBootstrap() . ' start.migrations must be an array.');
            }

            $migrations = $container['start.migrations'];
        }
        if (isset($container['start.migrations_path'])) {
            if (!is_dir($container['start.migrations_path'])) {
                throw new \RuntimeException($this->getBootstrap() . ' start.migrations_path must be a directory.');
            }

            $migrationsPath = realpath($container['start.migrations_path']);
            $migrations = array_merge($migrations, glob($migrationsPath . DIRECTORY_SEPARATOR . '*.php'));
        }
        if (isset($container['start.sets']) && isset($container['start.sets'][$set]['migrations_path'])) {
            if (!is_dir($container['start.sets'][$set]['migrations_path'])) {
                throw new \RuntimeException($this->getBootstrap() . " ['start.sets']['" . $set . "']['migrations_path'] must be a directory.");
            }

            $migrationsPath = realpath($container['start.sets'][$set]['migrations_path']);
            $migrations = array_merge($migrations, glob($migrationsPath . DIRECTORY_SEPARATOR . '*.php'));
        }
        $migrations = array_unique($migrations);

        $versions = array();
        $names = array();
        foreach ($migrations as $path) {
            if (!preg_match('/^[0-9]+/', basename($path), $matches)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" does not have a valid migration filename', $path));
            }

            $version = $matches[0];
            if (isset($versions[$version])) {
                throw new \InvalidArgumentException(sprintf('Duplicate migration, "%s" has the same version as "%s"', $path, $versions[$version]->getName()));
            }

            $migrationName = preg_replace('/^[0-9]+_/', '', basename($path));
            if (false !== strpos($migrationName, '.')) {
                $migrationName = substr($migrationName, 0, strpos($migrationName, '.'));
            }
            $class = $this->migrationToClassName($migrationName);

            if ($this instanceof GenerateCommand
                && $class == $this->migrationToClassName($input->getArgument('name'))) {
                throw new \InvalidArgumentException(sprintf(
                    'Migration Class "%s" already exists',
                    $class
                ));
            }

            if (isset($names[$class])) {
                throw new \InvalidArgumentException(sprintf(
                    'Migration "%s" has the same name as "%s"',
                    $path,
                    $names[$class]
                ));
            }
            $names[$class] = $path;

            require_once $path;
            if (!class_exists($class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not find class "%s" in file "%s"',
                    $class,
                    $path
                ));
            }

            $migration = new $class($version);

            if (!($migration instanceof Migration)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" in file "%s" must extend \Start\Migration\Migration',
                    $class,
                    $path
                ));
            }

            $migration->setOutput($output); // inject output

            $versions[$version] = $migration;
        }

        if (isset($container['start.sets']) && isset($container['start.sets'][$set]['connection'])) {
            $container['start.connection'] = $container['start.sets'][$set]['connection'];
        }

        ksort($versions);

        return $versions;
    }

    /**
     * @param OutputInterface $output
     * @return mixed
     */
    protected function bootstrapMigrator(OutputInterface $output)
    {
        return new Migrator($this->getAdapter(), $this->getContainer(), $output);
    }

    /**
     * Set bootstrap
     *
     * @var string
     * @return AbstractCommand
     */
    public function setBootstrap($bootstrap) 
    {
        $this->bootstrap = $bootstrap;
        return $this;
    }

    /**
     * Get bootstrap
     *
     * @return string 
     */
    public function getBootstrap()
    {
        return $this->bootstrap;
    }

    /**
     * Set migrations
     *
     * @param array $migrations
     * @return AbstractCommand
     */
    public function setMigrations(array $migrations) 
    {
        $this->migrations = $migrations;
        return $this;
    }

    /**
     * Get migrations
     *
     * @return array
     */
    public function getMigrations()
    {
        return $this->migrations;
    }

    /**
     * Set container
     *
     * @var \ArrayAccess
     * @return AbstractCommand
     */
    public function setContainer(\ArrayAccess $container) 
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Get container
     *
     * @return \ArrayAccess
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set adapter
     *
     * @param AdapterInterface $adapter
     * @return AbstractCommand
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get Adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }
    
    /**
     * transform create_table_user to CreateTableUser
     * @param $migrationName
     * @return string
     */
    protected function migrationToClassName( $migrationName )
    {
        $class = str_replace('_', ' ', $migrationName);
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);

        if (!$this->isValidClassName($class)) {
            throw new \InvalidArgumentException(sprintf(
                'Migration class "%s" is invalid',
                $class
            ));
        }

        return $class;
    }

    /**
     * @param $className
     * @return bool
     * @see http://php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class
     */
    private function isValidClassName($className)
    {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $className) === 1;
    }
}