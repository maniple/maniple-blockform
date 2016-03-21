<?php

// find autoload.php moving upwards, so that tests can be executed
// even if the library itself lies in the vendor/ directory of another
// project

$dir = dirname(__FILE__);
$autoload = null;

while ($parent = $dir . '/..') {
    if (file_exists($path = $parent . '/vendor/autoload.php')) {
        $autoload = $path;
        break;
    }
    $dir = $parent;
}
if (empty($autoload)) {
    die('Unable to find autoload.php');
}

require_once $autoload;
