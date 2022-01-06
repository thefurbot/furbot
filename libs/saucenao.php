<?php

class Saucenao
{
    /**
     * Returns URL's of sauces for specified image URL.
     * Has to be a direct URL to the image.
     * 
     * @param string $url Direct URL to the image
     * @return array Array of URLs to sauces
     */
    public function getSauce(string $url)
    {
        global $config;

        $apikey = $config['saucenao']['apikey'];
        $acceptedThreshold = $config['saucenao']['accept_threshold'];

        $url = 'https://saucenao.com/search.php?output_type=2&api_key=' . $apikey . '&url=' . urlencode($url);

        list($output, $error, $code) = $output = $this->getBrowser($url);
        $output = json_decode($output, true);

        if (!isset($output['header']['status'])) {
            unset($output['header']['index']);
            throw new \Exception('Unable to connect to Saucenao: ' . print_r($output['header'], true));
        }

        if (!empty($output['header']['message'])) {
            $message = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $output['header']['message']));
            $message = str_replace($_SERVER['SERVER_ADDR'] . ', ', '', $message);
            $message = str_replace("\r", '', $message);
            throw new \Exception('Saucenao returned an error: ' . $message);
        }

        if ($output['header']['results_returned'] == 0) {
            return [];
        }

        $urls = [];
        $results = $output['results'];
        foreach ($results as $result) {
            if (!empty($result['data']['ext_urls'])) foreach ($result['data']['ext_urls'] as $u) {
                if ($result['header']['similarity'] < $acceptedThreshold) {
                    continue;
                }

                $urls[] = [
                    'url' => $u,
                    'similarity' => $result['header']['similarity'],
                    'artist' => !empty($result['data']['author_name']) ? $result['data']['author_name'] : null
                ];
            }
        }
        
        return $urls;
    }

    private function getBrowser($url, $proxy = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.119 Safari/537.36');
        if (!empty($proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        $output = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$output, $error, $code];
    }
}
