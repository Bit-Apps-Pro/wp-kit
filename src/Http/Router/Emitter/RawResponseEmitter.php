<?php

namespace BitApps\WPKit\Http\Router\Emitter;

final class RawResponseEmitter implements ResponseEmitter
{
    public function emit(array $response)
    {
        return $response['data']['data'] ?? null;
    }
}
