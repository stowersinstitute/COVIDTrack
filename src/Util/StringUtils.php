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
    public static function generateRandomString(int $length) : string
    {
        $alphabet = [
            'B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z',
            '2', '5', '7', '9'
        ];

        $randomStr = '';

        for ($i=0; $i < $length; $i++) {
            $randomStr .= $alphabet[rand(0, count($alphabet) - 1)];
        }

        return $randomStr;
    }
}