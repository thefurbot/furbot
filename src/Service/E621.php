<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class E621
{
    const ENDPOINT = 'https://e621.net/';

    private $login;
    private $password;
    private $blacklist = null;

    public function __construct(ParameterBagInterface $params)
    {
        $this->login = $params->get('e621.login');
        $this->password = $params->get('e621.password_hash');
        $this->blacklist = $params->get('e621.blacklist');
    }

    public function getPost($postId)
    {
        $response = $this->get('posts/' . $postId);

        return !empty($response['post']) ? $response['post'] : null;
    }

    public function query($tags, $page = 0, $limit = 300)
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'tags' => (is_array($tags) ? implode(' ', $tags) : $tags)
        ];

        echo '[e621] --- Starting query ---' . "\n";
        echo '[e621] Query: ' . $params['tags'] . "\n";
        echo '[e621] Limit: ' . $params['limit'] . "\n";
        echo '[e621] Page:  ' . $params['page'] . "\n";
        echo '[e621] ----------------------' . "\n";

        $posts = $this->get('post/index', $params);
        $count = count($posts);
        echo '[e621] Got ' . $count . ' posts' . "\n";

        foreach ($posts as $index => $post) {
            $tags = explode(' ', $post['tags']);

            if ($tag = $this->onBlacklist($tags)) {
                unset($posts[$index]);
                echo '[e621] post ' . $post['id'] . ' has tag ' . $tag . ', skipping post...' . "\n";
            }

            if (!in_array($post['file_ext'], ['jpg', 'jpeg', 'png', 'gif'])) {
                unset($posts[$index]);
                echo '[e621] post ' . $post['id'] . ' has incompatibile file type, skipping post...' . "\n";
            }
        }

        $filtered = $count - count($posts);
        $count = count($posts);
        echo '[e621] Filtered ' . $filtered . ' posts, new count: ' . $count . "\n\n";

        return $posts;
    }

    private function get($request, $params = null)
    {
        if (empty($params)) $params = [];

        $params['login'] = $this->login;
        $params['api_key'] = $this->password;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::ENDPOINT . $request . '.json?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Cache-Control: no-cache',
            'User-Agent: furbot/1.0',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        $output = json_decode($server_output, true);

        return $output;
    }

    private function onBlacklist(array $tags)
    {
        foreach ($this->blacklist as $blacklistedTag) {
            if (in_array($blacklistedTag, $tags)) return $blacklistedTag;
        }

        return false;
    }
}
