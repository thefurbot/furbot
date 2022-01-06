<?php

class Wykop
{
    const ENDPOINT = 'https://a2.wykop.pl/';

    private $appkey;
    private $secret;
    private $accountkey;
    private $userkey;
    private $username;

    public function __construct()
    {
        global $config;

        $this->appkey = $config['wykop']['appkey'];
        $this->secret = $config['wykop']['secret'];
        $this->accountkey = $config['wykop']['accountkey'];
        $this->username = $config['wykop']['login'];
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

    public function addEntry(Mirko $entry)
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

class Mirko
{
    public $content;
    public $embed;
    public $adult = false;

    /**
     * Creates Mikroblog entry
     * 
     * @param string $content Body of the entry
     */
    public function __construct($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Adds file from URL
     * 
     * @param string $filename URL of the file to attach (video/image)
     */
    public function file($filename)
    {
        $this->embed = $filename;
        return $this;
    }

    /**
     * Sets entry as adult content or not
     * 
     * @param bool $adult Is the content +18?
     */
    public function adult($adult)
    {
        $this->adult = $adult;
        return $this;
    }

    /**
     * Sets body of the entry
     * 
     * @param string $message Body of the entry
     */
    public function message($message)
    {
        $this->content = $message;
        return $this;
    }
}

class MirkoComment
{
    public $entryId;
    public $content;
    public $embed;

    /**
     * Creates new comment for Mirkoblog entry
     * 
     * @param int $parentId Entry ID from Mirkoblog
     * @param string $message Body of the comment
     */
    public function __construct($parentId, $message)
    {
        $this->entryId = $parentId;
        $this->content = $message;
        return $this;
    }

    /**
     * Adds file from URL
     * 
     * @param string $filename URL of the file to attach (video/image)
     */
    public function file($filename)
    {
        $this->embed = $filename;
        return $this;
    }

    /**
     * Sets body of the comment
     * 
     * @param string $message Body of the comment
     */
    public function message($message)
    {
        $this->content = $message;
        return $this;
    }
}
