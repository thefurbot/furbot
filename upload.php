#!/usr/bin/php
<?php
chdir(__DIR__);

require_once 'config.php';
require_once 'libs/wykopapi.php';
require_once 'libs/e621api.php';

echo '-- [ furbot ' . VERSION . ' ] --' . "\n\n";

echo 'Beep boop, starting process...' . "\n";

$queries = [
    'date:week order:favcount -rating:e score:>=20',
];

$query = $queries[rand(0, count($queries) - 1)];

$e621 = new E621();
$posts = $e621->query($query);

if(empty($posts)) {
    die('No posts returned!');
}

$posted = 0;
$postCount = $config['post_count'];

$postHistory = @file_get_contents(__DIR__ . '/post_history.json');
if ($postHistory !== false) {
    $postHistory = json_decode($postHistory, true);
} else {
    die('Error reading history');
}

$scannedPosts = @file_get_contents(__DIR__ . '/post_sauce.json');
if ($scannedPosts !== false) {
    $scannedPosts = json_decode($scannedPosts, true);
} else {
    die('Error reading scanned history');
}

$wykop = new Wykop();
if (!$wykop->login()) {
    die('[wykop] Unable to get userkey');
}

foreach ($posts as $i => $post) {
    echo str_pad($i, 3, ' ', STR_PAD_LEFT) . ': ';
    echo $post['id'] . ' ' . implode(' ', $post['artist']) . "\n";
    echo '     https://e621.net/post/show/' . $post['id'] . "\n";

    if (in_array($post['id'], $scannedPosts)) {
        echo '     Somebody else posted it already, skipping.' . "\n\n";
        continue;
    }

    if (in_array($post['id'], $postHistory)) {
        echo '     Posted already, skipping.' . "\n\n";
        continue;
    }

    if (in_array($post['parent_id'], $postHistory)) {
        echo '     Parent posted already, skipping' . "\n\n";
        continue;
    }

    if(!empty($post['children'])) foreach(explode(',', $post['children']) as $child) {
        if (in_array($child, $postHistory)) {
            echo '     Child posted already, skipping' . "\n\n";
            continue 2;
        }
    }

    $message = '#furry ';

    // Tagowanie
    $tags = explode(' ', $post['tags']);
    if (in_array('female', $tags)) $message .= '#sfur ';
    if (in_array('male', $tags) && !in_array('female', $tags)) $message .= '#gfur ';
    if (in_array('herm', $tags) || in_array('dickgirl', $tags) || in_array('gynomorph', $tags) || in_array('shemale', $tags)) $message .= '#hfur ';
    if (in_array('feral', $tags)) $message .= '#feral ';
    if (in_array('scalie', $tags) || in_array('shark', $tags) || in_array('snake', $tags) || in_array('dragon', $tags)) $message .= '#scalie ';

    if($post['rating'] != 's') $message .= '#nsfw ';

    $artists = $post['artist'];
    foreach($config['remove_artist'] as $removeArist) {
        if (($key = array_search($removeArist, $artists)) !== false) {
            unset($artists[$key]);
        }
    }

    foreach($artists as &$artist) {
        $artist = str_replace('_', ' ', $artist);
        $artist = ucwords($artist);
    }

    $message .= "\n" . (!empty($artists) ? 'By: ' . implode(', ', $artists) . "\n" : '') . 'Sauce: [' . $post['id'] . '](https://e621.net/post/show/' . $post['id'] . ')';

    $file_url = $post['file_url'];
    if($post['file_size'] >= 8388608) {
        $file_url = $post['sample_url'];
    }

    $mirko = new Mirko($message);
    $mirko->file($file_url);

    if($post['rating'] != 's') {
        $mirko->adult(true);
    }

    $wykopPostId = $wykop->addEntry($mirko);
    if($wykopPostId !== false) {
        echo "\n" . '[wykop] https://www.wykop.pl/wpis/' . $wykopPostId . "\n";

        sleep(2);
        $comment = 'Nie chcesz widzieć moich postów? Dodaj mnie na #czarnolisto' . "\n" .
            'Nie chcesz widzieć takich postów? Dodaj #furry #sfur #gfur #hfur #ifur i #futrowpis na #czarnolisto' . "\n" .
            '!Beep boop - jestem botem. Arty pochodzą z e621. Dobrego dzionka! (✌ ﾟ ∀ ﾟ)☞';
        $comment = new MirkoComment($wykopPostId, $comment);
        $result = $wykop->addComment($comment);

        if($result === false) echo '[wykop] Nie dodano komentarza!' . "\n";
        else echo '[wykop] Dodano komentarz!' . "\n";
    }

    $postHistory[] = $post['id'];

    $posted++;
    if ($posted >= $postCount) break;
}

file_put_contents(__DIR__ . '/post_history.json', json_encode($postHistory));

echo 'Done!' . "\n\n";
