<?php

namespace App;

use Illuminate\Support\Facades\Http;

class DeepstackClient implements DeepstackClientInterface
{
    /**
     * @var string
     */
    private $api_base_url;

    public function __construct($api_base_url)
    {
        $this->api_base_url = $api_base_url;
    }

    public function detection($image_path)
    {
        $url = $this->api_base_url . 'v1/vision/detection';

        $response = Http::attach(
            'image',
            file_get_contents($image_path),
            'photo.jpg'
        )->post($url);

        return $response->body();
    }
}
