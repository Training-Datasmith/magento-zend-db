<?php

namespace ZendDbTest\Adapter;

use ZendDbTest\TestCase;
use Zend_Db_Adapter_Db2;
use Zend_Db_Adapter_Mysqli;
use Zend_Db_Adapter_Oracle;
use Zend_Db_Adapter_Oracle_Exception;
use Zend_Db_Adapter_Pdo_Ibm_Db2;
use Zend_Db_Adapter_Pdo_Ibm_Ids;
use Zend_Db_Adapter_Pdo_Mssql;
use Zend_Db_Adapter_Pdo_Mysql;
use Zend_Db_Adapter_Pdo_Oci;
use Zend_Db_Adapter_Pdo_Pgsql;
use Zend_Db_Adapter_Sqlsrv;
use Zend_Db_Adapter_Db2_Exception;
use Zend_Db_Adapter_Exception;

class OfflineLimitTest extends TestCase
{
    private function cfg()
    {
        return array('dbname' => 'x', 'username' => 'u', 'password' => 'p');
    }

    public function testMysqlBackticksAndLimit()
    {
        $pdo = new Zend_Db_Adapter_Pdo_Mysql($this->cfg());
        $mysqli = new Zend_Db_Adapter_Mysqli($this->cfg());
        $mysqli = new Zend_Db_Adapter_Mysqli($this->cfg());
        $this->assertSame('`', $pdo->getQuoteIdentifierSymbol());
        $this->assertSame('`', $mysqli->getQuoteIdentifierSymbol());
        $this->assertSame('`a`', $pdo->quoteIdentifier('a'));
        $sql = $pdo->limit('SELECT 1', 2, 4);
        $this->assertContains('LIMIT 2', $sql);
        $this->assertContains('OFFSET 4', $sql);
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $pdo->limit('SELECT 1', 0);
    }

    public function testPgsqlLimitShape()
    {
        $pgsql = new Zend_Db_Adapter_Pdo_Pgsql($this->cfg());
        $sql = $pgsql->limit('SELECT 1', 5);
        $this->assertContains('LIMIT 5', $sql);
        $this->assertNotContains('OFFSET', $sql);
    }

    public function testOracleRownumWindow()
    {
        $ora = new Zend_Db_Adapter_Oracle($this->cfg());
        $sql = $ora->limit('SELECT 1', 2, 3);
        $this->assertContains('ROWNUM', $sql);
        $this->assertContains('4', $sql);
        $this->assertContains('5', $sql);
        $this->assertFalse($ora->supportsParameters('positional'));
        $this->assertTrue($ora->supportsParameters('named'));
        $this->expectException(Zend_Db_Adapter_Oracle_Exception::class);
        $ora->limit('SELECT 1', 0);
    }

    public function testSqlsrvTopAndPositionalOnly()
    {
        $sqlsrv = new Zend_Db_Adapter_Sqlsrv($this->cfg());
        $sql = $sqlsrv->limit('SELECT 1', 2, 0);
        $this->assertContains('TOP 2', $sql);
        $this->assertTrue($sqlsrv->supportsParameters('positional'));
        $this->assertFalse($sqlsrv->supportsParameters('named'));
        $this->expectException(Zend_Db_Adapter_Exception::class);
        $sqlsrv->limit('SELECT 1', 0);
    }

    public function testMssqlTopIncludesOffset()
    {
        $mssql = new Zend_Db_Adapter_Pdo_Mssql($this->cfg());
        $sql = $mssql->limit('SELECT a FROM t', 2, 3);
        $this->assertContains('TOP 5', $sql);
    }

    public function testDb2LimitShape()
    {
        if (!defined('DB2_AUTOCOMMIT_ON')) {
            define('DB2_AUTOCOMMIT_ON', 1);
        }
        if (!defined('DB2_AUTOCOMMIT_OFF')) {
            define('DB2_AUTOCOMMIT_OFF', 0);
        }
        $db2 = new Zend_Db_Adapter_Db2($this->cfg());
        $sql = $db2->limit('SELECT 1', 2, 0);
        $this->assertContains('2', $sql);
        $this->expectException(Zend_Db_Adapter_Db2_Exception::class);
        $db2->limit('SELECT 1', 0);
    }

    public function testIbmHelpersLimitWithoutConnecting()
    {
        $stub = new \Zend_Db_Adapter_Pdo_Sqlite(array('dbname' => ':memory:'));
        $ibmDb2 = new Zend_Db_Adapter_Pdo_Ibm_Db2($stub);
        $ibmIds = new Zend_Db_Adapter_Pdo_Ibm_Ids($stub);
        foreach (array($ibmDb2, $ibmIds) as $adapter) {
            $sql = $adapter->limit('SELECT 1', 2, 0);
            $this->assertContains('2', $sql);
        }
    }

    public function testOciLimitContainsBounds()
    {
        $oci = new Zend_Db_Adapter_Pdo_Oci($this->cfg());
        $sql = $oci->limit('SELECT 1', 2, 3);
        $this->assertContains('4', $sql);
        $this->assertContains('5', $sql);
    }
}
