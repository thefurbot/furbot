#!/usr/bin/php
<?php
require_once 'config.php';

$blacklist = $config['e621']['blacklist'];

sort($blacklist);

echo implode("\n", $blacklist) . "\n\n";

echo 'Count: ' . count($config['e621']['blacklist']) . "\n";
