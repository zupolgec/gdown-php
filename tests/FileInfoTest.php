<?php

declare(strict_types=1);

use Zupolgec\GDown\FileInfo;

test('file info creation', function () {
    $fileInfo = new FileInfo(
        name: 'test.txt',
        size: 1024,
        mimeType: 'text/plain',
    );

    expect($fileInfo->name)->toBe('test.txt');
    expect($fileInfo->size)->toBe(1024);
    expect($fileInfo->mimeType)->toBe('text/plain');
});

test('formatted size bytes', function () {
    $fileInfo = new FileInfo(
        name: 'file',
        size: 512,
    );
    expect($fileInfo->getFormattedSize())->toBe('512.00 B');
});

test('formatted size kilobytes', function () {
    $fileInfo = new FileInfo(
        name: 'file',
        size: 2048,
    );
    expect($fileInfo->getFormattedSize())->toBe('2.00 KB');
});

test('formatted size megabytes', function () {
    $fileInfo = new FileInfo(
        name: 'file',
        size: 5242880,
    ); // 5 MB
    expect($fileInfo->getFormattedSize())->toBe('5.00 MB');
});

test('formatted size gigabytes', function () {
    $fileInfo = new FileInfo(
        name: 'file',
        size: 2147483648,
    ); // 2 GB
    expect($fileInfo->getFormattedSize())->toBe('2.00 GB');
});

test('formatted size unknown', function () {
    $fileInfo = new FileInfo(
        name: 'file',
        size: null,
    );
    expect($fileInfo->getFormattedSize())->toBe('Unknown');
});

test('file info with datetime', function () {
    $dateTime = new \DateTime('2024-01-01 12:00:00');
    $fileInfo = new FileInfo(
        name: 'file',
        size: 1024,
        mimeType: 'text/plain',
        lastModified: $dateTime,
    );

    expect($fileInfo->lastModified)->toBe($dateTime);
});
