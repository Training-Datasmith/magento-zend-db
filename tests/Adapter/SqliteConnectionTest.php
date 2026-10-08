<?php

namespace ZendDbTest\Adapter;

use ZendDbTest\TestCase;
use Zend_Db;
use Zend_Db_Adapter_Exception;
use Zend_Db_Adapter_Pdo_Sqlite;
use PDOException;

class SqliteConnectionTest extends TestCase
{
    /** @var string|null */
    private $dbFile;

    protected function tearDown()
    {
        if ($this->dbFile && file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
        $this->dbFile = null;
        parent::tearDown();
    }

    public function testMissingDbnameThrows()
    {
        $this->expectException(Zend_Db_Adapter_Exception::class);
        new Zend_Db_Adapter_Pdo_Sqlite(array());
    }

    public function testBadPathChainsPdoException()
    {
        $path = sys_get_temp_dir() . '/zenddb-missing-' . uniqid('', true) . '/nope.sqlite';
        $db = new Zend_Db_Adapter_Pdo_Sqlite(array('dbname' => $path));
        try {
            $db->getConnection();
            $this->fail('Expected connection failure');
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->assertTrue($e->hasChainedException());
            $this->assertInstanceOf(PDOException::class, $e->getChainedException());
        }
    }

    public function testCloseAndReconnectOnFileDb()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'zenddb');
        unlink($this->dbFile);
        $this->dbFile .= '.sqlite';
        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => $this->dbFile));
        $db->query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $db->insert('t', array('v' => 'persist'));
        $db->closeConnection();
        $this->assertFalse($db->isConnected());
        $this->assertSame('persist', $db->fetchOne('SELECT v FROM t'));
    }

    public function testSerializationDropsConnection()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'zenddb');
        unlink($this->dbFile);
        $this->dbFile .= '.sqlite';
        $db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => $this->dbFile));
        $db->query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $db->insert('t', array('v' => 'row'));
        $serialized = serialize($db);
        $db2 = unserialize($serialized);
        $this->assertFalse($db2->isConnected());
        $this->assertSame('row', $db2->fetchOne('SELECT v FROM t'));
    }

    public function testSerializationForbiddenWhenDisabled()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'zenddb');
        unlink($this->dbFile);
        $this->dbFile .= '.sqlite';
        $noSer = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => $this->dbFile,
            'options' => array(Zend_Db::ALLOW_SERIALIZATION => false),
        ));
        $this->expectException(Zend_Db_Adapter_Exception::class);
        serialize($noSer);
    }

    public function testAutoReconnectOnUnserialize()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'zenddb');
        unlink($this->dbFile);
        $this->dbFile .= '.sqlite';
        $db = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => $this->dbFile,
            'options' => array(Zend_Db::AUTO_RECONNECT_ON_UNSERIALIZE => true),
        ));
        $db->query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $db->insert('t', array('v' => 'live'));
        $db2 = unserialize(serialize($db));
        $this->assertTrue($db2->isConnected());
        $this->assertSame('live', $db2->fetchOne('SELECT v FROM t'));
    }

    public function testGetServerVersionIsDotted()
    {
        $db = $this->sqliteAdapter();
        $version = $db->getServerVersion();
        $this->assertInternalType('string', $version);
        $this->assertRegExp('/^\d+\.\d+/', $version);
    }
}
