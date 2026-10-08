<?php

namespace ZendDbTest;

use Zend_Db_Expr;

class ExprTest extends TestCase
{
    public function testToStringReturnsConstructorText()
    {
        $expr = new Zend_Db_Expr('COUNT(*)');
        $this->assertSame('COUNT(*)', (string) $expr);
    }

    public function testNonStringIsCast()
    {
        $expr = new Zend_Db_Expr(12);
        $this->assertSame('12', (string) $expr);
    }
}
