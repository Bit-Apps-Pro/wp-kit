<?php

namespace BitApps\WPKit\Http\Router\Emitter;

interface ResponseEmitter
{
    /**
     * Formats and/or sends the response snapshot for one transport.
     *
     * @param array $response ['data' => array, 'http_status' => int, 'headers' => array]
     *
     * @return mixed
     */
    public function emit(array $response);
}
