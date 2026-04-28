<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use ReflectionMethod;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Internal\RequestBuilder;
use Override;

/**
 * @internal
 */
readonly class QueryMapParameterHandler implements ParameterHandler
{
    use WithMapParameter;

    public function __construct(
        private bool $encoded,
        private StringConverter $converter,
        private ReflectionMethod $reflectionMethod,
        private int $position,
    )
    {
    }

    #[Override]
    public function apply(RequestBuilder $requestBuilder, mixed $value): void
    {
        if (is_null($value)) {
            return;
        }

        $this->validateAndApply($value, 'Query', $this->converter, function (string $entryKey, string $entryValue) use ($requestBuilder): void {
            $requestBuilder->addQueryParam($entryKey, $entryValue, $this->encoded);
        });
    }
}
