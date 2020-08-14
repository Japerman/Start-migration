<?php
/**
 * @package    Start
 * @subpackage Start\Adapter
 */
namespace Start\Adapter;

use \Start\Migration\Migration;

/**
 * This file is part of start
 *
 * Copyright (c) 2011 Jose Perez <dave.marshall@atstsolutuions.co.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adapter interface
 *
 * @author      Jose Perez <david.marshall@atstsolutions.co.uk
 */
interface AdapterInterface
{
    /**
     * Get all migrated version numbers
     *
     * @return array
     */
    public function fetchAll();

    /**
     * Up
     *
     * @param Migration $migration
     * @return AdapterInterface
     */
    public function up(Migration $migration);

    /**
     * Down
     *
     * @param Migration $migration
     * @return AdapterInterface
     */
    public function down(Migration $migration);

    /**
     * Is the schema ready? 
     *
     * @return bool
     */
    public function hasSchema();

    /**
     * Create Schema
     *
     * @return AdapterInterface
     */
    public function createSchema();
}



