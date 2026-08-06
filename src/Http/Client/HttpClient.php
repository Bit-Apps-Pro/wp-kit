<?php

namespace BitApps\WPKit\Http\Client;

use BadMethodCallException;
use BitApps\WPKit\Helpers\JSON;

use InvalidArgumentException;

final class HttpClient
{
    private array $_headers = [];

    private $_body;

    private $_formParams = [];

    private $_multipart = [];

    private $_json = [];

    private $_queryParams = [];

    private $_params = [];

    private $_baseUri;

    private ?string $_boundary = null;

    private ?string $_method = null;

    private $_responseHeaders = [];

    private $_requestResponse;

    private array $_options = [];

    private bool $_allowUnsafeUrls = false;

    private array $_allowedUnsafeHosts = [];

    /**
     * Undocumented function.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->setDefault($config);
    }

    public function __call(string $method, array $params)
    {
        if (\in_array($method, ['post', 'get', 'put','patch', 'delete', 'head', 'option'])) {
            $this->_method = $method;
            $url           = $this->_baseUri . $params[0];
            $query         = http_build_query($this->getQueryParams());
            if (!empty($query)) {
                $url = $url . '?' . $query;
            }

            $data    = $this->getPreparedPayload();
            $headers = $this->getHeaders();
            $options = $this->getOptions();

            return $this->request($url, $method, $data, $headers, $options);
        }

        throw new BadMethodCallException($method . ' Method not found in ' . self::class);
    }

    public function setBaseUri($uri): self
    {
        $this->_baseUri = $uri;

        return $this;
    }

    public function getBaseUri()
    {
        return $this->_baseUri;
    }

    public function setHeaders(array $headers): self
    {
        if (empty($this->_headers)) {
            $this->_headers = $headers;
        } else {
            foreach ($headers as $key => $value) {
                $this->setHeader($key, $value);
            }
        }

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->_headers as $key => $value) {
            $headers[$key] = \is_array($value) ? implode(';', $value) : $value;
        }

        return $headers;
    }

    public function getHeader($key)
    {
        return $this->_headers[$key] ?? false;
    }

    public function setHeader($key, $value)
    {
        return $this->_headers[ucwords($key)][] = $value;
    }

    public function getOptions(): array
    {
        return $this->_options;
    }

    public function setOptions(array $options): self
    {
        $this->_options = $options;

        return $this;
    }

    public function allowUnsafeUrls($allow = true, array $allowedHosts = []): self
    {
        $this->_allowUnsafeUrls = (bool) $allow;
        $this->setAllowedUnsafeHosts($allowedHosts);

        return $this;
    }

    public function getAllowedUnsafeHosts(): array
    {
        return $this->_allowedUnsafeHosts;
    }

    public function setAllowedUnsafeHosts(array $hosts): self
    {
        $this->_allowedUnsafeHosts = array_values(array_unique(array_filter(array_map(
            [$this, 'normalizeHost'],
            $hosts,
        ))));

        return $this;
    }

    public function setBoundary($boundary): self
    {
        $this->_boundary = '-------' . (string) $boundary;

        return $this;
    }

    public function getBoundary(): string
    {
        if (!isset($this->_boundary)) {
            $this->setBoundary(wp_generate_password(24));
        }

        return $this->_boundary;
    }

    public function setContentType($contentType): self
    {
        $this->setHeader('Content-Type', $contentType);

        return $this;
    }

    public function getContentType($type)
    {
        return $this->_headers[$type] ?? '';
    }

    public function setParams($data): self
    {
        $this->_params = $data;

        return $this;
    }

    public function getParams()
    {
        return $this->_params;
    }

    public function getParam($key)
    {
        return $this->_params[$key] ?? false;
    }

    public function setParam($key, $value)
    {
        return $this->_params[$key] = $value;
    }

    public function setQueryParams($data): self
    {
        $this->_queryParams = $data;

        return $this;
    }

    public function getQueryParams()
    {
        return $this->_queryParams;
    }

    public function getQueryParam($key)
    {
        return $this->_queryParams[$key] ?? false;
    }

    public function setQueryParam($key, $value): self
    {
        if (isset($this->_queryParams[$key])) {
            if (!\is_array($this->_queryParams[$key])) {
                $this->_queryParams[$key] = [$this->_queryParams[$key]];
            }

            $this->_queryParams[$key][] = $value;
        } else {
            $this->_queryParams[$key] = $value;
        }

        return $this;
    }

    public function setBody($body): self
    {
        $this->_body = $body;

        return $this;
    }

    public function getBody()
    {
        return $this->_body;
    }

    public function request($url, $type, $data, $headers = null, $options = null)
    {
        $defaultOptions = [
            'method'  => strtoupper($type),
            'headers' => empty($headers) ? $this->getHeaders() : $headers,
            'body'    => $data,
            'timeout' => 30,

        ];
        $options = wp_parse_args($options, $defaultOptions);

        $requestResponse = $this->_allowUnsafeUrls
            ? wp_remote_request($url, $options)
            : wp_safe_remote_request($url, $options);

        $this->_requestResponse = $requestResponse;

        if (is_wp_error($requestResponse)) {
            return $requestResponse;
        }

        $responseBody = wp_remote_retrieve_body($requestResponse);

        $decodedData = JSON::decode($responseBody);

        $this->_responseHeaders = wp_remote_retrieve_headers($requestResponse);

        return empty($decodedData) ? $responseBody : $decodedData;
    }

    public function getResponseHeaders()
    {
        return $this->_responseHeaders;
    }

    public function getResponseCode()
    {
        return wp_remote_retrieve_response_code($this->_requestResponse);
    }

    public function setDefault(array $config): void
    {
        if (isset($config['base_uri'])) {
            $this->setBaseUri($config['base_uri']);
        }

        if (isset($config['content_type'])) {
            $this->setContentType($config['content_type']);
        }

        if (isset($config['headers'])) {
            $this->setHeaders($config['headers']);
        }

        if (isset($config['body'])) {
            $this->setBody($config['body']);
        }

        if (isset($config['form_params'])) {
            $this->setFormParams($config['form_params']);
        }

        if (isset($config['json'])) {
            $this->setJson($config['json']);
        }

        if (isset($config['multipart'])) {
            $this->setMultipart($config['multipart']);
        }

        $this->allowUnsafeUrls(
            $config['allow_unsafe_urls']    ?? false,
            $config['allowed_unsafe_hosts'] ?? [],
        );
    }

    public function setJson($data): self
    {
        $this->setContentType('application/json');
        $this->_json = $data;

        return $this;
    }

    public function getJson()
    {
        return $this->_json;
    }

    public function setFormParams($data): self
    {
        $this->setContentType('application/x-www-form-urlencoded');
        $this->_formParams = $data;

        return $this;
    }

    public function getFormParams()
    {
        return $this->_formParams;
    }

    public function setMultipart($data): self
    {
        $this->setContentType('multipart/form-data; charset=UTF-8');
        $this->_multipart = $data;

        return $this;
    }

    public function getMultipart()
    {
        return $this->_multipart;
    }

    public function getPreparedPayload()
    {
        $payload = null;
        if (!empty($this->_multipart)) {
            if (!empty($this->getBody()) && !empty($this->getFormParams()) && !empty($this->getJson())) {
                throw new InvalidArgumentException('Do not use multipart with json, params or body');
            }

            $payload = $this->getPreparedMultipart();
        } elseif (\is_string($this->getBody())) {
            $payload = $this->getBody();
        } else {
            $merged = array_merge(
                (array) $this->getBody(),
                (array) $this->getJson(),
                (array) $this->getFormParams()
            );
            if (!isset($this->_method) || $this->_method != 'get') {
                $payload = JSON::maybeEncode($merged);
            } else {
                $payload = $merged;
            }
        }

        return $payload;
    }

    public function getPreparedMultipart(): string
    {
        $multipart = '';
        if (!empty($this->getMultipart()) && \is_array($this->getMultipart())) {
            foreach ($this->getMultipart() as $part) {
                if (\is_array($part) && isset($part['name'], $part['contents'])) {
                    $multipart .= '--' . $this->getBoundary() . '\r\n';
                    $multipart .= 'Content-Disposition: form-data; name="' . $part['name'] . '"';
                    if (isset($part['filename'])) {
                        $multipart .= ';filename="' . $part['filename'] . '"';
                    }

                    $multipart .= '\r\n';
                    if (isset($part['headers'])) {
                        if (\is_array($part['headers'])) {
                            foreach ($part['headers'] as $key => $value) {
                                $multipart .= $key . ':';
                                $multipart .= \is_array($value) ? implode(';', $value) : $value;
                                $multipart .= '\r\n';
                            }
                        } elseif (\is_string($part['headers'])) {
                            $multipart .= $part['headers'] . '\r\n';
                        }
                    }

                    $multipart .= $part['contents'];
                    $multipart .= '\r\n';
                } else {
                    throw new InvalidArgumentException('Multipart must contain name, contents');
                }
            }
        }

        $multipart .= '--' . $this->getBoundary() . '--';

        return $multipart;
    }

    private function normalizeHost($host): string
    {
        if (!\is_string($host)) {
            return '';
        }

        $host = trim($host);
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return '';
            }
        }

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return strtolower($host);
        }

        $host = strtolower($host);
        if (str_ends_with($host, '.')) {
            $host = substr($host, 0, -1);
        }

        if ($host === '' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return '';
        }

        return $host;
    }
}
