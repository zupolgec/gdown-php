<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

/**
 * GDown - Convenience wrapper for downloading Google Drive files
 */
class GDown
{
    /**
     * Download a file from Google Drive
     */
    public static function download(
        ?string $url = null,
        ?string $output = null,
        bool $quiet = false,
        ?string $proxy = null,
        ?float $speed = null,
        bool $useCookies = true,
        bool $verify = true,
        ?string $id = null,
        bool $fuzzy = false,
        bool $resume = false,
        ?string $format = null,
        ?string $userAgent = null
    ): string {
        $downloader = new Downloader(
            quiet: $quiet,
            proxy: $proxy,
            speedLimit: $speed,
            useCookies: $useCookies,
            verify: $verify,
            userAgent: $userAgent
        );

        return $downloader->download(
            url: $url,
            output: $output,
            id: $id,
            fuzzy: $fuzzy,
            resume: $resume,
            format: $format
        );
    }

    /**
     * Get file info without downloading
     */
    public static function getFileInfo(
        ?string $url = null,
        ?string $id = null,
        bool $fuzzy = false,
        ?string $userAgent = null
    ): FileInfo {
        $downloader = new Downloader(
            quiet: true,
            userAgent: $userAgent
        );

        return $downloader->getFileInfo(
            url: $url,
            id: $id,
            fuzzy: $fuzzy
        );
    }
}
