#!/usr/bin/php
<?php
$scannedPosts = file_get_contents(__DIR__ . '/post_sauce.json');
if ($scannedPosts !== false) {
    $scannedPosts = json_decode($scannedPosts, true);
} else {
    die('Błąd czytania historii!');
}

$singlePost = $argv[1] ?? 'tail';
$full = (isset($argv[2]) && $argv[2] === 'full');

function simplifySauces($sauces) {
    $s = [];
    foreach($sauces as $sauce) {
        $s[] = '[' . $sauce['label'] . '] ' . $sauce['url'];
    }

    return $s;
}

function showPost($postData) {
    global $full;

    echo '----[ ' . $postData['id'] . ' ]-------------------------------------' . "\n";

    if ($postData['status'] == 'old') {
        echo 'Old post' . "\n\n";
        return;
    }

    echo 'Stan:' . "\t";
    switch($postData['status']) {
        case 'commented': echo 'Dodano komentarz'; break;
        case 'wykop_error': echo 'Nie dodano komentarza'; break;
        case 'no_valid_sauces': echo 'Nie znaleziono obsługiwanych źródeł'; break;
        case 'checked': echo 'Sprawdzono'; break;
    }
    echo "\n";
    echo 'Źródła:' . "\t" . implode("\n\t", simplifySauces($postData['sauces'])) . "\n";
    echo 'Czas:' . "\t" . date('d.m.Y H:i:s', $postData['time']) . "\n";

    if (!empty($postData['entry'])) {
        echo 'Autor:' . "\t" . $postData['entry']['author'] . "\n";
        echo 'Embed:' . "\t" . $postData['entry']['embed'] . "\n";
    }

    echo 'Komentarz:' . "\n" . preg_replace('/^/m', '> ', $postData['message']) . "\n";

    if ($full) {
        echo 'Saucenao:' . "\n";
        echo preg_replace('/^/m', '> ', stripslashes(json_encode($postData['snao'], JSON_PRETTY_PRINT))) . "\n";
    }

    echo "\n";
}

if ($singlePost && is_numeric($singlePost)) {
    // Single post mode

    if (!empty($scannedPosts[$singlePost])) {
        showPost($scannedPosts[$singlePost]);
    } else {
        echo 'Nie ma takiego postu w historii';
        echo "\n\n";
    }

    exit;
}

$counter = 0;
$posts = array_reverse($scannedPosts);

if ($singlePost == 'all') {
    foreach ($posts as $post) {
        showPost($post);
    }
} else {
    foreach ($posts as $post) {
        showPost($post);

        $counter++;
        if ($counter >= 20) break;
    }
}