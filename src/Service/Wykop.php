<?php

namespace App\Service;

use App\Entity\MirkoComment;
use App\Entity\MirkoEntry;
use stdClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Wykop
{
    const ENDPOINT = 'https://a2.wykop.pl/';

    private $appkey;
    private $secret;
    private $accountkey;
    private $userkey;
    private $username;

    public function __construct(ParameterBagInterface $params)
    {
        $this->appkey = $params->get('wykop.appkey');
        $this->secret = $params->get('wykop.secret');
        $this->accountkey = $params->get('wykop.accountkey');
        $this->username = $params->get('wykop.login');
    }

    private function sign($request)
    {
        return md5($this->secret . $request->url . (!empty($request->post) ? implode(',', $request->post) : ''));
    }

    private function post($request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request->post));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'apisign: ' . $this->sign($request),
            'Cache-Control: no-cache',
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);

        curl_close($ch);

        return json_decode($server_output);
    }

    private function get($request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'apisign: ' . $this->sign($request),
            'Cache-Control: no-cache',
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);

        curl_close($ch);

        return json_decode($server_output);
    }

    public function addEntry(MirkoEntry $entry)
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'entries/add/appkey/' . $this->appkey . '/userkey/' . $this->userkey;
        $request->post = [
            'body' => $entry->content,
        ];

        if (!empty($entry->embed)) {
            $request->post['embed'] = $entry->embed;
            $request->post['adultmedia'] = (int)$entry->adult;
        }

        $post = $this->post($request);

        if (empty($post->data->id)) return false;
        return $post->data->id;
    }

    public function addComment(MirkoComment $entry)
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'entries/commentadd/' . $entry->entryId . '/appkey/' . $this->appkey . '/userkey/' . $this->userkey;
        $request->post = [
            'body' => $entry->content,
        ];

        if (!empty($entry->embed)) {
            $request->post['embed'] = $entry->embed;
        }

        $post = $this->post($request);

        if (empty($post->data->id)) return false;
        return $post->data->id;
    }

    public function login()
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'login/index/appkey/' . $this->appkey;
        $request->post = [
            'login' => $this->username,
            'accountkey' => $this->accountkey
        ];

        $login = $this->post($request);

        if (empty($login->data->userkey)) return false;

        $this->userkey = $login->data->userkey;
        return true;
    }

    public function getTag(string $tag)
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'tags/entries/' . $tag . '/page/0/appkey/' . $this->appkey . '/userkey/' . $this->userkey;
        $get = $this->get($request);

        return $get;
    }

    public function getStream()
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'entries/stream/page/0/appkey/' . $this->appkey . '/userkey/' . $this->userkey;
        $get = $this->get($request);

        return $get;
    }

    public function getNotifications()
    {
        $request = new StdClass();

        $request->url = self::ENDPOINT . 'Notifications/Index/page/0/appkey/' . $this->appkey . '/userkey/' . $this->userkey;
        $get = $this->get($request);

        return $get;
    }
}
