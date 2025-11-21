<?php

declare(strict_types=1);

use Zupolgec\GDown\Downloader;
use Zupolgec\GDown\FolderDownloader;
use Zupolgec\GDown\GDown;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/gdown_integration_' . uniqid();
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
        
        // Remove subdirectories
        $dirs = glob($this->testDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $subFiles = glob($dir . '/*');
            foreach ($subFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
        
        rmdir($this->testDir);
    }
});

test('download small text file from google drive', function () {
    $output = $this->testDir . '/test.txt';
    
    $result = GDown::download(
        url: 'https://drive.google.com/file/d/1aAlW5_7bNNsmInqDMMbSCtmzzhp7B1HY/view?usp=sharing',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(file_get_contents($output))->toBe('test');
    expect(filesize($output))->toBe(4);
})->group('integration', 'network');

test('download 100mb binary file from google drive', function () {
    $output = $this->testDir . '/100mb.bin';
    
    $result = GDown::download(
        url: 'https://drive.google.com/file/d/1Zpy0qOJPPCqSHjQN0nA0s30iqc-X1V9A/view?usp=sharing',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBe(100 * 1024 * 1024); // Exactly 100MB
    
    // Verify it's binary, not HTML
    $firstBytes = file_get_contents($output, false, null, 0, 100);
    expect($firstBytes)->not()->toContain('<!DOCTYPE');
    expect($firstBytes)->not()->toContain('<html');
})->group('integration', 'network', 'slow');

test('download html file from google drive', function () {
    $output = $this->testDir . '/test.html';
    
    $result = GDown::download(
        url: 'https://drive.google.com/file/d/1lMyghvGkvtaEGQKcJoqZYrQ205NfuCTy/view?usp=drive_link',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    
    $content = file_get_contents($output);
    expect($content)->toContain('<!DOCTYPE html>');
    expect($content)->toContain('<title>Test');
    expect(filesize($output))->toBe(92);
})->group('integration', 'network');

test('download folder from google drive', function () {
    $downloader = new FolderDownloader(quiet: true);
    
    $result = $downloader->downloadFolder(
        url: 'https://drive.google.com/drive/folders/1aRybMmKzVskp1skdA3GtpEVobuIaoDrE?usp=sharing',
        output: $this->testDir . '/test-folder'
    );
    
    expect($result['folder'])->toBeDirectory();
    expect($result['files'])->toBeArray();
    expect(count($result['files']))->toBeGreaterThanOrEqual(2);
    
    // Check that PDFs were downloaded
    $files = glob($result['folder'] . '/*.pdf');
    expect(count($files))->toBeGreaterThanOrEqual(2);
    
    // Verify files are actual PDFs, not HTML
    foreach ($files as $file) {
        expect($file)->toBeFile();
        $header = file_get_contents($file, false, null, 0, 4);
        expect($header)->toBe('%PDF'); // PDF magic number
    }
})->group('integration', 'network', 'slow');

test('library works without fuzzy flag for standard urls', function () {
    $output = $this->testDir . '/no-fuzzy.txt';
    
    // Standard /file/d/ URL should work WITHOUT fuzzy flag
    $result = GDown::download(
        url: 'https://drive.google.com/file/d/1aAlW5_7bNNsmInqDMMbSCtmzzhp7B1HY/view?usp=sharing',
        output: $output,
        quiet: true,
        fuzzy: false  // Explicitly false
    );
    
    expect($result)->toBe($output);
    expect(file_get_contents($output))->toBe('test');
})->group('integration', 'network');

test('download google doc as docx', function () {
    $output = $this->testDir . '/test.docx';
    
    $result = GDown::download(
        url: 'https://docs.google.com/document/d/1N__1FO24cDRHBx5PkriAG2yYgroWVK8n0VYfovy4H5M/edit?usp=drive_link',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
    
    // Verify it's a DOCX (ZIP-based format)
    $header = file_get_contents($output, false, null, 0, 4);
    expect($header)->toBe("PK\x03\x04"); // ZIP magic number (DOCX is ZIP)
})->group('integration', 'network', 'google-docs');

test('download google sheet as xlsx', function () {
    $output = $this->testDir . '/test.xlsx';
    
    $result = GDown::download(
        url: 'https://docs.google.com/spreadsheets/d/1ZhBKpcvZICW-U2iDI8Oda_LKjFZv1vVG88lQA8woHD8/edit?usp=drive_link',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
    
    // Verify it's an XLSX (ZIP-based format)
    $header = file_get_contents($output, false, null, 0, 4);
    expect($header)->toBe("PK\x03\x04"); // ZIP magic number (XLSX is ZIP)
})->group('integration', 'network', 'google-docs');

test('download google slides as pptx', function () {
    $output = $this->testDir . '/test.pptx';
    
    $result = GDown::download(
        url: 'https://docs.google.com/presentation/d/1oaQ5Db5GOQZPiaFaA64xjtxvlqVB-DLzKkIz_2zK_0k/edit?usp=drive_link',
        output: $output,
        quiet: true
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
    
    // Verify it's a PPTX (ZIP-based format)
    $header = file_get_contents($output, false, null, 0, 4);
    expect($header)->toBe("PK\x03\x04"); // ZIP magic number (PPTX is ZIP)
})->group('integration', 'network', 'google-docs');

test('download google doc as pdf', function () {
    $output = $this->testDir . '/test-doc.pdf';
    
    $result = GDown::download(
        url: 'https://docs.google.com/document/d/1N__1FO24cDRHBx5PkriAG2yYgroWVK8n0VYfovy4H5M/edit?usp=drive_link',
        output: $output,
        quiet: true,
        format: 'pdf'
    );
    
    expect($result)->toBe($output);
    expect($output)->toBeFile();
    expect(filesize($output))->toBeGreaterThan(0);
    
    // Verify it's a PDF
    $header = file_get_contents($output, false, null, 0, 4);
    expect($header)->toBe('%PDF');
})->group('integration', 'network', 'google-docs');
