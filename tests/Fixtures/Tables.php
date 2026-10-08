<?php

namespace ZendDbTest\Fixtures;

class Bugs extends \Zend_Db_Table_Abstract
{
    protected $_name = 'bugs';
    protected $_primary = 'id';
}

class Comments extends \Zend_Db_Table_Abstract
{
    protected $_name = 'comments';
    protected $_primary = 'id';

    protected $_referenceMap = array(
        'Bug' => array(
            'columns' => 'bug_id',
            'refTableClass' => 'ZendDbTest\\Fixtures\\BugsDependent',
            'refColumns' => 'id',
            'onDelete' => \Zend_Db_Table_Abstract::CASCADE,
        ),
    );
}

class Codes extends \Zend_Db_Table_Abstract
{
    protected $_name = 'codes';
    protected $_primary = 'code';
}

class BugsWithBadPrimary extends Bugs
{
    protected $_primary = array('id', 'not_a_column');
}

class BugsDefaultStatus extends Bugs
{
    protected $_defaultSource = self::DEFAULT_CLASS;
    protected $_defaultValues = array('status' => 'new');
}

class BugsDependent extends Bugs
{
    protected $_dependentTables = array('ZendDbTest\\Fixtures\\Comments');
}

class BugsCompositePrimary extends Bugs
{
    protected $_primary = array('id', 'name');
}

class BugsIdOnlyPrimary extends BugsCompositePrimary
{
    protected $_primary = array('id');
}
