#!/usr/bin/env php
<?php
/**
 * @category System
 * @package  PEAR_LogfileAnalysis
 * @author   Till Klampaeckel <till@php.net>
 * @license
 * @link     http://github.com/pear/PEAR_LogfileAnalysis
 */
require_once __DIR__ . '/PEAR/LogfileAnalysis.php';

PEAR_LogfileAnalysis::init(); // pseudo __construct()

$start = null;
if (isset($argv[1])) {
    $start = $argv[1];
}
if ($start !== null) {
    echo "Resuming!\n\n";
}

$lineNo = false;
if (isset($argv[2])) {
    $lineNo = (int) $argv[2];
    if (empty($lineNo)) {
        $lineNo = false;
    }
}

$logFiles = PEAR_LogfileAnalysis::globr(__DIR__ . '/*');

$startAt = false;
foreach ($logFiles as $log) {

    $prettyLog = str_replace(__DIR__, '', $log);

    if ($startAt === false) {
        if ($start === null) {
            $startAt = true;
        } else {
            if ($start === $prettyLog) {
                $startAt = true;
            } else {
                echo "Skipping: {$prettyLog}\n";
            }
        }
    }

    if ($startAt === false) {
        continue;
    }

    echo "Currently crunching: {$prettyLog}\n";

    $handle = @fopen($log, 'r');
    if (!$handle) {
        echo "Could not open {$prettyLog}\n";
        continue;
    }

    $lineCount = 1;
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($lineNo !== false) {
            if ($lineCount < $lineNo) {
                continue;
            }
            if ($lineCount == $lineNo) {
                $lineNo = false; // start crunching
            }
        }

        $doc = PEAR_LogfileAnalysis::parseLine($line);
        if ($doc === false) {
            $lineCount++;
            continue;
        }
        PEAR_LogfileAnalysis::sendToCouchDB($doc, $prettyLog, $lineCount);
        // usleep(500000);

        unset($line);
        unset($doc);

        $lineCount++;
    }

    unset($prettyLog);

    echo "\t" . round((memory_get_usage(true)/1024/1024), 2) . "MB\n";
}