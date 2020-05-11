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
}