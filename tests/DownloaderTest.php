<?php

declare(strict_types=1);

use Zupolgec\GDown\Downloader;
use Zupolgec\GDown\Exceptions\FileURLRetrievalException;

$testDir = '';

beforeEach(function () use (&$testDir) {
    $testDir = sys_get_temp_dir() . '/gdown_test_' . uniqid();
    mkdir($testDir, 0755, true);
});

afterEach(function () use (&$testDir) {
    if (is_dir($testDir)) {
        $files = glob($testDir . '/*');
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            unlink($file);
        }
        rmdir($testDir);
    }
});

test('download regular url', function () use (&$testDir) {
    $downloader = new Downloader(quiet: true);
    $output = $testDir . '/test_file.txt';

    $result = $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
        output: $output,
    );

    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
});

test('download with invalid url', function () use (&$testDir) {
    $downloader = new Downloader(quiet: true);
    $downloader->download(
        url: 'https://invalid-domain-that-does-not-exist-12345.com/file.txt',
        output: $testDir . '/output.txt',
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

test('download with invalid id throws exception', function () use (&$testDir) {
    $downloader = new Downloader(quiet: true);
    $output = $testDir . '/test_file.txt';

    $downloader->download(
        id: 'invalid_file_id_that_does_not_exist',
        output: $output,
    );
})->throws(FileURLRetrievalException::class);



test('download with output directory', function () use (&$testDir) {
    $downloader = new Downloader(quiet: true);

    $result = $downloader->download(
        url: 'https://raw.githubusercontent.com/wkentaro/gdown/3.1.0/gdown/__init__.py',
        output: $testDir . '/',
    );

    expect($result)->toStartWith($testDir);
    expect($result)->toBeFile();
});

test('download resume', function () use (&$testDir) {
    $downloader = new Downloader(quiet: true);
    $output = $testDir . '/resume_test.txt';

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
