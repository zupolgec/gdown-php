<?php

declare(strict_types=1);

namespace Zupolgec\GDown\Tests;

use Zupolgec\GDown\FileInfo;
use PHPUnit\Framework\TestCase;

class FileInfoTest extends TestCase
{
    public function testFileInfoCreation(): void
    {
        $fileInfo = new FileInfo(
            name: 'test.txt',
            size: 1024,
            mimeType: 'text/plain'
        );

        $this->assertEquals('test.txt', $fileInfo->name);
        $this->assertEquals(1024, $fileInfo->size);
        $this->assertEquals('text/plain', $fileInfo->mimeType);
    }

    public function testFormattedSizeBytes(): void
    {
        $fileInfo = new FileInfo(name: 'file', size: 512);
        $this->assertEquals('512.00 B', $fileInfo->getFormattedSize());
    }

    public function testFormattedSizeKilobytes(): void
    {
        $fileInfo = new FileInfo(name: 'file', size: 2048);
        $this->assertEquals('2.00 KB', $fileInfo->getFormattedSize());
    }

    public function testFormattedSizeMegabytes(): void
    {
        $fileInfo = new FileInfo(name: 'file', size: 5242880); // 5 MB
        $this->assertEquals('5.00 MB', $fileInfo->getFormattedSize());
    }

    public function testFormattedSizeGigabytes(): void
    {
        $fileInfo = new FileInfo(name: 'file', size: 2147483648); // 2 GB
        $this->assertEquals('2.00 GB', $fileInfo->getFormattedSize());
    }

    public function testFormattedSizeUnknown(): void
    {
        $fileInfo = new FileInfo(name: 'file', size: null);
        $this->assertEquals('Unknown', $fileInfo->getFormattedSize());
    }

    public function testFileInfoWithDateTime(): void
    {
        $dateTime = new \DateTime('2024-01-01 12:00:00');
        $fileInfo = new FileInfo(
            name: 'file',
            size: 1024,
            mimeType: 'text/plain',
            lastModified: $dateTime
        );

        $this->assertSame($dateTime, $fileInfo->lastModified);
    }
}
