<?php

namespace App\Api\ServiceNow\Request;

/**
 * Base class for any Request sent to ServiceNow API.
 */
abstract class Request implements \JsonSerializable
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
    abstract public function jsonSerialize();
}
