<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;

class HttpClientFactory
{
    public static function createClient(
        bool $verify = true,
        null|string $userAgent = null,
        null|string $proxy = null,
        null|CookieJar $cookieJar = null,
    ): Client {
        $config = [
            'verify' => $verify,
            'headers' => [
                'User-Agent' => $userAgent ?? UserAgent::DEFAULT,
            ],
        ];

        if ($proxy !== null) {
            $config['proxy'] = [
                'http' => $proxy,
                'https' => $proxy,
            ];
        }

        if ($cookieJar !== null) {
            $config['cookies'] = $cookieJar;
        }

        return new Client($config);
    }

    public static function createCookieJar(null|string $cookieFile = null): CookieJar
    {
        if ($cookieFile === null) {
            return new CookieJar();
        }

        if (file_exists($cookieFile) && filesize($cookieFile) > 0) {
            $content = file_get_contents($cookieFile);
            if ($content !== false && json_decode($content) !== null) {
                return new FileCookieJar($cookieFile, true);
            }
        }

        return new CookieJar();
    }
}