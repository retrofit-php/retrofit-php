<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use ReflectionMethod;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\Internal\Utils\Utils;
use Override;

/**
 * @internal
 */
readonly class BodyParameterHandler implements ParameterHandler
{
    public function __construct(
        private RequestBodyConverter $converter,
        private ReflectionMethod $reflectionMethod,
        private int $position,
    )
    {
    }

    #[Override]
    public function apply(RequestBuilder $requestBuilder, mixed $value): void
    {
        if (is_null($value)) {
            throw Utils::parameterException($this->reflectionMethod, $this->position, 'Body was null.');
        }

        $value = $this->converter->convert($value);
        $requestBuilder->setBody($value);
    }
}
