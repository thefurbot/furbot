<?php
function pushbullet($title, $body, $link = null) {
    global $config;

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
		'Access-Token: ' . $config['pushbullet']['apikey']
	]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$server_output = curl_exec ($ch);
	curl_close ($ch);

	$data = json_decode($server_output, true);

	return !empty($data);
}