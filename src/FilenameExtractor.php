<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class FilenameExtractor
{
    public static function extractFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === false || $path === '') {
            return 'download';
        }
        
        return basename($path) ?? 'download';
    }

    public static function extractFromResponse($response): ?string
    {
        if (!$response->hasHeader('Content-Disposition')) {
            return null;
        }

        $contentDisposition = urldecode($response->getHeader('Content-Disposition')[0]);

        // Try UTF-8 format first
        if (preg_match("/filename\\*=UTF-8''(.+)/", $contentDisposition, $matches)) {
            return str_replace(DIRECTORY_SEPARATOR, '_', $matches[1]);
        }

        // Try standard format
        if (preg_match('/attachment; filename="(.+?)"/', $contentDisposition, $matches)) {
            return $matches[1];
        }

        return null;
    }
}