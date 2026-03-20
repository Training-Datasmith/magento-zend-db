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
 * @see Zend_Db_Adapter_Abstract
 */
#require_once 'Zend/Db/Adapter/Abstract.php';
/**
 * @see Zend_Db_Expr
 */
#require_once 'Zend/Db/Expr.php';
/**
 * Class for SQL SELECT generation and results.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Select
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Select
{
    public const DISTINCT = 'distinct';
    public const COLUMNS = 'columns';
    public const FROM = 'from';
    public const UNION = 'union';
    public const WHERE = 'where';
    public const GROUP = 'group';
    public const HAVING = 'having';
    public const ORDER = 'order';
    public const LIMIT_COUNT = 'limitcount';
    public const LIMIT_OFFSET = 'limitoffset';
    public const FOR_UPDATE = 'forupdate';
    public const INNER_JOIN = 'inner join';
    public const LEFT_JOIN = 'left join';
    public const RIGHT_JOIN = 'right join';
    public const FULL_JOIN = 'full join';
    public const CROSS_JOIN = 'cross join';
    public const NATURAL_JOIN = 'natural join';
    public const SQL_WILDCARD = '*';
    public const SQL_SELECT = 'SELECT';
    public const SQL_UNION = 'UNION';
    public const SQL_UNION_ALL = 'UNION ALL';
    public const SQL_FROM = 'FROM';
    public const SQL_WHERE = 'WHERE';
    public const SQL_DISTINCT = 'DISTINCT';
    public const SQL_GROUP_BY = 'GROUP BY';
    public const SQL_ORDER_BY = 'ORDER BY';
    public const SQL_HAVING = 'HAVING';
    public const SQL_FOR_UPDATE = 'FOR UPDATE';
    public const SQL_AND = 'AND';
    public const SQL_AS = 'AS';
    public const SQL_OR = 'OR';
    public const SQL_ON = 'ON';
    public const SQL_ASC = 'ASC';
    public const SQL_DESC = 'DESC';
    public const REGEX_COLUMN_EXPR = '/^([\w]*\s*\(([^\(\)]|(?1))*\))$/';
    public const REGEX_COLUMN_EXPR_ORDER = '/^([\w]+\s*\(([^\(\)]|(?1))*\))$/';
    public const REGEX_COLUMN_EXPR_GROUP = '/^([\w]+\s*\(([^\(\)]|(?1))*\))$/';
    // @see http://stackoverflow.com/a/13823184/2028814
    public const REGEX_SQL_COMMENTS = '@
    (([\'"]).*?[^\\\\]\2) # $1 : Skip single & double quoted expressions
    |(                   # $3 : Match comments
        (?:\#|--).*?$    # - Single line comments
        |                # - Multi line (nested) comments
         /\*             #   . comment open marker
            (?: [^/*]    #   . non comment-marker characters
                |/(?!\*) #   . ! not a comment open
                |\*(?!/) #   . ! not a comment close
                |(?R)    #   . recursive case
            )*           #   . repeat eventually
        \*\/             #   . comment close marker
    )\s*                 # Trim after comments
    |(?<=;)\s+           # Trim after semi-colon
    @msx';
    /**
     * Bind variables for query
     *
     * @var array
     */
    protected $_bind = [];
    /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_adapter;
    /**
     * The initial values for the $_parts array.
     * NOTE: It is important for the 'FOR_UPDATE' part to be last to ensure
     * meximum compatibility with database adapters.
     *
     * @var array
     */
    protected static $_parts_init = [self::DISTINCT => false, self::COLUMNS => [], self::UNION => [], self::FROM => [], self::WHERE => [], self::GROUP => [], self::HAVING => [], self::ORDER => [], self::LIMIT_COUNT => null, self::LIMIT_OFFSET => null, self::FOR_UPDATE => false];
    /**
     * Specify legal join types.
     *
     * @var array
     */
    protected static $_join_types = [self::INNER_JOIN, self::LEFT_JOIN, self::RIGHT_JOIN, self::FULL_JOIN, self::CROSS_JOIN, self::NATURAL_JOIN];
    /**
     * Specify legal union types.
     *
     * @var array
     */
    protected static $_union_types = [self::SQL_UNION, self::SQL_UNION_ALL];
    /**
     * The component parts of a SELECT statement.
     * Initialized to the $_partsInit array in the constructor.
     *
     * @var array
     */
    protected $_parts = [];
    /**
     * Tracks which columns are being select from each table and join.
     *
     * @var array
     */
    protected $_table_cols = [];
    /**
     * Class constructor
     */
    public function __construct(Zend_Db_Adapter_Abstract $adapter)
    {
        $this->_adapter = $adapter;
        $this->_parts = self::$_parts_init;
    }
    /**
     * Get bind variables
     *
     * @return array
     */
    public function get_bind()
    {
        return $this->_bind;
    }
    /**
     * Set bind variables
     *
     * @param mixed $bind
     */
    public function bind($bind): self
    {
        $this->_bind = $bind;
        return $this;
    }
    /**
     * Makes the query SELECT DISTINCT.
     *
     * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function distinct($flag = true): self
    {
        $this->_parts[self::DISTINCT] = (bool) $flag;
        return $this;
    }
    /**
     * Adds a FROM table and optional columns to the query.
     *
     * The first parameter $name can be a simple string, in which case the
     * correlation name is generated automatically.  If you want to specify
     * the correlation name, the first parameter must be an associative
     * array in which the key is the correlation name, and the value is
     * the physical table name.  For example, array('alias' => 'table').
     * The correlation name is prepended to all columns fetched for this
     * table.
     *
     * The second parameter can be a single string or Zend_Db_Expr object,
     * or else an array of strings or Zend_Db_Expr objects.
     *
     * The first parameter can be null or an empty string, in which case
     * no correlation name is generated or prepended to the columns named
     * in the second parameter.
     *
     * @param  array|string|Zend_Db_Expr $name The table name or an associative array
     *                                         relating correlation name to table name.
     * @param  array|string|Zend_Db_Expr $cols The columns to select from this table.
     * @param  string $schema The schema name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function from($name, $cols = '*', $schema = null)
    {
        return $this->_join(self::FROM, $name, null, $cols, $schema);
    }
    /**
     * Specifies the columns used in the FROM clause.
     *
     * The parameter can be a single string or Zend_Db_Expr object,
     * or else an array of strings or Zend_Db_Expr objects.
     *
     * @param  array|string|Zend_Db_Expr $cols The columns to select from this table.
     * @param  string $correlationName Correlation name of target table. OPTIONAL
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function columns($cols = '*', $correlation_name = null): self
    {
        if ($correlation_name === null && count($this->_parts[self::FROM])) {
            $correlation_name_keys = array_keys($this->_parts[self::FROM]);
            $correlation_name = current($correlation_name_keys);
        }
        if (!array_key_exists($correlation_name, $this->_parts[self::FROM])) {
            /**
             * @see Zend_Db_Select_Exception
             */
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('No table has been specified for the FROM clause');
        }
        $this->_table_cols($correlation_name, $cols);
        return $this;
    }
    /**
     * Adds a UNION clause to the query.
     *
     * The first parameter has to be an array of Zend_Db_Select or
     * sql query strings.
     *
     * <code>
     * $sql1 = $db->select();
     * $sql2 = "SELECT ...";
     * $select = $db->select()
     *      ->union(array($sql1, $sql2))
     *      ->order("id");
     * </code>
     *
     * @param  array $select Array of select clauses for the union.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function union($select = [], $type = self::SQL_UNION): self
    {
        if (!is_array($select)) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('union() only accepts an array of Zend_Db_Select instances of sql query strings.');
        }
        if (!in_array($type, self::$_union_types)) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid union type '{$type}'");
        }
        foreach ($select as $target) {
            $this->_parts[self::UNION][] = [$target, $type];
        }
        return $this;
    }
    /**
     * Adds a JOIN table and columns to the query.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->join_inner($name, $cond, $cols, $schema);
    }
    /**
     * Add an INNER JOIN table and colums to the query
     * Rows in both tables are matched according to the expression
     * in the $cond argument.  The result set is comprised
     * of all cases where rows from the left table match
     * rows from the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_inner($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::INNER_JOIN, $name, $cond, $cols, $schema);
    }
    /**
     * Add a LEFT OUTER JOIN table and colums to the query
     * All rows from the left operand table are included,
     * matching rows from the right operand table included,
     * and the columns from the right operand table are filled
     * with NULLs if no row exists matching the left table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_left($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::LEFT_JOIN, $name, $cond, $cols, $schema);
    }
    /**
     * Add a RIGHT OUTER JOIN table and colums to the query.
     * Right outer join is the complement of left outer join.
     * All rows from the right operand table are included,
     * matching rows from the left operand table included,
     * and the columns from the left operand table are filled
     * with NULLs if no row exists matching the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_right($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols, $schema);
    }
    /**
     * Add a FULL OUTER JOIN table and colums to the query.
     * A full outer join is like combining a left outer join
     * and a right outer join.  All rows from both tables are
     * included, paired with each other on the same row of the
     * result set if they satisfy the join condition, and otherwise
     * paired with NULLs in place of columns from the other table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_full($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::FULL_JOIN, $name, $cond, $cols, $schema);
    }
    /**
     * Add a CROSS JOIN table and colums to the query.
     * A cross join is a cartesian product; there is no join condition.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_cross($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::CROSS_JOIN, $name, null, $cols, $schema);
    }
    /**
     * Add a NATURAL JOIN table and colums to the query.
     * A natural join assumes an equi-join across any column(s)
     * that appear with the same name in both tables.
     * Only natural inner joins are supported by this API,
     * even though SQL permits natural outer joins as well.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join_natural($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::NATURAL_JOIN, $name, null, $cols, $schema);
    }
    /**
     * Adds a WHERE condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. Array values are quoted and comma-separated.
     *
     * <code>
     * // simplest but non-secure
     * $select->where("id = $id");
     *
     * // secure (ID is quoted but matched anyway)
     * $select->where('id = ?', $id);
     *
     * // alternatively, with named binding
     * $select->where('id = :id');
     * </code>
     *
     * Note that it is more correct to use named bindings in your
     * queries for values other than strings. When you use named
     * bindings, don't forget to pass the values when actually
     * making a query:
     *
     * <code>
     * $db->fetchAll($select, array('id' => 5));
     * </code>
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function where($cond, $value = null, $type = null): self
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);
        return $this;
    }
    /**
     * Adds a WHERE condition to the query by OR.
     *
     * Otherwise identical to where().
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see where()
     */
    public function or_where($cond, $value = null, $type = null): self
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);
        return $this;
    }
    /**
     * Adds grouping to the query.
     *
     * @param  array|string $spec The column(s) to group by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function group($spec): self
    {
        if (!is_array($spec)) {
            $spec = [$spec];
        }
        foreach ($spec as $val) {
            // Remove comments from SQL statement
            $no_comments = preg_replace(self::REGEX_SQL_COMMENTS, '$1', (string) $val);
            if (preg_match(self::REGEX_COLUMN_EXPR_GROUP, $no_comments)) {
                $val = new Zend_Db_Expr($val);
            }
            $this->_parts[self::GROUP][] = $val;
        }
        return $this;
    }
    /**
     * Adds a HAVING condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. See {@link where()} for an example
     *
     * @param string $cond The HAVING condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function having($cond, $value = null, $type = null): self
    {
        if ($value !== null) {
            $cond = $this->_adapter->quote_into($cond, $value, $type);
        }
        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_AND . " ({$cond})";
        } else {
            $this->_parts[self::HAVING][] = "({$cond})";
        }
        return $this;
    }
    /**
     * Adds a HAVING condition to the query by OR.
     *
     * Otherwise identical to orHaving().
     *
     * @param string $cond The HAVING condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see having()
     */
    public function or_having($cond, $value = null, $type = null): self
    {
        if ($value !== null) {
            $cond = $this->_adapter->quote_into($cond, $value, $type);
        }
        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_OR . " ({$cond})";
        } else {
            $this->_parts[self::HAVING][] = "({$cond})";
        }
        return $this;
    }
    /**
     * Adds a row order to the query.
     *
     * @param mixed $spec The column(s) and direction to order by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function order($spec): self
    {
        if (!is_array($spec)) {
            $spec = [$spec];
        }
        // force 'ASC' or 'DESC' on each order spec, default is ASC.
        foreach ($spec as $val) {
            if ($val instanceof Zend_Db_Expr) {
                $expr = $val->__toString();
                if (empty($expr)) {
                    continue;
                }
                $this->_parts[self::ORDER][] = $val;
            } else {
                if (empty($val)) {
                    continue;
                }
                $direction = self::SQL_ASC;
                if (preg_match('/(.*\W)(' . self::SQL_ASC . '|' . self::SQL_DESC . ')\b/si', $val, $matches)) {
                    $val = trim($matches[1]);
                    $direction = $matches[2];
                }
                // Remove comments from SQL statement
                $no_comments = preg_replace(self::REGEX_SQL_COMMENTS, '$1', (string) $val);
                if (preg_match(self::REGEX_COLUMN_EXPR_ORDER, $no_comments)) {
                    $val = new Zend_Db_Expr($val);
                }
                $this->_parts[self::ORDER][] = [$val, $direction];
            }
        }
        return $this;
    }
    /**
     * Sets a limit count and offset to the query.
     *
     * @param int $count OPTIONAL The number of rows to return.
     * @param int $offset OPTIONAL Start returning after this many rows.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limit($count = null, $offset = null): self
    {
        $this->_parts[self::LIMIT_COUNT] = (int) $count;
        $this->_parts[self::LIMIT_OFFSET] = (int) $offset;
        return $this;
    }
    /**
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     * @param int $rowCount Use this many rows per page.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limit_page($page, $row_count): self
    {
        $page = $page > 0 ? $page : 1;
        $row_count = $row_count > 0 ? $row_count : 1;
        $this->_parts[self::LIMIT_COUNT] = (int) $row_count;
        $this->_parts[self::LIMIT_OFFSET] = (int) $row_count * ($page - 1);
        return $this;
    }
    /**
     * Makes the query SELECT FOR UPDATE.
     *
     * @param bool $flag Whether or not the SELECT is FOR UPDATE (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function for_update($flag = true): self
    {
        $this->_parts[self::FOR_UPDATE] = (bool) $flag;
        return $this;
    }
    /**
     * Get part of the structured information for the current query.
     *
     * @param string $part
     * @return mixed
     * @throws Zend_Db_Select_Exception
     */
    public function get_part($part)
    {
        $part = strtolower($part);
        if (!array_key_exists($part, $this->_parts)) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid Select part '{$part}'");
        }
        return $this->_parts[$part];
    }
    /**
     * Executes the current select object and returns the result
     *
     * @param integer $fetchMode OPTIONAL
     * @param  mixed  $bind An array of data to bind to the placeholders.
     * @return PDO_Statement|Zend_Db_Statement
     */
    public function query($fetch_mode = null, $bind = [])
    {
        if (!empty($bind)) {
            $this->bind($bind);
        }
        $stmt = $this->_adapter->query($this);
        if ($fetch_mode == null) {
            $fetch_mode = $this->_adapter->get_fetch_mode();
        }
        $stmt->set_fetch_mode($fetch_mode);
        return $stmt;
    }
    /**
     * Converts this object to an SQL SELECT string.
     *
     * @return string|null This object as a SELECT string. (or null if a string cannot be produced.)
     */
    public function assemble()
    {
        $sql = self::SQL_SELECT;
        foreach (array_keys(self::$_parts_init) as $part) {
            $method = '_render' . ucfirst($part);
            if (method_exists($this, $method)) {
                $sql = $this->{$method}($sql);
            }
        }
        return $sql;
    }
    /**
     * Clear parts of the Select object, or an individual part.
     *
     * @param string $part OPTIONAL
     */
    public function reset($part = null): self
    {
        if ($part == null) {
            $this->_parts = self::$_parts_init;
        } elseif (array_key_exists($part, self::$_parts_init)) {
            $this->_parts[$part] = self::$_parts_init[$part];
        }
        return $this;
    }
    /**
     * Gets the Zend_Db_Adapter_Abstract for this
     * particular Zend_Db_Select object.
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function get_adapter()
    {
        return $this->_adapter;
    }
    /**
     * Populate the {@link $_parts} 'join' key
     *
     * Does the dirty work of populating the join key.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  null|string $type Type of join; inner, left, and null are currently supported
     * @param  array|string|Zend_Db_Expr $name Table name
     * @param  string $cond Join on this condition
     * @param  array|string $cols The columns to select from the joined table
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object
     * @throws Zend_Db_Select_Exception
     */
    protected function _join($type, $name, $cond, $cols, $schema = null): self
    {
        if (!in_array($type, self::$_join_types) && $type != self::FROM) {
            /**
             * @see Zend_Db_Select_Exception
             */
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid join type '{$type}'");
        }
        if (count($this->_parts[self::UNION])) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('Invalid use of table with ' . self::SQL_UNION);
        }
        if (empty($name)) {
            $correlation_name = $table_name = '';
        } elseif (is_array($name)) {
            // Must be array($correlationName => $tableName) or array($ident, ...)
            foreach ($name as $_correlation_name => $_table_name) {
                if (is_string($_correlation_name)) {
                    // We assume the key is the correlation name and value is the table name
                    $table_name = $_table_name;
                    $correlation_name = $_correlation_name;
                } else {
                    // We assume just an array of identifiers, with no correlation name
                    $table_name = $_table_name;
                    $correlation_name = $this->_unique_correlation($table_name);
                }
                break;
            }
        } elseif ($name instanceof Zend_Db_Expr || $name instanceof Zend_Db_Select) {
            $table_name = $name;
            $correlation_name = $this->_unique_correlation('t');
        } elseif (preg_match('/^(.+)\s+AS\s+(.+)$/i', $name, $m)) {
            $table_name = $m[1];
            $correlation_name = $m[2];
        } else {
            $table_name = $name;
            $correlation_name = $this->_unique_correlation($table_name);
        }
        // Schema from table name overrides schema argument
        if ($table_name && !is_object($table_name) && strpos($table_name, '.') !== false) {
            list($schema, $table_name) = explode('.', $table_name);
        }
        $last_from_correlation_name = null;
        if (!empty($correlation_name)) {
            if (array_key_exists($correlation_name, $this->_parts[self::FROM])) {
                /**
                 * @see Zend_Db_Select_Exception
                 */
                #require_once 'Zend/Db/Select/Exception.php';
                throw new Zend_Db_Select_Exception("You cannot define a correlation name '{$correlation_name}' more than once");
            }
            if ($type == self::FROM) {
                // append this from after the last from joinType
                $tmp_from_parts = $this->_parts[self::FROM];
                $this->_parts[self::FROM] = [];
                // move all the froms onto the stack
                while ($tmp_from_parts) {
                    $current_correlation_name = key($tmp_from_parts);
                    if ($tmp_from_parts[$current_correlation_name]['joinType'] != self::FROM) {
                        break;
                    }
                    $last_from_correlation_name = $current_correlation_name;
                    $this->_parts[self::FROM][$current_correlation_name] = array_shift($tmp_from_parts);
                }
            } else {
                $tmp_from_parts = [];
            }
            $this->_parts[self::FROM][$correlation_name] = ['joinType' => $type, 'schema' => $schema, 'tableName' => $table_name, 'joinCondition' => $cond];
            while ($tmp_from_parts) {
                $current_correlation_name = key($tmp_from_parts);
                $this->_parts[self::FROM][$current_correlation_name] = array_shift($tmp_from_parts);
            }
        }
        // add to the columns from this joined table
        if ($type == self::FROM && $last_from_correlation_name == null) {
            $last_from_correlation_name = true;
        }
        $this->_table_cols($correlation_name, $cols, $last_from_correlation_name);
        return $this;
    }
    /**
     * Handle JOIN... USING... syntax
     *
     * This is functionality identical to the existing JOIN methods, however
     * the join condition can be passed as a single column name. This method
     * then completes the ON condition by using the same field for the FROM
     * table and the JOIN table.
     *
     * <code>
     * $select = $db->select()->from('table1')
     *                        ->joinUsing('table2', 'column1');
     *
     * // SELECT * FROM table1 JOIN table2 ON table1.column1 = table2.column2
     * </code>
     *
     * These joins are called by the developer simply by adding 'Using' to the
     * method name. E.g.
     * * joinUsing
     * * joinInnerUsing
     * * joinFullUsing
     * * joinRightUsing
     * * joinLeftUsing
     *
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function _join_using($type, $name, $cond, $cols = '*', $schema = null)
    {
        if (empty($this->_parts[self::FROM])) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('You can only perform a joinUsing after specifying a FROM table');
        }
        $join = $this->_adapter->quote_identifier(key($this->_parts[self::FROM]), true);
        $from = $this->_adapter->quote_identifier($this->_unique_correlation($name), true);
        $join_cond = [];
        foreach ((array) $cond as $field_name) {
            $cond1 = $from . '.' . $field_name;
            $cond2 = $join . '.' . $field_name;
            $join_cond[] = $cond1 . ' = ' . $cond2;
        }
        $cond = implode(' ' . self::SQL_AND . ' ', $join_cond);
        return $this->_join($type, $name, $cond, $cols, $schema);
    }
    /**
     * Generate a unique correlation name
     *
     * @param string|array $name A qualified identifier.
     * @return string A unique correlation name.
     */
    private function _unique_correlation($name)
    {
        if (is_array($name)) {
            $k = key($name);
            $c = is_string($k) ? $k : end($name);
        } else {
            // Extract just the last name of a qualified table name
            $dot = strrpos($name, '.');
            $c = $dot === false ? $name : substr($name, $dot + 1);
        }
        for ($i = 2; array_key_exists($c, $this->_parts[self::FROM]); ++$i) {
            $c = $name . '_' . $i;
        }
        return $c;
    }
    /**
     * Adds to the internal table-to-column mapping array.
     *
     * @param  string $tbl The table/join the columns come from.
     * @param  array|string $cols The list of columns; preferably as
     * an array, but possibly as a string containing one column.
     * @param  bool|string True if it should be prepended, a correlation name if it should be inserted
     * @return void
     */
    protected function _table_cols($correlation_name, $cols, $after_correlation_name = null)
    {
        if (!is_array($cols)) {
            $cols = [$cols];
        }
        if ($correlation_name == null) {
            $correlation_name = '';
        }
        $column_values = [];
        foreach (array_filter($cols) as $alias => $col) {
            $current_correlation_name = $correlation_name;
            if (is_string($col)) {
                // Check for a column matching "<column> AS <alias>" and extract the alias name
                $col = trim(str_replace("\n", ' ', $col));
                if (preg_match('/^(.+)\s+' . self::SQL_AS . '\s+(.+)$/i', $col, $m)) {
                    $col = $m[1];
                    $alias = $m[2];
                }
                // Check for columns that look like functions and convert to Zend_Db_Expr
                if (preg_match(self::REGEX_COLUMN_EXPR, $col)) {
                    $col = new Zend_Db_Expr($col);
                } elseif (preg_match('/(.+)\.(.+)/', $col, $m)) {
                    $current_correlation_name = $m[1];
                    $col = $m[2];
                }
            }
            $column_values[] = [$current_correlation_name, $col, is_string($alias) ? $alias : null];
        }
        if ($column_values) {
            // should we attempt to prepend or insert these values?
            if ($after_correlation_name === true || is_string($after_correlation_name)) {
                $tmp_columns = $this->_parts[self::COLUMNS];
                $this->_parts[self::COLUMNS] = [];
            } else {
                $tmp_columns = [];
            }
            // find the correlation name to insert after
            if (is_string($after_correlation_name)) {
                while ($tmp_columns) {
                    $this->_parts[self::COLUMNS][] = $current_column = array_shift($tmp_columns);
                    if ($current_column[0] == $after_correlation_name) {
                        break;
                    }
                }
            }
            // apply current values to current stack
            foreach ($column_values as $column_value) {
                array_push($this->_parts[self::COLUMNS], $column_value);
            }
            // finish ensuring that all previous values are applied (if they exist)
            while ($tmp_columns) {
                array_push($this->_parts[self::COLUMNS], array_shift($tmp_columns));
            }
        }
    }
    /**
     * Internal function for creating the where clause
     *
     * @param string   $condition
     * @param mixed    $value  optional
     * @param string   $type   optional
     * @param boolean  $bool  true = AND, false = OR
     * @return string  clause
     */
    protected function _where($condition, $value = null, $type = null, $bool = true): string
    {
        if (count($this->_parts[self::UNION])) {
            #require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('Invalid use of where clause with ' . self::SQL_UNION);
        }
        if ($value !== null) {
            $condition = $this->_adapter->quote_into($condition, $value, $type);
        }
        $cond = '';
        if ($this->_parts[self::WHERE]) {
            if ($bool === true) {
                $cond = self::SQL_AND . ' ';
            } else {
                $cond = self::SQL_OR . ' ';
            }
        }
        return $cond . "({$condition})";
    }
    protected function _get_dummy_table(): array
    {
        return [];
    }
    /**
     * Return a quoted schema name
     *
     * @param string   $schema  The schema name OPTIONAL
     * @return string|null
     */
    protected function _get_quoted_schema($schema = null)
    {
        if ($schema === null) {
            return null;
        }
        return $this->_adapter->quote_identifier($schema, true) . '.';
    }
    /**
     * Return a quoted table name
     *
     * @param string   $tableName        The table name
     * @param string   $correlationName  The correlation name OPTIONAL
     * @return string
     */
    protected function _get_quoted_table($table_name, $correlation_name = null)
    {
        return $this->_adapter->quote_table_as($table_name, $correlation_name, true);
    }
    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_distinct(string $sql): string
    {
        if ($this->_parts[self::DISTINCT]) {
            $sql .= ' ' . self::SQL_DISTINCT;
        }
        return $sql;
    }
    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     * @return string|null
     */
    protected function _render_columns(string $sql)
    {
        if (!count($this->_parts[self::COLUMNS])) {
            return null;
        }
        $columns = [];
        foreach ($this->_parts[self::COLUMNS] as $column_entry) {
            list($correlation_name, $column, $alias) = $column_entry;
            if ($column instanceof Zend_Db_Expr) {
                $columns[] = $this->_adapter->quote_column_as($column, $alias, true);
            } else {
                if ($column == self::SQL_WILDCARD) {
                    $column = new Zend_Db_Expr(self::SQL_WILDCARD);
                    $alias = null;
                }
                if (empty($correlation_name)) {
                    $columns[] = $this->_adapter->quote_column_as($column, $alias, true);
                } else {
                    $columns[] = $this->_adapter->quote_column_as([$correlation_name, $column], $alias, true);
                }
            }
        }
        return $sql . ' ' . implode(', ', $columns);
    }
    /**
     * Render FROM clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_from(string $sql): string
    {
        /*
         * If no table specified, use RDBMS-dependent solution
         * for table-less query.  e.g. DUAL in Oracle.
         */
        if (empty($this->_parts[self::FROM])) {
            $this->_parts[self::FROM] = $this->_get_dummy_table();
        }
        $from = [];
        foreach ($this->_parts[self::FROM] as $correlation_name => $table) {
            $tmp = '';
            $join_type = $table['joinType'] == self::FROM ? self::INNER_JOIN : $table['joinType'];
            // Add join clause (if applicable)
            if (!empty($from)) {
                $tmp .= ' ' . strtoupper($join_type) . ' ';
            }
            $tmp .= $this->_get_quoted_schema($table['schema']);
            $tmp .= $this->_get_quoted_table($table['tableName'], $correlation_name);
            // Add join conditions (if applicable)
            if (!empty($from) && !empty($table['joinCondition'])) {
                $tmp .= ' ' . self::SQL_ON . ' ' . $table['joinCondition'];
            }
            // Add the table name and condition add to the list
            $from[] = $tmp;
        }
        // Add the list of all joins
        if (!empty($from)) {
            $sql .= ' ' . self::SQL_FROM . ' ' . implode("\n", $from);
        }
        return $sql;
    }
    /**
     * Render UNION query
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _render_union($sql)
    {
        if ($this->_parts[self::UNION]) {
            $parts = count($this->_parts[self::UNION]);
            foreach ($this->_parts[self::UNION] as $cnt => $union) {
                list($target, $type) = $union;
                if ($target instanceof Zend_Db_Select) {
                    $target = $target->assemble();
                }
                $sql .= $target;
                if ($cnt < $parts - 1) {
                    $sql .= ' ' . $type . ' ';
                }
            }
        }
        return $sql;
    }
    /**
     * Render WHERE clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_where(string $sql): string
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::WHERE]) {
            $sql .= ' ' . self::SQL_WHERE . ' ' . implode(' ', $this->_parts[self::WHERE]);
        }
        return $sql;
    }
    /**
     * Render GROUP clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_group(string $sql): string
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::GROUP]) {
            $group = [];
            foreach ($this->_parts[self::GROUP] as $term) {
                $group[] = $this->_adapter->quote_identifier($term, true);
            }
            $sql .= ' ' . self::SQL_GROUP_BY . ' ' . implode(",\n\t", $group);
        }
        return $sql;
    }
    /**
     * Render HAVING clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_having(string $sql): string
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::HAVING]) {
            $sql .= ' ' . self::SQL_HAVING . ' ' . implode(' ', $this->_parts[self::HAVING]);
        }
        return $sql;
    }
    /**
     * Render ORDER clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_order(string $sql): string
    {
        if ($this->_parts[self::ORDER]) {
            $order = [];
            foreach ($this->_parts[self::ORDER] as $term) {
                if (is_array($term)) {
                    if (is_numeric($term[0]) && strval(intval($term[0])) == $term[0]) {
                        $order[] = (int) trim($term[0]) . ' ' . $term[1];
                    } else {
                        $order[] = $this->_adapter->quote_identifier($term[0], true) . ' ' . $term[1];
                    }
                } elseif (is_numeric($term) && strval(intval($term)) == $term) {
                    $order[] = (int) trim($term);
                } else {
                    $order[] = $this->_adapter->quote_identifier($term, true);
                }
            }
            $sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
        }
        return $sql;
    }
    /**
     * Render LIMIT OFFSET clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _render_limitoffset($sql)
    {
        $count = 0;
        $offset = 0;
        if (!empty($this->_parts[self::LIMIT_OFFSET])) {
            $offset = (int) $this->_parts[self::LIMIT_OFFSET];
            $count = PHP_INT_MAX;
        }
        if (!empty($this->_parts[self::LIMIT_COUNT])) {
            $count = (int) $this->_parts[self::LIMIT_COUNT];
        }
        /*
         * Add limits clause
         */
        if ($count > 0) {
            return trim($this->_adapter->limit($sql, $count, $offset));
        }
        return $sql;
    }
    /**
     * Render FOR UPDATE clause
     *
     * @param string   $sql SQL query
     */
    protected function _render_forupdate(string $sql): string
    {
        if ($this->_parts[self::FOR_UPDATE]) {
            $sql .= ' ' . self::SQL_FOR_UPDATE;
        }
        return $sql;
    }
    /**
     * Turn magic function calls into non-magic function calls
     * for joinUsing syntax
     *
     * @param array $args OPTIONAL Zend_Db_Table_Select query modifier
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception If an invalid method is called.
     */
    public function __call(string $method, array $args)
    {
        $matches = [];
        /**
         * Recognize methods for Has-Many cases:
         * findParent<Class>()
         * findParent<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^join([a-zA-Z]*?)Using$/', $method, $matches)) {
            $type = strtolower($matches[1]);
            if ($type) {
                $type .= ' join';
                if (!in_array($type, self::$_join_types)) {
                    #require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Unrecognized method '{$method}()'");
                }
                if (in_array($type, [self::CROSS_JOIN, self::NATURAL_JOIN])) {
                    #require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Cannot perform a joinUsing with method '{$method}()'");
                }
            } else {
                $type = self::INNER_JOIN;
            }
            array_unshift($args, $type);
            return call_user_func_array([$this, '_joinUsing'], $args);
        }
        #require_once 'Zend/Db/Select/Exception.php';
        throw new Zend_Db_Select_Exception("Unrecognized method '{$method}()'");
    }
    /**
     * Implements magic method.
     *
     * @return string This object as a SELECT string.
     */
    public function __toString(): string
    {
        try {
            $sql = $this->assemble();
        } catch (Exception $e) {
            trigger_error($e->get_message(), E_USER_WARNING);
            $sql = '';
        }
        return (string) $sql;
    }
}