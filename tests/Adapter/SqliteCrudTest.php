<?php

namespace ZendDbTest\Adapter;

use ZendDbTest\TestCase;
use Zend_Db;
use Zend_Db_Adapter_Exception;
use Zend_Db_Expr;
use Zend_Db_Profiler;

class SqliteCrudTest extends TestCase
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
    }

    public function testInsertUpdateDeleteRoundTrip()
    {
        $this->db->insert('bugs', array('name' => 'alpha', 'status' => 'open', 'score' => 1));
        $this->db->insert('bugs', array('name' => 'beta', 'status' => 'open', 'score' => 2));
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT id FROM bugs WHERE name = ?', array('alpha')));

        $this->db->update('bugs', array('name' => 'alpha2'), array('id = ?' => 1));
        $this->assertSame('alpha2', $this->db->fetchOne('SELECT name FROM bugs WHERE id = 1'));
        $this->assertSame('beta', $this->db->fetchOne('SELECT name FROM bugs WHERE id = 2'));

        $this->db->delete('bugs', array('id = ?' => 1));
        $this->assertCount(1, $this->db->fetchAll('SELECT * FROM bugs'));
        $this->assertSame('beta', $this->db->fetchOne('SELECT name FROM bugs'));
    }

    public function testInsertExprSkipsBinding()
    {
        $this->db->setProfiler(true);
        $this->db->insert('bugs', array(
            'name' => 'expr-row',
            'status' => new Zend_Db_Expr("'open'"),
            'score' => 1,
        ));
        $row = $this->db->fetchRow('SELECT status FROM bugs WHERE name = ?', array('expr-row'));
        $this->assertSame('open', $row['status']);
    }

    public function testFetchHelpers()
    {
        $this->db->insert('bugs', array('name' => 'a', 'status' => 'x', 'score' => 1));
        $this->db->insert('bugs', array('name' => 'b', 'status' => 'x', 'score' => 2));
        $all = $this->db->fetchAll('SELECT * FROM bugs ORDER BY id');
        $this->assertCount(2, $all);
        $this->assertSame('a', $this->db->fetchRow('SELECT * FROM bugs ORDER BY id')['name']);
        $this->assertSame('a', $this->db->fetchOne('SELECT name FROM bugs ORDER BY id'));
        $this->assertSame(array('a', 'b'), $this->db->fetchCol('SELECT name FROM bugs ORDER BY id'));
        $pairs = $this->db->fetchPairs('SELECT id, name FROM bugs ORDER BY id');
        $this->assertSame('a', $pairs[1]);
        $assoc = $this->db->fetchAssoc('SELECT id, name FROM bugs ORDER BY id');
        $this->assertArrayHasKey(1, $assoc);
        $this->assertSame('a', $assoc[1]['name']);
    }

    public function testFetchAssocAndPairsOmitNullKeys()
    {
        $rows = $this->db->fetchAssoc("SELECT NULL AS k, 'x' AS v UNION ALL SELECT 'a', 'b'");
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('a', $rows);
        $pairs = $this->db->fetchPairs("SELECT NULL AS k, 'x' UNION ALL SELECT 'a', 'b'");
        $this->assertCount(1, $pairs);
        $this->assertArrayHasKey('a', $pairs);
    }

    public function testFetchModes()
    {
        $this->db->insert('bugs', array('name' => 'mode', 'status' => 'x', 'score' => 1));
        $this->db->setFetchMode(Zend_Db::FETCH_NUM);
        $row = $this->db->fetchRow('SELECT name FROM bugs');
        $this->assertSame(0, key($row));
        $this->db->setFetchMode(Zend_Db::FETCH_OBJ);
        $obj = $this->db->fetchRow('SELECT name FROM bugs');
        $this->assertInstanceOf('stdClass', $obj);
        $this->assertSame('mode', $obj->name);
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->db->setFetchMode(99);
    }

    public function testCaseFoldingUpper()
    {
        $db = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => ':memory:',
            'options' => array(Zend_Db::CASE_FOLDING => Zend_Db::CASE_UPPER),
        ));
        $db->query('CREATE TABLE bugs (id INTEGER, name TEXT)');
        $db->insert('bugs', array('id' => 1, 'name' => 'n'));
        $row = $db->fetchRow('SELECT name FROM bugs');
        $this->assertArrayHasKey('NAME', $row);
        $this->assertArrayNotHasKey('name', $row);
    }

    public function testTransactionsCommitAndRollback()
    {
        $this->db->beginTransaction();
        $this->db->insert('bugs', array('name' => 'tx', 'status' => 'x', 'score' => 1));
        $this->db->rollBack();
        $this->assertCount(0, $this->db->fetchAll('SELECT * FROM bugs'));

        $this->db->beginTransaction();
        $this->db->insert('bugs', array('name' => 'tx2', 'status' => 'x', 'score' => 1));
        $this->db->commit();
        $this->assertSame('tx2', $this->db->fetchOne('SELECT name FROM bugs'));
    }

    public function testProfilerSeesInsert()
    {
        $this->db->setProfiler(true);
        $this->db->insert('bugs', array('name' => 'p', 'status' => 'x', 'score' => 1));
        $qp = $this->db->getProfiler()->getLastQueryProfile();
        $this->assertContains('INSERT', $qp->getQuery());
        $this->assertContains('bugs', $qp->getQuery());
        $this->assertSame(Zend_Db_Profiler::INSERT, $qp->getQueryType());
    }

    public function testListAndDescribeTable()
    {
        $tables = $this->db->listTables();
        $this->assertContains('bugs', $tables);
        $desc = $this->db->describeTable('bugs');
        $this->assertTrue($desc['id']['PRIMARY']);
        $this->assertSame(1, (int) $desc['id']['PRIMARY_POSITION']);
        $this->assertTrue($desc['id']['IDENTITY']);
        $this->assertSame(100, (int) $desc['name']['LENGTH']);
        $this->assertFalse($desc['name']['NULLABLE']);
        $this->assertTrue($desc['status']['NULLABLE']);
    }

    public function testQuoteRoundTripAndTypes()
    {
        $this->db->query('CREATE TABLE q (a TEXT)');
        $q1 = $this->db->quote("a'b");
        $this->db->query("INSERT INTO q (a) VALUES ($q1)");
        $row = $this->db->fetchRow('SELECT a FROM q');
        $this->assertSame("a'b", $row['a']);

        $this->assertSame('42', $this->db->quote(42, Zend_Db::INT_TYPE));
        $this->assertSame('42', $this->db->quote('42abc', 'INTEGER'));
        $this->assertSame('0', $this->db->quote('abc', Zend_Db::INT_TYPE));
        $this->assertSame('0x10', $this->db->quote('0x10', Zend_Db::BIGINT_TYPE));
        $this->assertSame('1.500000', $this->db->quote(1.5, Zend_Db::FLOAT_TYPE));
        $this->assertSame('1+1', $this->db->quote(new Zend_Db_Expr('1+1')));
        $this->assertContains(',', $this->db->quote(array(1, 'a')));
    }

    public function testQuoteIntoCount()
    {
        $one = $this->db->quoteInto('a=? AND b=?', 'x', null, 1);
        $this->assertContains('x', $one);
        $this->assertContains('?', $one);
        $zero = $this->db->quoteInto('id=?', 0);
        $this->assertContains('0', $zero);
        $this->assertNotContains('?', $zero);
    }

    public function testQuoteIdentifier()
    {
        $this->assertSame('"bugs"', $this->db->quoteIdentifier('bugs'));
        $this->assertSame('"my""t"', $this->db->quoteIdentifier('my"t'));
        $this->assertSame('"s"."t"', $this->db->quoteIdentifier(array('s', 't')));
        $this->assertSame('t', $this->db->quoteIdentifier(new Zend_Db_Expr('t')));

        $off = Zend_Db::factory('Pdo_Sqlite', array(
            'dbname' => ':memory:',
            'options' => array(Zend_Db::AUTO_QUOTE_IDENTIFIERS => false),
        ));
        $this->assertSame('bugs', $off->quoteIdentifier('bugs', true));
    }

    public function testLimitRejectsInvalidCount()
    {
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->db->limit('SELECT 1', 0);
    }

    public function testLimitRejectsInvalidOffset()
    {
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $this->db->limit('SELECT 1', 2, -1);
    }

    public function testLimitAppendsCountAndOffset()
    {
        $limited = $this->db->limit('SELECT 1', 2, 3);
        $this->assertContains('LIMIT 2', $limited);
        $this->assertContains('OFFSET 3', $limited);
    }

    public function testPositionalAndNamedQuery()
    {
        $this->db->insert('bugs', array('name' => 'bind', 'status' => 'x', 'score' => 1));
        $id = (int) $this->db->fetchOne('SELECT id FROM bugs WHERE name = ?', array('bind'));
        $this->assertSame('bind', $this->db->fetchOne('SELECT name FROM bugs WHERE id = ?', array($id)));
        $rows = $this->db->fetchAll('SELECT name FROM bugs WHERE name = :name', array('name' => 'bind'));
        $this->assertCount(1, $rows);
    }
}
