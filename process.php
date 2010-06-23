<?php
/**
 * This is for the initial cleanup. Most of the files would uncompress to
 * 'access_log' which doesn't work.
 */
function organize() {
    $compressedExt = array('gz', 'bz2');

    foreach ($compressedExt as $ext) {

        $pattern = __DIR__ . "/*.{$ext}";

        /**
         * @desc Create a directory for each file .gz in this directory, move it in and gunzip.
         */
        foreach (glob($pattern) as $file) {
            $base = basename($file);
            $dir  = str_replace(".{$ext}", '', $base);
            echo "{$base} / {$dir}\n";

            $target = __DIR__ . "/{$dir}";

            if (file_exists($target) && is_dir($target)) {
                continue;
            }

            mkdir($target);
            rename($file, "{$target}/access_log.{$ext}");
        }
    }
}

/**
 * Recursive glob() function - find all files.
 *
 * @param string $pattern A glob() pattern.
 *
 * @return array
 */
function globr($pattern)
{
    $files = array();
    foreach (glob($pattern) as $file) {
        $base = basename($file);
        if ($base == '.' || $base == '..') {
            continue;
        }
        if ($base == basename(__FILE__)) {
            continue;
        }
        if (!is_dir($file)) {
            $files[] = $file;
            continue;
        }
        $files = array_merge($files, globr("$file/*"));
    }
    return $files;
}

/**
 * Parse a single line from the access_log. We'll make sure the $paths are matched
 * so we avoid other stats.
 *
 * @param string $line
 *
 * @return mixed boolean if $line was empty, otherwise an array.
 */
function parseLine($line)
{
    if (empty($line)) {
        return false;
    }
    static $pattern = '/^([^ ]+) ([^ ]+) ([^ ]+) (\[[^\]]+\]) "(.*) (.*) (.*)" ([0-9\-]+) ([0-9\-]+) "(.*)" "(.*)"$/';

    $isRelevant = false;
    if (strstr($line, ' /rest')) {
        $isRelevant = true;
    }
    if (strstr($line, ' /channel.xml')) {
        $isRelevant = true;
    }
    if (strstr($line, ' /get')) {
        $isRelevant = true;
    }
    if ($isRelevant === false) {
        return false;
    }

    if (preg_match($pattern, $line, $matches)) {

        /**
         * @desc Put each part of the match in an appropriately-named variable
         * @link http://docstore.mik.ua/orelly/webprog/pcook/ch11_14.htm 
         */
        list($whole_match,$remote_host,$logname,$user,$time,
            $method,$request,$protocol,$status,$bytes,$referer,
            $user_agent) = $matches;

        /**
         * @desc Relevant user-agent follows 'PEAR/foo/PHP/bar' pattern.
         */
        if (!strstr($user_agent, '/PHP/')) {
            return false;
        }

        $resp = array(
            'line'     => $whole_match,
            'host'     => $remote_host,
            'log'      => $logname,
            'user'     => $user,
            'time'     => $time,
            'method'   => $method,
            'request'  => $request,
            'protocol' => $protocol,
            'status'   => $status,
            'bytes'    => $bytes,
            'referer'  => $referer,
            'agent'    => $user_agent,
        );

        $resp['time'] = substr($time, 1, -1);

        list($foo, $pear, $bar, $php) = explode('/', $user_agent);

        $resp['php']  = $php;
        $resp['pear'] = $pear;

        return $resp;
    }

    // no match
    return false;
}

function sendToCouchDb(array $data)
{
    static $config;
    if ($config === null) {
        $config = parse_ini_file(__DIR__ . '/config.ini', true);
        if ($config === false) {
            echo "Couldn't read config.ini.";
            exit(1);
        }
    }

    static $req;
    if ($req === null) {
        require_once 'HTTP/Request2.php';

        $req = new HTTP_Request2;
        $req->setAuth($config['couchdb']['user'], $config['couchdb']['pass']);
        $req->setMethod(HTTP_Request2::METHOD_PUT);
    }

    $obj = (object) $data;

    $id = md5($obj->line);

    try {
        $req->setUrl($config['couchdb']['host'] . "/{$id}");
        $req->setBody(json_encode($obj));

        $resp = $req->send();

        echo "Document: {$id}, " . json_encode($obj) . "\n";
        echo "Response: " . $resp->getStatus() . "\n\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        exit(1);
    }
}

$logFiles = globr(dirname(__FILE__) . '/*');

foreach ($logFiles as $log) {

    $data  = file_get_contents($log);
    $lines = explode("\n", $data);

    foreach ($lines as $line) {
        $data = parseLine($line);
        if ($data === false) {
            continue;
        }
        sendToCouchDB($data);
        sleep(1);
    }
}