<?php

namespace App\Services\Integration\Economic;

use GuzzleHttp\Client;

class Api
{
    const BASE_URL = 'https://restapi.e-conomic.com/';

    const POST = 'POST';
    const GET =  'GET';

    const GRANT_TOKEN = 'GRANT_TOKEN';
    const SECRET_TOKEN = 'SECRET_TOKEN';

    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-AgreementGrantToken' => self::GRANT_TOKEN,
                'X-AppSecretToken' => self::SECRET_TOKEN
            ],
        ]);
    }

    public function request(string $endpoint, string $method = 'GET', array $options = [])
    {
        $response = $this->client->request($method, $endpoint, $options);

        return json_decode($response->getBody()->getContents());
    }
}
