<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class GoogleDriveDetector
{
    public static function detectDocumentType(string $title): ?string
    {
        if (str_ends_with($title, ' - Google Docs')) {
            return 'docs';
        }
        
        if (str_ends_with($title, ' - Google Sheets')) {
            return 'sheets';
        }
        
        if (str_ends_with($title, ' - Google Slides')) {
            return 'slides';
        }
        
        return null;
    }

    public static function getExportUrl(string $fileId, string $documentType, ?string $format = null): string
    {
        $format = match ($documentType) {
            'docs' => $format ?? 'docx',
            'sheets' => $format ?? 'xlsx',
            'slides' => $format ?? 'pptx',
            default => throw new \InvalidArgumentException("Unknown document type: {$documentType}"),
        };

        return match ($documentType) {
            'docs' => "https://docs.google.com/document/d/{$fileId}/export?format={$format}",
            'sheets' => "https://docs.google.com/spreadsheets/d/{$fileId}/export?format={$format}",
            'slides' => "https://docs.google.com/presentation/d/{$fileId}/export?format={$format}",
            default => throw new \InvalidArgumentException("Unknown document type: {$documentType}"),
        };
    }

    public static function extractTitle(string $content): ?string
    {
        if (preg_match('/<title>(.+)<\/title>/', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public static function extractFolderName(string $html): ?string
    {
        if (preg_match('/<title>(.+?) - Google Drive<\/title>/', $html, $matches)) {
            $name = trim($matches[1]);
            $name = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $name);
            return $name ?? 'gdrive_folder';
        }
        
        return null;
    }
}