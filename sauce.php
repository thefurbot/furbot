#!/usr/bin/php
<?php
chdir(__DIR__);

require_once 'config.php';
require_once 'libs/wykopapi.php';
require_once 'libs/e621api.php';
require_once 'libs/saucenao.php';

echo '-- [ furbot ' . VERSION . ' ] --' . "\n\n";

echo 'Beep boop, zaczynamy...' . "\n";

if (defined('TEST')) {
    echo 'TRYB TESTOWY' . "\n\n";
}

$scannedPosts = @file_get_contents(__DIR__ . '/post_sauce.json');
if ($scannedPosts !== false) {
    $scannedPosts = json_decode($scannedPosts, true);
} else {
    die('Błąd czytania historii!');
}

$scannedPostIds = array_keys($scannedPosts);

$saucenao = new Saucenao();
$e621 = new E621();
$wykop = new Wykop();
if (!$wykop->login()) {
    die('[wykop] Nie udało się pobrać klucza użytkownika!');
}

$myLogin = $config['wykop']['login'];
$data = $wykop->getTag('furry');

foreach($data->data as $entry) {
    if($entry->author->login == $myLogin) continue;
    if(empty($entry->embed)) continue;

    echo '----[ ' . $entry->author->login . ' ]-------------------------------------' . "\n";
    echo 'URL:       https://wykop.pl/wpis/' . $entry->id. " \n";
    echo 'Typ:       ' . $entry->embed->type . "\n";
    echo 'Załącznik: ' . $entry->embed->url;
    if($entry->embed->plus18) echo ' [+18]';
    echo "\n";

    echo 'Stan:      ';
    if (in_array($entry->id, $scannedPostIds)) {
        if (defined('TEST')) {
            echo 'Post już sprawdzony, sprawdzę jeszcze raz w trybie testowym.' . "\n\n";
        } else {
            echo 'Post już sprawdzony, pomijam.' . "\n\n";
            continue;
        }
    } else echo 'Nowy post' . "\n\n";

    $artists = [];
    $sauces = [];
    $status = 'checked';

    if($entry->embed->type == 'image') {
        try {
            $sources = $saucenao->getSauce($entry->embed->url);
        } catch(Exception $e) {
            echo '[sauce] ' . $e->getMessage() . "\n";
        }

        $entryTags = [];
        $body = strip_tags($entry->body);

        preg_match_all("/(#\w+)/u", $body, $matches);  
        if ($matches) {
            $hashtagsArray = array_count_values($matches[0]);
            $entryTags = array_keys($hashtagsArray);
        }

        $message = '';

        if(!empty($sources)) {
            $hasSuperiorArtistList = false;

            foreach($sources as $source) {
                $sauce = $source['url'];
                echo '[sauce ' . $source['similarity'] . '%]  ' . $sauce . "\n";

                $url = parse_url($sauce);
                $host = str_replace('www.', '', $url['host']);
                switch($host) {
                    case 'e621.net':
                        $postId = explode('/', $url['path'])[3];
                        $post = $e621->getPost($postId);

                        if (!is_array($post)) {
                            echo '        E621 API Error, no post returned, skipping' . "\n";
                            continue;
                        }

                        if (is_array($post['tags']['artist'])) {
                            // $artists = array_merge($artists, $post['tags']['artist']);

                            // Override artists list, as we treat e621 as superior
                            $artists = $post['tags']['artist'];
                            $hasSuperiorArtistList = true;
                        }
                        $sauces[] = [
                            'label' => 'e621',
                            'url' => $sauce
                        ];
                        break;

                    case 'pixiv.net':
                        $sauces[] = [
                            'label' => 'pixiv',
                            'url' => $sauce
                        ];
                        break;

                    case 'gelbooru.com':
                        $sauces[] = [
                            'label' => 'gelbooru',
                            'url' => $sauce
                        ];
                        break;

                    case 'gelbooru.com':
                        $sauces[] = [
                            'label' => 'gelbooru',
                            'url' => $sauce
                        ];
                        break;

                    case 'danbooru.donmai.us':
                        $sauces[] = [
                            'label' => 'danbooru',
                            'url' => $sauce
                        ];
                        break;

                    case 'deviantart.com':
                        $sauces[] = [
                            'label' => 'deviantArt',
                            'url' => $sauce
                        ];
                        break;

                    case 'chan.sankakucomplex.com':
                        $sauces[] = [
                            'label' => 'sankakucomplex',
                            'url' => $sauce
                        ];
                    break;

                    case 'furaffinity.net':
                        $sauces[] = [
                            'label' => 'FA',
                            'url' => $sauce
                        ];
                        if (!$hasSuperiorArtistList && !empty($source['artist'])) {
                            $artists[] = $source['artist'];
                        }
                        break;
                }
            }

            if (empty($sauces)) {
                echo '[sauce] Nie znaleziono źródeł z obsługiwanych stron! Pomijam post...' . "\n";
                $status = 'no_valid_sauces';

                $scannedPosts[$entry->id] = [
                    'id' => $entry->id,
                    'status' => $status,
                    'sauces' => $sauces,
                    'snao' => $sources,
                    'time' => time()
                ];
                continue;
            }

            foreach($config['remove_artist'] as $removeArist) {
                if (($key = array_search($removeArist, $artists)) !== false) {
                    unset($artists[$key]);
                }
            }

            foreach($artists as &$artist) {
                $artist = str_replace('_', ' ', $artist);
                $artist = str_replace(' (artist)', '', $artist);
                $artist = ucwords($artist);
            }

            $artists = array_unique($artists);

            $message = (!empty($artists) ? 'Artysta: ' . implode(', ', $artists) . "\n" : '') . 
                (count($sauces) == 1 ? 'Źródło:' : 'Źródła:');

            foreach ($sauces as $s) {
                $message .= ' [' . $s['label'] . '](' . $s['url'] . ') |';
            }

            $message = substr($message, 0, -2);

            echo "\n" . '  [ furbot ]' . "\n" . preg_replace('/^/m', '> ', $message) . "\n\n";

            $comment = new MirkoComment($entry->id, $message);
            if (defined('TEST')) {
                echo '[wykop] Tryb testowy, nie dodaję komentarza.' . "\n";
            } else {
                $result = $wykop->addComment($comment);

                if($result === false) {
                    echo '[wykop] Nie dodano komentarza!' . "\n";
                    $status = 'wykop_error';
                } else {
                    echo '[wykop] Dodano komentarz!' . "\n";
                    $status = 'commented';
                }
            }

            // #furry info
            $hashtags = [
                '#furry', '#sfur', '#gfur', '#hfur', '#ifur', '#futrowpis'
            ];
            
            $hashtags = array_merge($entryTags, $hashtags);
            $hashtags = array_unique($hashtags);
            $lastTag = array_pop($hashtags);

            $disclaimer = 
                'Nie chcesz widzieć takich postów? Dodaj ' . implode(' ', $hashtags) . ' i ' . $lastTag . ' na #czarnolisto' . "\n" .
                '!Beep boop - jestem botem. Dobrego dzionka! (✌ ﾟ ∀ ﾟ)☞';

            echo "\n" . '  [ furbot ]' . "\n" . preg_replace('/^/m', '> ', $message) . "\n\n";

            $comment = new MirkoComment($entry->id, $disclaimer);
            if (defined('TEST')) {
                echo '[wykop] Tryb testowy, nie dodaję informacji.' . "\n";
            } else {
                $wykop->addComment($comment);
            }

        } else echo '[sauce] Nie znaleziono źródeł!' . "\n";

        $scannedPosts[$entry->id] = [
            'id' => $entry->id,
            'status' => $status,
            'sauces' => $sauces,
            'message' => $message,
            'snao' => $sources,
            'time' => time(),
            'entry' => [
                'author' => $entry->author->login,
                'embed' => $entry->embed->url
            ]
        ];

        if (!defined('TEST')) file_put_contents(__DIR__ . '/post_sauce.json', json_encode($scannedPosts));
    }

    echo "\n\n";
}


