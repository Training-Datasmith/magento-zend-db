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
/** @see Zend_Db_Adapter_Pdo_Abstract */
#require_once 'Zend/Db/Adapter/Pdo/Abstract.php';
/** @see Zend_Db_Abstract_Pdo_Ibm_Db2 */
#require_once 'Zend/Db/Adapter/Pdo/Ibm/Db2.php';
/** @see Zend_Db_Abstract_Pdo_Ibm_Ids */
#require_once 'Zend/Db/Adapter/Pdo/Ibm/Ids.php';
/** @see Zend_Db_Statement_Pdo_Ibm */
#require_once 'Zend/Db/Statement/Pdo/Ibm.php';
/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Adapter_Pdo_Ibm extends Zend_Db_Adapter_Pdo_Abstract
{
    /**
     * PDO type.
     *
     * @var string
     */
    protected $_pdo_type = 'ibm';
    /**
     * The IBM data server connected to
     *
     * @var string
     */
    protected $_server_type;
    /**
     * Keys are UPPERCASE SQL datatypes or the constants
     * Zend_Db::INT_TYPE, Zend_Db::BIGINT_TYPE, or Zend_Db::FLOAT_TYPE.
     *
     * Values are:
     * 0 = 32-bit integer
     * 1 = 64-bit integer
     * 2 = float or decimal
     *
     * @var array Associative array of datatypes to values 0, 1, or 2.
     */
    protected $_numeric_data_types = [Zend_Db::INT_TYPE => Zend_Db::INT_TYPE, Zend_Db::BIGINT_TYPE => Zend_Db::BIGINT_TYPE, Zend_Db::FLOAT_TYPE => Zend_Db::FLOAT_TYPE, 'INTEGER' => Zend_Db::INT_TYPE, 'SMALLINT' => Zend_Db::INT_TYPE, 'BIGINT' => Zend_Db::BIGINT_TYPE, 'DECIMAL' => Zend_Db::FLOAT_TYPE, 'DEC' => Zend_Db::FLOAT_TYPE, 'REAL' => Zend_Db::FLOAT_TYPE, 'NUMERIC' => Zend_Db::FLOAT_TYPE, 'DOUBLE PRECISION' => Zend_Db::FLOAT_TYPE, 'FLOAT' => Zend_Db::FLOAT_TYPE];
    /**
     * Creates a PDO object and connects to the database.
     *
     * The IBM data server is set.
     * Current options are DB2 or IDS
     * @todo also differentiate between z/OS and i/5
     *
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    public function _connect()
    {
        if ($this->_connection) {
            return;
        }
        parent::_connect();
        $this->get_connection()->set_attribute(Zend_Db::ATTR_STRINGIFY_FETCHES, true);
        try {
            if ($this->_server_type === null) {
                $server = substr($this->get_connection()->get_attribute(PDO::ATTR_SERVER_INFO), 0, 3);
                switch ($server) {
                    case 'DB2':
                        $this->_server_type = new Zend_Db_Adapter_Pdo_Ibm_Db2($this);
                        // Add DB2-specific numeric types
                        $this->_numeric_data_types['DECFLOAT'] = Zend_Db::FLOAT_TYPE;
                        $this->_numeric_data_types['DOUBLE'] = Zend_Db::FLOAT_TYPE;
                        $this->_numeric_data_types['NUM'] = Zend_Db::FLOAT_TYPE;
                        break;
                    case 'IDS':
                        $this->_server_type = new Zend_Db_Adapter_Pdo_Ibm_Ids($this);
                        // Add IDS-specific numeric types
                        $this->_numeric_data_types['SERIAL'] = Zend_Db::INT_TYPE;
                        $this->_numeric_data_types['SERIAL8'] = Zend_Db::BIGINT_TYPE;
                        $this->_numeric_data_types['INT8'] = Zend_Db::BIGINT_TYPE;
                        $this->_numeric_data_types['SMALLFLOAT'] = Zend_Db::FLOAT_TYPE;
                        $this->_numeric_data_types['MONEY'] = Zend_Db::FLOAT_TYPE;
                        break;
                }
            }
        } catch (PDOException $e) {
            /** @see Zend_Db_Adapter_Exception */
            #require_once 'Zend/Db/Adapter/Exception.php';
            $error = strpos($e->get_message(), 'driver does not support that attribute');
            if ($error) {
                throw new Zend_Db_Adapter_Exception('PDO_IBM driver extension is downlevel.  Please use driver release version 1.2.1 or later', 0, $e);
            }
            throw new Zend_Db_Adapter_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Creates a PDO DSN for the adapter from $this->_config settings.
     */
    protected function _dsn(): string
    {
        $this->_check_required_options($this->_config);
        // check if using full connection string
        if (array_key_exists('host', $this->_config)) {
            $dsn = ';DATABASE=' . $this->_config['dbname'] . ';HOSTNAME=' . $this->_config['host'] . ';PORT=' . $this->_config['port'] . ';PROTOCOL=' . 'TCPIP;';
        } else {
            // catalogued connection
            $dsn = $this->_config['dbname'];
        }
        return $this->_pdo_type . ': ' . $dsn;
    }
    /**
     * Checks required options
     *
     * @throws Zend_Db_Adapter_Exception
     * @return void
     */
    protected function _check_required_options(array $config)
    {
        parent::_check_required_options($config);
        if (array_key_exists('host', $this->_config) && !array_key_exists('port', $config)) {
            /** @see Zend_Db_Adapter_Exception */
            #require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("Configuration must have a key for 'port' when 'host' is specified");
        }
    }
    /**
     * Prepares an SQL statement.
     *
     * @param string $sql The SQL statement with placeholders.
     * @param array $bind An array of data to bind to the placeholders.
     * @return PDOStatement
     */
    public function prepare($sql): object
    {
        $this->_connect();
        $stmt_class = $this->_default_stmt_class;
        $stmt = new $stmt_class($this, $sql);
        $stmt->set_fetch_mode($this->_fetch_mode);
        return $stmt;
    }
    /**
     * Returns a list of the tables in the database.
     *
     * @return array
     */
    public function list_tables()
    {
        $this->_connect();
        return $this->_server_type->list_tables();
    }
    /**
     * Returns the column descriptions for a table.
     *
     * The return value is an associative array keyed by the column name,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * COLUMN_POSITION  => number; ordinal position of column in table
     * DATA_TYPE        => string; SQL datatype name of column
     * DEFAULT          => string; default expression of column, null if none
     * NULLABLE         => boolean; true if column can have nulls
     * LENGTH           => number; length of CHAR/VARCHAR
     * SCALE            => number; scale of NUMERIC/DECIMAL
     * PRECISION        => number; precision of NUMERIC/DECIMAL
     * UNSIGNED         => boolean; unsigned property of an integer type
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     *
     * @todo Discover integer unsigned property.
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     * @return array
     */
    public function describe_table($table_name, $schema_name = null)
    {
        $this->_connect();
        return $this->_server_type->describe_table($table_name, $schema_name);
    }
    /**
     * Inserts a table row with specified data.
     * Special handling for PDO_IBM
     * remove empty slots
     *
     * @param mixed $table The table to insert data into.
     * @param array $bind Column-value pairs.
     * @return int The number of affected rows.
     */
    public function insert($table, array $bind)
    {
        $this->_connect();
        $newbind = [];
        if (is_array($bind)) {
            foreach ($bind as $name => $value) {
                if ($value !== null) {
                    $newbind[$name] = $value;
                }
            }
        }
        return parent::insert($table, $newbind);
    }
    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql
     * @param integer $count
     * @param integer $offset OPTIONAL
     * @return string
     */
    public function limit($sql, $count, $offset = 0)
    {
        $this->_connect();
        return $this->_server_type->limit($sql, $count, $offset);
    }
    /**
     * Gets the last ID generated automatically by an IDENTITY/AUTOINCREMENT
     * column.
     *
     * @param string $tableName OPTIONAL
     * @param string $primaryKey OPTIONAL
     * @return integer
     */
    public function last_insert_id($table_name = null, $primary_key = null)
    {
        $this->_connect();
        if ($table_name !== null) {
            $sequence_name = $table_name;
            if ($primary_key) {
                $sequence_name .= "_{$primary_key}";
            }
            $sequence_name .= '_seq';
            return $this->last_sequence_id($sequence_name);
        }
        return $this->get_connection()->last_insert_id();
    }
    /**
     * Return the most recent value from the specified sequence in the database.
     *
     * @param string $sequenceName
     * @return integer
     */
    public function last_sequence_id($sequence_name)
    {
        $this->_connect();
        return $this->_server_type->last_sequence_id($sequence_name);
    }
    /**
     * Generate a new value from the specified sequence in the database,
     * and return it.
     *
     * @param string $sequenceName
     * @return integer
     */
    public function next_sequence_id($sequence_name)
    {
        $this->_connect();
        return $this->_server_type->next_sequence_id($sequence_name);
    }
    /**
     * Retrieve server version in PHP style
     * Pdo_Idm doesn't support getAttribute(PDO::ATTR_SERVER_VERSION)
     * @return string
     */
    public function get_server_version()
    {
        try {
            $stmt = $this->query('SELECT service_level, fixpack_num FROM TABLE (sysproc.env_get_inst_info()) as INSTANCEINFO');
            $result = $stmt->fetch_all(Zend_Db::FETCH_NUM);
            if (count($result)) {
                $matches = null;
                if (preg_match('/((?:[0-9]{1,2}\.){1,3}[0-9]{1,2})/', $result[0][0], $matches)) {
                    return $matches[1];
                }
                return null;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
}