<?php
global $BASE_URL;
$config=array();
$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$config['db'] = [
    'default'   => 'mysql',
    'driver'    => 'mysql',
    'host'      => "HOST",
    'database'  => "DATABASE",
    'username'  => "USER",
    'password'  => "PASSWORD",
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci'
];

// RENAME FILE TO config.inc.php TO USE IT