<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Ouzo\Utilities\Arrays;
use Ouzo\Utilities\Strings;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Retrofit\Core\Attribute\HttpRequest;
use Retrofit\Core\Internal\Utils\Utils;
use RuntimeException;

/**
 * @internal
 */
class RequestBuilder
{
    private const PARAMETER_PLACEHOLDER = '{%s}';

    private UriInterface $uri;

    /** @var list<array<string, string>> */
    private array $pathParameters = [];

    /** @var list<string> */
    private array $queries = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $fields = [];

    /** @var array<int, array{name: string, contents: StreamInterface|string, headers: array<string, string>, filename?: string|null}> */
    private array $parts = [];

    private ?StreamInterface $body = null;

    /** @param array<string, string> $defaultHeaders */
    public function __construct(
        UriInterface $baseUrl,
        private readonly HttpRequest $httpRequest,
        array $defaultHeaders = [],
    )
    {
        $this->uri = new Uri($baseUrl->__toString());
        if (!is_null($this->httpRequest->path())) {
            $this->uri = $this->uri->withPath($this->httpRequest->path());
        }

        foreach ($defaultHeaders as $name => $value) {
            $this->addHeader($name, $value);
        }
    }

    public function setBaseUrl(UriInterface|string $value): void
    {
        if (is_string($value)) {
            $value = new Uri($value);
        }
        $this->uri = $value->withQuery($this->uri->getQuery());
    }

    public function addPathParam(string $name, string $value, bool $encoded): void
    {
        if (!$encoded) {
            $value = rawurlencode($value);
        }
        $this->pathParameters[] = ['name' => $name, 'value' => $value];
    }

    /**
     * @param string|list<string> $name
     * @param string|list<string>|null $value
     */
    public function addQueryParam(string|array $name, string|array|null $value, bool $encoded): void
    {
        if (is_null($value)) {
            $name = Arrays::toArray($name);
            /** @var list<string> $queries */
            $queries = Arrays::map($name, fn(string $item) => $encoded ? $item : rawurlencode($item));
            $this->queries = $queries;
        } else {
            if (is_array($name)) {
                throw new RuntimeException('Cannot add query param name when is an array.');
            }

            /** @var list<string> $value */
            $value = Arrays::toArray($value);
            foreach ($value as $item) {
                if (!$encoded) {
                    $item = rawurlencode($item);
                }
                $this->queries[] = "{$name}={$item}";
            }
        }
    }

    public function addHeader(string $name, string $value): void
    {
        $name = strtolower($name);
        $this->headers[$name] = $value;
    }

    public function addFormField(string $name, string $value, bool $encoded): void
    {
        if (!$encoded) {
            $value = rawurlencode($value);
        }
        $this->fields[$name] = $value;
    }

    /** @param array<string, string> $headers */
    public function addPart(string $name, StreamInterface|string $body, array $headers = [], ?string $filename = null): void
    {
        $this->parts[] = [
            'name' => $name,
            'contents' => $body,
            'headers' => $headers,
            'filename' => $filename,
        ];
    }

    public function setBody(StreamInterface $body): void
    {
        $this->body = $body;
    }

    public function build(): RequestInterface
    {
        $this->replacePathParameters();
        $this->initializeQueryString();
        $body = $this->initializeBody();

        return new Request($this->httpRequest->httpMethod()->value, $this->uri, $this->headers, $body);
    }

    private function replacePathParameters(): void
    {
        if (!empty($this->pathParameters)) {
            $path = rawurldecode($this->uri->getPath());
            $parsePathParameters = Utils::parsePathParameters($path);
            foreach ($this->pathParameters as $pathParameter) {
                $name = $pathParameter['name'];
                if (!in_array($name, $parsePathParameters)) {
                    throw new RuntimeException("URL '{$path}' does not contain '{$name}'.");
                }

                $path = str_replace(sprintf(self::PARAMETER_PLACEHOLDER, $pathParameter['name']), $pathParameter['value'], $path);
            }
            $this->uri = $this->uri->withPath($path);
        }
    }

    private function initializeQueryString(): void
    {
        if (!empty($this->queries)) {
            $query = implode('&', $this->queries);
            $this->uri = Strings::isBlank($this->uri->getQuery()) ? $this->uri->withQuery($query) : $this->uri->withQuery("{$query}&{$this->uri->getQuery()}");
        }
    }

    private function initializeBody(): StreamInterface|string|null
    {
        if (is_null($this->body)) {
            if (!empty($this->fields)) {
                return Query::build($this->fields, false);
            }

            if (!empty($this->parts)) {
                return new MultipartStream($this->parts);
            }
        }

        return $this->body;
    }
}
