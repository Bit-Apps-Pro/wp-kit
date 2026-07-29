<?php

namespace BitApps\WPKit\Http\Router;

use RuntimeException;

/**
 * Internal control-flow signal: dispatch is blocked, carry the blocking response. Never escapes handleRequest().
 */
final class RouteBlockedException extends RuntimeException
{
    public function __construct(private $_response)
    {
        parent::__construct('Route dispatch blocked');
    }

    public function getResponse()
    {
        return $this->_response;
    }
}
