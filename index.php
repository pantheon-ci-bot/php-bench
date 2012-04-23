<?php

ignore_user_abort(TRUE);
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush(1);

define('PHPBENCH_VERSION', '0.8.1');
define('CSV_SEP', ',');
define('CSV_NL', "\n");
define('DEFAULT_BASE', 100);
define('MIN_BASE', 50);

$TESTS_DIRS = array('/usr/local/lib/phpbench/tests',
            '/usr/local/share/phpbench/tests',
            '/usr/lib/phpbench/tests',
            '/usr/share/phpbench/tests',
            '/opt/phpbench/tests',
            'tests',
            '.');

function test_start($func) {
    global $GLOBAL_TEST_FUNC;
    global $GLOBAL_TEST_START_TIME;

    $GLOBAL_TEST_FUNC = $func;
    //echo sprintf('%34s', $func) . "\t";
    flush();
    list($usec, $sec) = explode(' ', microtime());
    $GLOBAL_TEST_START_TIME = $usec + $sec;
}

function test_end($func) {
    global $GLOBAL_TEST_FUNC;
    global $GLOBAL_TEST_START_TIME;

    list($usec, $sec) = explode(' ', microtime());
    $now = $usec + $sec;
    if ($func !== $GLOBAL_TEST_FUNC) {
    trigger_error('Wrong func: [' . $func . '] ' .
              'vs ' . $GLOBAL_TEST_FUNC);
    return FALSE;
    }
    if ($now < $GLOBAL_TEST_START_TIME) {
    trigger_error('Wrong func: [' . $func . '] ' .
              'vs ' . $GLOBAL_TEST_FUNC);
    return FALSE;
    }
    $duration = $now - $GLOBAL_TEST_START_TIME;
    //echo sprintf('%9.04f', $duration) . ' seconds.' . "\n";

    return $duration;
}

function test_regression($func) {
    trigger_error('* REGRESSION * [' . $func . ']' . "\n");
    die();
}

function do_tests($base, &$tests_list, &$results) {
    foreach ($tests_list as $test) {
    $results[$test] = call_user_func($test, $base, $results);
    }
}

function load_test($tests_dir, &$tests_list) {
    if (($dir = @opendir($tests_dir)) === FALSE) {
    return FALSE;
    }
    $matches = array();
    while (($entry = readdir($dir)) !== FALSE) {
    if (preg_match('/^(test_.+)[.]php$/i', $entry, $matches) <= 0) {
        continue;
    }
    $test_name = $matches[1];
    include_once($tests_dir . '/' . $entry);
    //echo 'Test [' . $test_name . '] ';
    flush();
    if (!function_exists($test_name . '_enabled')) {
        echo 'INVALID !' . "\n";
        continue;
    }
    if (call_user_func($test_name . '_enabled') !== TRUE) {
        echo 'disabled.' . "\n";
        continue;
    }
    if (!function_exists($test_name)) {
        echo 'BROKEN !' . "\n";
        continue;
    }
    array_push($tests_list, $test_name);
    //echo 'enabled.' . "\n";
    }
    closedir($dir);

    return TRUE;
}

function load_tests(&$tests_dirs, &$tests_list) {
    $ret = FALSE;

    foreach ($tests_dirs as $tests_dir) {
    if (load_test($tests_dir, $tests_list) === TRUE) {
        $ret = TRUE;
    }
    }
    if (count($tests_list) <= 0) {
    return FALSE;
    }
    asort($tests_list);

    return $ret;
}

function generate_summary($base, &$results) {
    $output = array();
    $output['total_time'] = 0.0;
    foreach ($results as $test => $time) {
      $output['total_time'] += $time;
    }
    if ($output['total_time'] <= 0.0) {
      die('Not enough iterations, please try with more.' . "\n");
    }
    $output['percentile_times'] = array();
    foreach ($results as $test => $time) {
      $output['percentile_times'][$test] = $time * 100.0 / $output['total_time'];
    }
    $output['score'] = (float) $base * 10.0 / $output['total_time'];
    if (function_exists('php_uname')) {
      $output['php_uname'] = php_uname();
    }
    if (function_exists('phpversion')) {
      $output['phpversion'] = phpversion();
    }
    return $output;
}

function output_summary($output, $output_json) {
    if ($output_json) {
        echo json_encode($output);
    } else {
        output_summary_html($output);
    }
}

function output_summary_html($output) {
    echo 'System     : ' . $output['php_uname'] . "\n";
    echo 'PHP version: ' . $output['phpversion'] . "\n";
    echo
      'PHPBench   : ' . PHPBENCH_VERSION . "\n" .
      'Date       : ' . date('F j, Y, g:i a') . "\n" .
      'Tests      : ' . count($output['results']) . "\n" .
      'Iterations : ' . $base . "\n" .
      'Total time : ' . round($output['total_time']) . ' seconds' . "\n" .
      'Score      : ' . round($output['score']) . ' (higher is better)' . "\n";
}

$base = DEFAULT_BASE;
if (array_key_exists('iterations', $_GET)) {
  $base = intval($_GET['iterations']);
}

//echo 'Starting the benchmark with ' . $base . ' iterations.' . "\n\n";
$tests_list = array();
$results = array();
if (load_tests($TESTS_DIRS, $tests_list) === FALSE) {
    die('Unable to load tests');
}

//echo "\n";
do_tests($base, $tests_list, $results);
//echo "\n";
$summary = generate_summary($base, $results);
$output_json = array_key_exists('json', $_GET);
output_summary($summary, $output_json);
//echo "\n";

?>