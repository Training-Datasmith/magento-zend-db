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
 *
 */
/**
 * @see Zend_Db
 */
#require_once 'Zend/Db.php';
/**
 * @see Zend_Db_Adapter_Abstract
 */
#require_once 'Zend/Db/Adapter/Abstract.php';
/**
 * @see Zend_Db_Statement_Db2
 */
#require_once 'Zend/Db/Statement/Db2.php';
/**
 * @package    Zend_Db
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Adapter_Db2 extends Zend_Db_Adapter_Abstract
{
    /**
     * User-provided configuration.
     *
     * Basic keys are:
     *
     * username   => (string)  Connect to the database as this username.
     * password   => (string)  Password associated with the username.
     * host       => (string)  What host to connect to (default 127.0.0.1)
     * dbname     => (string)  The name of the database to user
     * protocol   => (string)  Protocol to use, defaults to "TCPIP"
     * port       => (integer) Port number to use for TCP/IP if protocol is "TCPIP"
     * persistent => (boolean) Set TRUE to use a persistent connection (db2_pconnect)
     * os         => (string)  This should be set to 'i5' if the db is on an os400/i5
     * schema     => (string)  The default schema the connection should use
     *
     * @var array
     */
    protected $_config = ['dbname' => null, 'username' => null, 'password' => null, 'host' => 'localhost', 'port' => '50000', 'protocol' => 'TCPIP', 'persistent' => false, 'os' => null, 'schema' => null];
    /**
     * Execution mode
     *
     * @var int execution flag (DB2_AUTOCOMMIT_ON or DB2_AUTOCOMMIT_OFF)
     */
    protected $_execute_mode = DB2_AUTOCOMMIT_ON;
    /**
     * Default class name for a DB statement.
     *
     * @var string
     */
    protected $_default_stmt_class = 'Zend_Db_Statement_Db2';
    protected $_is_i5 = false;
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
    protected $_numeric_data_types = [Zend_Db::INT_TYPE => Zend_Db::INT_TYPE, Zend_Db::BIGINT_TYPE => Zend_Db::BIGINT_TYPE, Zend_Db::FLOAT_TYPE => Zend_Db::FLOAT_TYPE, 'INTEGER' => Zend_Db::INT_TYPE, 'SMALLINT' => Zend_Db::INT_TYPE, 'BIGINT' => Zend_Db::BIGINT_TYPE, 'DECIMAL' => Zend_Db::FLOAT_TYPE, 'NUMERIC' => Zend_Db::FLOAT_TYPE];
    /**
     * Creates a connection resource.
     *
     * @return void
     */
    protected function _connect()
    {
        if (is_resource($this->_connection)) {
            // connection already exists
            return;
        }
        if (!extension_loaded('ibm_db2')) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception('The IBM DB2 extension is required for this adapter but the extension is not loaded');
        }
        $this->_determine_i5();
        if ($this->_config['persistent']) {
            // use persistent connection
            $conn_func_name = 'db2_pconnect';
        } else {
            // use "normal" connection
            $conn_func_name = 'db2_connect';
        }
        if (!isset($this->_config['driver_options']['autocommit'])) {
            // set execution mode
            $this->_config['driver_options']['autocommit'] =& $this->_execute_mode;
        }
        if (isset($this->_config['options'][Zend_Db::CASE_FOLDING])) {
            $case_attr_map = [Zend_Db::CASE_NATURAL => DB2_CASE_NATURAL, Zend_Db::CASE_UPPER => DB2_CASE_UPPER, Zend_Db::CASE_LOWER => DB2_CASE_LOWER];
            $this->_config['driver_options']['DB2_ATTR_CASE'] = $case_attr_map[$this->_config['options'][Zend_Db::CASE_FOLDING]];
        }
        if ($this->_is_i5 && isset($this->_config['driver_options']['i5_naming'])) {
            if ($this->_config['driver_options']['i5_naming']) {
                $this->_config['driver_options']['i5_naming'] = DB2_I5_NAMING_ON;
            } else {
                $this->_config['driver_options']['i5_naming'] = DB2_I5_NAMING_OFF;
            }
        }
        if ($this->_config['host'] !== 'localhost' && !$this->_is_i5) {
            // if the host isn't localhost, use extended connection params
            $dbname = 'DRIVER={IBM DB2 ODBC DRIVER}' . ';DATABASE=' . $this->_config['dbname'] . ';HOSTNAME=' . $this->_config['host'] . ';PORT=' . $this->_config['port'] . ';PROTOCOL=' . $this->_config['protocol'] . ';UID=' . $this->_config['username'] . ';PWD=' . $this->_config['password'] . ';';
            $this->_connection = $conn_func_name($dbname, null, null, $this->_config['driver_options']);
        } else {
            // host is localhost, so use standard connection params
            $this->_connection = $conn_func_name($this->_config['dbname'], $this->_config['username'], $this->_config['password'], $this->_config['driver_options']);
        }
        // check the connection
        if (!$this->_connection) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception(db2_conn_errormsg(), db2_conn_error());
        }
    }
    /**
     * Test if a connection is active
     */
    public function is_connected(): bool
    {
        return is_resource($this->_connection) && get_resource_type($this->_connection) == 'DB2 Connection';
    }
    /**
     * Force the connection to close.
     *
     * @return void
     */
    public function close_connection()
    {
        if ($this->is_connected()) {
            db2_close($this->_connection);
        }
        $this->_connection = null;
    }
    /**
     * Returns an SQL statement for preparation.
     *
     * @param string $sql The SQL statement with placeholders.
     * @return Zend_Db_Statement_Db2
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
        $stmt->set_fetch_mode($this->_fetch_mode);
        return $stmt;
    }
    /**
     * Gets the execution mode
     *
     * @return int the execution mode (DB2_AUTOCOMMIT_ON or DB2_AUTOCOMMIT_OFF)
     */
    public function _get_execute_mode()
    {
        return $this->_execute_mode;
    }
    /**
     * @param integer $mode
     * @return void
     */
    public function _set_execute_mode($mode)
    {
        switch ($mode) {
            case DB2_AUTOCOMMIT_OFF:
            case DB2_AUTOCOMMIT_ON:
                $this->_execute_mode = $mode;
                db2_autocommit($this->_connection, $mode);
                break;
            default:
                /**
                 * @see Zend_Db_Adapter_Db2_Exception
                 */
                #require_once 'Zend/Db/Adapter/Db2/Exception.php';
                throw new Zend_Db_Adapter_Db2_Exception('execution mode not supported');
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
        /**
         * Use db2_escape_string() if it is present in the IBM DB2 extension.
         * But some supported versions of PHP do not include this function,
         * so fall back to default quoting in the parent class.
         */
        if (function_exists('db2_escape_string')) {
            return "'" . db2_escape_string($value) . "'";
        }
        return parent::_quote($value);
    }
    /**
     * @return string
     */
    public function get_quote_identifier_symbol()
    {
        $this->_connect();
        $info = db2_server_info($this->_connection);
        if ($info) {
            $ident_quote = $info->IDENTIFIER_QUOTE_CHAR;
        } else if ($this->_is_i5) {
            $ident_quote = "'";
        }
        return $ident_quote;
    }
    /**
     * Returns a list of the tables in the database.
     * @param string $schema OPTIONAL
     * @return array
     */
    public function list_tables($schema = null)
    {
        $this->_connect();
        if ($schema === null && $this->_config['schema'] != null) {
            $schema = $this->_config['schema'];
        }
        $tables = [];
        if (!$this->_is_i5) {
            if ($schema) {
                $stmt = db2_tables($this->_connection, null, $schema);
            } else {
                $stmt = db2_tables($this->_connection);
            }
            while ($row = db2_fetch_assoc($stmt)) {
                $tables[] = $row['TABLE_NAME'];
            }
        } else {
            $tables = $this->_i5list_tables($schema);
        }
        return $tables;
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
     *                     DB2 not supports UNSIGNED integer.
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     */
    public function describe_table($table_name, $schema_name = null): array
    {
        // Ensure the connection is made so that _isI5 is set
        $this->_connect();
        if ($schema_name === null && $this->_config['schema'] != null) {
            $schema_name = $this->_config['schema'];
        }
        if (!$this->_is_i5) {
            $sql = "SELECT DISTINCT c.tabschema, c.tabname, c.colname, c.colno,\n                c.typename, c.default, c.nulls, c.length, c.scale,\n                c.identity, tc.type AS tabconsttype, k.colseq\n                FROM syscat.columns c\n                LEFT JOIN (syscat.keycoluse k JOIN syscat.tabconst tc\n                ON (k.tabschema = tc.tabschema\n                    AND k.tabname = tc.tabname\n                    AND tc.type = 'P'))\n                ON (c.tabschema = k.tabschema\n                    AND c.tabname = k.tabname\n                    AND c.colname = k.colname)\n                WHERE " . $this->quote_into('UPPER(c.tabname) = UPPER(?)', $table_name);
            if ($schema_name) {
                $sql .= $this->quote_into(' AND UPPER(c.tabschema) = UPPER(?)', $schema_name);
            }
            $sql .= ' ORDER BY c.colno';
        } else {
            // DB2 On I5 specific query
            $sql = "SELECT DISTINCT C.TABLE_SCHEMA, C.TABLE_NAME, C.COLUMN_NAME, C.ORDINAL_POSITION,\n                C.DATA_TYPE, C.COLUMN_DEFAULT, C.NULLS ,C.LENGTH, C.SCALE, LEFT(C.IDENTITY,1),\n                LEFT(tc.TYPE, 1) AS tabconsttype, k.COLSEQ\n                FROM QSYS2.SYSCOLUMNS C\n                LEFT JOIN (QSYS2.syskeycst k JOIN QSYS2.SYSCST tc\n                    ON (k.TABLE_SCHEMA = tc.TABLE_SCHEMA\n                      AND k.TABLE_NAME = tc.TABLE_NAME\n                      AND LEFT(tc.type,1) = 'P'))\n                    ON (C.TABLE_SCHEMA = k.TABLE_SCHEMA\n                       AND C.TABLE_NAME = k.TABLE_NAME\n                       AND C.COLUMN_NAME = k.COLUMN_NAME)\n                WHERE " . $this->quote_into('UPPER(C.TABLE_NAME) = UPPER(?)', $table_name);
            if ($schema_name) {
                $sql .= $this->quote_into(' AND UPPER(C.TABLE_SCHEMA) = UPPER(?)', $schema_name);
            }
            $sql .= ' ORDER BY C.ORDINAL_POSITION FOR FETCH ONLY';
        }
        $desc = [];
        $stmt = $this->query($sql);
        /**
         * To avoid case issues, fetch using FETCH_NUM
         */
        $result = $stmt->fetch_all(Zend_Db::FETCH_NUM);
        /**
         * The ordering of columns is defined by the query so we can map
         * to variables to improve readability
         */
        $tabschema = 0;
        $tabname = 1;
        $colname = 2;
        $colno = 3;
        $typename = 4;
        $default = 5;
        $nulls = 6;
        $length = 7;
        $scale = 8;
        $identity_col = 9;
        $tabconst_type = 10;
        $colseq = 11;
        foreach ($result as $row) {
            list($primary, $primary_position, $identity) = [false, null, false];
            if ($row[$tabconst_type] == 'P') {
                $primary = true;
                $primary_position = $row[$colseq];
            }
            /**
             * In IBM DB2, an column can be IDENTITY
             * even if it is not part of the PRIMARY KEY.
             */
            if ($row[$identity_col] == 'Y') {
                $identity = true;
            }
            // only colname needs to be case adjusted
            $desc[$this->fold_case($row[$colname])] = ['SCHEMA_NAME' => $this->fold_case($row[$tabschema]), 'TABLE_NAME' => $this->fold_case($row[$tabname]), 'COLUMN_NAME' => $this->fold_case($row[$colname]), 'COLUMN_POSITION' => !$this->_is_i5 ? $row[$colno] + 1 : $row[$colno], 'DATA_TYPE' => $row[$typename], 'DEFAULT' => $row[$default], 'NULLABLE' => $row[$nulls] == 'Y', 'LENGTH' => $row[$length], 'SCALE' => $row[$scale], 'PRECISION' => $row[$typename] == 'DECIMAL' ? $row[$length] : 0, 'UNSIGNED' => false, 'PRIMARY' => $primary, 'PRIMARY_POSITION' => $primary_position, 'IDENTITY' => $identity];
        }
        return $desc;
    }
    /**
     * Return the most recent value from the specified sequence in the database.
     * This is supported only on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2).  Other RDBMS brands return null.
     *
     * @param string $sequenceName
     */
    public function last_sequence_id($sequence_name): string
    {
        $this->_connect();
        if (!$this->_is_i5) {
            $quoted_sequence_name = $this->quote_identifier($sequence_name, true);
            $sql = 'SELECT PREVVAL FOR ' . $quoted_sequence_name . ' AS VAL FROM SYSIBM.SYSDUMMY1';
        } else {
            $quoted_sequence_name = $sequence_name;
            $sql = 'SELECT PREVVAL FOR ' . $this->quote_identifier($sequence_name, true) . ' AS VAL FROM QSYS2.QSQPTABL';
        }
        $value = $this->fetch_one($sql);
        return (string) $value;
    }
    /**
     * Generate a new value from the specified sequence in the database, and return it.
     * This is supported only on RDBMS brands that support sequences
     * (e.g. Oracle, PostgreSQL, DB2).  Other RDBMS brands return null.
     *
     * @param string $sequenceName
     */
    public function next_sequence_id($sequence_name): string
    {
        $this->_connect();
        $sql = 'SELECT NEXTVAL FOR ' . $this->quote_identifier($sequence_name, true) . ' AS VAL FROM SYSIBM.SYSDUMMY1';
        $value = $this->fetch_one($sql);
        return (string) $value;
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
     * The IDENTITY_VAL_LOCAL() function gives the last generated identity value
     * in the current process, even if it was for a GENERATED column.
     *
     * @param string $tableName OPTIONAL
     * @param string $primaryKey OPTIONAL
     * @param string $idType OPTIONAL used for i5 platform to define sequence/idenity unique value
     * @return string
     */
    public function last_insert_id($table_name = null, $primary_key = null, $id_type = null)
    {
        $this->_connect();
        if ($this->_is_i5) {
            return (string) $this->_i5last_insert_id($table_name, $id_type);
        }
        if ($table_name !== null) {
            $sequence_name = $table_name;
            if ($primary_key) {
                $sequence_name .= "_{$primary_key}";
            }
            $sequence_name .= '_seq';
            return $this->last_sequence_id($sequence_name);
        }
        $sql = 'SELECT IDENTITY_VAL_LOCAL() AS VAL FROM SYSIBM.SYSDUMMY1';
        $value = $this->fetch_one($sql);
        return (string) $value;
    }
    /**
     * Begin a transaction.
     *
     * @return void
     */
    protected function _begin_transaction()
    {
        $this->_set_execute_mode(DB2_AUTOCOMMIT_OFF);
    }
    /**
     * Commit a transaction.
     *
     * @return void
     */
    protected function _commit()
    {
        if (!db2_commit($this->_connection)) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception(db2_conn_errormsg($this->_connection), db2_conn_error($this->_connection));
        }
        $this->_set_execute_mode(DB2_AUTOCOMMIT_ON);
    }
    /**
     * Rollback a transaction.
     *
     * @return void
     */
    protected function _roll_back()
    {
        if (!db2_rollback($this->_connection)) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception(db2_conn_errormsg($this->_connection), db2_conn_error($this->_connection));
        }
        $this->_set_execute_mode(DB2_AUTOCOMMIT_ON);
    }
    /**
     * Set the fetch mode.
     *
     * @param integer $mode
     * @return void
     * @throws Zend_Db_Adapter_Db2_Exception
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
                 * @see Zend_Db_Adapter_Db2_Exception
                 */
                #require_once 'Zend/Db/Adapter/Db2/Exception.php';
                throw new Zend_Db_Adapter_Db2_Exception('FETCH_BOUND is not supported yet');
            default:
                /**
                 * @see Zend_Db_Adapter_Db2_Exception
                 */
                #require_once 'Zend/Db/Adapter/Db2/Exception.php';
                throw new Zend_Db_Adapter_Db2_Exception("Invalid fetch mode '{$mode}' specified");
        }
    }
    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql
     * @param integer $count
     * @param integer $offset OPTIONAL
     */
    public function limit($sql, $count, $offset = 0): string
    {
        $count = intval($count);
        if ($count <= 0) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception("LIMIT argument count={$count} is not valid");
        }
        $offset = intval($offset);
        if ($offset < 0) {
            /**
             * @see Zend_Db_Adapter_Db2_Exception
             */
            #require_once 'Zend/Db/Adapter/Db2/Exception.php';
            throw new Zend_Db_Adapter_Db2_Exception("LIMIT argument offset={$offset} is not valid");
        }
        if ($offset == 0) {
            return $sql . " FETCH FIRST {$count} ROWS ONLY";
        }
        /**
         * DB2 does not implement the LIMIT clause as some RDBMS do.
         * We have to simulate it with subqueries and ROWNUM.
         * Unfortunately because we use the column wildcard "*",
         * this puts an extra column into the query result set.
         */
        $limit_sql = 'SELECT z2.*
            FROM (
                SELECT ROW_NUMBER() OVER() AS "ZEND_DB_ROWNUM", z1.*
                FROM (
                    ' . $sql . '
                ) z1
            ) z2
            WHERE z2.zend_db_rownum BETWEEN ' . ($offset + 1) . ' AND ' . ($offset + $count);
        return $limit_sql;
    }
    /**
     * Check if the adapter supports real SQL parameters.
     *
     * @param string $type 'positional' or 'named'
     */
    public function supports_parameters($type): bool
    {
        if ($type == 'positional') {
            return true;
        }
        // if its 'named' or anything else
        return false;
    }
    /**
     * Retrieve server version in PHP style
     *
     * @return string
     */
    public function get_server_version()
    {
        $this->_connect();
        $server_info = db2_server_info($this->_connection);
        if ($server_info !== false) {
            $version = $server_info->DBMS_VER;
            if ($this->_is_i5) {
                return (int) substr($version, 0, 2) . '.' . (int) substr($version, 2, 2) . '.' . (int) substr($version, 4);
            }
            return $version;
        }
        return null;
    }
    /**
     * Return whether or not this is running on i5
     */
    public function is_i5(): bool
    {
        if ($this->_is_i5 === null) {
            $this->_determine_i5();
        }
        return (bool) $this->_is_i5;
    }
    /**
     * Check the connection parameters according to verify
     * type of used OS
     *
     *  @return void
     */
    protected function _determine_i5()
    {
        // first us the compiled flag.
        $this->_is_i5 = php_uname('s') == 'OS400' ? true : false;
        // if this is set, then us it
        if (isset($this->_config['os'])) {
            if (strtolower($this->_config['os']) === 'i5') {
                $this->_is_i5 = true;
            } else {
                // any other value passed in, its null
                $this->_is_i5 = false;
            }
        }
    }
    /**
     * Db2 On I5 specific method
     *
     * Returns a list of the tables in the database .
     * Used only for DB2/400.
     */
    protected function _i5list_tables($schema = null): array
    {
        //list of i5 libraries.
        $tables = [];
        if ($schema) {
            $tables_statement = db2_tables($this->_connection, null, $schema);
            while ($row_tables = db2_fetch_assoc($tables_statement)) {
                if ($row_tables['TABLE_NAME'] !== null) {
                    $tables[] = $row_tables['TABLE_NAME'];
                }
            }
        } else {
            $schema_statement = db2_tables($this->_connection);
            while ($schema = db2_fetch_assoc($schema_statement)) {
                if ($schema['TABLE_SCHEM'] !== null) {
                    // list of the tables which belongs to the selected library
                    $tables_statement = db2_tables($this->_connection, null, $schema['TABLE_SCHEM']);
                    if (is_resource($tables_statement)) {
                        while ($row_tables = db2_fetch_assoc($tables_statement)) {
                            if ($row_tables['TABLE_NAME'] !== null) {
                                $tables[] = $row_tables['TABLE_NAME'];
                            }
                        }
                    }
                }
            }
        }
        return $tables;
    }
    protected function _i5last_insert_id($object_name = null, $id_type = null)
    {
        if ($object_name === null) {
            $sql = 'SELECT IDENTITY_VAL_LOCAL() AS VAL FROM QSYS2.QSQPTABL';
            return $this->fetch_one($sql);
        }
        if (strtoupper($id_type) === 'S') {
            //check i5_lib option
            $sequence_name = $object_name;
            return $this->last_sequence_id($sequence_name);
        }
        //returns last identity value for the specified table
        //if (strtoupper($idType) === 'I') {
        $table_name = $object_name;
        return $this->fetch_one('SELECT IDENTITY_VAL_LOCAL() from ' . $this->quote_identifier($table_name));
    }
}