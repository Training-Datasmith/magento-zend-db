<?php

error_reporting(-1);
ini_set('display_errors', '1');

$zendExceptionFile = dirname(__DIR__) . '/vendor/magento/zend-exception/library/Zend/Exception.php';
if (PHP_VERSION_ID < 70100 && is_readable($zendExceptionFile)) {
    $source = file_get_contents($zendExceptionFile);
    if (strpos($source, '?Exception') !== false) {
        file_put_contents($zendExceptionFile, str_replace('?Exception', 'Exception', $source));
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

require_once __DIR__ . '/Adapter/NotAnAdapter.php';
