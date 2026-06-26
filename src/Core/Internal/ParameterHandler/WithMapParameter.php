<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use Closure;
use Ouzo\Utilities\Strings;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Internal\Utils\Utils;

/**
 * @internal
 */
trait WithMapParameter
{
    protected function validateAndApply(mixed $value, string $context, StringConverter|RequestBodyConverter $converter, Closure $apply): void
    {
        if (!is_array($value)) {
            throw Utils::parameterException($this->reflectionMethod, $this->position, 'Parameter should be an array.');
        }

        foreach ($value as $entryKey => $entryValue) {
            if (Strings::isBlank($entryKey)) {
                throw Utils::parameterException(
                    $this->reflectionMethod,
                    $this->position,
                    "{$context} map contained empty key.",
                );
            }
            if (is_null($entryValue)) {
                throw Utils::parameterException(
                    $this->reflectionMethod,
                    $this->position,
                    "{$context} map contained null value for key '{$entryKey}'.",
                );
            }

            $elements = is_array($entryValue) ? $entryValue : [$entryValue];
            foreach ($elements as $element) {
                $apply($entryKey, $converter->convert($element), $element);
            }
        }
    }
}
