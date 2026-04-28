<?php

declare(strict_types=1);

namespace Retrofit\Core\Attribute;

use Attribute;
use Retrofit\Core\HttpMethod;
use Retrofit\Core\Internal\Utils\Utils;
use Override;

/**
 * Make an OPTIONS request.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class OPTIONS implements HttpRequest
{
    /** @var list<string> */
    private array $pathParameters;

    public function __construct(private ?string $path = null)
    {
        $this->pathParameters = Utils::parsePathParameters($this->path);
    }

    #[Override]
    public function httpMethod(): HttpMethod
    {
        return HttpMethod::OPTIONS;
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
        return false;
    }
}
