<?php

declare(strict_types=1);

use Zupolgec\GDown\UrlParser;

test('is google drive url', function () {
    expect(UrlParser::isGoogleDriveUrl('https://drive.google.com/file/d/123/view'))->toBeTrue();
    expect(UrlParser::isGoogleDriveUrl('https://docs.google.com/document/d/123/edit'))->toBeTrue();
    expect(UrlParser::isGoogleDriveUrl('https://example.com/file'))->toBeFalse();
});

test('parse url with file id', function () {
    $result = UrlParser::parseUrl('https://drive.google.com/uc?id=1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ', false);

    expect($result['fileId'])->toBe('1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ');
    expect($result['isDownloadLink'])->toBeTrue();
});

test('parse url with file view', function () {
    $result = UrlParser::parseUrl('https://drive.google.com/file/d/10DXTyitz_PnjGP9u7vDzp916iRshE43K/view', false);

    expect($result['fileId'])->toBe('10DXTyitz_PnjGP9u7vDzp916iRshE43K');
    expect($result['isDownloadLink'])->toBeFalse();
});

test('parse url with document edit', function () {
    $result = UrlParser::parseUrl('https://docs.google.com/document/d/abc123/edit', false);

    expect($result['fileId'])->toBe('abc123');
    expect($result['isDownloadLink'])->toBeFalse();
});

test('parse url with presentation', function () {
    $result = UrlParser::parseUrl('https://docs.google.com/presentation/d/xyz789/view', false);

    expect($result['fileId'])->toBe('xyz789');
    expect($result['isDownloadLink'])->toBeFalse();
});

test('parse url with spreadsheet', function () {
    $result = UrlParser::parseUrl('https://docs.google.com/spreadsheets/d/sheet123/edit', false);

    expect($result['fileId'])->toBe('sheet123');
    expect($result['isDownloadLink'])->toBeFalse();
});

test('parse non google drive url', function () {
    $result = UrlParser::parseUrl('https://example.com/file.zip', false);

    expect($result['fileId'])->toBeNull();
    expect($result['isDownloadLink'])->toBeFalse();
});

test('parse url with user path', function () {
    $result = UrlParser::parseUrl('https://drive.google.com/file/u/0/d/file123/view', false);

    expect($result['fileId'])->toBe('file123');
    expect($result['isDownloadLink'])->toBeFalse();
});
