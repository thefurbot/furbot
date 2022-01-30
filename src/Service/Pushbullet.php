<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Pushbullet
{
    private $apikey;

    public function __construct(ParameterBagInterface $params)
    {
        $this->apikey = $params->get('pushbullet.apikey');
    }

    function push($title, $body, $link = null)
    {
        $message = [
            'type' => 'note',
            'title' => $title,
            'body' => $body
        ];

        if ($link !== null) {
            $message['type'] = 'link';
            $message['url'] = $link;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.pushbullet.com/v2/pushes");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Access-Token: ' . $this->apikey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($server_output, true);

        return !empty($data);
    }

}
