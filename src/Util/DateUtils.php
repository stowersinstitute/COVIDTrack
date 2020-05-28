<?php

namespace App\Util;

/**
 * Utility methods for working with dates
 * From Zan Utils library.
 */
class DateUtils
{
    public static function minutesToSeconds($minutes)
    {
        return $minutes * 60;
    }

    public static function hoursToSeconds($hours)
    {
        return $hours * 60 * 60;
    }

    public static function daysToSeconds($days)
    {
        return $days * 24 * 60 * 60;
    }

    public static function hoursToMinutes($hours)
    {
        return $hours * 60;
    }

    public static function daysToMinutes($days)
    {
        return $days * 24 * 60;
    }

    /**
     * Returns a Date object created from the given string.
     *
     * @param $string string anything strtotime understands
     * @return \DateTime
     * @throws \InvalidArgumentException
     * @deprecated Create DateTime directly via `new \DateTime($string)`
     */
    public static function strToDate($string)
    {
        $date = \DateTime::createFromFormat("U", strtotime($string));
        if (!$date) {
            throw new \InvalidArgumentException("Invalid date string");
        }

        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $date;
    }

    /**
     * Returns a short date formatted in the current locale
     *
     * @param \DateTime $time
     * @return string
     */
    public static function formatShort(\DateTime $time)
    {
        return date("Y-m-d H:i", $time->getTimestamp());
    }

    /**
     * Returns a verbose date (including time).
     * Example: 06/03/2014 04:00:00pm America/Chicago
     *
     * @param \DateTime $time
     * @return string
     */
    public static function formatLong(\DateTime $time)
    {
        return date("l, M d, Y h:ia", $time->getTimestamp());
    }

    /**
     * Returns a verbose date (including time) formatted in the current locale's
     * "FULL" setting
     *
     * @param \DateTime $time
     * @param string    $locale
     * @return string
     */
    public static function formatFull(\DateTime $time, $locale = null)
    {
        // locale: if we ever need locale support, update this
        if (!$locale) {
            $locale = static::getDefaultLocale();
        }

        $dateFormatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);

        return $dateFormatter->format($time);
    }

    /**
     * Formats a date for use as a string in an API request.
     * Example: 2014-08-22T13:00:00-05:00
     *
     * @param \DateTime $date
     * @return bool|string
     */
    public static function formatForApi(\DateTime $date)
    {
        return date_format($date, "c");
    }

    /**
     * Formats a date with year first, good for log entries or lists of dates
     *
     * Example: 2015-04-10 05:03:45am
     *
     * @param \DateTime $date
     * @return bool|string
     */
    public static function formatYmdWithTimestamp(\DateTime $date)
    {
        return date_format($date, 'Y-m-d H:i:s');
    }

    /**
     * Formats a date with year first, good for log entries or lists of dates
     *
     * Example: 2015-04-10
     *
     * @param \DateTime $date
     * @return bool|string
     */
    public static function formatYmd(\DateTime $date)
    {
        return date_format($date, 'Y-m-d');
    }

    /**
     * Attempts to print the smallest amount of text to describe a date
     * range. For example, if the two dates are on the same day, only the
     * hour and minute will be printed.
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param bool      $includePrnDuration
     * @return string
     */
    public static function formatDateRangeShort(\DateTime $start, \DateTime $end, $includePrnDuration = false)
    {
        $str = "";
        // Dates are on the same day
        if (static::dayFloor($start) == static::dayFloor($end)) {
            $str = sprintf('%s %s - %s',
                $start->format('Y-m-d'),
                $start->format('H:i'),
                $end->format('H:i')
            );
        } // Dates occur on different days
        else {
            $str = sprintf('%s - %s',
                $start->format('Y-m-d H:i'),
                $end->format('Y-m-d H:i')
            );
        }

        if ($includePrnDuration) {
            $str .= sprintf(' (%s)', static::getPrnDuration($start, $end, true));
        }

        return $str;
    }

    /**
     * Returns a date set to midnight on the first day of the month that $relDate occurs in.
     *
     * $relDate defaults to now if it is not specified
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function monthFloor(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $retDate = clone $relDate;

        $retDate->setTime(0, 0, 0);
        $retDate->setDate(
            $relDate->format("Y"),   // year
            $relDate->format("m"),
            1
        );

        return $retDate;
    }

    /**
     * Returns a date set to 23:59:59 on the last day of the month that $relDate occurs in.
     *
     * $relDate defaults to now if it is not specified
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function monthCeil(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $retDate = static::monthFloor($relDate);

        // Add a month and then subtract a day
        $retDate->add(new \DateInterval("P1M"));
        $retDate->sub(new \DateInterval("P1D"));

        // Set time to 23:59:59
        $retDate->setTime(23, 59, 59);

        return $retDate;
    }

    /**
     * Returns midnight on the specified date. Defaults to the current day if no $relDate is passed
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function dayFloor(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $relDate = clone $relDate;
        $relDate->setTime(0, 0, 0);

        return $relDate;
    }

    /**
     * Returns 23:59:59 on the specified date. Defaults to the current day if no $relDate is passed
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function dayCeil(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $relDate = clone $relDate;
        $relDate->setTime(23, 59, 59);

        return $relDate;
    }

    /**
     * Returns the specified date with seconds rounded down to the previous minute.
     * Defaults to the current minute if no $relDate is passed.
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function minuteFloor(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $date = clone $relDate;
        $date->setTime($relDate->format('H'), $relDate->format('i'));

        return $date;
    }

    /**
     * Returns DateTime preserving time down to the second, discarding
     * microsecond precision.
     *
     * @param \DateTime $relDate
     * @return \DateTime
     */
    public static function secondsFloor(\DateTime $relDate = null)
    {
        if ($relDate === null) {
            $relDate = new \DateTime();
        }

        $relDate = clone $relDate;
        $relDate->setTime($relDate->format('H'), $relDate->format('i'), $relDate->format('s'));

        return $relDate;
    }

    /**
     * Returns the current system time in milliseconds
     *
     * @return int
     */
    public static function getMilliseconds()
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * Adds the given interval to the specified date. $interval can have any
     * of the following formats:
     *
     *  - DateInterval object
     *  - DateInterval constructor string ("P1D")
     *  - DateInterval::createFromDateString parameter ("-10 days")
     *
     * @param \DateTime            $date
     * @param string|\DateInterval $interval
     *
     * @return \DateTime
     */
    public static function add(\DateTime $date, $interval)
    {
        $dateInterval = null;

        // Convert the interval to a \DateInterval object
        if (is_string($interval)) {
            // Starts with P
            if (strpos($interval, "P") === 0) {
                $dateInterval = new \DateInterval($interval);
            } else {
                $dateInterval = \DateInterval::createFromDateString($interval);
            }
        } else {
            $dateInterval = $interval;
        }

        $newDate = clone $date;
        $newDate->add($dateInterval);

        return $newDate;
    }

    /**
     * Subtracts the given interval from the specified date. $interval can have any
     * of the following formats:
     *
     *  - DateInterval object
     *  - DateInterval constructor string ("P1D")
     *  - DateInterval::createFromDateString parameter ("-10 days")
     *
     * @param \DateTime            $date
     * @param string|\DateInterval $interval
     *
     * @return \DateTime
     */
    public static function subtract(\DateTime $date, $interval)
    {
        $dateInterval = null;

        // Convert the interval to a \DateInterval object
        if (is_string($interval)) {
            // Starts with P
            if (strpos($interval, "P") === 0) {
                $dateInterval = new \DateInterval($interval);
            } else {
                $dateInterval = \DateInterval::createFromDateString($interval);
            }
        } else {
            $dateInterval = $interval;
        }

        $newDate = clone $date;
        $newDate->sub($dateInterval);

        return $newDate;
    }

    /**
     * Sets the hour, minute, and second fields on $date to the
     * values in $time
     *
     * @param \DateTime $date
     * @param \DateTime $time
     * @return \DateTime
     */
    public static function setTime(\DateTime $date, \DateTime $time)
    {
        $date = clone $date;
        $date->setTime($time->format("G"), $time->format("i"), $time->format("s"));

        return $date;
    }

    /**
     * @param \DateTime $date1
     * @param \DateTime $date2
     * @return int
     */
    public static function getDurationSeconds(\DateTime $date1, \DateTime $date2)
    {
        $timestamp1 = date_format($date1, "U");
        $timestamp2 = date_format($date2, "U");

        return $timestamp2 - $timestamp1;
    }

    /**
     * @param \DateTime $date1
     * @param \DateTime $date2
     * @return int
     */
    public static function getDurationMins(\DateTime $date1, \DateTime $date2)
    {
        $timestamp1 = date_format($date1, "U");
        $timestamp2 = date_format($date2, "U");

        return floor(($timestamp2 - $timestamp1) / 60);
    }

    /**
     * @param \DateTime $date1
     * @param \DateTime $date2
     * @return int
     */
    public static function getDurationDays(\DateTime $date1, \DateTime $date2)
    {
        $timestamp1 = date_format($date1, "U");
        $timestamp2 = date_format($date2, "U");

        return floor(($timestamp2 - $timestamp1) / (60 * 60 * 24));
    }

    /**
     * Returns a human-readable duration between two dates.
     *
     * Example: 5 hours 20 minutes
     *
     * With short label: 5h 20m
     *
     * @param \DateTime $date1
     * @param \DateTime $date2
     * @param bool      $shortLabels Whether words like "years" should be abbreviated like "y" in text
     * @return string
     */
    public static function getPrnDuration(\DateTime $date1, \DateTime $date2, $shortLabels = false)
    {
        $prnDuration = "";
        $diff = date_diff($date1, $date2, true);

        if ($diff->y) {
            $prnDuration .= " " . $diff->y;
            if ($shortLabels) {
                $prnDuration .= "y";
            } else {
                $prnDuration .= ($diff->y != 1) ? " years" : " year";
            }
        }
        if ($diff->m) {
            $prnDuration .= " " . $diff->m;
            if ($shortLabels) {
                $prnDuration .= "mo";
            } else {
                $prnDuration .= ($diff->m != 1) ? " months" : " month";
            }
        }
        if ($diff->d) {
            $prnDuration .= " " . $diff->d;
            if ($shortLabels) {
                $prnDuration .= "d";
            } else {
                $prnDuration .= ($diff->d != 1) ? " days" : " day";
            }
        }
        if ($diff->h) {
            $prnDuration .= " " . $diff->h;
            if ($shortLabels) {
                $prnDuration .= "h";
            } else {
                $prnDuration .= ($diff->h != 1) ? " hours" : " hour";
            }
        }
        if ($diff->i) {
            $prnDuration .= " " . $diff->i;
            if ($shortLabels) {
                $prnDuration .= "m";
            } else {
                $prnDuration .= ($diff->i != 1) ? " minutes" : " minute";
            }
        }
        if ($diff->s) {
            $prnDuration .= " " . $diff->s;
            if ($shortLabels) {
                $prnDuration .= "s";
            } else {
                $prnDuration .= ($diff->s != 1) ? " seconds" : " second";
            }
        }

        if (!$prnDuration) {
            $prnDuration = "0s";
        }

        return trim($prnDuration);
    }

    /**
     * Returns a human-readable description of the length of time represented
     * by $minutesOrDateTime.
     *
     * If $minutesOrDateTime is a DateTime, this calculates the difference
     * between the date and 'now'.
     *
     * The return value of this function will look something like:
     *
     *      6 hours 4 minutes 59 seconds
     *
     * @param      $minutesOrDateTime
     * @param bool $shortLabels
     * @return string
     */
    public static function getPrnElapsedMinutes($minutesOrDateTime, $shortLabels = false)
    {
        // If an instance of \DateTime was passed in then we need to calculate
        // the actual time elapsed between two dates including any DST changes
        if ($minutesOrDateTime instanceof \DateTime) {
            $date1 = $minutesOrDateTime;
            $date2 = new \DateTime();
        }
        // If we're using seconds then we must create our dates in UTC time
        // to prevent issues with daylight savings time
        else {
            $date2 = new \DateTime('now', new \DateTimeZone('UTC'));
            $date1 = static::subtract($date2, sprintf("%s minutes", $minutesOrDateTime));
        }

        return static::getPrnDuration($date1, $date2, $shortLabels);
    }

    /**
     * Converts a number of seconds to a short representation
     * such as 1h 30m.
     *
     * A maximum of two fields are printed, so instead of outputting
     * 1d 4h 30m 15s, this function will just return 1d 4h
     *
     * todo: this should take the same argument and work the same way as
     * getPrnElapsedMinutes. Problem: it defaults to shortLabels=true, unlike
     * the above, so we'd have to update any code that used this method.
     *
     * @param $seconds
     * @return string
     */
    public static function getPrnElapsedSeconds($seconds)
    {
        $prnDuration = "";

        $days = floor($seconds / (60 * 60 * 24));
        $seconds -= ($days * (60 * 60 * 24));

        $hours = floor($seconds / (60 * 60));
        $seconds -= ($hours * (60 * 60));

        $minutes = floor($seconds / 60);
        $seconds -= ($minutes * 60);

        $numPrinted = 0;
        if ($days != 0 && $numPrinted < 2) {
            $prnDuration .= $days . "d ";
            $numPrinted++;
        }
        if ($hours != 0 && $numPrinted < 2) {
            $prnDuration .= $hours . "h ";
            $numPrinted++;
        }
        if ($minutes != 0 && $numPrinted < 2) {
            $prnDuration .= $minutes . "m ";
            $numPrinted++;
        }
        if ($numPrinted < 2 && $seconds != 0) {
            $prnDuration .= $seconds . "s";
        }

        if ($prnDuration == "") {
            $prnDuration = "0s";
        }

        return trim($prnDuration);
    }

    /**
     * Returns an array of hashes describing the days that occur on a given range
     *
     * For example:
     *
     * start = 3/25/2014 9am, end = 3/25/2014 10:30am
     * Returns:
     *  [
     *      ['start' => '3/25/2014 00:00:00', 'end' => '3/25/2014 23:59:59']
     *  ]
     *
     * start = 3/25/2014 9am, end = 3/27/2014 11:00pm
     * [
     *      ['start' => '3/25/2014 00:00:00', 'end' => '3/25/2014 23:59:59'],
     *      ['start' => '3/26/2014 00:00:00', 'end' => '3/26/2014 23:59:59'],
     *      ['start' => '3/27/2014 00:00:00', 'end' => '3/27/2014 23:59:59'],
     * ]
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return array
     */
    public static function getDaysFromRange(\DateTime $start, \DateTime $end)
    {
        $days = array();
        $currDay = $start;

        do {
            $days[] = array(
                'start' => static::dayFloor($currDay),
                'end' => static::dayCeil($currDay)
            );

            $currDay = static::add($currDay, '+1 day');
        } while (static::dayFloor($currDay) <= static::dayFloor($end));

        return $days;
    }

    /**
     * For now, we default our locale to en-us if the intl extension is installed,
     * otherwise 'en'
     *
     * @return string
     */
    public static function getDefaultLocale()
    {
        if (extension_loaded('intl')) {
            return 'en-us';
        }

        return 'en';
    }

    /**
     * Returns the UTC offset of the server in minutes.
     *
     * A negative offset indicates that the server's timezone is behind
     * UTC. For example, -420 would indicate that the server is 7 hours
     * behind UTC.
     *
     * @return int
     */
    public static function getUtcOffsetMinutes()
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());

        return round(($timezone->getOffset(new \DateTime()) / 60));
    }

    /**
     * Returns true if the given date ranges overlap in any way.
     *
     * Ranges can be immediately adjacent to each other. For example, 12pm-1pm and
     * 1pm-2pm will NOT intersect.
     *
     * @param \DateTime $r1StartsAt
     * @param \DateTime $r1EndsAt
     * @param \DateTime $r2StartsAt
     * @param \DateTime $r2EndsAt
     * @return bool
     */
    public static function rangesIntersect(\DateTime $r1StartsAt, \DateTime $r1EndsAt, \DateTime $r2StartsAt, \DateTime $r2EndsAt)
    {
        return ($r1StartsAt < $r2EndsAt && $r1EndsAt > $r2StartsAt);
    }

    /**
     * Returns true if $tz1 and $tz2 have the same UTC offset on the given $refDate
     *
     * $refDate defaults to the current time
     *
     * @param string|\DateTimeZone $tz1
     * @param string|\DateTimeZone $tz2
     * @param null                 $refDate
     * @return bool
     */
    public static function timezonesAreEqual($tz1, $tz2, $refDate = null)
    {
        // Ensure timezones are \DateTimeZone objects
        if (!$tz1 instanceof \DateTimeZone) $tz1 = new \DateTimeZone($tz1);
        if (!$tz2 instanceof \DateTimeZone) $tz2 = new \DateTimeZone($tz2);

        // Default $refDate to now
        if (null === $refDate) $refDate = new \DateTime();

        return $tz1->getOffset($refDate) == $tz2->getOffset($refDate);
    }

    /**
     * Check if given $format is a valid DateTime format.
     *
     * @param string $format DateTime->format() compatible string
     * @return bool
     */
    public static function isValidFormat($format)
    {
        $date = '2017-01-20 22:11:55'; // Specific date doesn't matter, only for comparison
        $d = \DateTime::createFromFormat($format, $date);

        return $d && $d->format($format) == $date;
    }

    /**
     * Helper method for cloning dates that accepts null
     */
    public static function cloneDate(\DateTime $date = null): ?\DateTime
    {
        if ($date === null) return null;

        return clone $date;
    }

    /**
     * Adds business days to a given date, returning a new date.
     *
     * todo: integrate holiday schedule
     *
     * Example:
     *
     *     $date = 'Fri Jan 31 2020 12:30:00' and $minBusinessDays = 2
     *     returns 'Tue Feb 4 2020 12:30:00'
     *
     * @param \DateTime $startDate
     * @param int       $minBusinessDays
     * @return \DateTime $endDate
     */
    public static function addBusinessDays($startDate, $minBusinessDays)
    {
        $count = 0;
        $endDate = $startDate;

        while ($count < $minBusinessDays) {
            $endDate = static::add($endDate, '+1 day');
            $count++;

            if ($endDate->format('l') === 'Saturday') {
                $endDate = static::add($endDate, '+1 day');
                $count++;
                $minBusinessDays++;
            }

            if ($endDate->format('l') === 'Sunday') {
                $endDate = static::add($endDate, '+1 day');
                $count++;
                $minBusinessDays++;
            }
        }

        return $endDate;
    }

    /**
     * Reads time of day fields (hour, minute, second) in $to and sets them on $from
     *
     * The new date is returned as a \DateTimeImmutable
     */
    public static function copyTimeOfDay(\DateTimeInterface $from, \DateTimeInterface $to) : \DateTimeImmutable
    {
        // Start with a date that's a copy of the target
        $tmpDate = \DateTime::createFromFormat(DATE_ATOM, $to->format(DATE_ATOM));

        // Update the time fields to match the source
        $tmpDate->setTime($from->format('H'), $from->format('i'), $from->format('s'));

        // Return as an immutable
        return \DateTimeImmutable::createFromFormat(DATE_ATOM, $tmpDate->format(DATE_ATOM));
    }

    /**
     * Converts $source to a \DateTimeImmutable
     *
     * The optional $modification parameter is applied to $source
     *
     * Examples:
     *      toImmutable($dateTime) -> returns a \DateTimeImmutable created from $dateTime
     *
     *      toImmutable($dateTime, '+1 day') -> returns a \DateTimeImmutable one day ahead of $dateTime
     */
    public static function toImmutable(\DateTimeInterface $source, string $modification = null) : \DateTimeImmutable
    {
        if ($modification === null) {
            return \DateTimeImmutable::createFromFormat(DATE_ATOM, $source->format(DATE_ATOM));
        }

        // Modification requires creating a temporary date object
        $tmp = \DateTime::createFromFormat(DATE_ATOM, $source->format(DATE_ATOM));

        $tmp->modify($modification);

        // Return as an immutable
        return \DateTimeImmutable::createFromFormat(DATE_ATOM, $tmp->format(DATE_ATOM));
    }
}
