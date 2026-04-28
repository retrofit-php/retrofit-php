<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use ReflectionMethod;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\Internal\Utils\Utils;
use Override;

/**
 * @internal
 */
readonly class HeaderMapParameterHandler implements ParameterHandler
{
    use WithMapParameter;

    public function __construct(
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
            throw Utils::parameterException($this->reflectionMethod, $this->position, 'Header map was null.');
        }

        $this->validateAndApply($value, 'Header', $this->converter, function (string $entryKey, string $entryValue) use ($requestBuilder): void {
            $requestBuilder->addHeader($entryKey, $entryValue);
        });
    }
}
