<?php
namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 request implementation.
 */
class Request implements RequestInterface
{
    use MessageTrait;

    /** @var string */
    private $method;

    /** @var null|string */
    private $requestTarget;

    /** @var UriInterface */
    private $uri;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param array                                $headers Request headers
     * @param string|null|resource|StreamInterface $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $version = '1.1'
    ) {
        phpLog('-7361-');
        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }
        phpLog('-7362-');
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocol = $version;
        phpLog('-7363-');
        if (!$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }
        phpLog('-7364-');
        if ($body !== '' && $body !== null) {
            phpLog('-7365-');
            $this->stream = stream_for($body);
            phpLog('-7366-');
        }
    }
    /**
     * 调试时候用来写日志文件的函数
     *
     * @param filename 保存的文件名
     *
     * @author <23585472@qq.com>
     */
    public function phpLog ($str)
    {
        $time = "\n\t" . date('Y-m-d H:i:s', time()) . "------------------------------------------------------------------------------------\n\t";
        if (is_array($str)) {
            $str = var_export($str, true);
        }
        file_put_contents('./log.php', $time . $str, FILE_APPEND);
    }

    public function getRequestTarget()
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target == '') {
            $target = '/';
        }
        if ($this->uri->getQuery() != '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget($requestTarget)
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $new = clone $this;
        $new->method = strtoupper($method);
        return $new;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    private function updateHostFromUri()
    {
        $host = $this->uri->getHost();

        if ($host == '') {
            return;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        if (isset($this->headerNames['host'])) {
            $header = $this->headerNames['host'];
        } else {
            $header = 'Host';
            $this->headerNames['host'] = 'Host';
        }
        // Ensure Host is the first header.
        // See: http://tools.ietf.org/html/rfc7230#section-5.4
        $this->headers = [$header => [$host]] + $this->headers;
    }
}
