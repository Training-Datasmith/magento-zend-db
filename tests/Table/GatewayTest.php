<?php

namespace ZendDbTest\Table;

use ZendDbTest\Fixtures\Bugs;
use ZendDbTest\Fixtures\BugsDefaultStatus;
use ZendDbTest\Fixtures\BugsDependent;
use ZendDbTest\Fixtures\BugsWithBadPrimary;
use ZendDbTest\Fixtures\Codes;
use ZendDbTest\Fixtures\Comments;
use ZendDbTest\TestCase;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Definition;
use Zend_Db_Table_Exception;
use Zend_Db_Table_Row_Exception;
use Zend_Db_Table_Rowset_Exception;
use Zend_Db_Table_Select_Exception;

class GatewayTest extends TestCase
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
        $this->db->query('CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bug_id INTEGER,
            body TEXT
        )');
        $this->db->query('CREATE TABLE codes (
            code VARCHAR(10) PRIMARY KEY,
            label VARCHAR(50)
        )');
        Zend_Db_Table_Abstract::setDefaultAdapter($this->db);
    }

    public function testInsertFindFetchCreateRowSave()
    {
        $bugs = new Bugs();
        $id = $bugs->insert(array('name' => 'first', 'status' => 'open', 'score' => 1));
        $this->assertSame(1, (int) $id);
        $rowset = $bugs->find(1);
        $this->assertCount(1, $rowset);
        $this->assertSame('first', $rowset->current()->name);
        $this->assertSame('first', $bugs->fetchRow($bugs->select()->where('name = ?', 'first'))->name);
        $this->assertCount(1, $bugs->fetchAll());
        $row = $bugs->createRow();
        $row->name = 'second';
        $row->status = 'open';
        $row->score = 2;
        $row->save();
        $this->assertCount(2, $bugs->fetchAll());
        $row->name = 'second-renamed';
        $row->save();
        $this->assertSame('second-renamed', $bugs->find(2)->current()->name);
    }

    public function testFindZeroAndEmptyStringKeys()
    {
        $codes = new Codes();
        $codes->insert(array('code' => '0', 'label' => 'zero'));
        $this->db->insert('codes', array('code' => '', 'label' => 'empty'));
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'b1', 'status' => 'x', 'score' => 1));
        $this->assertCount(1, $codes->find(0));
        $this->assertSame('zero', $codes->find(0)->current()->label);
        $this->assertCount(1, $codes->find(''));
        $this->assertSame('empty', $codes->find('')->current()->label);
        $this->assertCount(1, $bugs->find(1));
        $this->assertCount(0, $bugs->find(array()));
    }

    public function testFindArgumentCount()
    {
        $bugs = new Bugs();
        $this->expectException(Zend_Db_Table_Exception::class);
        $bugs->find();
    }

    public function testPartialPrimaryKeyIsRejected()
    {
        $this->expectException(Zend_Db_Table_Exception::class);
        $bad = new BugsWithBadPrimary();
        $bad->info();
    }

    public function testReadOnlyRowCannotSaveOrDelete()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'ro', 'status' => 'x', 'score' => 1));
        $row = $bugs->fetchRow();
        $row->setReadOnly(true);
        $this->expectException(Zend_Db_Table_Row_Exception::class);
        $row->save();
    }

    public function testRowArrayAccessAndMissingColumn()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'arr', 'status' => 'x', 'score' => 1));
        $row = $bugs->fetchRow();
        $this->assertSame('arr', $row->name);
        $this->assertSame('arr', $row['name']);
        $this->assertTrue(isset($row['name']));
        $this->assertFalse(isset($row['missing']));
        $this->expectException(Zend_Db_Table_Row_Exception::class);
        $row->missing;
    }

    public function testUnsetPrimaryThrows()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'pk', 'status' => 'x', 'score' => 1));
        $row = $bugs->fetchRow();
        $this->expectException(Zend_Db_Table_Row_Exception::class);
        unset($row->id);
    }

    public function testDependentRowsetAndCascadeDelete()
    {
        $bugs = new BugsDependent();
        $comments = new Comments();
        $bugId = $bugs->insert(array('name' => 'parent', 'status' => 'x', 'score' => 1));
        $comments->insert(array('bug_id' => $bugId, 'body' => 'c1'));
        $comments->insert(array('bug_id' => $bugId, 'body' => 'c2'));
        $bug = $bugs->find($bugId)->current();
        $this->assertCount(2, $bug->findDependentRowset('ZendDbTest\\Fixtures\\Comments'));
        $bug->delete();
        $this->assertCount(0, $comments->fetchAll());
        $this->assertCount(0, $bugs->fetchAll());
    }

    public function testTableSelectAssembleFromOnly()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'sel', 'status' => 'x', 'score' => 1));
        $sql = $bugs->select(true)->assemble();
        $this->assertContains('FROM', $sql);
        $this->assertNotContains('INNER JOIN', $sql);
    }

    public function testTableSelectIntegrityRejectsJoin()
    {
        $bugs = new Bugs();
        $bad = $bugs->select(true)->join('comments', 'bugs.id = comments.bug_id');
        $this->expectException(Zend_Db_Table_Select_Exception::class);
        $bad->assemble();
    }

    public function testTableSelectIntegrityCanBeDisabled()
    {
        $bugs = new Bugs();
        $ok = $bugs->select(true)->setIntegrityCheck(false)->join('comments', 'bugs.id = comments.bug_id');
        $this->assertContains('JOIN', $ok->assemble());
    }

    public function testTableSelectIsReadOnlyWithExpr()
    {
        $bugs = new Bugs();
        $exprSelect = $bugs->select(true)->columns(new \Zend_Db_Expr('1'));
        $this->assertTrue($exprSelect->isReadOnly());
        $this->assertFalse($bugs->select(true)->isReadOnly());
    }

    public function testRowsetIteratorSeekAndBounds()
    {
        $bugs = new Bugs();
        $bugs->insert(array('name' => 'r1', 'status' => 'x', 'score' => 1));
        $bugs->insert(array('name' => 'r2', 'status' => 'x', 'score' => 2));
        $rowset = $bugs->fetchAll();
        $this->assertCount(2, $rowset);
        $names = array();
        foreach ($rowset as $row) {
            $names[] = $row->name;
        }
        $this->assertContains('r1', $names);
        $this->assertContains('r2', $names);
        $rowset->seek(1);
        $this->assertSame('r2', $rowset->current()->name);
        $this->expectException(Zend_Db_Table_Rowset_Exception::class);
        $rowset->seek(5);
    }

    public function testDefinitionBuildsTable()
    {
        $def = new Zend_Db_Table_Definition();
        $def->setOptions(array('bugs' => array('name' => 'bugs', 'primary' => 'id')));
        $this->assertTrue($def->hasTableConfig('bugs'));
        $table = new \Zend_Db_Table(array_merge(
            $def->getTableConfig('bugs'),
            array('db' => $this->db)
        ));
        $table->insert(array('name' => 'def', 'status' => 'x', 'score' => 1));
        $this->assertCount(1, $table->fetchAll());
        $def->removeTableConfig('bugs');
        $this->assertFalse($def->hasTableConfig('bugs'));
    }

    public function testDefaultValuesOnCreateRow()
    {
        $bugs = new BugsDefaultStatus();
        $row = $bugs->createRow();
        $this->assertSame('new', $row->status);
        $row->name = 'd';
        $row->score = 1;
        $row->save();
        $this->assertGreaterThan(0, $bugs->fetchAll()->count());
    }
}
