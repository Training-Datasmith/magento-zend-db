<?php

namespace ZendDbTest\Table;

use ZendDbTest\Fixtures\Bugs;
use ZendDbTest\Fixtures\BugsCompositePrimary;
use ZendDbTest\Fixtures\BugsIdOnlyPrimary;
use ZendDbTest\Fixtures\Codes;
use ZendDbTest\TestCase;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Row_Exception;

class RowReconnectTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->db = $this->sqliteAdapter();
        $this->db->query('CREATE TABLE bugs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            status VARCHAR(20),
            score INTEGER
        )');
        $this->db->query('CREATE TABLE codes (
            code VARCHAR(10) PRIMARY KEY,
            label VARCHAR(50)
        )');
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    public function testSerializeReconnect()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'live', 'status' => 'x', 'score' => 1));
        $row = $bugs->fetchRow();
        $row2 = unserialize(serialize($row));
        $this->assertFalse($row2->isConnected());
        $this->assertTrue($row2->setTable($bugs));
        $row2->name = 'updated';
        $row2->save();
        $this->assertSame('updated', $bugs->fetchRow()->name);
        $this->assertFalse($row2->setTable(null));
        $this->assertFalse($row2->isConnected());
    }

    public function testSetTableRejectsDifferentPrimary()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'x', 'status' => 's', 'score' => 1));
        $row = $bugs->fetchRow();
        $codes = new Codes();
        $this->expectException(Zend_Db_Table_Row_Exception::class);
        $row->setTable($codes);
    }

    public function testSetTableRejectsPartialPrimaryOverlap()
    {
        $composite = new BugsCompositePrimary();
        $composite->insert(array('name' => 'n', 'status' => 's', 'score' => 1));
        $row = $composite->fetchRow();
        $idOnly = new BugsIdOnlyPrimary();
        $this->expectException(Zend_Db_Table_Row_Exception::class);
        $this->expectExceptionMessage('does not have the same primary key');
        $row->setTable($idOnly);
    }
}
