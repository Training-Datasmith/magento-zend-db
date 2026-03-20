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
 * @subpackage Profiler
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Profiler
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Profiler
{
    /**
     * A connection operation or selecting a database.
     */
    public const CONNECT = 1;
    /**
     * Any general database query that does not fit into the other constants.
     */
    public const QUERY = 2;
    /**
     * Adding new data to the database, such as SQL's INSERT.
     */
    public const INSERT = 4;
    /**
     * Updating existing information in the database, such as SQL's UPDATE.
     *
     */
    public const UPDATE = 8;
    /**
     * An operation related to deleting data in the database,
     * such as SQL's DELETE.
     */
    public const DELETE = 16;
    /**
     * Retrieving information from the database, such as SQL's SELECT.
     */
    public const SELECT = 32;
    /**
     * Transactional operation, such as start transaction, commit, or rollback.
     */
    public const TRANSACTION = 64;
    /**
     * Inform that a query is stored (in case of filtering)
     */
    public const STORED = 'stored';
    /**
     * Inform that a query is ignored (in case of filtering)
     */
    public const IGNORED = 'ignored';
    /**
     * Array of Zend_Db_Profiler_Query objects.
     *
     * @var array
     */
    protected $_query_profiles = [];
    /**
     * Stores enabled state of the profiler.  If set to False, calls to
     * queryStart() will simply be ignored.
     *
     * @var boolean
     */
    protected $_enabled = false;
    /**
     * Stores the number of seconds to filter.  NULL if filtering by time is
     * disabled.  If an integer is stored here, profiles whose elapsed time
     * is less than this value in seconds will be unset from
     * the self::$_queryProfiles array.
     *
     * @var integer
     */
    protected $_filter_elapsed_secs;
    /**
     * Logical OR of any of the filter constants.  NULL if filtering by query
     * type is disable.  If an integer is stored here, it is the logical OR of
     * any of the query type constants.  When the query ends, if it is not
     * one of the types specified, it will be unset from the
     * self::$_queryProfiles array.
     *
     * @var integer
     */
    protected $_filter_types;
    /**
     * Class constructor.  The profiler is disabled by default unless it is
     * specifically enabled by passing in $enabled here or calling setEnabled().
     *
     * @param  boolean $enabled
     */
    public function __construct($enabled = false)
    {
        $this->set_enabled($enabled);
    }
    /**
     * Enable or disable the profiler.  If $enable is false, the profiler
     * is disabled and will not log any queries sent to it.
     *
     * @param  boolean $enable
     * @return Zend_Db_Profiler Provides a fluent interface
     */
    public function set_enabled($enable): self
    {
        $this->_enabled = (bool) $enable;
        return $this;
    }
    /**
     * Get the current state of enable.  If True is returned,
     * the profiler is enabled.
     *
     * @return boolean
     */
    public function get_enabled()
    {
        return $this->_enabled;
    }
    /**
     * Sets a minimum number of seconds for saving query profiles.  If this
     * is set, only those queries whose elapsed time is equal or greater than
     * $minimumSeconds will be saved.  To save all queries regardless of
     * elapsed time, set $minimumSeconds to null.
     *
     * @param  integer $minimumSeconds OPTIONAL
     * @return Zend_Db_Profiler Provides a fluent interface
     */
    public function set_filter_elapsed_secs($minimum_seconds = null): self
    {
        if (null === $minimum_seconds) {
            $this->_filter_elapsed_secs = null;
        } else {
            $this->_filter_elapsed_secs = (int) $minimum_seconds;
        }
        return $this;
    }
    /**
     * Returns the minimum number of seconds for saving query profiles, or null if
     * query profiles are saved regardless of elapsed time.
     *
     * @return integer|null
     */
    public function get_filter_elapsed_secs()
    {
        return $this->_filter_elapsed_secs;
    }
    /**
     * Sets the types of query profiles to save.  Set $queryType to one of
     * the Zend_Db_Profiler::* constants to only save profiles for that type of
     * query.  To save more than one type, logical OR them together.  To
     * save all queries regardless of type, set $queryType to null.
     *
     * @param  integer $queryTypes OPTIONAL
     * @return Zend_Db_Profiler Provides a fluent interface
     */
    public function set_filter_query_type($query_types = null): self
    {
        $this->_filter_types = $query_types;
        return $this;
    }
    /**
     * Returns the types of query profiles saved, or null if queries are saved regardless
     * of their types.
     *
     * @return integer|null
     * @see    Zend_Db_Profiler::setFilterQueryType()
     */
    public function get_filter_query_type()
    {
        return $this->_filter_types;
    }
    /**
     * Clears the history of any past query profiles.  This is relentless
     * and will even clear queries that were started and may not have
     * been marked as ended.
     *
     * @return Zend_Db_Profiler Provides a fluent interface
     */
    public function clear(): self
    {
        $this->_query_profiles = [];
        return $this;
    }
    /**
     * Clone a profiler query
     *
     * @return integer or null
     */
    public function query_clone(Zend_Db_Profiler_Query $query)
    {
        $this->_query_profiles[] = clone $query;
        end($this->_query_profiles);
        return key($this->_query_profiles);
    }
    /**
     * Starts a query.  Creates a new query profile object (Zend_Db_Profiler_Query)
     * and returns the "query profiler handle".  Run the query, then call
     * queryEnd() and pass it this handle to make the query as ended and
     * record the time.  If the profiler is not enabled, this takes no
     * action and immediately returns null.
     *
     * @param  string  $queryText   SQL statement
     * @param  integer $queryType   OPTIONAL Type of query, one of the Zend_Db_Profiler::* constants
     * @return integer|null
     */
    public function query_start($query_text, $query_type = null)
    {
        if (!$this->_enabled) {
            return null;
        }
        // make sure we have a query type
        if (null === $query_type) {
            switch (strtolower(substr(ltrim($query_text), 0, 6))) {
                case 'insert':
                    $query_type = self::INSERT;
                    break;
                case 'update':
                    $query_type = self::UPDATE;
                    break;
                case 'delete':
                    $query_type = self::DELETE;
                    break;
                case 'select':
                    $query_type = self::SELECT;
                    break;
                default:
                    $query_type = self::QUERY;
                    break;
            }
        }
        /**
         * @see Zend_Db_Profiler_Query
         */
        #require_once 'Zend/Db/Profiler/Query.php';
        $this->_query_profiles[] = new Zend_Db_Profiler_Query($query_text, $query_type);
        end($this->_query_profiles);
        return key($this->_query_profiles);
    }
    /**
     * Ends a query. Pass it the handle that was returned by queryStart().
     * This will mark the query as ended and save the time.
     *
     * @param  integer $queryId
     * @throws Zend_Db_Profiler_Exception
     * @return string   Inform that a query is stored or ignored.
     */
    public function query_end($query_id): string
    {
        // Don't do anything if the Zend_Db_Profiler is not enabled.
        if (!$this->_enabled) {
            return self::IGNORED;
        }
        // Check for a valid query handle.
        if (!isset($this->_query_profiles[$query_id])) {
            /**
             * @see Zend_Db_Profiler_Exception
             */
            #require_once 'Zend/Db/Profiler/Exception.php';
            throw new Zend_Db_Profiler_Exception("Profiler has no query with handle '{$query_id}'.");
        }
        $qp = $this->_query_profiles[$query_id];
        // Ensure that the query profile has not already ended
        if ($qp->has_ended()) {
            /**
             * @see Zend_Db_Profiler_Exception
             */
            #require_once 'Zend/Db/Profiler/Exception.php';
            throw new Zend_Db_Profiler_Exception("Query with profiler handle '{$query_id}' has already ended.");
        }
        // End the query profile so that the elapsed time can be calculated.
        $qp->end();
        /**
         * If filtering by elapsed time is enabled, only keep the profile if
         * it ran for the minimum time.
         */
        if (null !== $this->_filter_elapsed_secs && $qp->get_elapsed_secs() < $this->_filter_elapsed_secs) {
            unset($this->_query_profiles[$query_id]);
            return self::IGNORED;
        }
        /**
         * If filtering by query type is enabled, only keep the query if
         * it was one of the allowed types.
         */
        if (null !== $this->_filter_types && !($qp->get_query_type() & $this->_filter_types)) {
            unset($this->_query_profiles[$query_id]);
            return self::IGNORED;
        }
        return self::STORED;
    }
    /**
     * Get a profile for a query.  Pass it the same handle that was returned
     * by queryStart() and it will return a Zend_Db_Profiler_Query object.
     *
     * @param  integer $queryId
     * @throws Zend_Db_Profiler_Exception
     * @return Zend_Db_Profiler_Query
     */
    public function get_query_profile($query_id)
    {
        if (!array_key_exists($query_id, $this->_query_profiles)) {
            /**
             * @see Zend_Db_Profiler_Exception
             */
            #require_once 'Zend/Db/Profiler/Exception.php';
            throw new Zend_Db_Profiler_Exception("Query handle '{$query_id}' not found in profiler log.");
        }
        return $this->_query_profiles[$query_id];
    }
    /**
     * Get an array of query profiles (Zend_Db_Profiler_Query objects).  If $queryType
     * is set to one of the Zend_Db_Profiler::* constants then only queries of that
     * type will be returned.  Normally, queries that have not yet ended will
     * not be returned unless $showUnfinished is set to True.  If no
     * queries were found, False is returned. The returned array is indexed by the query
     * profile handles.
     *
     * @param  integer $queryType
     * @param  boolean $showUnfinished
     * @return array|false
     */
    public function get_query_profiles($query_type = null, $show_unfinished = false)
    {
        $query_profiles = [];
        foreach ($this->_query_profiles as $key => $qp) {
            if ($query_type === null) {
                $condition = true;
            } else {
                $condition = $qp->get_query_type() & $query_type;
            }
            if (($qp->has_ended() || $show_unfinished) && $condition) {
                $query_profiles[$key] = $qp;
            }
        }
        if (empty($query_profiles)) {
            return false;
        }
        return $query_profiles;
    }
    /**
     * Get the total elapsed time (in seconds) of all of the profiled queries.
     * Only queries that have ended will be counted.  If $queryType is set to
     * one or more of the Zend_Db_Profiler::* constants, the elapsed time will be calculated
     * only for queries of the given type(s).
     *
     * @param  integer $queryType OPTIONAL
     * @return float
     */
    public function get_total_elapsed_secs($query_type = null)
    {
        $elapsed_secs = 0;
        foreach ($this->_query_profiles as $qp) {
            if (null === $query_type) {
                $condition = true;
            } else {
                $condition = $qp->get_query_type() & $query_type;
            }
            if ($qp->has_ended() && $condition) {
                $elapsed_secs += $qp->get_elapsed_secs();
            }
        }
        return $elapsed_secs;
    }
    /**
     * Get the total number of queries that have been profiled.  Only queries that have ended will
     * be counted.  If $queryType is set to one of the Zend_Db_Profiler::* constants, only queries of
     * that type will be counted.
     *
     * @param  integer $queryType OPTIONAL
     */
    public function get_total_num_queries($query_type = null): int
    {
        if (null === $query_type) {
            return count($this->_query_profiles);
        }
        $num_queries = 0;
        foreach ($this->_query_profiles as $qp) {
            if ($qp->has_ended() && $qp->get_query_type() & $query_type) {
                $num_queries++;
            }
        }
        return $num_queries;
    }
    /**
     * Get the Zend_Db_Profiler_Query object for the last query that was run, regardless if it has
     * ended or not.  If the query has not ended, its end time will be null.  If no queries have
     * been profiled, false is returned.
     *
     * @return Zend_Db_Profiler_Query|false
     */
    public function get_last_query_profile()
    {
        if (empty($this->_query_profiles)) {
            return false;
        }
        end($this->_query_profiles);
        return current($this->_query_profiles);
    }
}