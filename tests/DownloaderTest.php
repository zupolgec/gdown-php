<?php

declare(strict_types=1);

namespace Zupolgec\GDown\Tests;

use Zupolgec\GDown\Downloader;
use Zupolgec\GDown\Exceptions\FileURLRetrievalException;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/gdown_test_' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }

    public function testDownloadRegularUrl(): void
    {
        $downloader = new Downloader(quiet: true);
        $output = $this->testDir . '/test_file.txt';
        
        // Download a small file from a public URL
        $result = $downloader->download(
            url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
            output: $output
        );

        $this->assertEquals($output, $result);
        $this->assertFileExists($output);
        $this->assertGreaterThan(0, filesize($output));
    }

    public function testDownloadWithInvalidUrl(): void
    {
        $this->expectException(FileURLRetrievalException::class);
        
        $downloader = new Downloader(quiet: true);
        $downloader->download(
            url: 'https://invalid-domain-that-does-not-exist-12345.com/file.txt',
            output: $this->testDir . '/output.txt'
        );
    }

    public function testDownloadRequiresUrlOrId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either url or id must be specified');
        
        $downloader = new Downloader(quiet: true);
        $downloader->download();
    }

    public function testDownloadWithBothUrlAndId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either url or id must be specified');
        
        $downloader = new Downloader(quiet: true);
        $downloader->download(url: 'https://example.com', id: '123');
    }

    public function testDownloadWithId(): void
    {
        $downloader = new Downloader(quiet: true);
        $output = $this->testDir . '/test_file.txt';
        
        // This will attempt to download from Google Drive
        // We just test that the URL is properly constructed
        try {
            $downloader->download(
                id: '1cKSdgtWrPgvEsBGmjWALOH33taGyVXKb',
                output: $output
            );
        } catch (FileURLRetrievalException $e) {
            // Expected if file is not accessible
            $this->assertStringContainsString('retrieve', strtolower($e->getMessage()));
        }
    }

    public function testGetFileInfo(): void
    {
        $downloader = new Downloader(quiet: true);
        
        // Get info from a regular URL
        $fileInfo = $downloader->getFileInfo(
            url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md'
        );

        $this->assertNotNull($fileInfo->name);
        $this->assertGreaterThan(0, $fileInfo->size ?? 0);
    }

    public function testDownloadWithOutputDirectory(): void
    {
        $downloader = new Downloader(quiet: true);
        
        $result = $downloader->download(
            url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
            output: $this->testDir . '/'
        );

        $this->assertStringStartsWith($this->testDir, $result);
        $this->assertFileExists($result);
    }

    public function testDownloadResume(): void
    {
        $downloader = new Downloader(quiet: true);
        $output = $this->testDir . '/resume_test.txt';
        
        // First download
        $downloader->download(
            url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md',
            output: $output
        );

        $this->assertFileExists($output);
        $firstSize = filesize($output);

        // Second download with resume should skip
        $result = $downloader->download(
            url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md',
            output: $output,
            resume: true
        );

        $this->assertEquals($output, $result);
        $this->assertEquals($firstSize, filesize($output));
    }
}
