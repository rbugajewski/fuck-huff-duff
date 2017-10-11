<?php

namespace PicoFeed;

use LogicException;
use Clients\Curl;
use Clients\Stream;
use PicoFeed\Logging;

/**
 * Client class
 *
 * @author  Frederic Guillot
 * @package client
 */
abstract class Client
{
    /**
     * Flag that say if the resource have been modified
     *
     * @access private
     * @var bool
     */
    private $is_modified = true;

    /**
     * HTTP encoding
     *
     * @access private
     * @var string
     */
    private $encoding = '';

    /**
     * HTTP Etag header
     *
     * @access protected
     * @var string
     */
    protected $etag = '';

    /**
     * HTTP Last-Modified header
     *
     * @access protected
     * @var string
     */
    protected $last_modified = '';

    /**
     * Proxy hostname
     *
     * @access protected
     * @var string
     */
    protected $proxy_hostname = '';

    /**
     * Proxy port
     *
     * @access protected
     * @var integer
     */
    protected $proxy_port = 3128;

    /**
     * Proxy username
     *
     * @access protected
     * @var string
     */
    protected $proxy_username = '';

    /**
     * Proxy password
     *
     * @access protected
     * @var string
     */
    protected $proxy_password = '';

    /**
     * Client connection timeout
     *
     * @access protected
     * @var integer
     */
    protected $timeout = 10;

    /**
     * User-agent
     *
     * @access protected
     * @var string
     */
    protected $user_agent = 'PicoFeed (https://github.com/fguillot/picoFeed)';

    /**
     * Real URL used (can be changed after a HTTP redirect)
     *
     * @access protected
     * @var string
     */
    protected $url = '';

    /**
     * Page/Feed content
     *
     * @access protected
     * @var string
     */
    protected $content = '';

    /**
     * Number maximum of HTTP redirections to avoid infinite loops
     *
     * @access protected
     * @var integer
     */
    protected $max_redirects = 5;

    /**
     * Maximum size of the HTTP body response
     *
     * @access protected
     * @var integer
     */
    protected $max_body_size = 2097152; // 2MB

    /**
     * Get client instance: curl or stream driver
     *
     * @static
     * @access public
     * @return \PicoFeed\Client
     */
    public static function getInstance()
    {
        if (function_exists('curl_init')) {

            require_once __DIR__.'/Clients/Curl.php';
            return new Clients\Curl;
        }
        else if (ini_get('allow_url_fopen')) {

            require_once __DIR__.'/Clients/Stream.php';
            return new Clients\Stream;
        }

        throw new LogicException('You must have "allow_url_fopen=1" or curl extension installed');
    }

    /**
     * Perform the HTTP request
     *
     * @access public
     * @param  string  $url  URL
     * @return bool
     */
    public function execute($url = '')
    {
        if ($url !== '') {
            $this->url = $url;
        }

        Logging::setMessage(get_called_class().' Fetch URL: '.$this->url);
        Logging::setMessage(get_called_class().' Etag provided: '.$this->etag);
        Logging::setMessage(get_called_class().' Last-Modified provided: '.$this->last_modified);

        $response = $this->doRequest();

        if (is_array($response)) {

            if ($response['status'] == 304) {
                $this->is_modified = false;
                Logging::setMessage(get_called_class().' Resource not modified');
            }
            else if ($response['status'] == 404) {
                Logging::setMessage(get_called_class().' Resource not found');
            }
            else {
                $etag = isset($response['headers']['ETag']) ? $response['headers']['ETag'] : '';
                $last_modified = isset($response['headers']['Last-Modified']) ? $response['headers']['Last-Modified'] : '';
                $this->content = $response['body'];

                if (isset($response['headers']['Content-Type'])) {
                    $result = explode('charset=', strtolower($response['headers']['Content-Type']));
                    $this->encoding = isset($result[1]) ? $result[1] : '';
                }

                if (($this->etag && $this->etag === $etag) || ($this->last_modified && $last_modified === $this->last_modified)) {
                    $this->is_modified = false;
                }

                $this->etag = $etag;
                $this->last_modified = $last_modified;
            }

            return true;
        }

        return false;
    }

    /**
     * Parse HTTP headers
     *
     * @access public
     * @param  array   $lines   List of headers
     * @return array
     */
    public function parseHeaders(array $lines)
    {
        $status = 200;
        $headers = array();

        foreach ($lines as $line) {

            if (strpos($line, 'HTTP') === 0) {
                $status = (int) substr($line, 9, 3);
            }
            else if (strpos($line, ':') !== false) {

                @list($name, $value) = explode(': ', $line);
                if ($value) $headers[trim($name)] = trim($value);
            }
        }

        Logging::setMessage(get_called_class().' HTTP status code: '.$status);

        foreach ($headers as $name => $value) {
            Logging::setMessage(get_called_class().' HTTP header: '.$name.' => '.$value);
        }

        return array($status, $headers);
    }

    /**
     * Set the Last-Modified HTTP header
     *
     * @access public
     * @param  string   $last_modified   Header value
     * @return \PicoFeed\Client
     */
    public function setLastModified($last_modified)
    {
        $this->last_modified = $last_modified;
        return $this;
    }

    /**
     * Get the value of the Last-Modified HTTP header
     *
     * @access public
     * @return string
     */
    public function getLastModified()
    {
        return $this->last_modified;
    }

    /**
     * Set the value of the Etag HTTP header
     *
     * @access public
     * @param  string   $etag   Etag HTTP header value
     * @return \PicoFeed\Client
     */
    public function setEtag($etag)
    {
        $this->etag = $etag;
        return $this;
    }

    /**
     * Get the Etag HTTP header value
     *
     * @access public
     * @return string
     */
    public function getEtag()
    {
        return $this->etag;
    }

    /**
     * Get the final url value
     *
     * @access public
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the url
     *
     * @access public
     * @return string
     * @return \PicoFeed\Client
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get the body of the HTTP response
     *
     * @access public
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get the encoding value from HTTP headers
     *
     * @access public
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Return true if the remote resource has changed
     *
     * @access public
     * @return bool
     */
    public function isModified()
    {
        return $this->is_modified;
    }

    /**
     * Set connection timeout
     *
     * @access public
     * @param  integer   $timeout   Connection timeout
     * @return \PicoFeed\Client
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout ?: $this->timeout;
        return $this;
    }

    /**
     * Set a custom user agent
     *
     * @access public
     * @param  string   $user_agent   User Agent
     * @return \PicoFeed\Client
     */
    public function setUserAgent($user_agent)
    {
        $this->user_agent = $user_agent ?: $this->user_agent;
        return $this;
    }

    /**
     * Set the mximum number of HTTP redirections
     *
     * @access public
     * @param  integer   $max   Maximum
     * @return \PicoFeed\Client
     */
    public function setMaxRedirections($max)
    {
        $this->max_redirects = $max ?: $this->max_redirects;
        return $this;
    }

    /**
     * Set the maximum size of the HTTP body
     *
     * @access public
     * @param  integer   $max   Maximum
     * @return \PicoFeed\Client
     */
    public function setMaxBodySize($max)
    {
        $this->max_body_size = $max ?: $this->max_body_size;
        return $this;
    }

    /**
     * Set the proxy hostname
     *
     * @access public
     * @param  string   $hostname    Proxy hostname
     * @return \PicoFeed\Client
     */
    public function setProxyHostname($hostname)
    {
        $this->proxy_hostname = $hostname ?: $this->proxy_hostname;
        return $this;
    }

    /**
     * Set the proxy port
     *
     * @access public
     * @param  integer   $port   Proxy port
     * @return \PicoFeed\Client
     */
    public function setProxyPort($port)
    {
        $this->proxy_port = $port ?: $this->proxy_port;
        return $this;
    }

    /**
     * Set the proxy username
     *
     * @access public
     * @param  string   $username   Proxy username
     * @return \PicoFeed\Client
     */
    public function setProxyUsername($username)
    {
        $this->proxy_username = $username ?: $this->proxy_username;
        return $this;
    }

    /**
     * Set the proxy password
     *
     * @access public
     * @param  string  $password  Password
     * @return \PicoFeed\Client
     */
    public function setProxyPassword($password)
    {
        $this->proxy_password = $password ?: $this->proxy_password;
        return $this;
    }

    /**
     * Set config object
     *
     * @access public
     * @param  \PicoFeed\Config  $config   Config instance
     * @return \PicoFeed\Client
     */
    public function setConfig($config)
    {
        $this->setTimeout($config->getGrabberTimeout());
        $this->setUserAgent($config->getGrabberUserAgent());
        $this->setMaxRedirections($config->getMaxRedirections());
        $this->setMaxBodySize($config->getMaxBodySize());
        $this->setProxyHostname($config->getProxyHostname());
        $this->setProxyPort($config->getProxyPort());
        $this->setProxyUsername($config->getProxyUsername());
        $this->setProxyPassword($config->getProxyPassword());

        return $this;
    }
}
