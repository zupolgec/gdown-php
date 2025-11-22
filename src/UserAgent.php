<?php

declare(strict_types=1);

namespace Zupolgec\GDown;

class UserAgent
{
    /**
     * Default User-Agent string for HTTP requests
     *
     * Updated: 2025-11-21
     * Chrome Version: 138.0.0.0
     * Source: https://jnrbsn.github.io/user-agents/user-agents.json
     */
    public const DEFAULT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36';

    /**
     * Legacy User-Agent for getFileInfo()
     *
     * Google Drive returns proper MIME types (application/vnd.android.package-archive, text/plain, text/html)
     * with older Chrome versions, but returns generic application/octet-stream with modern Chrome 98+.
     *
     * Chrome Version: 39.0.2171.95
     */
    public const LEGACY_FOR_FILE_INFO = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36';
}
