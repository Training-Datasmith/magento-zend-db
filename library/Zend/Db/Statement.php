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
 * @subpackage Statement
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Db
 */
#require_once 'Zend/Db.php';
/**
 * @see Zend_Db_Statement_Interface
 */
#require_once 'Zend/Db/Statement/Interface.php';
/**
 * Abstract class to emulate a PDOStatement for native database adapters.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Statement
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Db_Statement implements Zend_Db_Statement_Interface
{
    /**
     * @var resource|object The driver level statement object/resource
     */
    protected $_stmt;
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_adapter;
    /**
     * The current fetch mode.
     *
     * @var integer
     */
    protected $_fetch_mode = Zend_Db::FETCH_ASSOC;
    /**
     * Attributes.
     *
     * @var array
     */
    protected $_attribute = [];
    /**
     * Column result bindings.
     *
     * @var array
     */
    protected $_bind_column = [];
    /**
     * Query parameter bindings; covers bindParam() and bindValue().
     *
     * @var array
     */
    protected $_bind_param = [];
    /**
     * SQL string split into an array at placeholders.
     *
     * @var array
     */
    protected $_sql_split = [];
    /**
     * Parameter placeholders in the SQL string by position in the split array.
     *
     * @var array
     */
    protected $_sql_param = [];
    /**
     * @var Zend_Db_Profiler_Query
     */
    protected $_query_id;
    /**
     * Constructor for a statement.
     *
     * @param Zend_Db_Adapter_Abstract $adapter
     * @param mixed $sql Either a string or Zend_Db_Select.
     */
    public function __construct($adapter, $sql)
    {
        $this->_adapter = $adapter;
        if ($sql instanceof Zend_Db_Select) {
            $sql = $sql->assemble();
        }
        $this->_parse_parameters($sql);
        $this->_prepare($sql);
        $this->_query_id = $this->_adapter->get_profiler()->query_start($sql);
    }
    /**
     * Internal method called by abstract statment constructor to setup
     * the driver level statement
     *
     * @return void
     */
    protected function _prepare($sql)
    {
    }
    /**
     * @param string $sql
     * @return void
     */
    protected function _parse_parameters($sql)
    {
        $this->_sql_split = [];
        if ($sql !== null) {
            $sql = $this->_strip_quoted($sql);
            $this->_sql_split = preg_split('/(\?|\:[a-zA-Z0-9_]+)/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        }
        // map params
        $this->_sql_param = [];
        foreach ($this->_sql_split as $val) {
            if ($val == '?') {
                if ($this->_adapter->supports_parameters('positional') === false) {
                    /**
                     * @see Zend_Db_Statement_Exception
                     */
                    #require_once 'Zend/Db/Statement/Exception.php';
                    throw new Zend_Db_Statement_Exception("Invalid bind-variable position '{$val}'");
                }
            } elseif ($val[0] == ':') {
                if ($this->_adapter->supports_parameters('named') === false) {
                    /**
                     * @see Zend_Db_Statement_Exception
                     */
                    #require_once 'Zend/Db/Statement/Exception.php';
                    throw new Zend_Db_Statement_Exception("Invalid bind-variable name '{$val}'");
                }
            }
            $this->_sql_param[] = $val;
        }
        // set up for binding
        $this->_bind_param = [];
    }
    /**
     * Remove parts of a SQL string that contain quoted strings
     * of values or identifiers.
     *
     * @param string $sql
     * @return string
     */
    protected function _strip_quoted($sql)
    {
        // get the character for value quoting
        // this should be '
        $q = $this->_adapter->quote('a');
        $q = $q[0];
        // get the value used as an escaped quote,
        // e.g. \' or ''
        $qe = $this->_adapter->quote($q);
        $qe = substr($qe, 1, 2);
        $qe = preg_quote($qe);
        $escape_char = substr($qe, 0, 1);
        // remove 'foo\'bar'
        if (!empty($q)) {
            $escape_char = preg_quote($escape_char);
            // this segfaults only after 65,000 characters instead of 9,000
            $sql = preg_replace("/{$q}([^{$q}{$escape_char}]*|({$qe})*)*{$q}/s", '', $sql);
        }
        if ($sql === null) {
            // this preg_replace call can return NULL in case of error (PREG_BACKTRACK_LIMIT_ERROR).
            // In this case the result of this method will be an empty string.
            return '';
        }
        // get a version of the SQL statement with all quoted
        // values and delimited identifiers stripped out
        // remove "foo\"bar"
        $sql = preg_replace('/"(\\\\"|[^"])*"/Us', '', $sql);
        // get the character for delimited id quotes,
        // this is usually " but in MySQL is `
        $d = $this->_adapter->quote_identifier('a');
        $d = $d[0];
        // get the value used as an escaped delimited id quote,
        // e.g. \" or "" or \`
        $de = $this->_adapter->quote_identifier($d);
        $de = substr($de, 1, 2);
        $de = preg_quote($de);
        // Note: $de and $d where never used..., now they are:
        $sql = preg_replace("/{$d}({$de}|\\\\{2}|[^{$d}])*{$d}/Us", '', $sql);
        return $sql;
    }
    /**
     * Bind a column of the statement result set to a PHP variable.
     *
     * @param string $column Name the column in the result set, either by
     *                       position or by name.
     * @param mixed  $param  Reference to the PHP variable containing the value.
     * @param mixed  $type   OPTIONAL
     * @return bool
     */
    public function bind_column($column, &$param, $type = null)
    {
        $this->_bind_column[$column] =& $param;
        return true;
    }
    /**
     * Binds a parameter to the specified variable name.
     *
     * @param mixed $parameter Name the parameter, either integer or string.
     * @param mixed $variable  Reference to PHP variable containing the value.
     * @param mixed $type      OPTIONAL Datatype of SQL parameter.
     * @param mixed $length    OPTIONAL Length of SQL parameter.
     * @param mixed $options   OPTIONAL Other options.
     * @return bool
     */
    public function bind_param($parameter, &$variable, $type = null, $length = null, $options = null)
    {
        if (!is_int($parameter) && !is_string($parameter)) {
            /**
             * @see Zend_Db_Statement_Exception
             */
            #require_once 'Zend/Db/Statement/Exception.php';
            throw new Zend_Db_Statement_Exception('Invalid bind-variable position');
        }
        $position = null;
        if (($intval = (int) $parameter) > 0 && $this->_adapter->supports_parameters('positional')) {
            if ($intval >= 1 || $intval <= count($this->_sql_param)) {
                $position = $intval;
            }
        } elseif ($this->_adapter->supports_parameters('named')) {
            if ($parameter[0] != ':') {
                $parameter = ':' . $parameter;
            }
            if (in_array($parameter, $this->_sql_param) !== false) {
                $position = $parameter;
            }
        }
        if ($position === null) {
            /**
             * @see Zend_Db_Statement_Exception
             */
            #require_once 'Zend/Db/Statement/Exception.php';
            throw new Zend_Db_Statement_Exception("Invalid bind-variable position '{$parameter}'");
        }
        // Finally we are assured that $position is valid
        $this->_bind_param[$position] =& $variable;
        return $this->_bind_param($position, $variable, $type, $length, $options);
    }
    /**
     * Binds a value to a parameter.
     *
     * @param mixed $parameter Name the parameter, either integer or string.
     * @param mixed $value     Scalar value to bind to the parameter.
     * @param mixed $type      OPTIONAL Datatype of the parameter.
     * @return bool
     */
    public function bind_value($parameter, $value, $type = null)
    {
        return $this->bind_param($parameter, $value, $type);
    }
    /**
     * Executes a prepared statement.
     *
     * @param array $params OPTIONAL Values to bind to parameter placeholders.
     * @return bool
     */
    public function execute(?array $params = null)
    {
        /*
         * Simple case - no query profiler to manage.
         */
        if ($this->_query_id === null) {
            return $this->_execute($params);
        }
        /*
         * Do the same thing, but with query profiler
         * management before and after the execute.
         */
        $prof = $this->_adapter->get_profiler();
        $qp = $prof->get_query_profile($this->_query_id);
        if ($qp->has_ended()) {
            $this->_query_id = $prof->query_clone($qp);
            $qp = $prof->get_query_profile($this->_query_id);
        }
        if ($params !== null) {
            $qp->bind_params($params);
        } else {
            $qp->bind_params($this->_bind_param);
        }
        $qp->start($this->_query_id);
        $retval = $this->_execute($params);
        $prof->query_end($this->_query_id);
        return $retval;
    }
    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int $style OPTIONAL Fetch mode.
     * @param int $col   OPTIONAL Column number, if fetch mode is by column.
     * @return array Collection of rows, each in a format by the fetch mode.
     */
    public function fetch_all($style = null, $col = null)
    {
        $data = [];
        if ($style === Zend_Db::FETCH_COLUMN && $col === null) {
            $col = 0;
        }
        if ($col === null) {
            while ($row = $this->fetch($style)) {
                $data[] = $row;
            }
        } else {
            while (false !== $val = $this->fetch_column($col)) {
                $data[] = $val;
            }
        }
        return $data;
    }
    /**
     * Returns a single column from the next row of a result set.
     *
     * @param int $col OPTIONAL Position of the column to fetch.
     * @return string One value from the next row of result set, or false.
     */
    public function fetch_column($col = 0)
    {
        $col = (int) $col;
        $row = $this->fetch(Zend_Db::FETCH_NUM);
        if (!is_array($row)) {
            return false;
        }
        return $row[$col];
    }
    /**
     * Fetches the next row and returns it as an object.
     *
     * @param string $class  OPTIONAL Name of the class to create.
     * @param array  $config OPTIONAL Constructor arguments for the class.
     * @return mixed One object instance of the specified class, or false.
     */
    public function fetch_object($class = 'stdClass', array $config = [])
    {
        $obj = new $class($config);
        $row = $this->fetch(Zend_Db::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }
        foreach ($row as $key => $val) {
            $obj->{$key} = $val;
        }
        return $obj;
    }
    /**
     * Retrieve a statement attribute.
     *
     * @param string $key Attribute name.
     * @return mixed      Attribute value.
     */
    public function get_attribute($key)
    {
        if (array_key_exists($key, $this->_attribute)) {
            return $this->_attribute[$key];
        }
    }
    /**
     * Set a statement attribute.
     *
     * @param string $key Attribute name.
     * @param mixed  $val Attribute value.
     * @return bool
     */
    public function set_attribute($key, $val)
    {
        $this->_attribute[$key] = $val;
    }
    /**
     * Set the default fetch mode for this statement.
     *
     * @param int   $mode The fetch mode.
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public function set_fetch_mode($mode)
    {
        switch ($mode) {
            case Zend_Db::FETCH_NUM:
            case Zend_Db::FETCH_ASSOC:
            case Zend_Db::FETCH_BOTH:
            case Zend_Db::FETCH_OBJ:
                $this->_fetch_mode = $mode;
                break;
            case Zend_Db::FETCH_BOUND:
            default:
                $this->close_cursor();
                /**
                 * @see Zend_Db_Statement_Exception
                 */
                #require_once 'Zend/Db/Statement/Exception.php';
                throw new Zend_Db_Statement_Exception('invalid fetch mode');
        }
    }
    /**
     * Helper function to map retrieved row
     * to bound column variables
     *
     * @param array $row
     * @return bool True
     */
    public function _fetch_bound($row)
    {
        foreach ($row as $key => $value) {
            // bindColumn() takes 1-based integer positions
            // but fetch() returns 0-based integer indexes
            if (is_int($key)) {
                $key++;
            }
            // set results only to variables that were bound previously
            if (isset($this->_bind_column[$key])) {
                $this->_bind_column[$key] = $value;
            }
        }
        return true;
    }
    /**
     * Gets the Zend_Db_Adapter_Abstract for this
     * particular Zend_Db_Statement object.
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }
    /**
     * Gets the resource or object setup by the
     * _parse
     * @return unknown_type
     */
    public function get_driver_statement()
    {
        return $this->_stmt;
    }
}