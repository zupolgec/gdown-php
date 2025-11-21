<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zupolgec\GDown\Exceptions\FolderContentsMaximumLimitException;

class FolderDownloader
{
    private const MAX_FILES = 50;

    private Client $client;
    private Downloader $downloader;
    private LoggerInterface $logger;

    public function __construct(
        private readonly bool $quiet = false,
        private readonly null|string $userAgent = null,
        null|LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? ($quiet ? new NullLogger() : new StderrLogger());
        
        $this->client = new Client([
            'verify' => true,
            'headers' => [
                'User-Agent' => $this->userAgent ?? UserAgent::DEFAULT,
            ],
        ]);

        $this->downloader = new Downloader(
            quiet: $this->quiet,
            userAgent: $this->userAgent,
            logger: $this->logger,
        );
    }

    /**
     * Download entire folder from Google Drive
     *
     * @return array{files: array<string>, folder: string}
     */
    public function downloadFolder(string $url, null|string $output = null, bool $quiet = false): array
    {
        // Extract folder ID from URL
        $folderId = $this->extractFolderId($url);

        if (!$folderId) {
            throw new \InvalidArgumentException('Invalid Google Drive folder URL');
        }

        // Get folder contents
        $files = $this->getFolderContents($folderId);

        if (count($files) > self::MAX_FILES) {
            throw new FolderContentsMaximumLimitException(sprintf(
                'Folder contains %d files. Maximum supported is %d files.',
                count($files),
                self::MAX_FILES,
            ));
        }

        // Create output directory
        $folderName = $output ?? $this->getFolderName($folderId);
        if (!is_dir($folderName)) {
            mkdir($folderName, 0755, true);
        }

        $this->logger->info(sprintf("Downloading %d files to %s", count($files), realpath($folderName)));

        // Download all files
        $downloaded = [];
        $index = 0;

        foreach ($files as $file) {
            $index++;

            $this->logger->info(sprintf("[%d/%d] Downloading: %s", $index, count($files), $file['name']));

            try {
                $outputFile = $folderName . DIRECTORY_SEPARATOR . $file['name'];

                // Download file
                $this->downloader->download(
                    id: $file['id'],
                    output: $outputFile,
                );

                $downloaded[] = $outputFile;
            } catch (\Exception $e) {
                $this->logger->warning(sprintf("  ⚠ Failed: %s", $e->getMessage()));
            }
        }

        $this->logger->info(sprintf(
            "\n✓ Downloaded %d/%d files to %s",
            count($downloaded),
            count($files),
            realpath($folderName),
        ));

        return [
            'files' => $downloaded,
            'folder' => $folderName,
        ];
    }

    /**
     * Extract folder ID from Google Drive folder URL
     */
    private function extractFolderId(string $url): null|string
    {
        // Pattern: /folders/{id}
        if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern: ?id={id}
        $parsed = parse_url($url);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            if (isset($query['id'])) {
                return $query['id'];
            }
        }

        return null;
    }

    /**
     * Get folder name from folder ID
     */
    private function getFolderName(string $folderId): string
    {
        $url = "https://drive.google.com/drive/folders/{$folderId}";

        try {
            $response = $this->client->request('GET', $url);
            $html = (string) $response->getBody();

            // Try to extract folder name from title
            if (preg_match('/<title>(.+?) - Google Drive<\/title>/', $html, $matches)) {
                $name = trim($matches[1]);
                // Sanitize folder name
                $name = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $name);
                return $name ?: 'gdrive_folder';
            }
        } catch (\Exception $e) {
            // Fallback to folder ID
        }

        return 'gdrive_' . $folderId;
    }

    /**
     * Get list of files in folder
     *
     * @return array<array{id: string, name: string}>
     */
    private function getFolderContents(string $folderId): array
    {
        // Canonicalize language to English
        $url = "https://drive.google.com/drive/folders/{$folderId}?hl=en";

        try {
            $response = $this->client->request('GET', $url);
            $html = (string) $response->getBody();

            // Find the script tag with window['_DRIVE_ivd']
            $encodedData = null;

            // Try different quote styles
            if (preg_match("/window\['_DRIVE_ivd'\]\s*=\s*'([^']+)'/", $html, $matches)) {
                $encodedData = $matches[1];
            } elseif (preg_match('/window\[\'_DRIVE_ivd\'\]\s*=\s*\'([^\']+)\'/', $html, $matches)) {
                $encodedData = $matches[1];
            }

            if ($encodedData === null) {
                // Debug: check if the variable exists at all
                if (strpos($html, '_DRIVE_ivd') === false) {
                    throw new \RuntimeException('Cannot retrieve the folder information from the link. '
                    . 'The _DRIVE_ivd variable was not found in the page. '
                    . 'You may need to change the permission to "Anyone with the link".');
                }
                throw new \RuntimeException('Cannot retrieve the folder information from the link. '
                . 'Found _DRIVE_ivd but could not extract data. '
                . 'You may need to change the permission to "Anyone with the link", '
                . 'or have had many accesses.');
            }

            // Decode the escaped string (convert \x hex sequences)
            $decoded = $this->decodeEscapedString($encodedData);

            // Parse as JSON
            $folderArr = json_decode($decoded, true);

            if ($folderArr === null) {
                throw new \RuntimeException('Failed to parse folder data JSON');
            }

            $folderContents = $folderArr[0] ?? [];

            if (!is_array($folderContents)) {
                return [];
            }

            $files = [];

            // Each element in folderContents is an array where:
            // [0] = file ID
            // [2] = file name
            // [3] = MIME type
            foreach ($folderContents as $item) {
                if (!is_array($item) || count($item) < 4) {
                    continue;
                }

                $fileId = $item[0] ?? null;
                $fileName = $item[2] ?? null;
                $mimeType = $item[3] ?? null;

                // Skip folders
                if ($mimeType === 'application/vnd.google-apps.folder') {
                    continue;
                }

                if ($fileId && $fileName) {
                    $files[] = [
                        'id' => $fileId,
                        'name' => $fileName,
                    ];
                }
            }

            return $files;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to retrieve folder contents: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode escaped string from JavaScript (handles \x hex sequences)
     */
    private function decodeEscapedString(string $str): string
    {
        // Replace \x sequences with actual bytes
        $decoded = preg_replace_callback(
            '/\\\\\\\\x([0-9a-fA-F]{2})/',
            function ($matches) {
                return chr(hexdec($matches[1]));
            },
            $str,
        );

        // Handle other escape sequences
        $decoded = stripcslashes($decoded);

        return $decoded;
    }
}
