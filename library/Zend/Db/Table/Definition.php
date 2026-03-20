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
 * Class for SQL table interface.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Table
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Table_Definition
{
    /**
     * @var array
     */
    protected $_table_configs = [];
    /**
     * __construct()
     *
     * @param array|Zend_Config $options
     */
    public function __construct($options = null)
    {
        if ($options instanceof Zend_Config) {
            $this->set_config($options);
        } elseif (is_array($options)) {
            $this->set_options($options);
        }
    }
    /**
     * setConfig()
     */
    public function set_config(Zend_Config $config): self
    {
        $this->set_options($config->to_array());
        return $this;
    }
    /**
     * setOptions()
     */
    public function set_options(array $options): self
    {
        foreach ($options as $option_name => $option_value) {
            $this->set_table_config($option_name, $option_value);
        }
        return $this;
    }
    /**
     * @param string $tableName
     */
    public function set_table_config($table_name, array $table_config): self
    {
        // @todo logic here
        $table_config[Zend_Db_Table::DEFINITION_CONFIG_NAME] = $table_name;
        $table_config[Zend_Db_Table::DEFINITION] = $this;
        if (!isset($table_config[Zend_Db_Table::NAME])) {
            $table_config[Zend_Db_Table::NAME] = $table_name;
        }
        $this->_table_configs[$table_name] = $table_config;
        return $this;
    }
    /**
     * getTableConfig()
     *
     * @param string $tableName
     * @return array
     */
    public function get_table_config($table_name)
    {
        return $this->_table_configs[$table_name];
    }
    /**
     * removeTableConfig()
     *
     * @param string $tableName
     */
    public function remove_table_config($table_name)
    {
        unset($this->_table_configs[$table_name]);
    }
    /**
     * hasTableConfig()
     *
     * @param string $tableName
     */
    public function has_table_config($table_name): bool
    {
        return isset($this->_table_configs[$table_name]);
    }
}