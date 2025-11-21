<?php

declare(strict_types=1);

use Zupolgec\GDown\Downloader;
use Zupolgec\GDown\Exceptions\FileURLRetrievalException;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/gdown_test_' . uniqid();
    mkdir($this->testDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->testDir)) {
        $files = glob($this->testDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->testDir);
    }
});

test('download regular url', function () {
    $downloader = new Downloader(quiet: true);
    $output = $this->testDir . '/test_file.txt';

    $result = $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
        output: $output,
    );

    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
});

test('download with invalid url', function () {
    $downloader = new Downloader(quiet: true);
    $downloader->download(
        url: 'https://invalid-domain-that-does-not-exist-12345.com/file.txt',
        output: $this->testDir . '/output.txt',
    );
})->throws(FileURLRetrievalException::class);

test('download requires url or id', function () {
    $downloader = new Downloader(quiet: true);
    $downloader->download();
})->throws(\InvalidArgumentException::class, 'Either url or id must be specified');

test('download with both url and id', function () {
    $downloader = new Downloader(quiet: true);
    $downloader->download(
        url: 'https://example.com',
        id: '123',
    );
})->throws(\InvalidArgumentException::class, 'Either url or id must be specified');

test('download with invalid id throws exception', function () {
    $downloader = new Downloader(quiet: true);
    $output = $this->testDir . '/test_file.txt';

    $downloader->download(
        id: 'invalid_file_id_that_does_not_exist',
        output: $output,
    );
})->throws(FileURLRetrievalException::class);

test('get file info', function () {
    $downloader = new Downloader(quiet: true);
    $fileInfo = $downloader->getFileInfo(url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md');

    expect($fileInfo->name)->not()->toBeNull();
    expect($fileInfo->size ?? 0)->toBeGreaterThan(0);
});

test('download with output directory', function () {
    $downloader = new Downloader(quiet: true);

    $result = $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
        output: $this->testDir . '/',
    );

    expect($result)->toStartWith($this->testDir);
    expect($result)->toBeFile();
});

test('download resume', function () {
    $downloader = new Downloader(quiet: true);
    $output = $this->testDir . '/resume_test.txt';

    $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md',
        output: $output,
    );

    expect($output)->toBeFile();
    $firstSize = filesize($output);

    $result = $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/main/README.md',
        output: $output,
        resume: true,
    );

    expect($result)->toBe($output);
    expect(filesize($output))->toBe($firstSize);
});
