#!/usr/bin/env php
<?php
/**
 * @category System
 * @package  PEAR_LogfileAnalysis
 * @author   Till Klampaeckel <till@php.net>
 * @license
 * @link     http://github.com/pear/PEAR_LogfileAnalysis
 */

/**
 * @namespace
 */
namespace PEAR;

/**
 * @desc Include \PEAR\LogfileAnalysis
 */
require_once __DIR__ . '/PEAR/LogfileAnalysis.php';

LogfileAnalysis::init(); // pseudo __construct()

/**
 * @desc Argument parsing! Primarily to be able to resume the crunching.
 *       Example: ./process-single.php /logfile lineno
 *       The lineno argument is optional and is only used when supplied.
 */
$start = null;
if (isset($argv[1])) {
    $start = $argv[1];
}
if ($start === null) {
    echo "Need a logfile to crunch.\n";
    exit(1);
}

$lineNo = false;
if (isset($argv[2])) {
    $lineNo = (int) $argv[2];
    if (empty($lineNo)) {
        $lineNo = false;
    } else {
        echo "\n\tResuming at line: {$lineNo} in {$start}\n\n";
    }
}

$prettyLog = basename($start);
echo "Currently crunching: {$prettyLog}\n";

$handle = @fopen($start, 'r');
if (!$handle) {
    echo "Could not open {$prettyLog}\n";
    exit(1);
}

$lineCount  = 1;
$bulk       = new \stdClass;
$bulk->docs = array();

while (!feof($handle)) {
    $line = fgets($handle);
    if ($lineNo !== false) {
        if ($lineCount < $lineNo) {
            unset($line);
            $lineCount++;
            // echo "\t\tSkipping: {$lineCount} (>> {$lineNo})\n";
            continue;
        }
        if ($lineCount == $lineNo) {
            $lineNo = false; // start crunching
        }
    }

    $doc = LogfileAnalysis::parseLine($line);
    if ($doc === false) {
        $lineCount++;
        continue;
    }

    $doc['_id'] = md5($doc['line']);

    $bulk->docs[] = $doc;

    // LogfileAnalysis::sendToCouchDB($doc, $prettyLog, $lineCount);

    unset($line);
    unset($doc);

    if (($lineCount%50) == 0) {

        // send the bulk request
        LogfileAnalysis::sendBulkRequest($bulk, $prettyLog, $lineCount);
    }

    $lineCount++;
}

echo "\t" . round((memory_get_usage(true)/1024/1024), 2) . "MB\n";