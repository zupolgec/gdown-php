<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class FileInfo
{
    public function __construct(
        public readonly string $name,
        public readonly null|int $size,
        public readonly null|string $mimeType = null,
        public readonly null|\DateTimeInterface $lastModified = null,
    ) {}

    /**
     * Get human-readable file size
     */
    public function getFormattedSize(): string
    {
        if ($this->size === null) {
            return 'Unknown';
        }

        return ByteFormatter::formatBytes($this->size);
    }
}
