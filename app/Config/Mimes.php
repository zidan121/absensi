<?php

namespace Config;

/**
 * MIME type map used by CodeIgniter's uploaded-file handling.
 * Keep this list focused on formats accepted or commonly used by the app.
 */
class Mimes
{
    public static array $mimes = [
        'sql' => ['application/sql', 'text/sql', 'application/octet-stream', 'text/plain'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
        'json' => ['application/json', 'text/json'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'pdf' => ['application/pdf'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    public static function guessTypeFromExtension(string $extension)
    {
        $extension = trim(strtolower($extension), '. ');

        if (! array_key_exists($extension, static::$mimes)) {
            return null;
        }

        return is_array(static::$mimes[$extension])
            ? static::$mimes[$extension][0]
            : static::$mimes[$extension];
    }

    public static function guessExtensionFromType(string $type, ?string $proposedExtension = null)
    {
        $type = trim(strtolower($type), '. ');
        $proposedExtension = trim(strtolower($proposedExtension ?? ''), '. ');

        if (
            $proposedExtension !== ''
            && array_key_exists($proposedExtension, static::$mimes)
            && in_array($type, (array) static::$mimes[$proposedExtension], true)
        ) {
            return $proposedExtension;
        }

        foreach (static::$mimes as $extension => $types) {
            if (in_array($type, (array) $types, true)) {
                return $extension;
            }
        }

        return null;
    }
}
