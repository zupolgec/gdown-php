<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class FileInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $size,
        public readonly ?string $mimeType = null,
        public readonly ?\DateTimeInterface $lastModified = null
    ) {
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedSize(): string
    {
        if ($this->size === null) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $size, $units[$unitIndex]);
    }
}
