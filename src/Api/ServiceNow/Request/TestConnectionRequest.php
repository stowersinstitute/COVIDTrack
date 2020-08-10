<?php

namespace App\Api\ServiceNow\Request;

/**
 * Test if the ServiceNow connection is working.
 */
class TestConnectionRequest extends Request
{
    public function getRequestData()
    {
        return [
            'test' => true,
        ];
    }
}
