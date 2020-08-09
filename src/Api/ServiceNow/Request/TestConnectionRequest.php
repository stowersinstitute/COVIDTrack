<?php

namespace App\Api\ServiceNow\Request;

/**
 * Test if the ServiceNow connection is working.
 */
class TestConnectionRequest extends Request implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return [
            'test' => true,
        ];
    }
}
