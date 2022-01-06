#!/usr/bin/php
<?php
$scannedPosts = file_get_contents(__DIR__ . '/post_sauce.json');
if ($scannedPosts !== false) {
    $scannedPosts = json_decode($scannedPosts, true);
} else {
    die('Błąd czytania historii!');
}

$history = [];
foreach($scannedPosts as $id => $post) {
    $history[$id] = [
        'id' => $id,
        'status' => 'old'
    ];
}

file_put_contents(__DIR__ . '/post_sauce.json', json_encode($history));