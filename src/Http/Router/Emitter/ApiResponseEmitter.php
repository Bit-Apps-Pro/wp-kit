<?php

namespace BitApps\WPKit\Http\Router\Emitter;

use WP_REST_Response;

final class ApiResponseEmitter implements ResponseEmitter
{
    public function emit(array $response): WP_REST_Response
    {
        $restResponse = new WP_REST_Response();
        $restResponse->set_data($response['data']);
        $restResponse->set_status($response['http_status']);
        $restResponse->set_headers($response['headers']);

        return $restResponse;
    }
}
