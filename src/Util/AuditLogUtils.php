<?php


namespace App\Util;


class AuditLogUtils
{
    const HUMAN_READABLE_DATE_TIME_FORMAT = 'Y-m-d H:i';

    /**
     * Converts $value into a human-readable string suitable for display in the audit log
     */
    public static function getHumanReadableString($value) : string
    {
        // Null values
        if ($value === null) return '(empty)';

        // Dates
        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::HUMAN_READABLE_DATE_TIME_FORMAT);
        }

        // Booleans
        if (is_bool($value)) return ($value) ? 'Yes' : 'No';

        // Arrays
        if (is_array($value)) return join(", ", $value);

        return strval($value);
    }
}