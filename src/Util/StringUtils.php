<?php


namespace App\Util;


class StringUtils
{
    /**
     * Converts a camel-cased string to a spaced string and capitalizes the first letter of each word.
     *
     * Examples:
     *      - TestString => Test String
     *      - testString => Test String
     *      - TESTstring => Test String
     *
     * @param $string
     * @return string
     */
    public static function camelCaseToTitleCase($string) {
        /*
         * Insert a space before any capital letter followed by a lowercase letter
         *  - but not at the beginning of the string
         */
        $replaced = preg_replace('/(?<!^)([A-Z])(?=[a-z])/', ' $1', $string);

        // Special case: block of capitals at the end (exampleSTRING -> example STRING)
        $replaced = preg_replace('/^([a-z]+)([A-Z]+)$/', '$1 $2', $replaced);

        // Uppercase first letter of each word
        $replaced = ucwords(strtolower($replaced));

        return $replaced;
    }

    /**
     * Returns a string containing $length random characters
     *
     * The string has the following features:
     *  - No ambiguous characters (ie. 0 vs. O or 1 vs l)
     *  - No vowels (so no recognizable words, dirty or otherwise)
     */
    public static function generateRandomString(int $length, bool $lettersOnly = false) : string
    {
        $alphabet = [
            'B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z',
        ];

        if (!$lettersOnly) {
            $alphabet = array_merge($alphabet, ['2', '5', '7', '9']);
        }

        $randomStr = '';
        for ($i=0; $i < $length; $i++) {
            $randomStr .= $alphabet[array_rand($alphabet)];
        }

        return $randomStr;
    }

    /**
     * Converts a base-10 integer to a custom base20 encoding that avoids ambiguous characters
     *
     * Examples:
     *      0   -> B
     *      19  -> Z
     *      20  -> CB
     *      110 -> HN
     *
     * Negative numbers are not supported
     */
    public static function base10ToBase20(int $base10, $padUntilLength = null) : string
    {
        $base20Alphabet = [ 'B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z' ];
        $base20Str = '';

        if ($base10 < 0) throw new \InvalidArgumentException('Negative numbers are not supported');
        if ($padUntilLength !== null && $padUntilLength <= 0) throw new \InvalidArgumentException('padUntilLength must be greater than 0');

        do {
            $mod = $base10 % 20;
            $remainder = floor($base10 / 20);
            $base10 = $remainder;

            $base20Str = $base20Alphabet[$mod] . $base20Str;
        } while ($base10 > 0);

        // Apply padding, if requested
        if ($padUntilLength !== null) {
            $numPaddingNeeded = $padUntilLength - strlen($base20Str);
            if ($numPaddingNeeded > 0) {
                $base20Str = str_repeat($base20Alphabet[0], $numPaddingNeeded) . $base20Str;
            }
        }

        return $base20Str;
    }
}