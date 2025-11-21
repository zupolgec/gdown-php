# GDown PHP

> ⚠️ **VIBE-CODED**: This entire package was coded in a single AI-powered session. Proceed with confidence! 🎵

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)](https://www.php.net/)

**Google Drive Public File Downloader when Curl/Wget Fails - PHP Implementation**

GDown PHP is a PHP port of the popular Python [gdown](https://github.com/wkentaro/gdown) library, providing robust downloading of public files from Google Drive.

## Features

- ✅ **Folder downloads** - Download entire Google Drive folders (up to 50 files)
- ✅ **Skip security notices** - Download large files that curl/wget can't handle
- ✅ **File info preview** - Check file name and size BEFORE downloading (unique to PHP version!)
- ✅ **Fuzzy URL parsing** - Extract file IDs from any Google Drive URL format
- ✅ **Resume support** - Continue interrupted downloads
- ✅ **Google Docs/Sheets/Slides** - Download with format conversion (PDF, DOCX, XLSX, PPTX, etc.)
- ✅ **Speed limiting** - Control download bandwidth
- ✅ **Proxy support** - Download through HTTP/HTTPS proxies
- ✅ **Cookie support** - Use browser cookies for authenticated access
- ✅ **Progress display** - Real-time download progress
- ✅ **CLI and Library** - Use as command-line tool or integrate into your application

## Installation

### Via Composer

```bash
composer require zupolgec/gdown-php
```

### Global Installation (for CLI usage)

```bash
composer global require gdown/gdown-php
```

## Usage

### Command Line Interface

#### Basic Download

```bash
# Download by URL
gdown https://drive.google.com/uc?id=1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ

# Download by file ID
gdown 1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ

# Specify output filename
gdown https://drive.google.com/uc?id=FILE_ID -O output.zip
```

#### Fuzzy URL Extraction

```bash
# Extract file ID from any Drive URL format
gdown --fuzzy 'https://drive.google.com/file/d/10DXTyitz_PnjGP9u7vDzp916iRshE43K/view?usp=drive_link'
```

#### Get File Info (Without Downloading)

```bash
# Check file name and size before downloading
gdown --info https://drive.google.com/uc?id=FILE_ID
```

Output:
```
File Name: my-large-file.zip
File Size: 1.25 GB
MIME Type: application/zip
Last Modified: 2024-01-15 10:30:00
```

#### Download Google Docs/Sheets/Slides

```bash
# Download Google Doc as DOCX (default)
gdown https://docs.google.com/document/d/DOC_ID/edit

# Download as PDF
gdown https://docs.google.com/document/d/DOC_ID/edit --format pdf

# Download Google Sheet as XLSX
gdown https://docs.google.com/spreadsheets/d/SHEET_ID/edit

# Download Google Slides as PPTX
gdown https://docs.google.com/presentation/d/SLIDE_ID/edit
```

#### Resume Downloads

```bash
# Resume interrupted download
gdown https://drive.google.com/uc?id=FILE_ID --continue
```

#### Speed Limiting

```bash
# Limit download speed to 1MB/s
gdown https://drive.google.com/uc?id=FILE_ID --speed 1MB
```

#### Proxy Support

```bash
# Download through proxy
gdown https://drive.google.com/uc?id=FILE_ID --proxy http://proxy.example.com:8080
```

#### All Options

```bash
gdown --help
```

### PHP Library Usage

#### Basic Download

```php
<?php

require 'vendor/autoload.php';

use GDown\GDown;

// Download by URL
$file = GDown::download(
    url: 'https://drive.google.com/uc?id=1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ',
    output: 'downloaded-file.zip'
);

echo "Downloaded: {$file}\n";

// Download by file ID
$file = GDown::download(
    id: '1l_5RK28JRL19wpT22B-DY9We3TVXnnQQ',
    output: 'downloaded-file.zip'
);
```

#### Get File Info Before Downloading

```php
<?php

use GDown\GDown;

// Get file information without downloading
$fileInfo = GDown::getFileInfo(
    url: 'https://drive.google.com/uc?id=FILE_ID'
);

echo "File: {$fileInfo->name}\n";
echo "Size: {$fileInfo->getFormattedSize()}\n";
echo "MIME: {$fileInfo->mimeType}\n";

if ($fileInfo->size > 1024 * 1024 * 100) { // > 100MB
    echo "File is large, are you sure you want to download?\n";
    // ... then download if confirmed
}
```

#### Advanced Usage

```php
<?php

use GDown\Downloader;

$downloader = new Downloader(
    quiet: false,              // Show progress
    proxy: 'http://proxy:8080', // Use proxy
    speedLimit: 1024 * 1024,   // 1MB/s speed limit
    useCookies: true,          // Use cookies from ~/.cache/gdown/cookies.txt
    verify: true,              // Verify SSL certificates
    userAgent: 'My Custom UA'  // Custom user agent
);

$file = $downloader->download(
    url: 'https://drive.google.com/uc?id=FILE_ID',
    output: 'output.zip',
    fuzzy: true,               // Extract ID from any URL format
    resume: true,              // Resume if interrupted
    format: 'pdf'              // For Google Docs (pdf, docx, etc.)
);
```

#### Fuzzy URL Matching

```php
<?php

use GDown\GDown;

// Works with any Google Drive URL format
$file = GDown::download(
    url: 'https://drive.google.com/file/d/FILE_ID/view?usp=sharing',
    fuzzy: true,
    output: 'file.zip'
);
```

## Example: Testing with Your Provided URL

```bash
# Download the file from your example
gdown https://drive.google.com/file/d/10DXTyitz_PnjGP9u7vDzp916iRshE43K/view?usp=drive_link --fuzzy

# Or check its info first
gdown https://drive.google.com/file/d/10DXTyitz_PnjGP9u7vDzp916iRshE43K/view?usp=drive_link --info --fuzzy
```

## Comparison with Python gdown

### Feature Parity

| Feature | Python gdown | GDown PHP |
|---------|-------------|-----------|
| Download large files | ✅ | ✅ |
| Fuzzy URL parsing | ✅ | ✅ |
| Resume downloads | ✅ | ✅ |
| Speed limiting | ✅ | ✅ |
| Proxy support | ✅ | ✅ |
| Cookie support | ✅ | ✅ |
| Google Docs formats | ✅ | ✅ |
| Folder download | ✅ | ❌ (planned) |
| **File info preview** | ❌ | ✅ **NEW!** |

### Unique PHP Features

- **File Info Preview**: Check file size and name before downloading (not available in Python version)
- **Native Composer Integration**: Seamless integration with PHP projects
- **Type Safety**: Full PHP 8.0+ type declarations for better IDE support

## Requirements

- PHP 8.0 or higher
- ext-curl
- ext-json
- ext-mbstring

## Development

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

### Code Style

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

### Static Analysis

```bash
composer phpstan
```

## FAQ

### I get a 'Permission Denied' error

Make sure the Google Drive file permission is set to 'Anyone with the link'.

### I set 'Anyone with Link' but still can't download

Google restricts access when downloads are concentrated. Try:

1. Download your browser cookies using an extension like "Get cookies.txt LOCALLY"
2. Move `cookies.txt` to `~/.cache/gdown/cookies.txt`
3. Run download again

### How do I download private files?

Use the cookie method above. GDown will use your browser's authentication cookies.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Credits

- Inspired by [wkentaro/gdown](https://github.com/wkentaro/gdown) (Python)
- Developed with ❤️ for the PHP community

## Links

- [GitHub Repository](https://github.com/zupolgec/gdown-php)
- [Packagist Page](https://packagist.org/packages/zupolgec/gdown-php)
- [Issue Tracker](https://github.com/zupolgec/gdown-php/issues)
- [Original Python gdown](https://github.com/wkentaro/gdown)
