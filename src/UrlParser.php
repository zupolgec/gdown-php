<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class UrlParser
{
    /**
     * Check if URL is a Google Drive URL
     */
    public static function isGoogleDriveUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        return in_array($host, ['drive.google.com', 'docs.google.com'], true);
    }

    /**
     * Parse Google Drive URL to extract file ID
     *
     * @return array{fileId: string|null, isDownloadLink: bool}
     */
    public static function parseUrl(string $url, bool $warning = true): array
    {
        $parsed = parse_url($url);
        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $isGdrive = self::isGoogleDriveUrl($url);
        $path = $parsed['path'] ?? '';
        $isDownloadLink = str_ends_with($path, '/uc');

        if (!$isGdrive) {
            return ['fileId' => null, 'isDownloadLink' => $isDownloadLink];
        }

        $fileId = null;
        if (isset($query['id'])) {
            $fileId = $query['id'];
        } else {
            $patterns = [
                '/^\/file\/d\/(.*?)\/(edit|view)$/',
                '/^\/file\/u\/[0-9]+\/d\/(.*?)\/(edit|view)$/',
                '/^\/document\/d\/(.*?)\/(edit|htmlview|view)$/',
                '/^\/document\/u\/[0-9]+\/d\/(.*?)\/(edit|htmlview|view)$/',
                '/^\/presentation\/d\/(.*?)\/(edit|htmlview|view)$/',
                '/^\/presentation\/u\/[0-9]+\/d\/(.*?)\/(edit|htmlview|view)$/',
                '/^\/spreadsheets\/d\/(.*?)\/(edit|htmlview|view)$/',
                '/^\/spreadsheets\/u\/[0-9]+\/d\/(.*?)\/(edit|htmlview|view)$/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $path, $matches)) {
                    $fileId = $matches[1];
                    break;
                }
            }
        }

        if ($warning && !$isDownloadLink && $fileId) {
            trigger_error(
                'You specified a Google Drive link that is not the correct link '
                . 'to download a file. You might want to try --fuzzy option '
                . "or the following url: https://drive.google.com/uc?id={$fileId}",
                E_USER_WARNING,
            );
        }

        return ['fileId' => $fileId, 'isDownloadLink' => $isDownloadLink];
    }
}
