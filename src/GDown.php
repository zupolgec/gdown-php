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
        null|string $url = null,
        null|string $output = null,
        bool $quiet = false,
        null|string $proxy = null,
        null|float $speed = null,
        bool $useCookies = true,
        bool $verify = true,
        null|string $id = null,
        bool $fuzzy = false,
        bool $resume = false,
        null|string $format = null,
        null|string $userAgent = null,
    ): string {
        $downloader = new Downloader(
            quiet: $quiet,
            proxy: $proxy,
            speedLimit: $speed,
            useCookies: $useCookies,
            verify: $verify,
            userAgent: $userAgent,
        );

        return $downloader->download(
            url: $url,
            output: $output,
            id: $id,
            fuzzy: $fuzzy,
            resume: $resume,
            format: $format,
        );
    }

    /**
     * Get file info without downloading
     */
    public static function getFileInfo(
        null|string $url = null,
        null|string $id = null,
        bool $fuzzy = false,
        null|string $userAgent = null,
    ): FileInfo {
        $downloader = new Downloader(
            quiet: true,
            userAgent: $userAgent,
        );

        return $downloader->getFileInfo(
            url: $url,
            id: $id,
            fuzzy: $fuzzy,
        );
    }
}
