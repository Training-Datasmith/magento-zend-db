<?php

namespace ZendDbTest\Adapter {

    class Notanadapter
    {
    }

    class Extendsqlite extends \Zend_Db_Adapter_Pdo_Sqlite
    {
    }
}

namespace {

    class ZendDbTest_Adapter_Notanadapter extends \ZendDbTest\Adapter\Notanadapter
    {
    }

    class ZendDbTest_Adapter_Extendsqlite extends \ZendDbTest\Adapter\Extendsqlite
    {
    }
}
