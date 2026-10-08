<?php

namespace ZendDbTest;

use Zend_Db;
use Zend_Db_Adapter_Pdo_Sqlite;
use Zend_Db_Exception;
use ZendDbTest\Adapter\Extendsqlite;
use ZendDbTest\Adapter\Notanadapter;

class FactoryTest extends TestCase
{
    public function testFactorySqliteRunsAQuery()
    {
        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => ':memory:'));
        $this->assertInstanceOf(Zend_Db_Adapter_Pdo_Sqlite::class, $db);
        $this->assertSame('1', $db->fetchOne('SELECT 1'));
    }

    public function testFactoryNormalizesAdapterName()
    {
        $db = Zend_Db::factory('pdo_sqlite', array('dbname' => ':memory:'));
        $this->assertInstanceOf(Zend_Db_Adapter_Pdo_Sqlite::class, $db);
    }

    public function testEmptyAdapterNameThrows()
    {
        $this->expectException(Zend_Db_Exception::class);
        Zend_Db::factory('', array('dbname' => ':memory:'));
    }

    public function testNonArrayConfigThrows()
    {
        $this->expectException(Zend_Db_Exception::class);
        Zend_Db::factory('Pdo_Sqlite', 'memory');
    }

    public function testCustomNamespaceRejectsNonAdapter()
    {
        $this->expectException(Zend_Db_Exception::class);
        Zend_Db::factory('Notanadapter', array(
            'dbname' => ':memory:',
            'adapterNamespace' => 'ZendDbTest_Adapter',
        ));
    }

    public function testCustomNamespaceAcceptsSubclass()
    {
        $db = Zend_Db::factory('Extendsqlite', array(
            'dbname' => ':memory:',
            'adapterNamespace' => 'ZendDbTest_Adapter',
        ));
        $this->assertInstanceOf(Extendsqlite::class, $db);
    }

    public function testUnknownAdapterThrows()
    {
        $this->expectException(\Zend_Exception::class);
        set_error_handler(function ($severity, $message, $file) {
            if ($severity === E_WARNING
                && strpos($file, 'Zend/Loader.php') !== false
                && strpos($message, 'include_once') !== false
                && strpos($message, 'Zend/Db/Adapter/No/Such/Driver.php') !== false
            ) {
                return true;
            }

            return false;
        });
        try {
            Zend_Db::factory('No_Such_Driver', array('dbname' => 'x'));
        } finally {
            restore_error_handler();
        }
    }
}
