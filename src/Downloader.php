<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Zupolgec\GDown\Exceptions\FileURLRetrievalException;
use Symfony\Component\DomCrawler\Crawler;

class Downloader
{
    private const CHUNK_SIZE = 524288; // 512KB
    private const MAX_RETRIES = 3;

    private Client $client;
    private ?string $cookieFile = null;
    private ?CookieJar $cookieJar = null;

    public function __construct(
        private readonly bool $quiet = false,
        private readonly ?string $proxy = null,
        private readonly ?float $speedLimit = null,
        private readonly bool $useCookies = true,
        private readonly bool $verify = true,
        private readonly ?string $userAgent = null
    ) {
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $config = [
            'verify' => $this->verify,
            'headers' => [
                'User-Agent' => $this->userAgent ??
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) ' .
                    'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                    'Chrome/39.0.2171.95 Safari/537.36'
            ],
        ];

        if ($this->proxy !== null) {
            $config['proxy'] = [
                'http' => $this->proxy,
                'https' => $this->proxy,
            ];
            if (!$this->quiet) {
                fwrite(STDERR, "Using proxy: {$this->proxy}\n");
            }
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
    public function getFileInfo(
        ?string $url = null,
        ?string $id = null,
        bool $fuzzy = false
    ): FileInfo {
        if (($url === null && $id === null) || ($url !== null && $id !== null)) {
            throw new \InvalidArgumentException('Either url or id must be specified');
        }

        if ($id !== null) {
            $url = "https://drive.google.com/uc?id={$id}";
        }

        $urlOrigin = $url;
        ['fileId' => $gdrive_file_id, 'isDownloadLink' => $is_gdrive_download_link] =
            UrlParser::parseUrl($url, !$fuzzy);

        if ($fuzzy && $gdrive_file_id) {
            $url = "https://drive.google.com/uc?id={$gdrive_file_id}";
            $is_gdrive_download_link = true;
        }

        try {
            $response = $this->client->request('GET', $url, [
                'stream' => true,
                'allow_redirects' => true
            ]);

            if ($gdrive_file_id && $is_gdrive_download_link) {
                // Handle Google Drive confirmation page
                if (
                    $response->hasHeader('Content-Type') &&
                    str_starts_with($response->getHeader('Content-Type')[0], 'text/html')
                ) {
                    $content = (string) $response->getBody();
                    $url = $this->getUrlFromGdriveConfirmation($content);
                    $response = $this->client->request('GET', $url, ['stream' => true]);
                }
            }

            $filename = $this->getFilenameFromResponse($response);
            $size = $response->hasHeader('Content-Length')
                ? (int) $response->getHeader('Content-Length')[0]
                : null;
            $mimeType = $response->hasHeader('Content-Type')
                ? $response->getHeader('Content-Type')[0]
                : null;
            $lastModified = $this->getModifiedTimeFromResponse($response);

            return new FileInfo(
                name: $filename ?? 'unknown',
                size: $size,
                mimeType: $mimeType,
                lastModified: $lastModified
            );
        } catch (GuzzleException $e) {
            throw new FileURLRetrievalException(
                "Failed to retrieve file info: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Download file from URL
     */
    public function download(
        ?string $url = null,
        ?string $output = null,
        ?string $id = null,
        bool $fuzzy = false,
        bool $resume = false,
        ?string $format = null
    ): string {
        if (($url === null && $id === null) || ($url !== null && $id !== null)) {
            throw new \InvalidArgumentException('Either url or id must be specified');
        }

        if ($id !== null) {
            $url = "https://drive.google.com/uc?id={$id}";
        }

        $urlOrigin = $url;
        ['fileId' => $gdriveFileId, 'isDownloadLink' => $isGdriveDownloadLink] =
            UrlParser::parseUrl($url, !$fuzzy);

        if ($fuzzy && $gdriveFileId) {
            $url = "https://drive.google.com/uc?id={$gdriveFileId}";
            $urlOrigin = $url;
            $isGdriveDownloadLink = true;
        }

        // Handle Google Drive download with confirmation page
        $response = $this->handleGoogleDriveDownload(
            $url,
            $urlOrigin,
            $gdriveFileId,
            $isGdriveDownloadLink,
            $format
        );

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
            if (!$this->quiet) {
                fwrite(STDERR, "Skipping already downloaded file {$output}\n");
            }
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
                        'headers' => ['Range' => "bytes={$startSize}-"]
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

        if (!$this->quiet) {
            fwrite(STDERR, "Downloading...\n");
            if ($resume && $startSize > 0) {
                fwrite(STDERR, "Resume: {$tmpFile}\n");
            }
            if ($urlOrigin !== $url) {
                fwrite(STDERR, "From (original): {$urlOrigin}\n");
                fwrite(STDERR, "From (redirected): {$url}\n");
            } else {
                fwrite(STDERR, "From: {$url}\n");
            }
            fwrite(STDERR, "To: " . realpath(dirname($output)) . '/' . basename($output) . "\n\n");
        }

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
        ?string $gdriveFileId,
        bool $isGdriveDownloadLink,
        ?string $format
    ) {
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            try {
                $response = $this->client->request('GET', $url, ['stream' => true]);

                if (!($gdriveFileId && $isGdriveDownloadLink)) {
                    return $response;
                }

                // Check if it's a Google Doc/Sheet/Slide
                if ($url === $urlOrigin && $response->getStatusCode() === 500) {
                    $url = "https://drive.google.com/open?id={$gdriveFileId}";
                    $retries++;
                    continue;
                }

                if (
                    $response->hasHeader('Content-Type') &&
                    str_starts_with($response->getHeader('Content-Type')[0], 'text/html')
                ) {
                    $content = (string) $response->getBody();

                    // Check for Google Docs/Sheets/Slides
                    if (preg_match('/<title>(.+)<\/title>/', $content, $matches)) {
                        $title = $matches[1];

                        if (str_ends_with($title, ' - Google Docs')) {
                            $url = "https://docs.google.com/document/d/{$gdriveFileId}/export?format=" .
                                   ($format ?? 'docx');
                            $retries++;
                            continue;
                        } elseif (str_ends_with($title, ' - Google Sheets')) {
                            $url = "https://docs.google.com/spreadsheets/d/{$gdriveFileId}/export?format=" .
                                   ($format ?? 'xlsx');
                            $retries++;
                            continue;
                        } elseif (str_ends_with($title, ' - Google Slides')) {
                            $url = "https://docs.google.com/presentation/d/{$gdriveFileId}/export?format=" .
                                   ($format ?? 'pptx');
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
                $content = (string) $response->getBody();
                $url = $this->getUrlFromGdriveConfirmation($content);

                return $this->client->request('GET', $url, ['stream' => true]);
            } catch (GuzzleException $e) {
                throw new FileURLRetrievalException(
                    "Failed to retrieve file url:\n\n" . $e->getMessage() . "\n\n" .
                    "You may still be able to access the file from the browser:\n\n" .
                    "\t{$urlOrigin}\n\n" .
                    "but GDown can't. Please check connections and permissions.",
                    0,
                    $e
                );
            }
        }

        throw new FileURLRetrievalException("Maximum retries exceeded");
    }

    private function getUrlFromGdriveConfirmation(string $contents): string
    {
        $lines = explode("\n", $contents);

        foreach ($lines as $line) {
            // Try to find download URL in href
            if (preg_match('/href="(\/uc\?export=download[^"]+)"/', $line, $matches)) {
                $url = 'https://docs.google.com' . html_entity_decode($matches[1]);
                return str_replace('&amp;', '&', $url);
            }

            // Try to find download form
            $crawler = new Crawler($line);
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
                return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] .
                       ($parsedUrl['path'] ?? '') . '?' . $query;
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

        throw new FileURLRetrievalException(
            "Cannot retrieve the public link of the file. " .
            "You may need to change the permission to " .
            "'Anyone with the link', or have had many accesses. " .
            "Check FAQ in https://github.com/wkentaro/gdown?tab=readme-ov-file#faq."
        );
    }

    private function getFilenameFromResponse($response): ?string
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

    private function getModifiedTimeFromResponse($response): ?\DateTimeInterface
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
        $fp = fopen($outputFile, $startSize > 0 ? 'ab' : 'wb');
        if ($fp === false) {
            throw new \RuntimeException("Cannot open file for writing: {$outputFile}");
        }

        try {
            $body = $response->getBody();
            $contentLength = $response->hasHeader('Content-Length')
                ? (int) $response->getHeader('Content-Length')[0]
                : null;

            $totalSize = $contentLength !== null ? $contentLength + $startSize : null;
            $downloaded = $startSize;

            if (!$this->quiet && $totalSize !== null) {
                fwrite(STDERR, sprintf("Total size: %s\n", $this->formatBytes($totalSize)));
            }

            $startTime = microtime(true);

            while (!$body->eof()) {
                $chunk = $body->read(self::CHUNK_SIZE);
                fwrite($fp, $chunk);
                $downloaded += strlen($chunk);

                if (!$this->quiet) {
                    $this->showProgress($downloaded, $totalSize);
                }

                if ($this->speedLimit !== null) {
                    $elapsedTime = microtime(true) - $startTime;
                    $expectedTime = ($downloaded - $startSize) / $this->speedLimit;
                    if ($elapsedTime < $expectedTime) {
                        usleep((int)(($expectedTime - $elapsedTime) * 1000000));
                    }
                }
            }

            if (!$this->quiet) {
                fwrite(STDERR, "\n");
            }
        } finally {
            fclose($fp);
        }
    }

    private function showProgress(int $downloaded, ?int $total): void
    {
        if ($total !== null) {
            $percentage = ($downloaded / $total) * 100;
            $bar = str_repeat('=', (int)($percentage / 2));
            $bar = str_pad($bar, 50, ' ');
            fprintf(
                STDERR,
                "\r[%s] %3.1f%% %s / %s",
                $bar,
                $percentage,
                $this->formatBytes($downloaded),
                $this->formatBytes($total)
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

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
