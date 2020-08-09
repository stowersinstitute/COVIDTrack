<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Test some ServiceNow features.
 *
 * @Route(path="/service-now/test")
 */
class ServiceNowTestController extends AbstractController
{
    public const CONNECTION_SUCCESS_MESSAGE = 'Successful ServiceNow Test';

    /**
     * @Route(path="/connection", methods={"GET"}, name="servicenow_test_connection")
     */
    public function connection(Request $request)
    {
        // TODO: Get some data from request and echo it back
        return new Response(json_encode(
            [
                'Message' => self::CONNECTION_SUCCESS_MESSAGE,
                'Symfony Security user class' => get_class($this->getUser()),
            ]
        ));
    }
}
