<?php

declare(strict_types=1);

namespace Zupolgec\GDown\Tests;

use Zupolgec\GDown\UrlParser;
use PHPUnit\Framework\TestCase;

class UrlParserTest extends TestCase
{
    public function testIsGoogleDriveUrl(): void
    {
        $this->assertTrue(UrlParser::isGoogleDriveUrl('https://drive.google.com/file/d/123/view'));
        $this->assertTrue(UrlParser::isGoogleDriveUrl('https://docs.google.com/document/d/123/edit'));
        $this->assertFalse(UrlParser::isGoogleDriveUrl('https://example.com/file'));
    }

    public function testParseUrlWithFileId(): void
    {
        $result = UrlParser::parseUrl('https://drive.google.com/uc?id=1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ', false);

        $this->assertEquals('1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ', $result['fileId']);
        $this->assertTrue($result['isDownloadLink']);
    }

    public function testParseUrlWithFileView(): void
    {
        $result = UrlParser::parseUrl('https://drive.google.com/file/d/10DXTyitz_PnjGP9u7vDzp916iRshE43K/view', false);

        $this->assertEquals('10DXTyitz_PnjGP9u7vDzp916iRshE43K', $result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }

    public function testParseUrlWithDocumentEdit(): void
    {
        $result = UrlParser::parseUrl('https://docs.google.com/document/d/abc123/edit', false);

        $this->assertEquals('abc123', $result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }

    public function testParseUrlWithPresentation(): void
    {
        $result = UrlParser::parseUrl('https://docs.google.com/presentation/d/xyz789/view', false);

        $this->assertEquals('xyz789', $result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }

    public function testParseUrlWithSpreadsheet(): void
    {
        $result = UrlParser::parseUrl('https://docs.google.com/spreadsheets/d/sheet123/edit', false);

        $this->assertEquals('sheet123', $result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }

    public function testParseNonGoogleDriveUrl(): void
    {
        $result = UrlParser::parseUrl('https://example.com/file.zip', false);

        $this->assertNull($result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }

    public function testParseUrlWithUserPath(): void
    {
        $result = UrlParser::parseUrl('https://drive.google.com/file/u/0/d/file123/view', false);

        $this->assertEquals('file123', $result['fileId']);
        $this->assertFalse($result['isDownloadLink']);
    }
}
