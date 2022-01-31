<?php

namespace App\Service;

class EntryHelper
{


    public function convertSources(E621 $e621, array $sources)
    {
        if (empty($sources)) {
            return [[], []];
        }

        $sauces = [];
        $artists = [];

        // If we get artists from e621, use those only
        $hasSuperiorArtistList = false;

        foreach($sources as $source) {
            $sauce = $source['url'];

            // Get host and remove www. from it
            $url = parse_url($sauce);
            $host = str_replace('www.', '', $url['host']);

            // Deal with the source
            switch($host) {
                case 'e621.net':
                    $postId = explode('/', $url['path'])[3];
                    $post = $e621->getPost($postId);

                    if (!is_array($post)) {
                        // API error, skip
                        continue 2;
                    }

                    if (is_array($post['tags']['artist'])) {
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

        return [$sauces, $artists];
    }
}