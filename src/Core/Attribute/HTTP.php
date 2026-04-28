<?php

declare(strict_types=1);

namespace Retrofit\Core\Attribute;

use Attribute;
use Retrofit\Core\HttpMethod;
use Retrofit\Core\Internal\Utils\Utils;
use Override;

/**
 * Use a custom HTTP verb for a request.
 *
 * <pre>
 * interface Service
 * {
 *     #[HTTP('CUSTOM', 'custom/endpoint/')]
 *     public function customEndpoint(): Call;
 * }
 * </pre>
 *
 * This annotation can also used for sending DELETE with a request body:
 * <pre>
 * interface Service
 * {
 *     #[HTTP('DELETE', 'remove', true)]
 *     public function customEndpoint(): Call;
 * }
 *  </pre>
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class HTTP implements HttpRequest
{
    private HttpMethod $httpMethod;

    /** @var list<string> */
    private array $pathParameters;

    public function __construct(
        HttpMethod|string $httpMethod,
        private ?string $path = null,
        private bool $hasBody = false,
    )
    {
        $this->httpMethod = is_string($httpMethod) ? HttpMethod::from($httpMethod) : $httpMethod;
        $this->pathParameters = Utils::parsePathParameters($this->path);
    }

    #[Override]
    public function httpMethod(): HttpMethod
    {
        return $this->httpMethod;
    }

    #[Override]
    public function path(): ?string
    {
        return $this->path;
    }

    #[Override]
    public function pathParameters(): array
    {
        return $this->pathParameters;
    }

    #[Override]
    public function hasBody(): bool
    {
        return $this->hasBody;
    }
}
