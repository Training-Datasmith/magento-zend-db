<?php

declare (strict_types=1);
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Select
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Db_Select
 */
#require_once 'Zend/Db/Select.php';
/**
 * @see Zend_Db_Table_Abstract
 */
#require_once 'Zend/Db/Table/Abstract.php';
/**
 * Class for SQL SELECT query manipulation for the Zend_Db_Table component.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Table
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Table_Select extends Zend_Db_Select
{
    /**
     * Table schema for parent Zend_Db_Table.
     *
     * @var array
     */
    protected $_info;
    /**
     * Table integrity override.
     *
     * @var array
     */
    protected $_integrity_check = true;
    /**
     * Table instance that created this select object
     *
     * @var Zend_Db_Table_Abstract
     */
    protected $_table;
    /**
     * Class constructor
     *
     * @param Zend_Db_Table_Abstract $adapter
     */
    public function __construct(Zend_Db_Table_Abstract $table)
    {
        parent::__construct($table->get_adapter());
        $this->set_table($table);
    }
    /**
     * Return the table that created this select object
     *
     * @return Zend_Db_Table_Abstract
     */
    public function get_table()
    {
        return $this->_table;
    }
    /**
     * Sets the primary table name and retrieves the table schema.
     *
     * @param Zend_Db_Table_Abstract $adapter
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function set_table(Zend_Db_Table_Abstract $table): self
    {
        $this->_adapter = $table->get_adapter();
        $this->_info = $table->info();
        $this->_table = $table;
        return $this;
    }
    /**
     * Sets the integrity check flag.
     *
     * Setting this flag to false skips the checks for table joins, allowing
     * 'hybrid' table rows to be created.
     *
     * @param Zend_Db_Table_Abstract $adapter
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function set_integrity_check($flag = true): self
    {
        $this->_integrity_check = $flag;
        return $this;
    }
    /**
     * Tests query to determine if expressions or aliases columns exist.
     *
     * @return boolean
     */
    public function is_read_only()
    {
        $read_only = false;
        $fields = $this->get_part(Zend_Db_Table_Select::COLUMNS);
        $cols = $this->_info[Zend_Db_Table_Abstract::COLS];
        if (!count($fields)) {
            return $read_only;
        }
        foreach ($fields as $column_entry) {
            $column = $column_entry[1];
            $alias = $column_entry[2];
            if ($alias !== null) {
                $column = $alias;
            }
            switch (true) {
                case $column == self::SQL_WILDCARD:
                    break;
                case $column instanceof Zend_Db_Expr:
                case !in_array($column, $cols):
                    $read_only = true;
                    break 2;
            }
        }
        return $read_only;
    }
    /**
    * Adds a FROM table and optional columns to the query.
    *
    * The table name can be expressed
    *
    * @param  array|string|Zend_Db_Expr|Zend_Db_Table_Abstract $name The table name or an
                                                                     associative array relating
                                                                     table name to correlation
                                                                     name.
    * @param  array|string|Zend_Db_Expr $cols The columns to select from this table.
    * @param  string $schema The schema name to specify, if any.
    * @return Zend_Db_Table_Select This Zend_Db_Table_Select object.
    */
    public function from($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        if ($name instanceof Zend_Db_Table_Abstract) {
            $info = $name->info();
            $name = $info[Zend_Db_Table_Abstract::NAME];
            if (isset($info[Zend_Db_Table_Abstract::SCHEMA])) {
                $schema = $info[Zend_Db_Table_Abstract::SCHEMA];
            }
        }
        return $this->join_inner($name, null, $cols, $schema);
    }
    /**
     * Performs a validation on the select query before passing back to the parent class.
     * Ensures that only columns from the primary Zend_Db_Table are returned in the result.
     *
     * @return string|null This object as a SELECT string (or null if a string cannot be produced)
     */
    public function assemble()
    {
        $fields = $this->get_part(Zend_Db_Table_Select::COLUMNS);
        $primary = $this->_info[Zend_Db_Table_Abstract::NAME];
        $schema = $this->_info[Zend_Db_Table_Abstract::SCHEMA];
        if (count($this->_parts[self::UNION]) == 0) {
            // If no fields are specified we assume all fields from primary table
            if (!count($fields)) {
                $this->from($primary, self::SQL_WILDCARD, $schema);
                $fields = $this->get_part(Zend_Db_Table_Select::COLUMNS);
            }
            $from = $this->get_part(Zend_Db_Table_Select::FROM);
            if ($this->_integrity_check !== false) {
                foreach ($fields as $column_entry) {
                    list($table, $column) = $column_entry;
                    // Check each column to ensure it only references the primary table
                    if ($column) {
                        if (!isset($from[$table]) || $from[$table]['tableName'] != $primary) {
                            #require_once 'Zend/Db/Table/Select/Exception.php';
                            throw new Zend_Db_Table_Select_Exception('Select query cannot join with another table');
                        }
                    }
                }
            }
        }
        return parent::assemble();
    }
}