<?php

/**
 * Single-process PHPUnit run with Fisher-Yates test reorder (seed 20261008).
 * Exit 1 on test failure; exit 2 if fewer tests ran than discovered.
 */

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

$seed = 20261008;
mt_srand($seed);

$config = PHPUnit\Util\Configuration::getInstance($root . '/phpunit.xml.dist');
$suite = $config->getTestSuiteConfiguration();

$expected = countLeafTests($suite);
$leaves = array();
collectLeafTests($suite, $leaves);
fisherYates($leaves);

$shuffled = new PHPUnit\Framework\TestSuite('SeededZendDb');
foreach ($leaves as $test) {
    $shuffled->addTest($test);
}

$runner = new PHPUnit\TextUI\TestRunner();
$result = $runner->doRun($shuffled, array(), false);

$ran = $result->count();
if (!$result->wasSuccessful()) {
    exit(1);
}
if ($ran < $expected) {
    fwrite(STDERR, "Expected {$expected} tests, ran {$ran}\n");
    exit(2);
}
exit(0);

function collectLeafTests(PHPUnit\Framework\TestSuite $suite, array &$out)
{
    foreach ($suite as $test) {
        if ($test instanceof PHPUnit\Framework\TestSuite) {
            collectLeafTests($test, $out);
        } else {
            $out[] = $test;
        }
    }
}

function countLeafTests(PHPUnit\Framework\TestSuite $suite)
{
    $leaves = array();
    collectLeafTests($suite, $leaves);
    return count($leaves);
}

function fisherYates(array &$items)
{
    $n = count($items);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $tmp = $items[$i];
        $items[$i] = $items[$j];
        $items[$j] = $tmp;
    }
}
