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
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Db_Adapter_Abstract
 */
#require_once 'Zend/Db/Adapter/Abstract.php';
/**
 * @see Zend_Db_Statement_Pdo
 */
#require_once 'Zend/Db/Statement/Pdo.php';
/**
 * Class for connecting to SQL databases and performing common operations using PDO.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Db_Adapter_Pdo_Abstract extends Zend_Db_Adapter_Abstract
{
    /**
     * @var string
     */
    protected $_pdo_type = '';
    /**
     * Default class name for a DB statement.
     *
     * @var string
     */
    protected $_default_stmt_class = 'Zend_Db_Statement_Pdo';
    /**
     * Creates a PDO DSN for the adapter from $this->_config settings.
     *
     * @return string
     */
    protected function _dsn()
    {
        // baseline of DSN parts
        $dsn = $this->_config;
        // don't pass the username, password, charset, persistent and driver_options in the DSN
        unset($dsn['username']);
        unset($dsn['password']);
        unset($dsn['options']);
        unset($dsn['charset']);
        unset($dsn['persistent']);
        unset($dsn['driver_options']);
        // use all remaining parts in the DSN
        foreach ($dsn as $key => $val) {
            $dsn[$key] = "{$key}={$val}";
        }
        return $this->_pdo_type . ':' . implode(';', $dsn);
    }
    /**
     * Creates a PDO object and connects to the database.
     *
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    protected function _connect()
    {
        // if we already have a PDO object, no need to re-connect.
        if ($this->_connection) {
            return;
        }
        // get the dsn first, because some adapters alter the $_pdoType
        $dsn = $this->_dsn();
        // check for PDO extension
        if (!extension_loaded('pdo')) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception('The PDO extension is required for this adapter but the extension is not loaded');
        }
        // check the PDO driver is available
        if (!in_array($this->_pdo_type, PDO::get_available_drivers())) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception('The ' . $this->_pdo_type . ' driver is not currently installed');
        }
        // create PDO connection
        $q = $this->_profiler->query_start('connect', Zend_Db_Profiler::CONNECT);
        // add the persistence flag if we find it in our config array
        if (isset($this->_config['persistent']) && $this->_config['persistent'] == true) {
            $this->_config['driver_options'][PDO::ATTR_PERSISTENT] = true;
        }
        try {
            $this->_connection = new PDO($dsn, $this->_config['username'], $this->_config['password'], $this->_config['driver_options']);
            $this->_profiler->query_end($q);
            // set the PDO connection to perform case-folding on array keys, or not
            $this->_connection->set_attribute(PDO::ATTR_CASE, $this->_case_folding);
            // always use exceptions.
            $this->_connection->set_attribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Test if a connection is active
     *
     * @return boolean
     */
    public function is_connected()
    {
        return $this->_connection instanceof PDO;
    }
    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function close_connection()
    {
        $this->_connection = null;
    }
    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement with placeholders.
     * @param array $bind An array of data to bind to the placeholders.
     * @return PDOStatement
     */
    public function prepare($sql)
    {
        $this->_connect();
        $stmt_class = $this->_default_stmt_class;
        if (!class_exists($stmt_class)) {
            #require_once 'Zend/Loader.php';
            Zend_Loader::load_class($stmt_class);
        }
        $stmt = new $stmt_class($this, $sql);
        $stmt->set_fetch_mode($this->_fetch_mode);
        return $stmt;
    }
    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT column.
     *
     * As a convention, on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2), this method forms the name of a sequence
     * from the arguments and returns the last id generated by that sequence.
     * On RDBMS brands that support IDENTITY/AUTOINCREMENT columns, this method
     * returns the last value generated for such a column, and the table name
     * argument is disregarded.
     *
     * On RDBMS brands that don't support sequences, $tableName and $primaryKey
     * are ignored.
     *
     * @param string $tableName   OPTIONAL Name of table.
     * @param string $primaryKey  OPTIONAL Name of primary key column.
     * @return string
     */
    public function last_insert_id($table_name = null, $primary_key = null)
    {
        $this->_connect();
        return $this->_connection->last_insert_id();
    }
    /**
     * Special handling for PDO query().
     * All bind parameter names must begin with ':'
     *
     * @param string|Zend_Db_Select $sql The SQL statement with placeholders.
     * @param array $bind An array of data to bind to the placeholders.
     * @return Zend_Db_Statement_Pdo
     * @throws Zend_Db_Adapter_Exception To re-throw PDOException.
     */
    public function query($sql, $bind = [])
    {
        if (empty($bind) && $sql instanceof Zend_Db_Select) {
            $bind = $sql->get_bind();
        }
        if (is_array($bind)) {
            foreach ($bind as $name => $value) {
                if (!is_int($name) && !preg_match('/^:/', $name)) {
                    $new_name = ":{$name}";
                    unset($bind[$name]);
                    $bind[$new_name] = $value;
                }
            }
        }
        try {
            return parent::query($sql, $bind);
        } catch (PDOException $e) {
            /**
             * @see Zend_Db_Statement_Exception
             */
            #require_once 'Zend/Db/Statement/Exception.php';
            throw new Zend_Db_Statement_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Executes an SQL statement and return the number of affected rows
     *
     * @param  mixed  $sql  The SQL statement with placeholders.
     *                      May be a string or Zend_Db_Select.
     * @return integer      Number of rows that were modified
     *                      or deleted by the SQL statement
     */
    public function exec($sql)
    {
        if ($sql instanceof Zend_Db_Select) {
            $sql = $sql->assemble();
        }
        try {
            $affected = $this->get_connection()->exec($sql);
            if ($affected === false) {
                $error_info = $this->get_connection()->error_info();
                /**
                 * @see Zend_Db_Adapter_Exception
                 */
                #require_once 'Zend/Db/Adapter/Exception.php';
                throw new Zend_Db_Adapter_Exception($error_info[2]);
            }
            return $affected;
        } catch (PDOException $e) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Quote a raw string.
     *
     * @param string $value     Raw string
     * @return string           Quoted string
     */
    protected function _quote($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $this->_connect();
        return $this->_connection->quote($value);
    }
    /**
     * Begin a transaction.
     */
    protected function _begin_transaction()
    {
        $this->_connect();
        $this->_connection->begin_transaction();
    }
    /**
     * Commit a transaction.
     */
    protected function _commit()
    {
        $this->_connect();
        $this->_connection->commit();
    }
    /**
     * Roll-back a transaction.
     */
    protected function _roll_back()
    {
        $this->_connect();
        $this->_connection->roll_back();
    }
    /**
     * Set the PDO fetch mode.
     *
     * @todo Support FETCH_CLASS and FETCH_INTO.
     *
     * @param int $mode A PDO fetch mode.
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    public function set_fetch_mode($mode)
    {
        //check for PDO extension
        if (!extension_loaded('pdo')) {
            /**
             * @see Zend_Db_Adapter_Exception
             */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception('The PDO extension is required for this adapter but the extension is not loaded');
        }
        switch ($mode) {
            case PDO::FETCH_LAZY:
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NAMED:
            case PDO::FETCH_OBJ:
                $this->_fetch_mode = $mode;
                break;
            default:
                /**
                 * @see Zend_Db_Adapter_Exception
                 */
                #require_once 'Zend/Db/Adapter/Exception.php';
                throw new Zend_Db_Adapter_Exception("Invalid fetch mode '{$mode}' specified");
        }
    }
    /**
     * Check if the adapter supports real SQL parameters.
     *
     * @param string $type 'positional' or 'named'
     * @return bool
     */
    public function supports_parameters($type)
    {
        switch ($type) {
            case 'positional':
            case 'named':
            default:
                return true;
        }
    }
    /**
     * Retrieve server version in PHP style
     *
     * @return string
     */
    public function get_server_version()
    {
        $this->_connect();
        try {
            $version = $this->_connection->get_attribute(PDO::ATTR_SERVER_VERSION);
        } catch (PDOException $e) {
            // In case of the driver doesn't support getting attributes
            return null;
        }
        $matches = null;
        if (preg_match('/((?:[0-9]{1,2}\.){1,3}[0-9]{1,2})/', $version, $matches)) {
            return $matches[1];
        }
        return null;
    }
}