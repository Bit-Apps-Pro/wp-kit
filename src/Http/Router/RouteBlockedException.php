<?php

namespace BitApps\WPKit\Http\Router;

use RuntimeException;

/**
 * Internal control-flow signal: dispatch is blocked, carry the blocking response. Never escapes handleRequest().
 */
final class RouteBlockedException extends RuntimeException
{
    private $_response;

    public function __construct($response)
    {
        parent::__construct('Route dispatch blocked');
        $this->_response = $response;
    }

    public function getResponse()
    {
        return $this->_response;
    }
}
