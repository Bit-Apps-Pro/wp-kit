<?php

namespace BitApps\WPKit\Http\Router\Emitter;

use Stringable;
use UnexpectedValueException;

final class StaticResponseEmitter implements ResponseEmitter
{
    public function emit(array $response): string
    {
        $data = $response['data']['data'] ?? null;
        if ($data === null) {
            return '';
        }

        if (\is_string($data)) {
            return $data;
        }

        if (\is_scalar($data) || $data instanceof Stringable) {
            return (string) $data;
        }

        throw new UnexpectedValueException('Static route actions must return string-compatible content.');
    }
}
