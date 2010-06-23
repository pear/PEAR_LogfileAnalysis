#!/usr/bin/env php
<?php
require_once __DIR__ . '/PEAR/LogfileAnalysis.php';

PEAR_LogfileAnalysis::init(); // pseudo __construct()

$start = null;
if (isset($argv[1])) {
    $start = $argv[1];
}
if ($start !== null) {
    echo "Resuming!\n\n";
}

$logFiles = PEAR_LogfileAnalysis::globr(__DIR__ . '/*');
sort($logFiles);

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

    $data  = file_get_contents($log);
    $lines = explode("\n", $data);

    echo "Currently crunching: {$prettyLog}\n";

    foreach ($lines as $line) {
        $data = PEAR_LogfileAnalysis::parseLine($line);
        if ($data === false) {
            continue;
        }
        PEAR_LogfileAnalysis::sendToCouchDB($data);
        sleep(1);

        unset($line);
    }

    unset($lines);
    unset($data);
    unset($prettyLog);

    echo "\t" . round((memory_get_usage(true)/1024/1024), 2) . "MB\n";
}