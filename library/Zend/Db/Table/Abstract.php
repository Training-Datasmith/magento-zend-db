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
 * @see Zend_Db_Adapter_Abstract
 */
#require_once 'Zend/Db/Adapter/Abstract.php';
/**
 * @see Zend_Db_Adapter_Abstract
 */
#require_once 'Zend/Db/Select.php';
/**
 * @see Zend_Db
 */
#require_once 'Zend/Db.php';
/**
 * Class for SQL table interface.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Table
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Db_Table_Abstract
{
    public const ADAPTER = 'db';
    public const DEFINITION = 'definition';
    public const DEFINITION_CONFIG_NAME = 'definitionConfigName';
    public const SCHEMA = 'schema';
    public const NAME = 'name';
    public const PRIMARY = 'primary';
    public const COLS = 'cols';
    public const METADATA = 'metadata';
    public const METADATA_CACHE = 'metadataCache';
    public const METADATA_CACHE_IN_CLASS = 'metadataCacheInClass';
    public const ROW_CLASS = 'rowClass';
    public const ROWSET_CLASS = 'rowsetClass';
    public const REFERENCE_MAP = 'referenceMap';
    public const DEPENDENT_TABLES = 'dependentTables';
    public const SEQUENCE = 'sequence';
    public const COLUMNS = 'columns';
    public const REF_TABLE_CLASS = 'refTableClass';
    public const REF_COLUMNS = 'refColumns';
    public const ON_DELETE = 'onDelete';
    public const ON_UPDATE = 'onUpdate';
    public const CASCADE = 'cascade';
    public const CASCADE_RECURSE = 'cascadeRecurse';
    public const RESTRICT = 'restrict';
    public const SET_NULL = 'setNull';
    public const DEFAULT_NONE = 'defaultNone';
    public const DEFAULT_CLASS = 'defaultClass';
    public const DEFAULT_DB = 'defaultDb';
    public const SELECT_WITH_FROM_PART = true;
    public const SELECT_WITHOUT_FROM_PART = false;
    /**
     * Default Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected static $_default_db;
    /**
     * Optional Zend_Db_Table_Definition object
     *
     * @var unknown_type
     */
    protected $_definition;
    /**
     * Optional definition config name used in concrete implementation
     *
     * @var string
     */
    protected $_definition_config_name;
    /**
     * Default cache for information provided by the adapter's describeTable() method.
     *
     * @var Zend_Cache_Core
     */
    protected static $_default_metadata_cache;
    /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;
    /**
     * The schema name (default null means current schema)
     *
     * @var array
     */
    protected $_schema;
    /**
     * The table name.
     *
     * @var string
     */
    protected $_name;
    /**
     * The table column names derived from Zend_Db_Adapter_Abstract::describeTable().
     *
     * @var array
     */
    protected $_cols;
    /**
     * The primary key column or columns.
     * A compound key should be declared as an array.
     * You may declare a single-column primary key
     * as a string.
     *
     * @var mixed
     */
    protected $_primary;
    /**
     * If your primary key is a compound key, and one of the columns uses
     * an auto-increment or sequence-generated value, set _identity
     * to the ordinal index in the $_primary array for that column.
     * Note this index is the position of the column in the primary key,
     * not the position of the column in the table.  The primary key
     * array is 1-based.
     *
     * @var integer
     */
    protected $_identity = 1;
    /**
     * Define the logic for new values in the primary key.
     * May be a string, boolean true, or boolean false.
     *
     * @var mixed
     */
    protected $_sequence = true;
    /**
     * Information provided by the adapter's describeTable() method.
     *
     * @var array
     */
    protected $_metadata = [];
    /**
     * Cache for information provided by the adapter's describeTable() method.
     *
     * @var Zend_Cache_Core
     */
    protected $_metadata_cache;
    /**
     * Flag: whether or not to cache metadata in the class
     * @var bool
     */
    protected $_metadata_cache_in_class = true;
    /**
     * Classname for row
     *
     * @var string
     */
    protected $_row_class = 'Zend_Db_Table_Row';
    /**
     * Classname for rowset
     *
     * @var string
     */
    protected $_rowset_class = 'Zend_Db_Table_Rowset';
    /**
     * Associative array map of declarative referential integrity rules.
     * This array has one entry per foreign key in the current table.
     * Each key is a mnemonic name for one reference rule.
     *
     * Each value is also an associative array, with the following keys:
     * - columns       = array of names of column(s) in the child table.
     * - refTableClass = class name of the parent table.
     * - refColumns    = array of names of column(s) in the parent table,
     *                   in the same order as those in the 'columns' entry.
     * - onDelete      = "cascade" means that a delete in the parent table also
     *                   causes a delete of referencing rows in the child table.
     * - onUpdate      = "cascade" means that an update of primary key values in
     *                   the parent table also causes an update of referencing
     *                   rows in the child table.
     *
     * @var array
     */
    protected $_reference_map = [];
    /**
     * Simple array of class names of tables that are "children" of the current
     * table, in other words tables that contain a foreign key to this one.
     * Array elements are not table names; they are class names of classes that
     * extend Zend_Db_Table_Abstract.
     *
     * @var array
     */
    protected $_dependent_tables = [];
    protected $_default_source = self::DEFAULT_NONE;
    protected $_default_values = [];
    /**
     * Constructor.
     *
     * Supported params for $config are:
     * - db              = user-supplied instance of database connector,
     *                     or key name of registry instance.
     * - name            = table name.
     * - primary         = string or array of primary key(s).
     * - rowClass        = row class name.
     * - rowsetClass     = rowset class name.
     * - referenceMap    = array structure to declare relationship
     *                     to parent tables.
     * - dependentTables = array of child tables.
     * - metadataCache   = cache for information from adapter describeTable().
     *
     * @param  mixed $config Array of user-specified config options, or just the Db Adapter.
     */
    public function __construct($config = [])
    {
        /**
         * Allow a scalar argument to be the Adapter object or Registry key.
         */
        if (!is_array($config)) {
            $config = [self::ADAPTER => $config];
        }
        if ($config) {
            $this->set_options($config);
        }
        $this->_setup();
        $this->init();
    }
    /**
     * setOptions()
     *
     * @return Zend_Db_Table_Abstract
     */
    public function set_options(array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case self::ADAPTER:
                    $this->_set_adapter($value);
                    break;
                case self::DEFINITION:
                    $this->set_definition($value);
                    break;
                case self::DEFINITION_CONFIG_NAME:
                    $this->set_definition_config_name($value);
                    break;
                case self::SCHEMA:
                    $this->_schema = (string) $value;
                    break;
                case self::NAME:
                    $this->_name = (string) $value;
                    break;
                case self::PRIMARY:
                    $this->_primary = (array) $value;
                    break;
                case self::ROW_CLASS:
                    $this->set_row_class($value);
                    break;
                case self::ROWSET_CLASS:
                    $this->set_rowset_class($value);
                    break;
                case self::REFERENCE_MAP:
                    $this->set_references($value);
                    break;
                case self::DEPENDENT_TABLES:
                    $this->set_dependent_tables($value);
                    break;
                case self::METADATA_CACHE:
                    $this->_set_metadata_cache($value);
                    break;
                case self::METADATA_CACHE_IN_CLASS:
                    $this->set_metadata_cache_in_class($value);
                    break;
                case self::SEQUENCE:
                    $this->_set_sequence($value);
                    break;
                default:
                    // ignore unrecognized configuration directive
                    break;
            }
        }
        return $this;
    }
    /**
     * setDefinition()
     *
     * @return Zend_Db_Table_Abstract
     */
    public function set_definition(Zend_Db_Table_Definition $definition)
    {
        $this->_definition = $definition;
        return $this;
    }
    /**
     * getDefinition()
     *
     * @return Zend_Db_Table_Definition|null
     */
    public function get_definition()
    {
        return $this->_definition;
    }
    /**
     * setDefinitionConfigName()
     *
     * @param string $definition
     * @return Zend_Db_Table_Abstract
     */
    public function set_definition_config_name($definition_config_name)
    {
        $this->_definition_config_name = $definition_config_name;
        return $this;
    }
    /**
     * getDefinitionConfigName()
     *
     * @return string
     */
    public function get_definition_config_name()
    {
        return $this->_definition_config_name;
    }
    /**
     * @param  string $classname
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    public function set_row_class($classname)
    {
        $this->_row_class = (string) $classname;
        return $this;
    }
    /**
     * @return string
     */
    public function get_row_class()
    {
        return $this->_row_class;
    }
    /**
     * @param  string $classname
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    public function set_rowset_class($classname)
    {
        $this->_rowset_class = (string) $classname;
        return $this;
    }
    /**
     * @return string
     */
    public function get_rowset_class()
    {
        return $this->_rowset_class;
    }
    /**
     * Add a reference to the reference map
     *
     * @param string $ruleKey
     * @param string|array $columns
     * @param string $refTableClass
     * @param string|array $refColumns
     * @param string $onDelete
     * @param string $onUpdate
     * @return Zend_Db_Table_Abstract
     */
    public function add_reference($rule_key, $columns, $ref_table_class, $ref_columns, $on_delete = null, $on_update = null)
    {
        $reference = [self::COLUMNS => (array) $columns, self::REF_TABLE_CLASS => $ref_table_class, self::REF_COLUMNS => (array) $ref_columns];
        if (!empty($on_delete)) {
            $reference[self::ON_DELETE] = $on_delete;
        }
        if (!empty($on_update)) {
            $reference[self::ON_UPDATE] = $on_update;
        }
        $this->_reference_map[$rule_key] = $reference;
        return $this;
    }
    /**
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    public function set_references(array $reference_map)
    {
        $this->_reference_map = $reference_map;
        return $this;
    }
    /**
     * @param string $tableClassname
     * @param string $ruleKey OPTIONAL
     * @return array
     * @throws Zend_Db_Table_Exception
     */
    public function get_reference($table_classname, $rule_key = null)
    {
        $this_class = get_class($this);
        if ($this_class === 'Zend_Db_Table') {
            $this_class = $this->_definition_config_name;
        }
        $ref_map = $this->_get_reference_map_normalized();
        if ($rule_key !== null) {
            if (!isset($ref_map[$rule_key])) {
                #require_once "Zend/Db/Table/Exception.php";
                throw new Zend_Db_Table_Exception("No reference rule \"{$rule_key}\" from table {$this_class} to table {$table_classname}");
            }
            if ($ref_map[$rule_key][self::REF_TABLE_CLASS] != $table_classname) {
                #require_once "Zend/Db/Table/Exception.php";
                throw new Zend_Db_Table_Exception("Reference rule \"{$rule_key}\" does not reference table {$table_classname}");
            }
            return $ref_map[$rule_key];
        }
        foreach ($ref_map as $reference) {
            if ($reference[self::REF_TABLE_CLASS] == $table_classname) {
                return $reference;
            }
        }
        #require_once "Zend/Db/Table/Exception.php";
        throw new Zend_Db_Table_Exception("No reference from table {$this_class} to table {$table_classname}");
    }
    /**
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    public function set_dependent_tables(array $dependent_tables)
    {
        $this->_dependent_tables = $dependent_tables;
        return $this;
    }
    /**
     * @return array
     */
    public function get_dependent_tables()
    {
        return $this->_dependent_tables;
    }
    /**
     * set the defaultSource property - this tells the table class where to find default values
     *
     * @param string $defaultSource
     * @return Zend_Db_Table_Abstract
     */
    public function set_default_source($default_source = self::DEFAULT_NONE)
    {
        if (!in_array($default_source, [self::DEFAULT_CLASS, self::DEFAULT_DB, self::DEFAULT_NONE])) {
            $default_source = self::DEFAULT_NONE;
        }
        $this->_default_source = $default_source;
        return $this;
    }
    /**
     * returns the default source flag that determines where defaultSources come from
     *
     * @return unknown
     */
    public function get_default_source()
    {
        return $this->_default_source;
    }
    /**
     * set the default values for the table class
     *
     * @return Zend_Db_Table_Abstract
     */
    public function set_default_values(array $default_values)
    {
        foreach ($default_values as $default_name => $default_value) {
            if (array_key_exists($default_name, $this->_metadata)) {
                $this->_default_values[$default_name] = $default_value;
            }
        }
        return $this;
    }
    public function get_default_values()
    {
        return $this->_default_values;
    }
    /**
     * Sets the default Zend_Db_Adapter_Abstract for all Zend_Db_Table objects.
     *
     * @param  mixed $db Either an Adapter object, or a string naming a Registry key
     * @return void
     */
    public static function set_default_adapter($db = null)
    {
        self::$_default_db = self::_setup_adapter($db);
    }
    /**
     * Gets the default Zend_Db_Adapter_Abstract for all Zend_Db_Table objects.
     *
     * @return Zend_Db_Adapter_Abstract or null
     */
    public static function get_default_adapter()
    {
        return self::$_default_db;
    }
    /**
     * @param  mixed $db Either an Adapter object, or a string naming a Registry key
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    protected function _set_adapter($db)
    {
        $this->_db = self::_setup_adapter($db);
        return $this;
    }
    /**
     * Gets the Zend_Db_Adapter_Abstract for this particular Zend_Db_Table object.
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function get_adapter()
    {
        return $this->_db;
    }
    /**
     * @param  mixed $db Either an Adapter object, or a string naming a Registry key
     * @return Zend_Db_Adapter_Abstract
     * @throws Zend_Db_Table_Exception
     */
    protected static function _setup_adapter($db)
    {
        if ($db === null) {
            return null;
        }
        if (is_string($db)) {
            #require_once 'Zend/Registry.php';
            $db = Zend_Registry::get($db);
        }
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Argument must be of type Zend_Db_Adapter_Abstract, or a Registry key where a Zend_Db_Adapter_Abstract object is stored');
        }
        return $db;
    }
    /**
     * Sets the default metadata cache for information returned by Zend_Db_Adapter_Abstract::describeTable().
     *
     * If $defaultMetadataCache is null, then no metadata cache is used by default.
     *
     * @param  mixed $metadataCache Either a Cache object, or a string naming a Registry key
     * @return void
     */
    public static function set_default_metadata_cache($metadata_cache = null)
    {
        self::$_default_metadata_cache = self::_setup_metadata_cache($metadata_cache);
    }
    /**
     * Gets the default metadata cache for information returned by Zend_Db_Adapter_Abstract::describeTable().
     *
     * @return Zend_Cache_Core or null
     */
    public static function get_default_metadata_cache()
    {
        return self::$_default_metadata_cache;
    }
    /**
     * Sets the metadata cache for information returned by Zend_Db_Adapter_Abstract::describeTable().
     *
     * If $metadataCache is null, then no metadata cache is used. Since there is no opportunity to reload metadata
     * after instantiation, this method need not be public, particularly because that it would have no effect
     * results in unnecessary API complexity. To configure the metadata cache, use the metadataCache configuration
     * option for the class constructor upon instantiation.
     *
     * @param  mixed $metadataCache Either a Cache object, or a string naming a Registry key
     * @return Zend_Db_Table_Abstract Provides a fluent interface
     */
    protected function _set_metadata_cache($metadata_cache)
    {
        $this->_metadata_cache = self::_setup_metadata_cache($metadata_cache);
        return $this;
    }
    /**
     * Gets the metadata cache for information returned by Zend_Db_Adapter_Abstract::describeTable().
     *
     * @return Zend_Cache_Core or null
     */
    public function get_metadata_cache()
    {
        return $this->_metadata_cache;
    }
    /**
     * Indicate whether metadata should be cached in the class for the duration
     * of the instance
     *
     * @param  bool $flag
     * @return Zend_Db_Table_Abstract
     */
    public function set_metadata_cache_in_class($flag)
    {
        $this->_metadata_cache_in_class = (bool) $flag;
        return $this;
    }
    /**
     * Retrieve flag indicating if metadata should be cached for duration of
     * instance
     *
     * @return bool
     */
    public function metadata_cache_in_class()
    {
        return $this->_metadata_cache_in_class;
    }
    /**
     * @param mixed $metadataCache Either a Cache object, or a string naming a Registry key
     * @return Zend_Cache_Core
     * @throws Zend_Db_Table_Exception
     */
    protected static function _setup_metadata_cache($metadata_cache)
    {
        if ($metadata_cache === null) {
            return null;
        }
        if (is_string($metadata_cache)) {
            #require_once 'Zend/Registry.php';
            $metadata_cache = Zend_Registry::get($metadata_cache);
        }
        if (!$metadata_cache instanceof Zend_Cache_Core) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Argument must be of type Zend_Cache_Core, or a Registry key where a Zend_Cache_Core object is stored');
        }
        return $metadata_cache;
    }
    /**
     * Sets the sequence member, which defines the behavior for generating
     * primary key values in new rows.
     * - If this is a string, then the string names the sequence object.
     * - If this is boolean true, then the key uses an auto-incrementing
     *   or identity mechanism.
     * - If this is boolean false, then the key is user-defined.
     *   Use this for natural keys, for example.
     *
     * @param mixed $sequence
     * @return Zend_Db_Table_Adapter_Abstract Provides a fluent interface
     */
    protected function _set_sequence($sequence)
    {
        $this->_sequence = $sequence;
        return $this;
    }
    /**
     * Turnkey for initialization of a table object.
     * Calls other protected methods for individual tasks, to make it easier
     * for a subclass to override part of the setup logic.
     *
     * @return void
     */
    protected function _setup()
    {
        $this->_setup_database_adapter();
        $this->_setup_table_name();
    }
    /**
     * Initialize database adapter.
     *
     * @return void
     * @throws Zend_Db_Table_Exception
     */
    protected function _setup_database_adapter()
    {
        if (!$this->_db) {
            $this->_db = self::get_default_adapter();
            if (!$this->_db instanceof Zend_Db_Adapter_Abstract) {
                #require_once 'Zend/Db/Table/Exception.php';
                throw new Zend_Db_Table_Exception('No adapter found for ' . get_class($this));
            }
        }
    }
    /**
     * Initialize table and schema names.
     *
     * If the table name is not set in the class definition,
     * use the class name itself as the table name.
     *
     * A schema name provided with the table name (e.g., "schema.table") overrides
     * any existing value for $this->_schema.
     *
     * @return void
     */
    protected function _setup_table_name()
    {
        if (!$this->_name) {
            $this->_name = get_class($this);
        } elseif (strpos($this->_name, '.')) {
            list($this->_schema, $this->_name) = explode('.', $this->_name);
        }
    }
    /**
     * Initializes metadata.
     *
     * If metadata cannot be loaded from cache, adapter's describeTable() method is called to discover metadata
     * information. Returns true if and only if the metadata are loaded from cache.
     *
     * @return boolean
     * @throws Zend_Db_Table_Exception
     */
    protected function _setup_metadata()
    {
        if ($this->metadata_cache_in_class() && count($this->_metadata) > 0) {
            return true;
        }
        // Assume that metadata will be loaded from cache
        $is_metadata_from_cache = true;
        // If $this has no metadata cache but the class has a default metadata cache
        if (null === $this->_metadata_cache && null !== self::$_default_metadata_cache) {
            // Make $this use the default metadata cache of the class
            $this->_set_metadata_cache(self::$_default_metadata_cache);
        }
        // If $this has a metadata cache
        if (null !== $this->_metadata_cache) {
            // Define the cache identifier where the metadata are saved
            //get db configuration
            $db_config = $this->_db->get_config();
            $port = isset($db_config['options']['port']) ? ':' . $db_config['options']['port'] : (isset($db_config['port']) ? ':' . $db_config['port'] : null);
            $host = isset($db_config['options']['host']) ? ':' . $db_config['options']['host'] : (isset($db_config['host']) ? ':' . $db_config['host'] : null);
            // Define the cache identifier where the metadata are saved
            $cache_id = md5(
                // port:host/dbname:schema.table (based on availabilty)
                $port . $host . '/' . $db_config['dbname'] . ':' . $this->_schema . '.' . $this->_name
            );
        }
        // If $this has no metadata cache or metadata cache misses
        if (null === $this->_metadata_cache || !$metadata = $this->_metadata_cache->load($cache_id)) {
            // Metadata are not loaded from cache
            $is_metadata_from_cache = false;
            // Fetch metadata from the adapter's describeTable() method
            $metadata = $this->_db->describe_table($this->_name, $this->_schema);
            // If $this has a metadata cache, then cache the metadata
            if (null !== $this->_metadata_cache && !$this->_metadata_cache->save($metadata, $cache_id)) {
                trigger_error('Failed saving metadata to metadataCache', E_USER_NOTICE);
            }
        }
        // Assign the metadata to $this
        $this->_metadata = $metadata;
        // Return whether the metadata were loaded from cache
        return $is_metadata_from_cache;
    }
    /**
     * Retrieve table columns
     *
     * @return array
     */
    protected function _get_cols()
    {
        if (null === $this->_cols) {
            $this->_setup_metadata();
            $this->_cols = array_keys($this->_metadata);
        }
        return $this->_cols;
    }
    /**
     * Initialize primary key from metadata.
     * If $_primary is not defined, discover primary keys
     * from the information returned by describeTable().
     *
     * @return void
     * @throws Zend_Db_Table_Exception
     */
    protected function _setup_primary_key()
    {
        if (!$this->_primary) {
            $this->_setup_metadata();
            $this->_primary = [];
            foreach ($this->_metadata as $col) {
                if ($col['PRIMARY']) {
                    $this->_primary[$col['PRIMARY_POSITION']] = $col['COLUMN_NAME'];
                    if ($col['IDENTITY']) {
                        $this->_identity = $col['PRIMARY_POSITION'];
                    }
                }
            }
            // if no primary key was specified and none was found in the metadata
            // then throw an exception.
            if (empty($this->_primary)) {
                #require_once 'Zend/Db/Table/Exception.php';
                throw new Zend_Db_Table_Exception("A table must have a primary key, but none was found for table '{$this->_name}'");
            }
        } elseif (!is_array($this->_primary)) {
            $this->_primary = [1 => $this->_primary];
        } elseif (isset($this->_primary[0])) {
            array_unshift($this->_primary, null);
            unset($this->_primary[0]);
        }
        $cols = $this->_get_cols();
        if (!array_intersect((array) $this->_primary, $cols) == (array) $this->_primary) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Primary key column(s) (' . implode(',', (array) $this->_primary) . ') are not columns in this table (' . implode(',', $cols) . ')');
        }
        $primary = (array) $this->_primary;
        $pk_identity = $primary[(int) $this->_identity];
        /**
         * Special case for PostgreSQL: a SERIAL key implicitly uses a sequence
         * object whose name is "<table>_<column>_seq".
         */
        if ($this->_sequence === true && $this->_db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
            $this->_sequence = $this->_db->quote_identifier("{$this->_name}_{$pk_identity}_seq");
            if ($this->_schema) {
                $this->_sequence = $this->_db->quote_identifier($this->_schema) . '.' . $this->_sequence;
            }
        }
    }
    /**
     * Returns a normalized version of the reference map
     *
     * @return array
     */
    protected function _get_reference_map_normalized()
    {
        $reference_map_normalized = [];
        foreach ($this->_reference_map as $rule => $map) {
            $reference_map_normalized[$rule] = [];
            foreach ($map as $key => $value) {
                switch ($key) {
                    // normalize COLUMNS and REF_COLUMNS to arrays
                    case self::COLUMNS:
                    case self::REF_COLUMNS:
                        if (!is_array($value)) {
                            $reference_map_normalized[$rule][$key] = [$value];
                        } else {
                            $reference_map_normalized[$rule][$key] = $value;
                        }
                        break;
                    // other values are copied as-is
                    default:
                        $reference_map_normalized[$rule][$key] = $value;
                        break;
                }
            }
        }
        return $reference_map_normalized;
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
     * Returns table information.
     *
     * You can elect to return only a part of this information by supplying its key name,
     * otherwise all information is returned as an array.
     *
     * @param  string $key The specific info part to return OPTIONAL
     * @return mixed
     * @throws Zend_Db_Table_Exception
     */
    public function info($key = null)
    {
        $this->_setup_primary_key();
        $info = [self::SCHEMA => $this->_schema, self::NAME => $this->_name, self::COLS => $this->_get_cols(), self::PRIMARY => (array) $this->_primary, self::METADATA => $this->_metadata, self::ROW_CLASS => $this->get_row_class(), self::ROWSET_CLASS => $this->get_rowset_class(), self::REFERENCE_MAP => $this->_reference_map, self::DEPENDENT_TABLES => $this->_dependent_tables, self::SEQUENCE => $this->_sequence];
        if ($key === null) {
            return $info;
        }
        if (!array_key_exists($key, $info)) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('There is no table information for the key "' . $key . '"');
        }
        return $info[$key];
    }
    /**
     * Returns an instance of a Zend_Db_Table_Select object.
     *
     * @param bool $withFromPart Whether or not to include the from part of the select based on the table
     * @return Zend_Db_Table_Select
     */
    public function select($with_from_part = self::SELECT_WITHOUT_FROM_PART)
    {
        #require_once 'Zend/Db/Table/Select.php';
        $select = new Zend_Db_Table_Select($this);
        if ($with_from_part == self::SELECT_WITH_FROM_PART) {
            $select->from($this->info(self::NAME), Zend_Db_Table_Select::SQL_WILDCARD, $this->info(self::SCHEMA));
        }
        return $select;
    }
    /**
     * Inserts a new row.
     *
     * @param  array  $data  Column-value pairs.
     * @return mixed         The primary key of the row inserted.
     */
    public function insert(array $data)
    {
        $this->_setup_primary_key();
        /**
         * Zend_Db_Table assumes that if you have a compound primary key
         * and one of the columns in the key uses a sequence,
         * it's the _first_ column in the compound key.
         */
        $primary = (array) $this->_primary;
        $pk_identity = $primary[(int) $this->_identity];
        /**
         * If the primary key can be generated automatically, and no value was
         * specified in the user-supplied data, then omit it from the tuple.
         *
         * Note: this checks for sensible values in the supplied primary key
         * position of the data.  The following values are considered empty:
         *   null, false, true, '', array()
         */
        if (array_key_exists($pk_identity, $data)) {
            if ($data[$pk_identity] === null || $data[$pk_identity] === '' || is_bool($data[$pk_identity]) || is_array($data[$pk_identity]) && empty($data[$pk_identity])) {
                // empty array
                unset($data[$pk_identity]);
            }
        }
        /**
         * If this table uses a database sequence object and the data does not
         * specify a value, then get the next ID from the sequence and add it
         * to the row.  We assume that only the first column in a compound
         * primary key takes a value from a sequence.
         */
        if (is_string($this->_sequence) && !isset($data[$pk_identity])) {
            $data[$pk_identity] = $this->_db->next_sequence_id($this->_sequence);
        }
        /**
         * INSERT the new row.
         */
        $table_spec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        $this->_db->insert($table_spec, $data);
        /**
         * Fetch the most recent ID generated by an auto-increment
         * or IDENTITY column, unless the user has specified a value,
         * overriding the auto-increment mechanism.
         */
        if ($this->_sequence === true && !isset($data[$pk_identity])) {
            $data[$pk_identity] = $this->_db->last_insert_id();
        }
        /**
         * Return the primary key value if the PK is a single column,
         * else return an associative array of the PK column/value pairs.
         */
        $pk_data = array_intersect_key($data, array_flip($primary));
        if (count($primary) == 1) {
            reset($pk_data);
            return current($pk_data);
        }
        return $pk_data;
    }
    /**
     * Check if the provided column is an identity of the table
     *
     * @throws Zend_Db_Table_Exception
     * @return boolean
     */
    public function is_identity(string $column)
    {
        $this->_setup_primary_key();
        if (!isset($this->_metadata[$column])) {
            /**
             * @see Zend_Db_Table_Exception
             */
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Column "' . $column . '" not found in table.');
        }
        return (bool) $this->_metadata[$column]['IDENTITY'];
    }
    /**
     * Updates existing rows.
     *
     * @param  array        $data  Column-value pairs.
     * @param  array|string $where An SQL WHERE clause, or an array of SQL WHERE clauses.
     * @return int          The number of rows updated.
     */
    public function update(array $data, $where)
    {
        $table_spec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        return $this->_db->update($table_spec, $data, $where);
    }
    /**
     * Called by a row object for the parent table's class during save() method.
     *
     * @param  string $parentTableClassname
     * @return int
     */
    public function _cascade_update($parent_table_classname, array $old_primary_key, array $new_primary_key)
    {
        $this->_setup_metadata();
        $rows_affected = 0;
        foreach ($this->_get_reference_map_normalized() as $map) {
            if ($map[self::REF_TABLE_CLASS] == $parent_table_classname && isset($map[self::ON_UPDATE])) {
                switch ($map[self::ON_UPDATE]) {
                    case self::CASCADE:
                        $new_refs = [];
                        $where = [];
                        for ($i = 0; $i < count($map[self::COLUMNS]); ++$i) {
                            $col = $this->_db->fold_case($map[self::COLUMNS][$i]);
                            $ref_col = $this->_db->fold_case($map[self::REF_COLUMNS][$i]);
                            if (array_key_exists($ref_col, $new_primary_key)) {
                                $new_refs[$col] = $new_primary_key[$ref_col];
                            }
                            $type = $this->_metadata[$col]['DATA_TYPE'];
                            $where[] = $this->_db->quote_into($this->_db->quote_identifier($col, true) . ' = ?', $old_primary_key[$ref_col], $type);
                        }
                        $rows_affected += $this->update($new_refs, $where);
                        break;
                    default:
                        // no action
                        break;
                }
            }
        }
        return $rows_affected;
    }
    /**
     * Deletes existing rows.
     *
     * @param  array|string $where SQL WHERE clause(s).
     * @return int          The number of rows deleted.
     */
    public function delete($where)
    {
        $dep_tables = $this->get_dependent_tables();
        if (!empty($dep_tables)) {
            $result_set = $this->fetch_all($where);
            if (count($result_set) > 0) {
                foreach ($result_set as $row) {
                    /**
                     * Execute cascading deletes against dependent tables
                     */
                    foreach ($dep_tables as $table_class) {
                        $t = self::get_table_from_string($table_class, $this);
                        $t->_cascade_delete(get_class($this), $row->get_primary_key());
                    }
                }
            }
        }
        $table_spec = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;
        return $this->_db->delete($table_spec, $where);
    }
    /**
     * Called by parent table's class during delete() method.
     *
     * @param  string $parentTableClassname
     * @return int    Number of affected rows
     */
    public function _cascade_delete($parent_table_classname, array $primary_key)
    {
        // setup metadata
        $this->_setup_metadata();
        // get this class name
        $this_class = get_class($this);
        if ($this_class === 'Zend_Db_Table') {
            $this_class = $this->_definition_config_name;
        }
        $rows_affected = 0;
        foreach ($this->_get_reference_map_normalized() as $map) {
            if ($map[self::REF_TABLE_CLASS] == $parent_table_classname && isset($map[self::ON_DELETE])) {
                $where = [];
                // CASCADE or CASCADE_RECURSE
                if (in_array($map[self::ON_DELETE], [self::CASCADE, self::CASCADE_RECURSE])) {
                    for ($i = 0; $i < count($map[self::COLUMNS]); ++$i) {
                        $col = $this->_db->fold_case($map[self::COLUMNS][$i]);
                        $ref_col = $this->_db->fold_case($map[self::REF_COLUMNS][$i]);
                        $type = $this->_metadata[$col]['DATA_TYPE'];
                        $where[] = $this->_db->quote_into($this->_db->quote_identifier($col, true) . ' = ?', $primary_key[$ref_col], $type);
                    }
                }
                // CASCADE_RECURSE
                if ($map[self::ON_DELETE] == self::CASCADE_RECURSE) {
                    /**
                     * Execute cascading deletes against dependent tables
                     */
                    $dep_tables = $this->get_dependent_tables();
                    if (!empty($dep_tables)) {
                        foreach ($dep_tables as $table_class) {
                            $t = self::get_table_from_string($table_class, $this);
                            foreach ($this->fetch_all($where) as $dep_row) {
                                $rows_affected += $t->_cascade_delete($this_class, $dep_row->get_primary_key());
                            }
                        }
                    }
                }
                // CASCADE or CASCADE_RECURSE
                if (in_array($map[self::ON_DELETE], [self::CASCADE, self::CASCADE_RECURSE])) {
                    $rows_affected += $this->delete($where);
                }
            }
        }
        return $rows_affected;
    }
    /**
     * Fetches rows by primary key.  The argument specifies one or more primary
     * key value(s).  To find multiple rows by primary key, the argument must
     * be an array.
     *
     * This method accepts a variable number of arguments.  If the table has a
     * multi-column primary key, the number of arguments must be the same as
     * the number of columns in the primary key.  To find multiple rows in a
     * table with a multi-column primary key, each argument must be an array
     * with the same number of elements.
     *
     * The find() method always returns a Rowset object, even if only one row
     * was found.
     *
     * @param  mixed $key The value(s) of the primary keys.
     * @return Zend_Db_Table_Rowset_Abstract Row(s) matching the criteria.
     * @throws Zend_Db_Table_Exception
     */
    public function find()
    {
        $this->_setup_primary_key();
        $args = func_get_args();
        $key_names = array_values((array) $this->_primary);
        if (count($args) < count($key_names)) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Too few columns for the primary key');
        }
        if (count($args) > count($key_names)) {
            #require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception('Too many columns for the primary key');
        }
        $where_list = [];
        $number_terms = 0;
        foreach ($args as $key_position => $key_values) {
            /**
             * PHP 7.2 PATCH -->
             */
            if (is_array($key_values) || $key_values instanceof \Countable) {
                $key_values_count = count($key_values);
            } else if (null == $key_values) {
                $key_values_count = 0;
            } else {
                $key_values_count = 1;
            }
            #$keyValuesCount = count($keyValues);
            /**
             * PHP 7.2 PATCH <--
             */
            // Coerce the values to an array.
            // Don't simply typecast to array, because the values
            // might be Zend_Db_Expr objects.
            if (!is_array($key_values)) {
                $key_values = [$key_values];
            }
            if ($number_terms == 0) {
                $number_terms = $key_values_count;
            } elseif ($key_values_count != $number_terms) {
                #require_once 'Zend/Db/Table/Exception.php';
                throw new Zend_Db_Table_Exception('Missing value(s) for the primary key');
            }
            $key_values = array_values($key_values);
            for ($i = 0; $i < $key_values_count; ++$i) {
                if (!isset($where_list[$i])) {
                    $where_list[$i] = [];
                }
                $where_list[$i][$key_position] = $key_values[$i];
            }
        }
        $where_clause = null;
        if (count($where_list)) {
            $where_or_terms = [];
            $table_name = $this->_db->quote_table_as($this->_name, null, true);
            foreach ($where_list as $key_value_sets) {
                $where_and_terms = [];
                foreach ($key_value_sets as $key_position => $key_value) {
                    $type = $this->_metadata[$key_names[$key_position]]['DATA_TYPE'];
                    $column_name = $this->_db->quote_identifier($key_names[$key_position], true);
                    $where_and_terms[] = $this->_db->quote_into($table_name . '.' . $column_name . ' = ?', $key_value, $type);
                }
                $where_or_terms[] = '(' . implode(' AND ', $where_and_terms) . ')';
            }
            $where_clause = '(' . implode(' OR ', $where_or_terms) . ')';
        }
        // issue ZF-5775 (empty where clause should return empty rowset)
        if ($where_clause == null) {
            $rowset_class = $this->get_rowset_class();
            if (!class_exists($rowset_class)) {
                #require_once 'Zend/Loader.php';
                Zend_Loader::load_class($rowset_class);
            }
            return new $rowset_class(['table' => $this, 'rowClass' => $this->get_row_class(), 'stored' => true]);
        }
        return $this->fetch_all($where_clause);
    }
    /**
     * Fetches all rows.
     *
     * Honors the Zend_Db_Adapter fetch mode.
     *
     * @param string|array|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
     * @param int                               $count  OPTIONAL An SQL LIMIT count.
     * @param int                               $offset OPTIONAL An SQL LIMIT offset.
     * @return Zend_Db_Table_Rowset_Abstract The row results per the Zend_Db_Adapter fetch mode.
     */
    public function fetch_all($where = null, $order = null, $count = null, $offset = null)
    {
        if (!$where instanceof Zend_Db_Table_Select) {
            $select = $this->select();
            if ($where !== null) {
                $this->_where($select, $where);
            }
            if ($order !== null) {
                $this->_order($select, $order);
            }
            if ($count !== null || $offset !== null) {
                $select->limit($count, $offset);
            }
        } else {
            $select = $where;
        }
        $rows = $this->_fetch($select);
        $data = ['table' => $this, 'data' => $rows, 'readOnly' => $select->is_read_only(), 'rowClass' => $this->get_row_class(), 'stored' => true];
        $rowset_class = $this->get_rowset_class();
        if (!class_exists($rowset_class)) {
            #require_once 'Zend/Loader.php';
            Zend_Loader::load_class($rowset_class);
        }
        return new $rowset_class($data);
    }
    /**
     * Fetches one row in an object of type Zend_Db_Table_Row_Abstract,
     * or returns null if no row matches the specified criteria.
     *
     * @param string|array|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
     * @param int                               $offset OPTIONAL An SQL OFFSET value.
     * @return Zend_Db_Table_Row_Abstract|null The row results per the
     *     Zend_Db_Adapter fetch mode, or null if no row found.
     */
    public function fetch_row($where = null, $order = null, $offset = null)
    {
        if (!$where instanceof Zend_Db_Table_Select) {
            $select = $this->select();
            if ($where !== null) {
                $this->_where($select, $where);
            }
            if ($order !== null) {
                $this->_order($select, $order);
            }
            $select->limit(1, is_numeric($offset) ? (int) $offset : null);
        } else {
            $select = $where->limit(1, $where->get_part(Zend_Db_Select::LIMIT_OFFSET));
        }
        $rows = $this->_fetch($select);
        if (count($rows) == 0) {
            return null;
        }
        $data = ['table' => $this, 'data' => $rows[0], 'readOnly' => $select->is_read_only(), 'stored' => true];
        $row_class = $this->get_row_class();
        if (!class_exists($row_class)) {
            #require_once 'Zend/Loader.php';
            Zend_Loader::load_class($row_class);
        }
        return new $row_class($data);
    }
    /**
     * Fetches a new blank row (not from the database).
     *
     * @return Zend_Db_Table_Row_Abstract
     * @deprecated since 0.9.3 - use createRow() instead.
     */
    public function fetch_new()
    {
        return $this->create_row();
    }
    /**
     * Fetches a new blank row (not from the database).
     *
     * @param  array $data OPTIONAL data to populate in the new row.
     * @param  string $defaultSource OPTIONAL flag to force default values into new row
     * @return Zend_Db_Table_Row_Abstract
     */
    public function create_row(array $data = [], $default_source = null)
    {
        $cols = $this->_get_cols();
        $defaults = array_combine($cols, array_fill(0, count($cols), null));
        // nothing provided at call-time, take the class value
        if ($default_source == null) {
            $default_source = $this->_default_source;
        }
        if (!in_array($default_source, [self::DEFAULT_CLASS, self::DEFAULT_DB, self::DEFAULT_NONE])) {
            $default_source = self::DEFAULT_NONE;
        }
        if ($default_source == self::DEFAULT_DB) {
            foreach ($this->_metadata as $metadata_name => $metadata) {
                if ($metadata['DEFAULT'] != null && ($metadata['NULLABLE'] !== true || $metadata['NULLABLE'] === true && isset($this->_default_values[$metadata_name]) && $this->_default_values[$metadata_name] === true) && !(isset($this->_default_values[$metadata_name]) && $this->_default_values[$metadata_name] === false)) {
                    $defaults[$metadata_name] = $metadata['DEFAULT'];
                }
            }
        } elseif ($default_source == self::DEFAULT_CLASS && $this->_default_values) {
            foreach ($this->_default_values as $default_name => $default_value) {
                if (array_key_exists($default_name, $defaults)) {
                    $defaults[$default_name] = $default_value;
                }
            }
        }
        $config = ['table' => $this, 'data' => $defaults, 'readOnly' => false, 'stored' => false];
        $row_class = $this->get_row_class();
        if (!class_exists($row_class)) {
            #require_once 'Zend/Loader.php';
            Zend_Loader::load_class($row_class);
        }
        $row = new $row_class($config);
        $row->set_from_array($data);
        return $row;
    }
    /**
     * Generate WHERE clause from user-supplied string or array
     *
     * @param  string|array $where  OPTIONAL An SQL WHERE clause.
     * @return Zend_Db_Table_Select
     */
    protected function _where(Zend_Db_Table_Select $select, $where)
    {
        $where = (array) $where;
        foreach ($where as $key => $val) {
            // is $key an int?
            if (is_int($key)) {
                // $val is the full condition
                $select->where($val);
            } else {
                // $key is the condition with placeholder,
                // and $val is quoted into the condition
                $select->where($key, $val);
            }
        }
        return $select;
    }
    /**
     * Generate ORDER clause from user-supplied string or array
     *
     * @param  string|array $order  OPTIONAL An SQL ORDER clause.
     * @return Zend_Db_Table_Select
     */
    protected function _order(Zend_Db_Table_Select $select, $order)
    {
        if (!is_array($order)) {
            $order = [$order];
        }
        foreach ($order as $val) {
            $select->order($val);
        }
        return $select;
    }
    /**
     * Support method for fetching rows.
     *
     * @param  Zend_Db_Table_Select $select  query options.
     * @return array An array containing the row results in FETCH_ASSOC mode.
     */
    protected function _fetch(Zend_Db_Table_Select $select)
    {
        $stmt = $this->_db->query($select);
        return $stmt->fetch_all(Zend_Db::FETCH_ASSOC);
    }
    /**
     * Get table gateway object from string
     *
     * @param  string                 $tableName
     * @param  Zend_Db_Table_Abstract $referenceTable
     * @throws Zend_Db_Table_Row_Exception
     * @return Zend_Db_Table_Abstract
     */
    public static function get_table_from_string($table_name, ?Zend_Db_Table_Abstract $reference_table = null)
    {
        if ($reference_table instanceof Zend_Db_Table_Abstract) {
            $table_definition = $reference_table->get_definition();
            if ($table_definition !== null && $table_definition->has_table_config($table_name)) {
                return new Zend_Db_Table($table_name, $table_definition);
            }
        }
        // assume the tableName is the class name
        if (!class_exists($table_name)) {
            try {
                #require_once 'Zend/Loader.php';
                Zend_Loader::load_class($table_name);
            } catch (Zend_Exception $e) {
                #require_once 'Zend/Db/Table/Row/Exception.php';
                throw new Zend_Db_Table_Row_Exception($e->get_message(), $e->get_code(), $e);
            }
        }
        $options = [];
        if ($reference_table instanceof Zend_Db_Table_Abstract) {
            $options['db'] = $reference_table->get_adapter();
        }
        if (isset($table_definition) && $table_definition !== null) {
            $options[Zend_Db_Table_Abstract::DEFINITION] = $table_definition;
        }
        return new $table_name($options);
    }
}