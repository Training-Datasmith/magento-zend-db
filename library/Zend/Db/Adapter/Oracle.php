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
 * @see Zend_Db_Statement_Oracle
 */
#require_once 'Zend/Db/Statement/Oracle.php';
/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Adapter_Oracle extends Zend_Db_Adapter_Abstract
{
    /**
     * User-provided configuration.
     *
     * Basic keys are:
     *
     * username => (string) Connect to the database as this username.
     * password => (string) Password associated with the username.
     * dbname   => Either the name of the local Oracle instance, or the
     *             name of the entry in tnsnames.ora to which you want to connect.
     * persistent => (boolean) Set TRUE to use a persistent connection
     * @var array
     */
    protected $_config = ['dbname' => null, 'username' => null, 'password' => null, 'persistent' => false];
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
    protected $_numeric_data_types = [Zend_Db::INT_TYPE => Zend_Db::INT_TYPE, Zend_Db::BIGINT_TYPE => Zend_Db::BIGINT_TYPE, Zend_Db::FLOAT_TYPE => Zend_Db::FLOAT_TYPE, 'BINARY_DOUBLE' => Zend_Db::FLOAT_TYPE, 'BINARY_FLOAT' => Zend_Db::FLOAT_TYPE, 'NUMBER' => Zend_Db::FLOAT_TYPE];
    /**
     * @var integer
     */
    protected $_execute_mode;
    /**
     * Default class name for a DB statement.
     *
     * @var string
     */
    protected $_default_stmt_class = 'Zend_Db_Statement_Oracle';
    /**
     * Check if LOB field are returned as string
     * instead of OCI-Lob object
     *
     * @var boolean
     */
    protected $_lob_as_string;
    /**
     * Creates a connection resource.
     *
     * @return void
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    protected function _connect()
    {
        if (is_resource($this->_connection)) {
            // connection already exists
            return;
        }
        if (!extension_loaded('oci8')) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception('The OCI8 extension is required for this adapter but the extension is not loaded');
        }
        $this->_set_execute_mode(OCI_COMMIT_ON_SUCCESS);
        $connection_func_name = $this->_config['persistent'] == true ? 'oci_pconnect' : 'oci_connect';
        $this->_connection = @$connection_func_name($this->_config['username'], $this->_config['password'], $this->_config['dbname'], $this->_config['charset']);
        // check the connection
        if (!$this->_connection) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception(oci_error());
        }
    }
    /**
     * Test if a connection is active
     */
    public function is_connected(): bool
    {
        return is_resource($this->_connection) && (get_resource_type($this->_connection) == 'oci8 connection' || get_resource_type($this->_connection) == 'oci8 persistent connection');
    }
    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function close_connection()
    {
        if ($this->is_connected()) {
            oci_close($this->_connection);
        }
        $this->_connection = null;
    }
    /**
     * Activate/deactivate return of LOB as string
     *
     * @param string $lob_as_string
     */
    public function set_lob_as_string($lob_as_string): self
    {
        $this->_lob_as_string = (bool) $lob_as_string;
        return $this;
    }
    /**
     * Return whether or not LOB are returned as string
     *
     * @return boolean
     */
    public function get_lob_as_string()
    {
        if ($this->_lob_as_string === null) {
            // if never set by user, we use driver option if it exists otherwise false
            if (isset($this->_config['driver_options']) && isset($this->_config['driver_options']['lob_as_string'])) {
                $this->_lob_as_string = (bool) $this->_config['driver_options']['lob_as_string'];
            } else {
                $this->_lob_as_string = false;
            }
        }
        return $this->_lob_as_string;
    }
    /**
     * Returns an SQL statement for preparation.
     *
     * @param string $sql The SQL statement with placeholders.
     * @return Zend_Db_Statement_Oracle
     */
    public function prepare($sql): object
    {
        $this->_connect();
        $stmt_class = $this->_default_stmt_class;
        if (!class_exists($stmt_class)) {
            #require_once 'Zend/Loader.php';
            Zend_Loader::load_class($stmt_class);
        }
        $stmt = new $stmt_class($this, $sql);
        if ($stmt instanceof Zend_Db_Statement_Oracle) {
            $stmt->set_lob_as_string($this->get_lob_as_string());
        }
        $stmt->set_fetch_mode($this->_fetch_mode);
        return $stmt;
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
        $value = str_replace("'", "''", $value);
        return "'" . addcslashes($value, "\x00\n\r\\\x1a") . "'";
    }
    /**
     * Quote a table identifier and alias.
     *
     * @param string|array|Zend_Db_Expr $ident The identifier or expression.
     * @param string $alias An alias for the table.
     * @param boolean $auto If true, heed the AUTO_QUOTE_IDENTIFIERS config option.
     * @return string The quoted identifier and alias.
     */
    public function quote_table_as($ident, $alias = null, $auto = false)
    {
        // Oracle doesn't allow the 'AS' keyword between the table identifier/expression and alias.
        return $this->_quote_identifier_as($ident, $alias, $auto, ' ');
    }
    /**
     * Return the most recent value from the specified sequence in the database.
     * This is supported only on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2).  Other RDBMS brands return null.
     *
     * @param string $sequenceName
     * @return string
     */
    public function last_sequence_id($sequence_name)
    {
        $this->_connect();
        $sql = 'SELECT ' . $this->quote_identifier($sequence_name, true) . '.CURRVAL FROM dual';
        return $this->fetch_one($sql);
    }
    /**
     * Generate a new value from the specified sequence in the database, and return it.
     * This is supported only on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2).  Other RDBMS brands return null.
     *
     * @param string $sequenceName
     * @return string
     */
    public function next_sequence_id($sequence_name)
    {
        $this->_connect();
        $sql = 'SELECT ' . $this->quote_identifier($sequence_name, true) . '.NEXTVAL FROM dual';
        return $this->fetch_one($sql);
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
     * Oracle does not support IDENTITY columns, so if the sequence is not
     * specified, this method returns null.
     *
     * @param string $tableName   OPTIONAL Name of table.
     * @param string $primaryKey  OPTIONAL Name of primary key column.
     * @return string
     */
    public function last_insert_id($table_name = null, $primary_key = null)
    {
        if ($table_name !== null) {
            $sequence_name = $table_name;
            if ($primary_key) {
                $sequence_name .= "_{$primary_key}";
            }
            $sequence_name .= '_seq';
            return $this->last_sequence_id($sequence_name);
        }
        // No support for IDENTITY columns; return null
        return null;
    }
    /**
     * Returns a list of the tables in the database.
     *
     * @return array
     */
    public function list_tables()
    {
        $this->_connect();
        return $this->fetch_col('SELECT table_name FROM all_tables');
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
     * SCHEMA_NAME      => string; name of schema
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
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @todo Discover integer unsigned property.
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     */
    public function describe_table($table_name, $schema_name = null): array
    {
        $version = $this->get_server_version();
        if ($version === null || version_compare($version, '9.0.0', '>=')) {
            $sql = "SELECT TC.TABLE_NAME, TC.OWNER, TC.COLUMN_NAME, TC.DATA_TYPE,\n                    TC.DATA_DEFAULT, TC.NULLABLE, TC.COLUMN_ID, TC.DATA_LENGTH,\n                    TC.DATA_SCALE, TC.DATA_PRECISION, C.CONSTRAINT_TYPE, CC.POSITION\n                FROM ALL_TAB_COLUMNS TC\n                LEFT JOIN (ALL_CONS_COLUMNS CC JOIN ALL_CONSTRAINTS C\n                    ON (CC.CONSTRAINT_NAME = C.CONSTRAINT_NAME AND CC.TABLE_NAME = C.TABLE_NAME AND CC.OWNER = C.OWNER AND C.CONSTRAINT_TYPE = 'P'))\n                  ON TC.TABLE_NAME = CC.TABLE_NAME AND TC.COLUMN_NAME = CC.COLUMN_NAME\n                WHERE UPPER(TC.TABLE_NAME) = UPPER(:TBNAME)";
            $bind[':TBNAME'] = $table_name;
            if ($schema_name) {
                $sql .= ' AND UPPER(TC.OWNER) = UPPER(:SCNAME)';
                $bind[':SCNAME'] = $schema_name;
            }
            $sql .= ' ORDER BY TC.COLUMN_ID';
        } else {
            $sub_sql = "SELECT AC.OWNER, AC.TABLE_NAME, ACC.COLUMN_NAME, AC.CONSTRAINT_TYPE, ACC.POSITION\n                from ALL_CONSTRAINTS AC, ALL_CONS_COLUMNS ACC\n                  WHERE ACC.CONSTRAINT_NAME = AC.CONSTRAINT_NAME\n                    AND ACC.TABLE_NAME = AC.TABLE_NAME\n                    AND ACC.OWNER = AC.OWNER\n                    AND AC.CONSTRAINT_TYPE = 'P'\n                    AND UPPER(AC.TABLE_NAME) = UPPER(:TBNAME)";
            $bind[':TBNAME'] = $table_name;
            if ($schema_name) {
                $sub_sql .= ' AND UPPER(ACC.OWNER) = UPPER(:SCNAME)';
                $bind[':SCNAME'] = $schema_name;
            }
            $sql = "SELECT TC.TABLE_NAME, TC.OWNER, TC.COLUMN_NAME, TC.DATA_TYPE,\n                    TC.DATA_DEFAULT, TC.NULLABLE, TC.COLUMN_ID, TC.DATA_LENGTH,\n                    TC.DATA_SCALE, TC.DATA_PRECISION, CC.CONSTRAINT_TYPE, CC.POSITION\n                FROM ALL_TAB_COLUMNS TC, ({$sub_sql}) CC\n                WHERE UPPER(TC.TABLE_NAME) = UPPER(:TBNAME)\n                  AND TC.OWNER = CC.OWNER(+) AND TC.TABLE_NAME = CC.TABLE_NAME(+) AND TC.COLUMN_NAME = CC.COLUMN_NAME(+)";
            if ($schema_name) {
                $sql .= ' AND UPPER(TC.OWNER) = UPPER(:SCNAME)';
            }
            $sql .= ' ORDER BY TC.COLUMN_ID';
        }
        $stmt = $this->query($sql, $bind);
        /**
         * Use FETCH_NUM so we are not dependent on the CASE attribute of the PDO connection
         */
        $result = $stmt->fetch_all(Zend_Db::FETCH_NUM);
        $table_name = 0;
        $owner = 1;
        $column_name = 2;
        $data_type = 3;
        $data_default = 4;
        $nullable = 5;
        $column_id = 6;
        $data_length = 7;
        $data_scale = 8;
        $data_precision = 9;
        $constraint_type = 10;
        $position = 11;
        $desc = [];
        foreach ($result as $row) {
            list($primary, $primary_position, $identity) = [false, null, false];
            if ($row[$constraint_type] == 'P') {
                $primary = true;
                $primary_position = $row[$position];
                /**
                 * Oracle does not support auto-increment keys.
                 */
                $identity = false;
            }
            $desc[$this->fold_case($row[$column_name])] = [
                'SCHEMA_NAME' => $this->fold_case($row[$owner]),
                'TABLE_NAME' => $this->fold_case($row[$table_name]),
                'COLUMN_NAME' => $this->fold_case($row[$column_name]),
                'COLUMN_POSITION' => $row[$column_id],
                'DATA_TYPE' => $row[$data_type],
                'DEFAULT' => $row[$data_default],
                'NULLABLE' => $row[$nullable] == 'Y',
                'LENGTH' => $row[$data_length],
                'SCALE' => $row[$data_scale],
                'PRECISION' => $row[$data_precision],
                'UNSIGNED' => null,
                // @todo
                'PRIMARY' => $primary,
                'PRIMARY_POSITION' => $primary_position,
                'IDENTITY' => $identity,
            ];
        }
        return $desc;
    }
    /**
     * Leave autocommit mode and begin a transaction.
     *
     * @return void
     */
    protected function _begin_transaction()
    {
        $this->_set_execute_mode(OCI_DEFAULT);
    }
    /**
     * Commit a transaction and return to autocommit mode.
     *
     * @return void
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    protected function _commit()
    {
        if (!oci_commit($this->_connection)) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception(oci_error($this->_connection));
        }
        $this->_set_execute_mode(OCI_COMMIT_ON_SUCCESS);
    }
    /**
     * Roll back a transaction and return to autocommit mode.
     *
     * @return void
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    protected function _roll_back()
    {
        if (!oci_rollback($this->_connection)) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception(oci_error($this->_connection));
        }
        $this->_set_execute_mode(OCI_COMMIT_ON_SUCCESS);
    }
    /**
     * Set the fetch mode.
     *
     * @todo Support FETCH_CLASS and FETCH_INTO.
     *
     * @param integer $mode A fetch mode.
     * @return void
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    public function set_fetch_mode($mode)
    {
        switch ($mode) {
            case Zend_Db::FETCH_NUM:
            // seq array
            case Zend_Db::FETCH_ASSOC:
            // assoc array
            case Zend_Db::FETCH_BOTH:
            // seq+assoc array
            case Zend_Db::FETCH_OBJ:
                // object
                $this->_fetch_mode = $mode;
                break;
            case Zend_Db::FETCH_BOUND:
                // bound to PHP variable
                /**
                 * @see Zend_Db_Adapter_Oracle_Exception
                 */
                #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
                throw new Zend_Db_Adapter_Oracle_Exception('FETCH_BOUND is not supported yet');
            default:
                /**
                 * @see Zend_Db_Adapter_Oracle_Exception
                 */
                #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
                throw new Zend_Db_Adapter_Oracle_Exception("Invalid fetch mode '{$mode}' specified");
        }
    }
    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql
     * @param integer $count
     * @param integer $offset OPTIONAL
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    public function limit($sql, $count, $offset = 0): string
    {
        $count = intval($count);
        if ($count <= 0) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception("LIMIT argument count={$count} is not valid");
        }
        $offset = intval($offset);
        if ($offset < 0) {
            /**
             * @see Zend_Db_Adapter_Oracle_Exception
             */
            #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
            throw new Zend_Db_Adapter_Oracle_Exception("LIMIT argument offset={$offset} is not valid");
        }
        /**
         * Oracle does not implement the LIMIT clause as some RDBMS do.
         * We have to simulate it with subqueries and ROWNUM.
         * Unfortunately because we use the column wildcard "*",
         * this puts an extra column into the query result set.
         */
        $limit_sql = 'SELECT z2.*
            FROM (
                SELECT z1.*, ROWNUM AS "zend_db_rownum"
                FROM (
                    ' . $sql . '
                ) z1
            ) z2
            WHERE z2."zend_db_rownum" BETWEEN ' . ($offset + 1) . ' AND ' . ($offset + $count);
        return $limit_sql;
    }
    /**
     * @throws Zend_Db_Adapter_Oracle_Exception
     */
    private function _set_execute_mode(int $mode)
    {
        switch ($mode) {
            case OCI_COMMIT_ON_SUCCESS:
            case OCI_DEFAULT:
            case OCI_DESCRIBE_ONLY:
                $this->_execute_mode = $mode;
                break;
            default:
                /**
                 * @see Zend_Db_Adapter_Oracle_Exception
                 */
                #require_once 'Zend/Db/Adapter/Oracle/Exception.php';
                throw new Zend_Db_Adapter_Oracle_Exception("Invalid execution mode '{$mode}' specified");
        }
    }
    /**
     * @return int
     */
    public function _get_execute_mode()
    {
        return $this->_execute_mode;
    }
    /**
     * Check if the adapter supports real SQL parameters.
     *
     * @param string $type 'positional' or 'named'
     */
    public function supports_parameters($type): bool
    {
        switch ($type) {
            case 'named':
                return true;
            case 'positional':
            default:
                return false;
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
        $version = oci_server_version($this->_connection);
        if ($version !== false) {
            $matches = null;
            if (preg_match('/((?:[0-9]{1,2}\.){1,3}[0-9]{1,2})/', $version, $matches)) {
                return $matches[1];
            }
            return null;
        }
        return null;
    }
}