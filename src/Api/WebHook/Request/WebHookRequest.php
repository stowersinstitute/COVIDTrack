<?php

namespace App\Api\WebHook\Request;

/**
 * Base class for any WebHookRequest sent to a WebHook API.
 */
abstract class WebHookRequest implements \JsonSerializable
{
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
}
