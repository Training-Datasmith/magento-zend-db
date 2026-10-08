<?php

namespace ZendDbTest;

use Zend_Db;
use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Select_Exception;

class SelectTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->db = $this->sqliteAdapter();
        $this->db->query('CREATE TABLE bugs (id INTEGER PRIMARY KEY, name TEXT, score INTEGER, status TEXT)');
        $this->db->query('CREATE TABLE comments (id INTEGER PRIMARY KEY, bug_id INTEGER, body TEXT)');
        $this->db->insert('bugs', array('name' => 'a', 'score' => 1, 'status' => 's'));
        $this->db->insert('bugs', array('name' => 'b', 'score' => 2, 'status' => 's'));
        $this->db->insert('bugs', array('name' => 'c', 'score' => 3, 'status' => 't'));
    }

    public function testFromWhereOrderLimitRoundTrip()
    {
        $select = $this->db->select()->from('bugs', array('name'))
            ->where('score > ?', 0)
            ->order('name ASC')
            ->limit(1, 1);
        $rows = $this->db->fetchAll($select);
        $this->assertCount(1, $rows);
        $this->assertSame('b', $rows[0]['name']);
        $sql = $select->assemble();
        $this->assertContains('FROM', $sql);
        $this->assertContains('"bugs"', $sql);
        $this->assertContains('WHERE', $sql);
        $this->assertContains('ORDER BY', $sql);
        $this->assertContains('LIMIT', $sql);
        $this->assertContains('OFFSET', $sql);
        $this->assertContains('"name" ASC', $sql);
    }

    public function testOrWhereAndHaving()
    {
        $select = $this->db->select()->from('bugs', array('name'))
            ->where('score = ?', 1)
            ->orWhere('score = ?', 2);
        $this->assertContains('OR', $select->assemble());
        $this->assertCount(2, $this->db->fetchAll($select));

        $having = $this->db->select()->from('bugs', array('status'))
            ->group('status')
            ->having('COUNT(*) > ?', 0);
        $this->assertGreaterThanOrEqual(1, count($this->db->fetchAll($having)));
    }

    public function testJoinLeftReturnsUnmatched()
    {
        $select = $this->db->select()->from('bugs', array('name'))
            ->joinLeft('comments', 'bugs.id = comments.bug_id', array('body'));
        $sql = $select->assemble();
        $this->assertContains('LEFT JOIN', $sql);
        $this->assertContains('ON', $sql);
        $rows = $this->db->fetchAll($select);
        $this->assertGreaterThan(0, count($rows));
    }

    public function testJoinUsingQuotesBothSides()
    {
        $this->db->query('CREATE TABLE comments2 (id INTEGER, bug_id INTEGER)');
        $select = $this->db->select()->from('bugs')->joinUsing('comments2', 'bug_id');
        $sql = $select->assemble();
        $this->assertContains('ON', $sql);
        $this->assertContains('bug_id', $sql);
        $this->assertContains('"comments2"', $sql);
        $this->assertContains('"bugs"', $sql);
    }

    public function testExprColumnIsNotQuoted()
    {
        $select = $this->db->select()->from('bugs', array(new Zend_Db_Expr('COUNT(*) AS n')));
        $n = (int) $this->db->fetchOne($select);
        $this->assertSame(3, $n);
    }

    public function testDistinctAndUnionExecute()
    {
        $d = $this->db->select()->from('bugs', array('status'))->distinct();
        $statuses = $this->db->fetchCol($d);
        sort($statuses);
        $this->assertSame(array('s', 't'), $statuses);
        $a = $this->db->select()->from('bugs', array('name'))->where('name = ?', 'a');
        $b = $this->db->select()->from('bugs', array('name'))->where('name = ?', 'c');
        $u = $this->db->select()->union(array($a, $b));
        $this->assertContains('UNION', $u->assemble());
        $names = $this->db->fetchCol($u);
        $this->assertContains('a', $names);
        $this->assertContains('c', $names);
    }

    public function testLimitPageAndForUpdate()
    {
        $select = $this->db->select()->from('bugs')->limitPage(2, 10);
        $sql = $select->assemble();
        $this->assertContains('LIMIT 10', $sql);
        $this->assertContains('OFFSET 10', $sql);
        $fu = $this->db->select()->from('bugs')->forUpdate(true);
        $this->assertContains('FOR UPDATE', $fu->assemble());
    }

    public function testResetAndInvalidPart()
    {
        $select = $this->db->select()->from('bugs')->where('id > ?', 0);
        $select->reset(Zend_Db_Select::WHERE);
        $this->assertSame(array(), $select->getPart(Zend_Db_Select::WHERE));
        $this->assertNotEmpty($select->getPart(Zend_Db_Select::FROM));
        $this->expectException(Zend_Db_Select_Exception::class);
        $select->getPart('nope');
    }

    public function testResetAllClearsFrom()
    {
        $select = $this->db->select()->from('bugs');
        $select->reset();
        $this->assertSame(array(), $select->getPart(Zend_Db_Select::FROM));
    }

    public function testSecondJoinUsesDistinctCorrelation()
    {
        $sql = $this->db->select()->from('bugs')->join('bugs', '1=1')->assemble();
        $this->assertContains('bugs_2', $sql);
    }

    public function testColumnsWithoutFromThrows()
    {
        $this->expectException(Zend_Db_Select_Exception::class);
        $this->db->select()->columns('id');
    }

    public function testJoinBogusUsingThrows()
    {
        $this->expectException(Zend_Db_Select_Exception::class);
        $this->db->select()->from('bugs')->joinBogusUsing('comments', 'id');
    }

    public function testBindIsUsedWhenQueryBindEmpty()
    {
        $select = $this->db->select()->from('bugs', array('name'))
            ->where('name = :name')
            ->bind(array('name' => 'a'));
        $rows = $this->db->fetchAll($select);
        $this->assertCount(1, $rows);
    }

    public function testAutoQuoteOff()
    {
        $off = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => ':memory:',
            'options' => array(Zend_Db::AUTO_QUOTE_IDENTIFIERS => false),
        ));
        $sql = $off->select()->from('bugs', array('name'))->assemble();
        $this->assertNotEmpty($sql);
        $this->assertContains('FROM bugs', $sql);
        $this->assertFalse(strpos($sql, '"bugs"') !== false);
    }

    public function testToStringMatchesAssemble()
    {
        $select = $this->db->select()->from('bugs', array('name'));
        $this->assertSame($select->assemble(), (string) $select);
        $this->assertContains('SELECT', (string) $select);
    }
}
