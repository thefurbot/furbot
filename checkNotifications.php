#!/usr/bin/php
<?php
chdir(__DIR__);

require_once 'config.php';
require_once 'libs/wykopapi.php';
require_once 'libs/pushbullet.php';

$wykop = new Wykop();
if (!$wykop->login()) {
    die('[wykop] Nie udało się pobrać klucza użytkownika!');
}

$myLogin = $config['wykop']['login'];
$data = $wykop->getNotifications();

if (empty($data->data)) exit;
$notifications = $data->data;

foreach ($notifications as $notify) {
    if ($notify->new) {
        pushbullet('[furbot] Powiadomienie z Wykopu', $notify->body, $notify->url);
    }
}