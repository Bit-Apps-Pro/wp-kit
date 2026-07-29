<?php

namespace BitApps\WPKit\Http\Router\Emitter;

final class StaticResponseEmitter implements ResponseEmitter
{
    public function emit(array $response)
    {
        // static/web routes consume the raw action output; other transports send full envelopes themselves
        return $response['data']['data'];
    }
}
