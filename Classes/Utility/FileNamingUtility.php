<?php

declare(strict_types=1);

namespace Remind\Backup\Utility;

use TYPO3\CMS\Core\Utility\PathUtility;

class FileNamingUtility
{
    public static function getRegexPattern(string $file): string
    {
        return '/^' . $file . '_\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2}.sql$/';
    }

    public static function buildPath(
        string $dir,
        string $file,
        ?bool $appendDateTime = false,
        bool $appendExtension = false
    ): string {
        $path = PathUtility::sanitizeTrailingSeparator($dir) . $file;
        if ($appendDateTime) {
            $path = $path . '_' . self::getDateTime();
        }
        if ($appendExtension) {
            $path = $path . '.sql';
        }
        return $path;
    }

    private static function getDateTime(): string
    {
        return date('Y-m-d\TH-i-s');
    }
}
