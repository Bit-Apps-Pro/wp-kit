<?php

namespace BitApps\WPKit\Http\Router\Emitter;

final class AjaxResponseEmitter implements ResponseEmitter
{
    public function emit(array $response): void
    {
        if (!headers_sent() && $response['headers']) {
            foreach ($response['headers'] as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        wp_send_json($response['data'], $response['http_status']);
    }
}
