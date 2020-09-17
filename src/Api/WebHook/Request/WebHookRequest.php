<?php

namespace App\Api\WebHook\Request;

/**
 * Base class for any WebHookRequest sent to a WebHook API.
 */
abstract class WebHookRequest implements \JsonSerializable
{
    protected const DATE_FORMAT_ISO8601 = 'Y-m-d\TH:i:s\Z';

    public function toJson($options = 0, $depth = 512): string
    {
        return json_encode($this->jsonSerialize(), $options, $depth);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->getRequestData();
    }

    /**
     * Return data sent as WebHookRequest payload. Must be compatible with json_encode().
     *
     * @return mixed
     */
    abstract public function getRequestData();

    /**
     * Convert a date to its string representation for sending through Web Hook API.
     *
     * @param \DateTimeInterface|null $date
     * @return string|null Either the string-formatted date or NULL
     */
    public static function dateToRequestDataFormat(?\DateTimeInterface $date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::DATE_FORMAT_ISO8601);
    }
}
