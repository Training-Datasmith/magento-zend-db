<?php

namespace ZendDbTest\Statement;

use ZendDbTest\TestCase;
use Zend_Db_Statement_Exception;

class BindTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->db = $this->sqliteAdapter();
        $this->db->query('CREATE TABLE bugs (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->insert('bugs', array('name' => 'one'));
    }

    public function testBindParamByReferenceChangesResult()
    {
        $stmt = $this->db->prepare('SELECT ?');
        $v = 'aa';
        $stmt->bindParam(1, $v);
        $stmt->execute();
        $this->assertSame('aa', $stmt->fetchColumn());
        $v = 'bb';
        $stmt->execute();
        $this->assertSame('bb', $stmt->fetchColumn());
    }

    public function testBindValueIsSnapshot()
    {
        $stmt = $this->db->prepare('SELECT ?');
        $v = 'aa';
        $stmt->bindValue(1, $v);
        $v = 'bb';
        $stmt->execute();
        $this->assertSame('aa', $stmt->fetchColumn());
    }

    public function testNamedBind()
    {
        $stmt = $this->db->prepare('SELECT :name');
        $v = 'bind';
        $stmt->bindParam('name', $v);
        $stmt->execute();
        $this->assertSame('bind', $stmt->fetchColumn());

        $stmt2 = $this->db->prepare('SELECT :x');
        $this->expectException(Zend_Db_Statement_Exception::class);
        $stmt2->bindParam('missing', $v);
    }

    public function testOutOfRangePositionalBindThrowsStatementException()
    {
        $stmt = $this->db->prepare('SELECT name FROM bugs WHERE id = ?');
        $v = 1;
        $this->expectException(Zend_Db_Statement_Exception::class);
        $stmt->bindParam(50, $v);
    }

    public function testExecuteProfilesBoundParams()
    {
        $this->db->setProfiler(true);
        $stmt = $this->db->prepare('SELECT ?');
        $stmt->execute(array(7));
        $qp = $this->db->getProfiler()->getLastQueryProfile();
        $this->assertContains(7, $qp->getQueryParams());
    }
}
