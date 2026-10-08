<?php

namespace ZendDbTest;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var Zend_Db_Adapter_Abstract|null */
    protected $db;

    protected function sqliteAdapter()
    {
        return Zend_Db::factory('Pdo_Sqlite', array('dbname' => ':memory:'));
    }

    protected function setUp()
    {
        parent::setUp();
        Zend_Db_Table_Abstract::setDefaultAdapter(null);
        Zend_Db_Table_Abstract::setDefaultMetadataCache(null);
    }

    protected function tearDown()
    {
        if ($this->db instanceof Zend_Db_Adapter_Abstract) {
            $this->db->closeConnection();
        }
        $this->db = null;
        Zend_Db_Table_Abstract::setDefaultAdapter(null);
        Zend_Db_Table_Abstract::setDefaultMetadataCache(null);
        parent::tearDown();
    }
}
