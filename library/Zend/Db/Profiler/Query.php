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
class Zend_Db_Profiler_Query
{
    /**
     * SQL query string or user comment, set by $query argument in constructor.
     *
     * @var string
     */
    protected $_query = '';
    /**
     * One of the Zend_Db_Profiler constants for query type, set by $queryType argument in constructor.
     *
     * @var integer
     */
    protected $_query_type = 0;
    /**
     * Unix timestamp with microseconds when instantiated.
     *
     * @var float
     */
    protected $_started_microtime;
    /**
     * Unix timestamp with microseconds when self::queryEnd() was called.
     *
     * @var integer
     */
    protected $_ended_microtime;
    /**
     * @var array
     */
    protected $_bound_params = [];
    /**
     * @var array
     */
    /**
     * Class constructor.  A query is about to be started, save the query text ($query) and its
     * type (one of the Zend_Db_Profiler::* constants).
     *
     * @param  string  $query
     * @param  integer $queryType
     */
    public function __construct($query, $query_type)
    {
        $this->_query = $query;
        $this->_query_type = $query_type;
        // by default, and for backward-compatibility, start the click ticking
        $this->start();
    }
    /**
     * Clone handler for the query object.
     */
    public function __clone()
    {
        $this->_bound_params = [];
        $this->_ended_microtime = null;
        $this->start();
    }
    /**
     * Starts the elapsed time click ticking.
     * This can be called subsequent to object creation,
     * to restart the clock.  For instance, this is useful
     * right before executing a prepared query.
     *
     * @return void
     */
    public function start()
    {
        $this->_started_microtime = microtime(true);
    }
    /**
     * Ends the query and records the time so that the elapsed time can be determined later.
     *
     * @return void
     */
    public function end()
    {
        $this->_ended_microtime = microtime(true);
    }
    /**
     * Returns true if and only if the query has ended.
     */
    public function has_ended(): bool
    {
        return $this->_ended_microtime !== null;
    }
    /**
     * Get the original SQL text of the query.
     *
     * @return string
     */
    public function get_query()
    {
        return $this->_query;
    }
    /**
     * Get the type of this query (one of the Zend_Db_Profiler::* constants)
     *
     * @return integer
     */
    public function get_query_type()
    {
        return $this->_query_type;
    }
    /**
     * @param string $param
     * @param mixed $variable
     * @return void
     */
    public function bind_param($param, $variable)
    {
        $this->_bound_params[$param] = $variable;
    }
    /**
     * @param array $param
     * @return void
     */
    public function bind_params(array $params)
    {
        if (array_key_exists(0, $params)) {
            array_unshift($params, null);
            unset($params[0]);
        }
        foreach ($params as $param => $value) {
            $this->bind_param($param, $value);
        }
    }
    /**
     * @return array
     */
    public function get_query_params()
    {
        return $this->_bound_params;
    }
    /**
     * Get the elapsed time (in seconds) that the query ran.
     * If the query has not yet ended, false is returned.
     *
     * @return float|false
     */
    public function get_elapsed_secs()
    {
        if (null === $this->_ended_microtime) {
            return false;
        }
        return $this->_ended_microtime - $this->_started_microtime;
    }
    /**
     * Get the time (in seconds) when the profiler started running.
     *
     * @return bool|float
     */
    public function get_started_microtime()
    {
        if (null === $this->_started_microtime) {
            return false;
        }
        return $this->_started_microtime;
    }
}