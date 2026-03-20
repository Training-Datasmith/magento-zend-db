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
 * @subpackage Table
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Db
 */
#require_once 'Zend/Db.php';
/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Table
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Db_Table_Row_Abstract implements ArrayAccess, IteratorAggregate
{
    /**
     * The data for each column in the row (column_name => value).
     * The keys must match the physical names of columns in the
     * table for which this row is defined.
     *
     * @var array
     */
    protected $_data = [];
    /**
     * This is set to a copy of $_data when the data is fetched from
     * a database, specified as a new tuple in the constructor, or
     * when dirty data is posted to the database with save().
     *
     * @var array
     */
    protected $_clean_data = [];
    /**
     * Tracks columns where data has been updated. Allows more specific insert and
     * update operations.
     *
     * @var array
     */
    protected $_modified_fields = [];
    /**
     * Zend_Db_Table_Abstract parent class or instance.
     *
     * @var Zend_Db_Table_Abstract
     */
    protected $_table;
    /**
     * Connected is true if we have a reference to a live
     * Zend_Db_Table_Abstract object.
     * This is false after the Rowset has been deserialized.
     *
     * @var boolean
     */
    protected $_connected = true;
    /**
     * A row is marked read only if it contains columns that are not physically represented within
     * the database schema (e.g. evaluated columns/Zend_Db_Expr columns). This can also be passed
     * as a run-time config options as a means of protecting row data.
     *
     * @var boolean
     */
    protected $_read_only = false;
    /**
     * Name of the class of the Zend_Db_Table_Abstract object.
     *
     * @var string
     */
    protected $_table_class;
    /**
     * Primary row key(s).
     *
     * @var array
     */
    protected $_primary;
    /**
     * Constructor.
     *
     * Supported params for $config are:-
     * - table       = class name or object of type Zend_Db_Table_Abstract
     * - data        = values of columns in this row.
     *
     * @param  array $config OPTIONAL Array of user-specified config options.
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __construct(array $config = [])
    {
        if (isset($config['table']) && $config['table'] instanceof Zend_Db_Table_Abstract) {
            $this->_table = $config['table'];
            $this->_table_class = get_class($this->_table);
        } elseif ($this->_table_class !== null) {
            $this->_table = $this->_get_table_from_string($this->_table_class);
        }
        if (isset($config['data'])) {
            if (!is_array($config['data'])) {
                #require_once 'Zend/Db/Table/Row/Exception.php';
                throw new Zend_Db_Table_Row_Exception('Data must be an array');
            }
            $this->_data = $config['data'];
        }
        if (isset($config['stored']) && $config['stored'] === true) {
            $this->_clean_data = $this->_data;
        }
        if (isset($config['readOnly']) && $config['readOnly'] === true) {
            $this->set_read_only(true);
        }
        // Retrieve primary keys from table schema
        if ($table = $this->_get_table()) {
            $info = $table->info();
            $this->_primary = (array) $info['primary'];
        }
        $this->init();
    }
    /**
     * Transform a column name from the user-specified form
     * to the physical form used in the database.
     * You can override this method in a custom Row class
     * to implement column name mappings, for example inflection.
     *
     * @param string $columnName Column name given.
     * @return string The column name after transformation applied (none by default).
     * @throws Zend_Db_Table_Row_Exception if the $columnName is not a string.
     */
    protected function _transform_column($column_name)
    {
        if (!is_string($column_name)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('Specified column is not a string');
        }
        // Perform no transformation by default
        return $column_name;
    }
    /**
     * Retrieve row field value
     *
     * @param  string $columnName The user-specified column name.
     * @return string             The corresponding column value.
     * @throws Zend_Db_Table_Row_Exception if the $columnName is not a column in the row.
     */
    public function __get(string $column_name)
    {
        $column_name = $this->_transform_column($column_name);
        if (!array_key_exists($column_name, $this->_data)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Specified column \"{$column_name}\" is not in the row");
        }
        return $this->_data[$column_name];
    }
    /**
     * Set row field value
     *
     * @param  string $columnName The column key.
     * @param  mixed  $value      The value for the property.
     * @return void
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __set(string $column_name, $value)
    {
        $column_name = $this->_transform_column($column_name);
        if (!array_key_exists($column_name, $this->_data)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Specified column \"{$column_name}\" is not in the row");
        }
        $this->_data[$column_name] = $value;
        $this->_modified_fields[$column_name] = true;
    }
    /**
     * Unset row field value
     *
     * @param  string $columnName The column key.
     * @return Zend_Db_Table_Row_Abstract
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __unset(string $column_name)
    {
        $column_name = $this->_transform_column($column_name);
        if (!array_key_exists($column_name, $this->_data)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Specified column \"{$column_name}\" is not in the row");
        }
        if ($this->is_connected() && in_array($column_name, $this->_table->info('primary'))) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Specified column \"{$column_name}\" is a primary key and should not be unset");
        }
        unset($this->_data[$column_name]);
        return $this;
    }
    /**
     * Test existence of row field
     *
     * @param  string  $columnName   The column key.
     * @return boolean
     */
    public function __isset(string $column_name)
    {
        $column_name = $this->_transform_column($column_name);
        return array_key_exists($column_name, $this->_data);
    }
    /**
     * Store table, primary key and data in serialized object
     *
     * @return array
     */
    public function __sleep()
    {
        return ['_tableClass', '_primary', '_data', '_cleanData', '_readOnly', '_modifiedFields'];
    }
    /**
     * Setup to do on wakeup.
     * A de-serialized Row should not be assumed to have access to a live
     * database connection, so set _connected = false.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_connected = false;
    }
    /**
     * Proxy to __isset
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }
    /**
     * Proxy to __get
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @return string
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }
    /**
     * Proxy to __set
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }
    /**
     * Proxy to __unset
     * Required by the ArrayAccess implementation
     *
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        return $this->__unset($offset);
    }
    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
    }
    /**
     * Returns the table object, or null if this is disconnected row
     *
     * @return Zend_Db_Table_Abstract|null
     */
    public function get_table()
    {
        return $this->_table;
    }
    /**
     * Set the table object, to re-establish a live connection
     * to the database for a Row that has been de-serialized.
     *
     * @param Zend_Db_Table_Abstract $table
     * @return boolean
     * @throws Zend_Db_Table_Row_Exception
     */
    public function set_table(?Zend_Db_Table_Abstract $table = null)
    {
        if ($table == null) {
            $this->_table = null;
            $this->_connected = false;
            return false;
        }
        $table_class = get_class($table);
        if (!$table instanceof $this->_table_class) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("The specified Table is of class {$table_class}, expecting class to be instance of {$this->_table_class}");
        }
        $this->_table = $table;
        $this->_table_class = $table_class;
        $info = $this->_table->info();
        if ($info['cols'] != array_keys($this->_data)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('The specified Table does not have the same columns as the Row');
        }
        if (!array_intersect((array) $this->_primary, $info['primary']) == (array) $this->_primary) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("The specified Table '{$table_class}' does not have the same primary key as the Row");
        }
        $this->_connected = true;
        return true;
    }
    /**
     * Query the class name of the Table object for which this
     * Row was created.
     *
     * @return string
     */
    public function get_table_class()
    {
        return $this->_table_class;
    }
    /**
     * Test the connected status of the row.
     *
     * @return boolean
     */
    public function is_connected()
    {
        return $this->_connected;
    }
    /**
     * Test the read-only status of the row.
     *
     * @return boolean
     */
    public function is_read_only()
    {
        return $this->_read_only;
    }
    /**
     * Set the read-only status of the row.
     *
     * @param boolean $flag
     * @return boolean
     */
    public function set_read_only($flag)
    {
        $this->_read_only = (bool) $flag;
    }
    /**
     * Returns an instance of the parent table's Zend_Db_Table_Select object.
     *
     * @return Zend_Db_Table_Select
     */
    public function select()
    {
        return $this->get_table()->select();
    }
    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    public function save()
    {
        /**
         * If the _cleanData array is empty,
         * this is an INSERT of a new row.
         * Otherwise it is an UPDATE.
         */
        if (empty($this->_clean_data)) {
            return $this->_do_insert();
        }
        return $this->_do_update();
    }
    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    protected function _do_insert()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_read_only === true) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('This row has been marked read-only');
        }
        /**
         * Run pre-INSERT logic
         */
        $this->_insert();
        /**
         * Execute the INSERT (this may throw an exception)
         */
        $data = array_intersect_key($this->_data, $this->_modified_fields);
        $primary_key = $this->_get_table()->insert($data);
        /**
         * Normalize the result to an array indexed by primary key column(s).
         * The table insert() method may return a scalar.
         */
        if (is_array($primary_key)) {
            $new_primary_key = $primary_key;
        } else {
            //ZF-6167 Use tempPrimaryKey temporary to avoid that zend encoding fails.
            $temp_primary_key = (array) $this->_primary;
            $new_primary_key = [current($temp_primary_key) => $primary_key];
        }
        /**
         * Save the new primary key value in _data.  The primary key may have
         * been generated by a sequence or auto-increment mechanism, and this
         * merge should be done before the _postInsert() method is run, so the
         * new values are available for logging, etc.
         */
        $this->_data = array_merge($this->_data, $new_primary_key);
        /**
         * Run post-INSERT logic
         */
        $this->_post_insert();
        /**
         * Update the _cleanData to reflect that the data has been inserted.
         */
        $this->_refresh();
        return $primary_key;
    }
    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    protected function _do_update()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_read_only === true) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('This row has been marked read-only');
        }
        /**
         * Get expressions for a WHERE clause
         * based on the primary key value(s).
         */
        $where = $this->_get_where_query(false);
        /**
         * Run pre-UPDATE logic
         */
        $this->_update();
        /**
         * Compare the data to the modified fields array to discover
         * which columns have been changed.
         */
        $diff_data = array_intersect_key($this->_data, $this->_modified_fields);
        /**
         * Were any of the changed columns part of the primary key?
         */
        $pk_diff_data = array_intersect_key($diff_data, array_flip((array) $this->_primary));
        /**
         * Execute cascading updates against dependent tables.
         * Do this only if primary key value(s) were changed.
         */
        if (count($pk_diff_data) > 0) {
            $dep_tables = $this->_get_table()->get_dependent_tables();
            if (!empty($dep_tables)) {
                $pk_new = $this->_get_primary_key(true);
                $pk_old = $this->_get_primary_key(false);
                foreach ($dep_tables as $table_class) {
                    $t = $this->_get_table_from_string($table_class);
                    $t->_cascade_update($this->get_table_class(), $pk_old, $pk_new);
                }
            }
        }
        /**
         * Execute the UPDATE (this may throw an exception)
         * Do this only if data values were changed.
         * Use the $diffData variable, so the UPDATE statement
         * includes SET terms only for data values that changed.
         */
        if (count($diff_data) > 0) {
            $this->_get_table()->update($diff_data, $where);
        }
        /**
         * Run post-UPDATE logic.  Do this before the _refresh()
         * so the _postUpdate() function can tell the difference
         * between changed data and clean (pre-changed) data.
         */
        $this->_post_update();
        /**
         * Refresh the data just in case triggers in the RDBMS changed
         * any columns.  Also this resets the _cleanData.
         */
        $this->_refresh();
        /**
         * Return the primary key value(s) as an array
         * if the key is compound or a scalar if the key
         * is a scalar.
         */
        $primary_key = $this->_get_primary_key(true);
        if (count($primary_key) == 1) {
            return current($primary_key);
        }
        return $primary_key;
    }
    /**
     * Deletes existing rows.
     *
     * @return int The number of rows deleted.
     */
    public function delete()
    {
        /**
         * A read-only row cannot be deleted.
         */
        if ($this->_read_only === true) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('This row has been marked read-only');
        }
        $where = $this->_get_where_query();
        /**
         * Execute pre-DELETE logic
         */
        $this->_delete();
        /**
         * Execute cascading deletes against dependent tables
         */
        $dep_tables = $this->_get_table()->get_dependent_tables();
        if (!empty($dep_tables)) {
            $pk = $this->_get_primary_key();
            foreach ($dep_tables as $table_class) {
                $t = $this->_get_table_from_string($table_class);
                $t->_cascade_delete($this->get_table_class(), $pk);
            }
        }
        /**
         * Execute the DELETE (this may throw an exception)
         */
        $result = $this->_get_table()->delete($where);
        /**
         * Execute post-DELETE logic
         */
        $this->_post_delete();
        /**
         * Reset all fields to null to indicate that the row is not there
         */
        $this->_data = array_combine(array_keys($this->_data), array_fill(0, count($this->_data), null));
        return $result;
    }
    public function getIterator()
    {
        return new ArrayIterator((array) $this->_data);
    }
    /**
     * Returns the column/value data as an array.
     *
     * @return array
     */
    public function to_array()
    {
        return (array) $this->_data;
    }
    /**
     * Sets all data in the row from an array.
     *
     * @return Zend_Db_Table_Row_Abstract Provides a fluent interface
     */
    public function set_from_array(array $data)
    {
        $data = array_intersect_key($data, $this->_data);
        foreach ($data as $column_name => $value) {
            $this->__set($column_name, $value);
        }
        return $this;
    }
    /**
     * Refreshes properties from the database.
     *
     * @return void
     */
    public function refresh()
    {
        return $this->_refresh();
    }
    /**
     * Retrieves an instance of the parent table.
     *
     * @return Zend_Db_Table_Abstract
     */
    protected function _get_table()
    {
        if (!$this->_connected) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('Cannot save a Row unless it is connected');
        }
        return $this->_table;
    }
    /**
     * Retrieves an associative array of primary keys.
     *
     * @param bool $useDirty
     * @return array
     */
    protected function _get_primary_key($use_dirty = true)
    {
        if (!is_array($this->_primary)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('The primary key must be set as an array');
        }
        $primary = array_flip($this->_primary);
        if ($use_dirty) {
            $array = array_intersect_key($this->_data, $primary);
        } else {
            $array = array_intersect_key($this->_clean_data, $primary);
        }
        if (count($primary) != count($array)) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("The specified Table '{$this->_table_class}' does not have the same primary key as the Row");
        }
        return $array;
    }
    /**
     * Retrieves an associative array of primary keys.
     *
     * @param bool $useDirty
     * @return array
     */
    public function get_primary_key($use_dirty = true)
    {
        return $this->_get_primary_key($use_dirty);
    }
    /**
     * Constructs where statement for retrieving row(s).
     *
     * @param bool $useDirty
     * @return array
     */
    protected function _get_where_query($use_dirty = true)
    {
        $where = [];
        $db = $this->_get_table()->get_adapter();
        $primary_key = $this->_get_primary_key($use_dirty);
        $info = $this->_get_table()->info();
        $metadata = $info[Zend_Db_Table_Abstract::METADATA];
        // retrieve recently updated row using primary keys
        $where = [];
        foreach ($primary_key as $column => $value) {
            $table_name = $db->quote_identifier($info[Zend_Db_Table_Abstract::NAME], true);
            $type = $metadata[$column]['DATA_TYPE'];
            $column_name = $db->quote_identifier($column, true);
            $where[] = $db->quote_into("{$table_name}.{$column_name} = ?", $value, $type);
        }
        return $where;
    }
    /**
     * Refreshes properties from the database.
     *
     * @return void
     */
    protected function _refresh()
    {
        $where = $this->_get_where_query();
        $row = $this->_get_table()->fetch_row($where);
        if (null === $row) {
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception('Cannot refresh row as parent is missing');
        }
        $this->_data = $row->to_array();
        $this->_clean_data = $this->_data;
        $this->_modified_fields = [];
    }
    /**
     * Allows pre-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _insert()
    {
    }
    /**
     * Allows post-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _post_insert()
    {
    }
    /**
     * Allows pre-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _update()
    {
    }
    /**
     * Allows post-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _post_update()
    {
    }
    /**
     * Allows pre-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _delete()
    {
    }
    /**
     * Allows post-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _post_delete()
    {
    }
    /**
     * Prepares a table reference for lookup.
     *
     * Ensures all reference keys are set and properly formatted.
     *
     * @param string                 $ruleKey
     * @return array
     */
    protected function _prepare_reference(Zend_Db_Table_Abstract $dependent_table, Zend_Db_Table_Abstract $parent_table, $rule_key)
    {
        $parent_table_name = get_class($parent_table) === 'Zend_Db_Table' ? $parent_table->get_definition_config_name() : get_class($parent_table);
        $map = $dependent_table->get_reference($parent_table_name, $rule_key);
        if (!isset($map[Zend_Db_Table_Abstract::REF_COLUMNS])) {
            $parent_info = $parent_table->info();
            $map[Zend_Db_Table_Abstract::REF_COLUMNS] = array_values((array) $parent_info['primary']);
        }
        $map[Zend_Db_Table_Abstract::COLUMNS] = (array) $map[Zend_Db_Table_Abstract::COLUMNS];
        $map[Zend_Db_Table_Abstract::REF_COLUMNS] = (array) $map[Zend_Db_Table_Abstract::REF_COLUMNS];
        return $map;
    }
    /**
     * Query a dependent table to retrieve rows matching the current row.
     *
     * @param string|Zend_Db_Table_Abstract  $dependentTable
     * @param string                         OPTIONAL $ruleKey
     * @param Zend_Db_Table_Select           OPTIONAL $select
     * @return Zend_Db_Table_Rowset_Abstract Query result from $dependentTable
     * @throws Zend_Db_Table_Row_Exception If $dependentTable is not a table or is not loadable.
     */
    public function find_dependent_rowset($dependent_table, $rule_key = null, ?Zend_Db_Table_Select $select = null)
    {
        $db = $this->_get_table()->get_adapter();
        if (is_string($dependent_table)) {
            $dependent_table = $this->_get_table_from_string($dependent_table);
        }
        if (!$dependent_table instanceof Zend_Db_Table_Abstract) {
            $type = gettype($dependent_table);
            if ($type == 'object') {
                $type = get_class($dependent_table);
            }
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Dependent table must be a Zend_Db_Table_Abstract, but it is {$type}");
        }
        // even if we are interacting between a table defined in a class and a
        // table via extension, ensure to persist the definition
        if (($table_definition = $this->_table->get_definition()) !== null && $dependent_table->get_definition() == null) {
            $dependent_table->set_options([Zend_Db_Table_Abstract::DEFINITION => $table_definition]);
        }
        if ($select === null) {
            $select = $dependent_table->select();
        } else {
            $select->set_table($dependent_table);
        }
        $map = $this->_prepare_reference($dependent_table, $this->_get_table(), $rule_key);
        for ($i = 0; $i < count($map[Zend_Db_Table_Abstract::COLUMNS]); ++$i) {
            $parent_column_name = $db->fold_case($map[Zend_Db_Table_Abstract::REF_COLUMNS][$i]);
            $value = $this->_data[$parent_column_name];
            // Use adapter from dependent table to ensure correct query construction
            $dependent_db = $dependent_table->get_adapter();
            $dependent_column_name = $dependent_db->fold_case($map[Zend_Db_Table_Abstract::COLUMNS][$i]);
            $dependent_column = $dependent_db->quote_identifier($dependent_column_name, true);
            $dependent_info = $dependent_table->info();
            $type = $dependent_info[Zend_Db_Table_Abstract::METADATA][$dependent_column_name]['DATA_TYPE'];
            $select->where("{$dependent_column} = ?", $value, $type);
        }
        return $dependent_table->fetch_all($select);
    }
    /**
     * Query a parent table to retrieve the single row matching the current row.
     *
     * @param string|Zend_Db_Table_Abstract $parentTable
     * @param string                        OPTIONAL $ruleKey
     * @param Zend_Db_Table_Select          OPTIONAL $select
     * @return Zend_Db_Table_Row_Abstract   Query result from $parentTable
     * @throws Zend_Db_Table_Row_Exception If $parentTable is not a table or is not loadable.
     */
    public function find_parent_row($parent_table, $rule_key = null, ?Zend_Db_Table_Select $select = null)
    {
        $db = $this->_get_table()->get_adapter();
        if (is_string($parent_table)) {
            $parent_table = $this->_get_table_from_string($parent_table);
        }
        if (!$parent_table instanceof Zend_Db_Table_Abstract) {
            $type = gettype($parent_table);
            if ($type == 'object') {
                $type = get_class($parent_table);
            }
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Parent table must be a Zend_Db_Table_Abstract, but it is {$type}");
        }
        // even if we are interacting between a table defined in a class and a
        // table via extension, ensure to persist the definition
        if (($table_definition = $this->_table->get_definition()) !== null && $parent_table->get_definition() == null) {
            $parent_table->set_options([Zend_Db_Table_Abstract::DEFINITION => $table_definition]);
        }
        if ($select === null) {
            $select = $parent_table->select();
        } else {
            $select->set_table($parent_table);
        }
        $map = $this->_prepare_reference($this->_get_table(), $parent_table, $rule_key);
        // iterate the map, creating the proper wheres
        for ($i = 0; $i < count($map[Zend_Db_Table_Abstract::COLUMNS]); ++$i) {
            $dependent_column_name = $db->fold_case($map[Zend_Db_Table_Abstract::COLUMNS][$i]);
            $value = $this->_data[$dependent_column_name];
            // Use adapter from parent table to ensure correct query construction
            $parent_db = $parent_table->get_adapter();
            $parent_column_name = $parent_db->fold_case($map[Zend_Db_Table_Abstract::REF_COLUMNS][$i]);
            $parent_column = $parent_db->quote_identifier($parent_column_name, true);
            $parent_info = $parent_table->info();
            // determine where part
            $type = $parent_info[Zend_Db_Table_Abstract::METADATA][$parent_column_name]['DATA_TYPE'];
            $nullable = $parent_info[Zend_Db_Table_Abstract::METADATA][$parent_column_name]['NULLABLE'];
            if ($value === null && $nullable == true) {
                $select->where("{$parent_column} IS NULL");
            } elseif ($value === null && $nullable == false) {
                return null;
            } else {
                $select->where("{$parent_column} = ?", $value, $type);
            }
        }
        return $parent_table->fetch_row($select);
    }
    /**
     * @param  string|Zend_Db_Table_Abstract  $matchTable
     * @param  string|Zend_Db_Table_Abstract  $intersectionTable
     * @param  string                         OPTIONAL $callerRefRule
     * @param  string                         OPTIONAL $matchRefRule
     * @param  Zend_Db_Table_Select           OPTIONAL $select
     * @return Zend_Db_Table_Rowset_Abstract Query result from $matchTable
     * @throws Zend_Db_Table_Row_Exception If $matchTable or $intersectionTable is not a table class or is not loadable.
     */
    public function find_many_to_many_rowset($match_table, $intersection_table, $caller_ref_rule = null, $match_ref_rule = null, ?Zend_Db_Table_Select $select = null)
    {
        $db = $this->_get_table()->get_adapter();
        if (is_string($intersection_table)) {
            $intersection_table = $this->_get_table_from_string($intersection_table);
        }
        if (!$intersection_table instanceof Zend_Db_Table_Abstract) {
            $type = gettype($intersection_table);
            if ($type == 'object') {
                $type = get_class($intersection_table);
            }
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Intersection table must be a Zend_Db_Table_Abstract, but it is {$type}");
        }
        // even if we are interacting between a table defined in a class and a
        // table via extension, ensure to persist the definition
        if (($table_definition = $this->_table->get_definition()) !== null && $intersection_table->get_definition() == null) {
            $intersection_table->set_options([Zend_Db_Table_Abstract::DEFINITION => $table_definition]);
        }
        if (is_string($match_table)) {
            $match_table = $this->_get_table_from_string($match_table);
        }
        if (!$match_table instanceof Zend_Db_Table_Abstract) {
            $type = gettype($match_table);
            if ($type == 'object') {
                $type = get_class($match_table);
            }
            #require_once 'Zend/Db/Table/Row/Exception.php';
            throw new Zend_Db_Table_Row_Exception("Match table must be a Zend_Db_Table_Abstract, but it is {$type}");
        }
        // even if we are interacting between a table defined in a class and a
        // table via extension, ensure to persist the definition
        if (($table_definition = $this->_table->get_definition()) !== null && $match_table->get_definition() == null) {
            $match_table->set_options([Zend_Db_Table_Abstract::DEFINITION => $table_definition]);
        }
        if ($select === null) {
            $select = $match_table->select();
        } else {
            $select->set_table($match_table);
        }
        // Use adapter from intersection table to ensure correct query construction
        $inter_info = $intersection_table->info();
        $inter_db = $intersection_table->get_adapter();
        $inter_name = $inter_info['name'];
        $inter_schema = $inter_info['schema'] ?? null;
        $match_info = $match_table->info();
        $match_name = $match_info['name'];
        $match_schema = $match_info['schema'] ?? null;
        $match_map = $this->_prepare_reference($intersection_table, $match_table, $match_ref_rule);
        for ($i = 0; $i < count($match_map[Zend_Db_Table_Abstract::COLUMNS]); ++$i) {
            $inter_col = $inter_db->quote_identifier('i' . '.' . $match_map[Zend_Db_Table_Abstract::COLUMNS][$i], true);
            $match_col = $inter_db->quote_identifier('m' . '.' . $match_map[Zend_Db_Table_Abstract::REF_COLUMNS][$i], true);
            $join_cond[] = "{$inter_col} = {$match_col}";
        }
        $join_cond = implode(' AND ', $join_cond);
        $select->from(['i' => $inter_name], [], $inter_schema)->join_inner(['m' => $match_name], $join_cond, Zend_Db_Select::SQL_WILDCARD, $match_schema)->set_integrity_check(false);
        $caller_map = $this->_prepare_reference($intersection_table, $this->_get_table(), $caller_ref_rule);
        for ($i = 0; $i < count($caller_map[Zend_Db_Table_Abstract::COLUMNS]); ++$i) {
            $caller_column_name = $db->fold_case($caller_map[Zend_Db_Table_Abstract::REF_COLUMNS][$i]);
            $value = $this->_data[$caller_column_name];
            $inter_column_name = $inter_db->fold_case($caller_map[Zend_Db_Table_Abstract::COLUMNS][$i]);
            $inter_col = $inter_db->quote_identifier("i.{$inter_column_name}", true);
            $inter_info = $intersection_table->info();
            $type = $inter_info[Zend_Db_Table_Abstract::METADATA][$inter_column_name]['DATA_TYPE'];
            $select->where($inter_db->quote_into("{$inter_col} = ?", $value, $type));
        }
        $stmt = $select->query();
        $config = ['table' => $match_table, 'data' => $stmt->fetch_all(Zend_Db::FETCH_ASSOC), 'rowClass' => $match_table->get_row_class(), 'readOnly' => false, 'stored' => true];
        $rowset_class = $match_table->get_rowset_class();
        if (!class_exists($rowset_class)) {
            try {
                #require_once 'Zend/Loader.php';
                Zend_Loader::load_class($rowset_class);
            } catch (Zend_Exception $e) {
                #require_once 'Zend/Db/Table/Row/Exception.php';
                throw new Zend_Db_Table_Row_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        return new $rowset_class($config);
    }
    /**
     * Turn magic function calls into non-magic function calls
     * to the above methods.
     *
     * @param array $args OPTIONAL Zend_Db_Table_Select query modifier
     * @return Zend_Db_Table_Row_Abstract|Zend_Db_Table_Rowset_Abstract
     * @throws Zend_Db_Table_Row_Exception If an invalid method is called.
     */
    public function __call(string $method, array $args)
    {
        $matches = [];
        if (count($args) && $args[0] instanceof Zend_Db_Table_Select) {
            $select = $args[0];
        } else {
            $select = null;
        }
        /**
         * Recognize methods for Has-Many cases:
         * findParent<Class>()
         * findParent<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^findParent(\w+?)(?:By(\w+))?$/', $method, $matches)) {
            $class = $matches[1];
            $rule_key1 = $matches[2] ?? null;
            return $this->find_parent_row($class, $rule_key1, $select);
        }
        /**
         * Recognize methods for Many-to-Many cases:
         * find<Class1>Via<Class2>()
         * find<Class1>Via<Class2>By<Rule>()
         * find<Class1>Via<Class2>By<Rule1>And<Rule2>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^find(\w+?)Via(\w+?)(?:By(\w+?)(?:And(\w+))?)?$/', $method, $matches)) {
            $class = $matches[1];
            $via_class = $matches[2];
            $rule_key1 = $matches[3] ?? null;
            $rule_key2 = $matches[4] ?? null;
            return $this->find_many_to_many_rowset($class, $via_class, $rule_key1, $rule_key2, $select);
        }
        /**
         * Recognize methods for Belongs-To cases:
         * find<Class>()
         * find<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^find(\w+?)(?:By(\w+))?$/', $method, $matches)) {
            $class = $matches[1];
            $rule_key1 = $matches[2] ?? null;
            return $this->find_dependent_rowset($class, $rule_key1, $select);
        }
        #require_once 'Zend/Db/Table/Row/Exception.php';
        throw new Zend_Db_Table_Row_Exception("Unrecognized method '{$method}()'");
    }
    /**
     * _getTableFromString
     *
     * @param string $tableName
     * @return Zend_Db_Table_Abstract
     */
    protected function _get_table_from_string($table_name)
    {
        return Zend_Db_Table_Abstract::get_table_from_string($table_name, $this->_table);
    }
}