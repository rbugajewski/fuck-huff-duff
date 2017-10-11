<?php

namespace PicoFeed\Clients;

use \PicoFeed\Logging;
use \PicoFeed\Client;

/**
 * Stream context HTTP client
 *
 * @author  Frederic Guillot
 * @package client
 */
class Stream extends Client
{
    /**
     * Do the HTTP request
     *
     * @access public
     * @return array   HTTP response ['body' => ..., 'status' => ..., 'headers' => ...]
     */
    public function doRequest()
    {
        // Prepare HTTP headers for the request
        $headers = array(
            'Connection: close',
            'User-Agent: '.$this->user_agent,
        );

        if (function_exists('gzdecode')) {
            $headers[] = 'Accept-Encoding: gzip';
        }

        if ($this->etag) {
            $headers[] = 'If-None-Match: '.$this->etag;
        }

        if ($this->last_modified) {
            $headers[] = 'If-Modified-Since: '.$this->last_modified;
        }

        // Create context
        $context_options = array(
            'http' => array(
                'method' => 'GET',
                'protocol_version' => 1.1,
                'timeout' => $this->timeout,
                'max_redirects' => $this->max_redirects,
                'header' => implode("\r\n", $headers)
            )
        );

        if ($this->proxy_hostname) {

            Logging::setMessage(get_called_class().' Proxy: '.$this->proxy_hostname.':'.$this->proxy_port);

            $context_options['http']['proxy'] = 'tcp://'.$this->proxy_hostname.':'.$this->proxy_port;
            $context_options['http']['request_fulluri'] = true;

            if ($this->proxy_username) {
                Logging::setMessage(get_called_class().' Proxy credentials: Yes');

                $headers[] = 'Proxy-Authorization: Basic '.base64_encode($this->proxy_username.':'.$this->proxy_password);
                $context_options['http']['header'] = implode("\r\n", $headers);
            }
            else {
                Logging::setMessage(get_called_class().' Proxy credentials: No');
            }
        }

        $context = stream_context_create($context_options);

        // Make HTTP request
        $stream = @fopen($this->url, 'r', false, $context);
        if (! is_resource($stream)) return false;

        // Get the entire body until the max size
        $body = stream_get_contents($stream, $this->max_body_size + 1);

        // If the body size is too large abort everything
        if (strlen($body) > $this->max_body_size) return false;

        // Get HTTP headers response
        $metadata = stream_get_meta_data($stream);

        list($status, $headers) = $this->parseHeaders($metadata['wrapper_data']);

        fclose($stream);

        if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding'] === 'chunked') {
            $body = $this->decodeChunked($body);
        }

        if (isset($headers['Content-Encoding']) && $headers['Content-Encoding'] === 'gzip') {
            $body = @gzdecode($body);
        }

        return array(
            'status' => $status,
            'body' => $body,
            'headers' => $headers
        );
    }

    /**
     * Decode a chunked body
     *
     * @access public
     * @param  string $str Raw body
     * @return string      Decoded body
     */
    public function decodeChunked($str)
    {
        for ($result = ''; ! empty($str); $str = trim($str)) {

            // Get the chunk length
            $pos = strpos($str, "\r\n");
            $len = hexdec(substr($str, 0, $pos));

            // Append the chunk to the result
            $result .= substr($str, $pos + 2, $len);
            $str = substr($str, $pos + 2 + $len);
        }

        return $result;
    }
}
