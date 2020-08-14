<?php
/**
 * @package    Start
 * @subpackage Start\Adapter
 */
namespace Start\Adapter\Zend;

use \Start\Migration\Migration,
    \Start\Adapter\AdapterInterface;

/**
 * This file is part of start
 *
 * Copyright (c) 2011 Jose Perez <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * Start adapter for Zend_Db
 *
 * @author      Wojtek Gancarczyk  <gancarczyk@gmail.com>
 */
class Db implements AdapterInterface
{
    const MSSQL_CREATE_STATEMENT = 'CREATE TABLE %s ( version VARCHAR(255) NOT NULL );';
    const MYSQL_CREATE_STATEMENT = 'CREATE TABLE `%s` ( version VARCHAR(255) UNSIGNED NOT NULL );';
    const PGSQL_CREATE_STATEMENT = 'CREATE TABLE %s ( version VARCHAR(255) NOT NULL );';
    const SQLITE_CREATE_STATEMENT = 'CREATE TABLE %s ( version VARCHAR);';

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $createStatement;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $adapter;

    /**
     *
     *
     * @param \Zend_Db_Adapter_Abstract $adapter
     * @param \Zend_Config $configuration
     */
    public function __construct(\Zend_Db_Adapter_Abstract $adapter, \Zend_Config $configuration)
    {
        $this->adapter = $adapter;
        $this->tableName = $configuration->start->tableName;
        $this->createStatement = $configuration->start->createStatement;
    }

    /**
     * Get all migrated version numbers
     *
     * @return array
     */
    public function fetchAll()
    {
        $select = $this->adapter->select();
        $select->from($this->tableName, 'version');
        $select->order('version ASC');
        $all = $this->adapter->fetchAll($select);
        return array_map(function($v) {return $v['version'];}, $all);
    }

    /**
     * Up
     *
     * @param Migration $migration
     * @return AdapterInterface
     */
    public function up(Migration $migration)
    {
        $this->adapter->insert($this->tableName, array(
            'version' => $migration->getVersion(),
        ));

        return $this;
    }

    /**
     * Down
     *
     * @param Migration $migration
     * @return AdapterInterface
     */
    public function down(Migration $migration)
    {
        $this->adapter->delete($this->tableName,
            $this->adapter->quoteInto('version = ?', $migration->getVersion())
        );

        return $this;
    }

    /**
     * Is the schema ready?
     *
     * @return bool
     */
    public function hasSchema()
    {
        try {
            $schema = $this->adapter->describeTable($this->tableName);
        } catch (\Zend_Db_Statement_Exception $exception) {
            return false;
        } catch (\PDOException $exception) {
            return false;
        }

        if (is_array($schema) && !empty($schema)) {
            return true;
        }

        return false;
    }

    /**
     * Create Schema
     *
     * @throws \InvalidArgumentException
     * @return AdapterInterface
     */
    public function createSchema()
    {
        $sql = $this->createStatement;
        if ($sql === null) {
            switch(get_class($this->adapter)) {
                case 'Zend_Db_Adapter_Pdo_Mssql':
                    $createStatement = static::MSSQL_CREATE_STATEMENT;
                    break;
                case 'Zend_Db_Adapter_Pdo_Mysql':
                case 'Zend_Db_Adapter_Mysqli':
                    $createStatement = static::MYSQL_CREATE_STATEMENT;
                    break;
                case 'Zend_Db_Adapter_Pdo_Pgsql':
                    $createStatement = static::PGSQL_CREATE_STATEMENT;
                    break;
                case 'Zend_Db_Adapter_Pdo_Sqlite':
                    $createStatement = static::SQLITE_CREATE_STATEMENT;
                    break;
                default:
                    throw new \InvalidArgumentException('Please provide a valid SQL statement for your database system in the config file as start.createStatement');
                    break;
            }
            $sql = sprintf($createStatement, $this->tableName);
        }

        try {
            $this->adapter->query($sql);
        } catch (\Zend_Db_Statement_Exception $exception) {
            throw new \InvalidArgumentException('Please provide a valid SQL statement for your database system in the config file as start.createStatement');
        }

        return $this;
    }

}
