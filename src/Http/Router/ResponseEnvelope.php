<?php

namespace BitApps\WPKit\Http\Router;

use BitApps\WPKit\Http\Response;

/**
 * Builds the transport-agnostic response snapshot from an action or middleware result.
 */
final class ResponseEnvelope
{
    /**
     * @param mixed  $result         Response object, WP_Error, or raw action output
     * @param string $bufferedOutput stray output captured during dispatch
     *
     * @return array ['data' => array, 'http_status' => int, 'headers' => array]
     */
    public static function build($result, $bufferedOutput = ''): array
    {
        $response = self::normalize($result);

        $data = [];
        if ($status = $response->getStatus()) {
            $data['status'] = $status;
        }

        if ($message = $response->getMessage()) {
            $data['message'] = $message;
        }

        if ($code = $response->getCode()) {
            $data['code'] = $code;
        }

        $data['data'] = $response->getData();
        if (!empty($bufferedOutput)) {
            $data['additional'] = $bufferedOutput;
        }

        return [
            'data'        => $data,
            'http_status' => $response->getHttpStatusCode(),
            'headers'     => $response->getHeaders(),
        ];
    }

    private static function normalize($result): Response
    {
        if (is_wp_error($result)) {
            return Response::error($result->get_error_data())
                ->code($result->get_error_code())
                ->message($result->get_error_message());
        }

        if (!$result instanceof Response) {
            return Response::success($result)->code('SUCCESS');
        }

        // the static accessors read the current holder, so make the passed response current
        return Response::adopt($result);
    }
}
