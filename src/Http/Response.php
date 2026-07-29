<?php

namespace BitApps\WPKit\Http;

use InvalidArgumentException;

final class Response
{
    const SUCCESS = 'success';

    const ERROR = 'error';

    private static $_current;

    private $_message;

    private $_status;

    private $_code;

    private $_data;

    private $_httpStatus;

    private $_headers = [];

    public static function instance()
    {
        return self::current();
    }

    public static function reset()
    {
        return self::$_current = new self();
    }

    /**
     * Makes an existing response the current one so the static accessors read it back.
     *
     * @return self
     */
    public static function adopt(self $response)
    {
        return self::$_current = $response;
    }

    /**
     * Sets data for success response.
     *
     * @param mixed $data       Data to return on response
     * @param mixed $httpStatus
     *
     * @return self
     */
    public static function success($data, $httpStatus = 200)
    {
        $current          = self::current();
        $current->_data   = $data;
        $current->_status = self::SUCCESS;

        $current->_httpStatus = $httpStatus;

        return $current;
    }

    /**
     * Sets data for error response.
     *
     * @param mixed $data       Data to return on response
     * @param mixed $httpStatus
     *
     * @return self
     */
    public static function error($data, $httpStatus = 400)
    {
        $current          = self::current();
        $current->_data   = $data;
        $current->_status = self::ERROR;

        $current->_httpStatus = $httpStatus;

        return $current;
    }

    /**
     * Returns data for response.
     *
     * @return mixed $_data
     */
    public static function getData()
    {
        return self::current()->_data;
    }

    /**
     * Returns status for response.
     *
     * @return string $_status
     */
    public static function getStatus()
    {
        return self::current()->_status;
    }

    /**
     * Sets message for response.
     *
     * @param string $message Data to return on response
     *
     * @return self
     */
    public static function message($message)
    {
        $current           = self::current();
        $current->_message = $message;

        return $current;
    }

    /**
     * Returns message response.
     *
     * @return mixed $_message
     */
    public static function getMessage()
    {
        return self::current()->_message;
    }

    /**
     * Sets code for response.
     *
     * @param string $code status code to return on response
     *
     * @return self
     */
    public static function code($code)
    {
        $current        = self::current();
        $current->_code = $code;

        return $current;
    }

    /**
     * Returns status code response.
     *
     * @return mixed $_code
     */
    public static function getCode()
    {
        $current = self::current();
        if (!isset($current->_code)) {
            return isset($current->_status) ? strtoupper($current->_status) : null;
        }

        return $current->_code;
    }

    /**
     * Sets http status code for response.
     *
     * @param int $code http status code to return on response
     *
     * @return self
     */
    public static function httpStatus($code)
    {
        $current              = self::current();
        $current->_httpStatus = $code;

        return $current;
    }

    /**
     * Returns status code response.
     *
     * @return mixed $_httpStatus
     */
    public static function getHttpStatusCode()
    {
        $current    = self::current();
        $statusCode = $current->_httpStatus;
        if (!$statusCode) {
            $statusCode = self::ERROR === $current->_status ? 400 : 200;
        }

        return $statusCode;
    }

    /**
     * Sets http headers for response.
     *
     * @param array $headers http headers to return on response
     *
     * @return self
     */
    public static function headers($headers)
    {
        if (!\is_array($headers)) {
            throw new InvalidArgumentException('Response headers must be an array.');
        }

        self::current()->_headers = [];
        foreach ($headers as $header => $value) {
            self::header($header, $value);
        }

        return self::current();
    }

    /**
     * Sets http headers for response.
     *
     * @param string $header http header
     * @param string $value
     *
     * @return self
     */
    public static function header($header, $value)
    {
        if (!\is_string($header) || preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $header) !== 1) {
            throw new InvalidArgumentException('Invalid response header name.');
        }

        if (!\is_scalar($value) || preg_match('/[\r\n\0]/', (string) $value)) {
            throw new InvalidArgumentException('Invalid response header value.');
        }

        $current                    = self::current();
        $current->_headers[$header] = $value;

        return $current;
    }

    /**
     * Returns headers for response.
     *
     * @return array $_headers
     */
    public static function getHeaders()
    {
        return self::current()->_headers;
    }

    private static function current()
    {
        if (\is_null(self::$_current)) {
            self::$_current = new self();
        }

        return self::$_current;
    }
}
