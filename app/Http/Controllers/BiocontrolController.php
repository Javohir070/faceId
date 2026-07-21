<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Response;

class BiocontrolController extends Controller
{
    public function biocontrol(): Response
    {
        return $this->proxyGet('/ISAPI/System/deviceInfo');
    }

    public function getHttpHost(): Response
    {
        return $this->proxyGet('/ISAPI/Event/notification/httpHosts/1');
    }

    private function proxyGet(string $path): Response
    {
        $baseUri = (string) config('hikvision.base_uri');
        $username = (string) config('hikvision.username');
        $password = (string) config('hikvision.password');

        if ($baseUri === '' || $username === '' || $password === '') {
            abort(503, 'Hikvision sozlamalari to‘liq emas.');
        }

        try {
            $client = new Client([
                'base_uri' => $baseUri,
                'auth' => [$username, $password, 'digest'],
                'timeout' => (float) config('hikvision.timeout', 5),
                'connect_timeout' => 3,
                'http_errors' => true,
            ]);

            $response = $client->request('GET', $path);
        } catch (\Throwable $exception) {
            report($exception);

            abort(502, 'Qurilma bilan bog‘lanib bo‘lmadi.');
        }

        return response($response->getBody()->getContents(), 200, [
            'Content-Type' => $response->getHeaderLine('Content-Type') ?: 'application/xml',
        ]);
    }
}
