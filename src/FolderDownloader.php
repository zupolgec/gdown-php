<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

use Zupolgec\GDown\Exceptions\FolderContentsMaximumLimitException;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class FolderDownloader
{
    private const MAX_FILES = 50;
    
    private Client $client;
    private Downloader $downloader;

    public function __construct(
        private readonly bool $quiet = false,
        private readonly ?string $userAgent = null
    ) {
        $this->client = new Client([
            'verify' => true,
            'headers' => [
                'User-Agent' => $this->userAgent ?? 
                    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) ' .
                    'AppleWebKit/537.36 (KHTML, like Gecko) ' .
                    'Chrome/39.0.2171.95 Safari/537.36'
            ],
        ]);
        
        $this->downloader = new Downloader(
            quiet: $this->quiet,
            userAgent: $this->userAgent
        );
    }

    /**
     * Download entire folder from Google Drive
     * 
     * @return array{files: array<string>, folder: string}
     */
    public function downloadFolder(
        string $url,
        ?string $output = null,
        bool $quiet = false
    ): array {
        // Extract folder ID from URL
        $folderId = $this->extractFolderId($url);
        
        if (!$folderId) {
            throw new \InvalidArgumentException('Invalid Google Drive folder URL');
        }

        // Get folder contents
        $files = $this->getFolderContents($folderId);
        
        if (count($files) > self::MAX_FILES) {
            throw new FolderContentsMaximumLimitException(
                sprintf(
                    'Folder contains %d files. Maximum supported is %d files.',
                    count($files),
                    self::MAX_FILES
                )
            );
        }

        // Create output directory
        $folderName = $output ?? $this->getFolderName($folderId);
        if (!is_dir($folderName)) {
            mkdir($folderName, 0755, true);
        }

        if (!$quiet && !$this->quiet) {
            fwrite(STDERR, sprintf(
                "Downloading %d files to %s\n\n",
                count($files),
                realpath($folderName)
            ));
        }

        // Download all files
        $downloaded = [];
        $index = 0;
        
        foreach ($files as $file) {
            $index++;
            
            if (!$quiet && !$this->quiet) {
                fwrite(STDERR, sprintf(
                    "[%d/%d] Downloading: %s\n",
                    $index,
                    count($files),
                    $file['name']
                ));
            }

            try {
                $outputFile = $folderName . DIRECTORY_SEPARATOR . $file['name'];
                
                // Download file
                $this->downloader->download(
                    id: $file['id'],
                    output: $outputFile
                );
                
                $downloaded[] = $outputFile;
            } catch (\Exception $e) {
                if (!$quiet && !$this->quiet) {
                    fwrite(STDERR, sprintf(
                        "  ⚠ Failed: %s\n",
                        $e->getMessage()
                    ));
                }
            }
        }

        if (!$quiet && !$this->quiet) {
            fwrite(STDERR, sprintf(
                "\n✓ Downloaded %d/%d files to %s\n",
                count($downloaded),
                count($files),
                realpath($folderName)
            ));
        }

        return [
            'files' => $downloaded,
            'folder' => $folderName
        ];
    }

    /**
     * Extract folder ID from Google Drive folder URL
     */
    private function extractFolderId(string $url): ?string
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
        $url = "https://drive.google.com/drive/folders/{$folderId}";
        
        try {
            $response = $this->client->request('GET', $url);
            $html = (string) $response->getBody();
            
            $files = [];
            
            // Method 1: Try to extract from JSON data in page
            if (preg_match('/\["([^"]+)","([^"]+)",\["application\/vnd\.google-apps\.folder/s', $html, $matches)) {
                // This is a complex pattern - let's use a simpler approach
            }
            
            // Method 2: Parse data from JavaScript
            // Look for file data in window initialization
            $lines = explode("\n", $html);
            foreach ($lines as $line) {
                // Match file entries - simplified pattern
                if (preg_match_all('/\["([a-zA-Z0-9_-]{25,})"[^\]]*"([^"]+\.(?:txt|pdf|jpg|jpeg|png|zip|doc|docx|xls|xlsx|ppt|pptx))"/', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $fileId = $match[1];
                        $fileName = $match[2];
                        
                        // Avoid duplicates
                        $exists = false;
                        foreach ($files as $file) {
                            if ($file['id'] === $fileId) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            $files[] = [
                                'id' => $fileId,
                                'name' => $fileName
                            ];
                        }
                    }
                }
            }
            
            return $files;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to retrieve folder contents: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
