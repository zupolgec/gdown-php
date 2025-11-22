<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DomCrawler\Crawler;
use Zupolgec\GDown\Exceptions\FileURLRetrievalException;

class Downloader
{
    private const CHUNK_SIZE = 524288; // 512KB
    private const MAX_RETRIES = 3;

    private Client $client;
    private null|string $cookieFile = null;
    private null|CookieJar $cookieJar = null;
    private LoggerInterface $logger;

    public function __construct(
        private readonly bool $quiet = true,
        private readonly null|string $proxy = null,
        private readonly null|float $speedLimit = null,
        private readonly bool $useCookies = true,
        private readonly bool $verify = true,
        private readonly null|string $userAgent = null,
        null|LoggerInterface $logger = null,
    ) {
        // Use custom logger, or NullLogger if quiet (default), or StderrLogger if verbose
        $this->logger = $logger ?? ($quiet ? new NullLogger() : new StderrLogger());
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $config = [
            'verify' => $this->verify,
            'headers' => [
                'User-Agent' => $this->userAgent ?? UserAgent::DEFAULT,
            ],
        ];

        if ($this->proxy !== null) {
            $config['proxy'] = [
                'http' => $this->proxy,
                'https' => $this->proxy,
            ];
            $this->logger->info("Using proxy: {$this->proxy}");
        }

        if ($this->useCookies) {
            $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
            $this->cookieFile = $homeDir . '/.cache/gdown/cookies.txt';

            if (file_exists($this->cookieFile) && filesize($this->cookieFile) > 0) {
                // Check if file contains valid JSON
                $content = file_get_contents($this->cookieFile);
                if ($content !== false && json_decode($content) !== null) {
                    $this->cookieJar = new FileCookieJar($this->cookieFile, true);
                    $config['cookies'] = $this->cookieJar;
                } else {
                    $this->cookieJar = new CookieJar();
                    $config['cookies'] = $this->cookieJar;
                }
            } else {
                $this->cookieJar = new CookieJar();
                $config['cookies'] = $this->cookieJar;
            }
        }

        $this->client = new Client($config);
    }

    /**
     * Get file information without downloading
     */
    public function getFileInfo(null|string $url = null, null|string $id = null, bool $fuzzy = false): FileInfo
    {
        if ($url === null && $id === null || $url !== null && $id !== null) {
            throw new \InvalidArgumentException('Either url or id must be specified');
        }

        if ($id !== null) {
            $url = "https://drive.google.com/uc?id={$id}";
        }

        $urlOrigin = $url;
        ['fileId' => $gdrive_file_id, 'isDownloadLink' => $is_gdrive_download_link] = UrlParser::parseUrl(
            $url,
            !$fuzzy,
        );

        // Auto-convert /file/d/ URLs to download format (same as download method)
        if ($gdrive_file_id && !$is_gdrive_download_link) {
            $url = "https://drive.google.com/uc?id={$gdrive_file_id}";
            $is_gdrive_download_link = true;
        }

        // Create a separate client with legacy User-Agent for getFileInfo
        // Google Drive returns proper MIME types with Chrome 39 but generic types with modern Chrome
        $fileInfoClient = new Client([
            'verify' => $this->verify,
            'headers' => [
                'User-Agent' => UserAgent::LEGACY_FOR_FILE_INFO,
            ],
        ]);

        try {
            $response = $fileInfoClient->request('GET', $url, [
                'stream' => true,
                'allow_redirects' => true,
                'http_errors' => false,  // Don't throw on 4xx/5xx
            ]);

            if ($gdrive_file_id && $is_gdrive_download_link) {
                // Handle Google Drive confirmation page
                if (
                    $response->hasHeader('Content-Type')
                    && str_starts_with($response->getHeader('Content-Type')[0], 'text/html')
                ) {
                    $content = (string) $response->getBody();
                    $url = $this->getUrlFromGdriveConfirmation($content);
                    // Use same fileInfoClient to maintain legacy UA
                    $response = $fileInfoClient->request('GET', $url, [
                        'stream' => true,
                        'http_errors' => false,
                    ]);
                }
            }

            $filename = $this->getFilenameFromResponse($response);
            $size = $response->hasHeader('Content-Length') ? (int) $response->getHeader('Content-Length')[0] : null;
            $mimeType = $response->hasHeader('Content-Type') ? $response->getHeader('Content-Type')[0] : null;
            $lastModified = $this->getModifiedTimeFromResponse($response);

            return new FileInfo(
                name: $filename ?? 'unknown',
                size: $size,
                mimeType: $mimeType,
                lastModified: $lastModified,
            );
        } catch (GuzzleException $e) {
            throw new FileURLRetrievalException('Failed to retrieve file info: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Download file from URL
     */
    public function download(
        null|string $url = null,
        null|string $output = null,
        null|string $id = null,
        bool $fuzzy = false,
        bool $resume = false,
        null|string $format = null,
    ): string {
        if ($url === null && $id === null || $url !== null && $id !== null) {
            throw new \InvalidArgumentException('Either url or id must be specified');
        }

        if ($id !== null) {
            $url = "https://drive.google.com/uc?id={$id}";
        }

        $urlOrigin = $url;
        ['fileId' => $gdriveFileId, 'isDownloadLink' => $isGdriveDownloadLink] = UrlParser::parseUrl($url, !$fuzzy);

        // Auto-convert /file/d/ and other Google Drive URLs to download format
        // This is the standard URL format from Google Drive, so always convert it
        if ($gdriveFileId && !$isGdriveDownloadLink) {
            $url = "https://drive.google.com/uc?id={$gdriveFileId}";
            $urlOrigin = $url;
            $isGdriveDownloadLink = true;
        }

        // Handle Google Drive download with confirmation page
        $response = $this->handleGoogleDriveDownload($url, $urlOrigin, $gdriveFileId, $isGdriveDownloadLink, $format);

        $filenameFromUrl = null;
        $lastModifiedTime = null;

        if ($gdriveFileId && $isGdriveDownloadLink) {
            $filenameFromUrl = $this->getFilenameFromResponse($response);
            $lastModifiedTime = $this->getModifiedTimeFromResponse($response);
        }

        if ($filenameFromUrl === null) {
            $filenameFromUrl = basename(parse_url($url, PHP_URL_PATH) ?: 'download');
        }

        if ($output === null) {
            $output = $filenameFromUrl;
        }

        if (str_ends_with($output, DIRECTORY_SEPARATOR)) {
            if (!is_dir($output)) {
                mkdir($output, 0755, true);
            }
            $output = $output . $filenameFromUrl;
        }

        // Check if file already exists (resume mode)
        if ($resume && file_exists($output)) {
            $this->logger->info("Skipping already downloaded file {$output}");
            return $output;
        }

        // Handle partial downloads
        $tmpFile = null;
        $startSize = 0;

        if ($resume) {
            $existingTmpFiles = glob(dirname($output) . '/' . basename($output) . '.*.part');
            if (count($existingTmpFiles) === 1) {
                $tmpFile = $existingTmpFiles[0];
                $startSize = filesize($tmpFile);

                // Request with Range header for resume
                try {
                    $response = $this->client->request('GET', $url, [
                        'stream' => true,
                        'headers' => ['Range' => "bytes={$startSize}-"],
                    ]);
                } catch (GuzzleException $e) {
                    // If range not supported, start fresh
                    $startSize = 0;
                    unlink($tmpFile);
                    $tmpFile = null;
                }
            }
        }

        if ($tmpFile === null) {
            $tmpFile = $output . '.' . uniqid() . '.part';
        }

        $this->logger->info("Downloading...");
        if ($resume && $startSize > 0) {
            $this->logger->info("Resume: {$tmpFile}");
        }
        if ($urlOrigin !== $url) {
            $this->logger->info("From (original): {$urlOrigin}");
            $this->logger->info("From (redirected): {$url}");
        } else {
            $this->logger->info("From: {$url}");
        }
        $this->logger->info('To: ' . realpath(dirname($output)) . '/' . basename($output));

        $this->downloadToFile($response, $tmpFile, $startSize);

        rename($tmpFile, $output);

        if ($lastModifiedTime !== null) {
            touch($output, $lastModifiedTime->getTimestamp());
        }

        return $output;
    }

    private function handleGoogleDriveDownload(
        string $url,
        string $urlOrigin,
        null|string $gdriveFileId,
        bool $isGdriveDownloadLink,
        null|string $format,
    ) {
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            try {
                $response = $this->client->request('GET', $url, [
                    'stream' => true,
                    'http_errors' => false  // Don't throw on 4xx/5xx status codes
                ]);

                if (!($gdriveFileId && $isGdriveDownloadLink)) {
                    return $response;
                }

                // Check if it's a Google Doc/Sheet/Slide (returns 500 on /uc?id= URL)
                if ($url === $urlOrigin && $response->getStatusCode() === 500) {
                    // Force English language for consistent title matching
                    $url = "https://drive.google.com/open?id={$gdriveFileId}&hl=en";
                    $retries++;
                    continue;
                }

                // Read response body once
                $content = null;
                if (
                    $response->hasHeader('Content-Type')
                    && str_starts_with($response->getHeader('Content-Type')[0], 'text/html')
                ) {
                    $content = (string) $response->getBody();

                    // Check for Google Docs/Sheets/Slides
                    if (preg_match('/<title>(.+)<\/title>/', $content, $matches)) {
                        $title = $matches[1];

                        if (str_ends_with($title, ' - Google Docs')) {
                            $url =
                                "https://docs.google.com/document/d/{$gdriveFileId}/export?format="
                                . ($format ?? 'docx');
                            $retries++;
                            continue;
                        } elseif (str_ends_with($title, ' - Google Sheets')) {
                            $url =
                                "https://docs.google.com/spreadsheets/d/{$gdriveFileId}/export?format="
                                . ($format ?? 'xlsx');
                            $retries++;
                            continue;
                        } elseif (str_ends_with($title, ' - Google Slides')) {
                            $url =
                                "https://docs.google.com/presentation/d/{$gdriveFileId}/export?format="
                                . ($format ?? 'pptx');
                            $retries++;
                            continue;
                        }
                    }
                }

                // Save cookies if needed
                if ($this->useCookies && $this->cookieJar && $this->cookieFile) {
                    $dir = dirname($this->cookieFile);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }

                if ($response->hasHeader('Content-Disposition')) {
                    return $response;
                }

                // Need to redirect with confirmation
                // Reuse $content if already loaded, otherwise load it now
                if ($content === null) {
                    $content = (string) $response->getBody();
                }
                
                $url = $this->getUrlFromGdriveConfirmation($content);

                return $this->client->request('GET', $url, ['stream' => true]);
            } catch (GuzzleException $e) {
                throw new FileURLRetrievalException(
                    "Failed to retrieve file url:\n\n"
                    . $e->getMessage()
                    . "\n\n"
                    . "You may still be able to access the file from the browser:\n\n"
                    . "\t{$urlOrigin}\n\n"
                    . "but GDown can't. Please check connections and permissions.",
                    0,
                    $e,
                );
            }
        }

        throw new FileURLRetrievalException('Maximum retries exceeded');
    }

    private function getUrlFromGdriveConfirmation(string $contents): string
    {
        // Try to find download form (PROCESS ENTIRE HTML AT ONCE)
        $crawler = new Crawler($contents);
        $form = $crawler->filter('#download-form')->first();

        if ($form->count() > 0) {
            $action = $form->attr('action');
            $action = str_replace('&amp;', '&', $action);
            $parsedUrl = parse_url($action);
            parse_str($parsedUrl['query'] ?? '', $queryParams);

            foreach ($form->filter('input[type="hidden"]') as $input) {
                $name = $input->getAttribute('name');
                $value = $input->getAttribute('value');
                $queryParams[$name] = $value;
            }

            $query = http_build_query($queryParams);
            return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '') . '?' . $query;
        }

        // Fallback: try line-by-line regex patterns
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            // Try to find download URL in href
            if (preg_match('/href="(\/uc\?export=download[^"]+)"/', $line, $matches)) {
                $url = 'https://docs.google.com' . html_entity_decode($matches[1]);
                return str_replace('&amp;', '&', $url);
            }

            // Try to find downloadUrl in JSON
            if (preg_match('/"downloadUrl":"([^"]+)"/', $line, $matches)) {
                $url = $matches[1];
                $url = str_replace('\\u003d', '=', $url);
                $url = str_replace('\\u0026', '&', $url);
                return $url;
            }

            // Check for error message
            if (preg_match('/<p class="uc-error-subcaption">(.*)<\/p>/', $line, $matches)) {
                throw new FileURLRetrievalException($matches[1]);
            }
        }

        throw new FileURLRetrievalException('Cannot retrieve the public link of the file. '
        . 'You may need to change the permission to '
        . "'Anyone with the link', or have had many accesses. "
        . 'Check FAQ in https://github.com/wkentaro/gdown?tab=readme-ov-file#faq.');
    }

    private function getFilenameFromResponse($response): null|string
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

    private function getModifiedTimeFromResponse($response): null|\DateTimeInterface
    {
        if (!$response->hasHeader('Last-Modified')) {
            return null;
        }

        $raw = $response->getHeader('Last-Modified')[0];
        try {
            return new \DateTime($raw);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function downloadToFile($response, string $outputFile, int $startSize = 0): void
    {
        // Detect Google Drive error/preview pages (NOT legitimate HTML files)
        // Only check if:
        // 1. Content-Type is text/html
        // 2. No Content-Disposition header (actual downloads have this)
        // 3. Small size (error pages are typically < 100KB)
        $contentType = $response->hasHeader('Content-Type') ? $response->getHeader('Content-Type')[0] : '';
        $hasContentDisposition = $response->hasHeader('Content-Disposition');
        $contentLength = $response->hasHeader('Content-Length')
            ? (int) $response->getHeader('Content-Length')[0]
            : null;

        $mightBeErrorPage =
            str_starts_with($contentType, 'text/html')
            && !$hasContentDisposition
            && ($contentLength !== null && $contentLength < 100000);

        $fp = fopen($outputFile, $startSize > 0 ? 'ab' : 'wb');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open file for writing: {$outputFile}");
        }

        try {
            $body = $response->getBody();

            $totalSize = $contentLength !== null ? $contentLength + $startSize : null;
            $downloaded = $startSize;

            if ($totalSize !== null) {
                $this->logger->info(sprintf("Total size: %s", $this->formatBytes($totalSize)));
            }

            $startTime = microtime(true);
            $firstChunk = true;

            while (!$body->eof()) {
                $chunk = $body->read(self::CHUNK_SIZE);
                
                // Check first chunk for Google Drive error pages
                if ($firstChunk && $downloaded === 0 && $mightBeErrorPage) {
                    $firstChunk = false;
                    // Check for specific Google Drive error page markers
                    if (
                        str_contains($chunk, 'Google Drive')
                        && (
                            str_contains($chunk, 'link sharing') 
                            || str_contains($chunk, 'permission')
                            || str_contains($chunk, 'Whoops!')
                            || str_contains($chunk, 'drive-viewer')
                        )
                    ) {
                        fclose($fp);
                        unlink($outputFile);
                        throw new FileURLRetrievalException(
                            'Downloaded content is a Google Drive error/preview page instead of the requested file. ' .
                            'This usually means the file is not shared publicly or the link is incorrect.'
                        );
                    }
                }
                
                fwrite($fp, $chunk);
                $downloaded += strlen($chunk);

                if (!$this->quiet) {
                    $this->showProgress($downloaded, $totalSize);
                }

                if ($this->speedLimit !== null) {
                    $elapsedTime = microtime(true) - $startTime;
                    $expectedTime = ($downloaded - $startSize) / $this->speedLimit;
                    if ($elapsedTime < $expectedTime) {
                        usleep((int) (($expectedTime - $elapsedTime) * 1000000));
                    }
                }
            }

            // Progress complete - callback can handle any final output
        } finally {
            fclose($fp);
        }
    }

    private function showProgress(int $downloaded, null|int $total): void
    {
        if ($total !== null) {
            $percentage = ($downloaded / $total) * 100;
            $bar = str_repeat('=', (int) ($percentage / 2));
            $bar = str_pad($bar, 50, ' ');
            fprintf(
                STDERR,
                "\r[%s] %3.1f%% %s / %s",
                $bar,
                $percentage,
                $this->formatBytes($downloaded),
                $this->formatBytes($total),
            );
        } else {
            fprintf(STDERR, "\rDownloaded: %s", $this->formatBytes($downloaded));
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < (count($units) - 1)) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
