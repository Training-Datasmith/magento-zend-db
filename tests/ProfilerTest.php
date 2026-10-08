<?php

namespace ZendDbTest;

use Zend_Db_Profiler;
use Zend_Db_Profiler_Exception;
use Zend_Db_Profiler_Query;

class ProfilerTest extends TestCase
{
    public function testDisabledByDefaultAndQueryStartReturnsNull()
    {
        $profiler = new Zend_Db_Profiler();
        $this->assertFalse($profiler->getEnabled());
        $this->assertNull($profiler->queryStart('SELECT 1'));
        $this->assertFalse($profiler->getLastQueryProfile());
    }

    public function testQueryStartInfersTypeFromSqlPrefix()
    {
        $profiler = new Zend_Db_Profiler(true);
        $selectId = $profiler->queryStart('SELECT 1');
        $profiler->queryEnd($selectId);
        $this->assertSame(Zend_Db_Profiler::SELECT, $profiler->getQueryProfile($selectId)->getQueryType());

        $updateId = $profiler->queryStart('  update t set x=1');
        $profiler->queryEnd($updateId);
        $this->assertSame(Zend_Db_Profiler::UPDATE, $profiler->getQueryProfile($updateId)->getQueryType());

        $deleteId = $profiler->queryStart('DELETE FROM t');
        $profiler->queryEnd($deleteId);
        $this->assertSame(Zend_Db_Profiler::DELETE, $profiler->getQueryProfile($deleteId)->getQueryType());

        $insertId = $profiler->queryStart('insert into t values (1)');
        $profiler->queryEnd($insertId);
        $this->assertSame(Zend_Db_Profiler::INSERT, $profiler->getQueryProfile($insertId)->getQueryType());

        $otherId = $profiler->queryStart('BEGIN');
        $profiler->queryEnd($otherId);
        $this->assertSame(Zend_Db_Profiler::QUERY, $profiler->getQueryProfile($otherId)->getQueryType());
    }

    public function testQueryEndStoresAndSecondEndThrows()
    {
        $profiler = new Zend_Db_Profiler(true);
        $id = $profiler->queryStart('SELECT 1', Zend_Db_Profiler::SELECT);
        $this->assertSame(Zend_Db_Profiler::STORED, $profiler->queryEnd($id));
        $qp = $profiler->getQueryProfile($id);
        $this->assertTrue($qp->hasEnded());
        $this->assertInternalType('float', $qp->getElapsedSecs());
        $this->assertGreaterThanOrEqual(0, $qp->getElapsedSecs());

        $this->expectException(Zend_Db_Profiler_Exception::class);
        $profiler->queryEnd($id);
    }

    public function testUnknownHandleThrows()
    {
        $profiler = new Zend_Db_Profiler(true);
        $this->expectException(Zend_Db_Profiler_Exception::class);
        $profiler->queryEnd(99);
    }

    public function testTypeFilterDropsOtherTypes()
    {
        $profiler = new Zend_Db_Profiler(true);
        $profiler->setFilterQueryType(Zend_Db_Profiler::INSERT);
        $selectId = $profiler->queryStart('SELECT 1');
        $this->assertSame(Zend_Db_Profiler::IGNORED, $profiler->queryEnd($selectId));
        $this->assertFalse($profiler->getQueryProfiles());

        $insertId = $profiler->queryStart('INSERT INTO t VALUES (1)');
        $this->assertSame(Zend_Db_Profiler::STORED, $profiler->queryEnd($insertId));
        $profiles = $profiler->getQueryProfiles();
        $this->assertInternalType('array', $profiles);
        $this->assertArrayHasKey($insertId, $profiles);
    }

    public function testElapsedFilterUsesThresholdWithoutSleep()
    {
        $profiler = new Zend_Db_Profiler(true);
        $profiler->setFilterElapsedSecs(100000);
        $id = $profiler->queryStart('SELECT 1');
        $this->assertSame(Zend_Db_Profiler::IGNORED, $profiler->queryEnd($id));

        $profiler->setFilterElapsedSecs(0);
        $id2 = $profiler->queryStart('SELECT 2');
        $this->assertSame(Zend_Db_Profiler::STORED, $profiler->queryEnd($id2));
    }

    public function testClearAndClone()
    {
        $profiler = new Zend_Db_Profiler(true);
        $id = $profiler->queryStart('SELECT 1');
        $profiler->queryEnd($id);
        $profiler->clear();
        $this->assertFalse($profiler->getLastQueryProfile());
        $this->assertFalse($profiler->getQueryProfiles());

        $id = $profiler->queryStart('SELECT 1');
        $profiler->queryEnd($id);
        $cloneId = $profiler->queryClone($profiler->getQueryProfile($id));
        $clone = $profiler->getQueryProfile($cloneId);
        $this->assertFalse($clone->hasEnded());
        $this->assertSame('SELECT 1', $clone->getQuery());
        $this->assertSame(array(), $clone->getQueryParams());
    }

    public function testBindParamsReindexesPositionalFromOne()
    {
        $qp = new Zend_Db_Profiler_Query('SELECT ?', Zend_Db_Profiler::SELECT);
        $qp->bindParams(array('a', 'b'));
        $params = $qp->getQueryParams();
        $this->assertArrayHasKey(1, $params);
        $this->assertArrayHasKey(2, $params);
        $this->assertSame('a', $params[1]);
        $this->assertSame('b', $params[2]);

        $qp2 = new Zend_Db_Profiler_Query('SELECT :id', Zend_Db_Profiler::SELECT);
        $qp2->bindParams(array(':id' => 5));
        $this->assertSame(5, $qp2->getQueryParams()[':id']);
    }

    public function testFilteredTypeCountIgnoresUnfinished()
    {
        $profiler = new Zend_Db_Profiler(true);
        $ended = $profiler->queryStart('SELECT 1');
        $profiler->queryEnd($ended);
        $profiler->queryStart('SELECT 2');
        $this->assertSame(1, $profiler->getTotalNumQueries(Zend_Db_Profiler::SELECT));
        $this->assertInternalType('float', $profiler->getTotalElapsedSecs(Zend_Db_Profiler::SELECT));
    }
}
