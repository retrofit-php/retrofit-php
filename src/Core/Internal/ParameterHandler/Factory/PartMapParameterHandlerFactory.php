<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler\Factory;

use ReflectionMethod;
use Retrofit\Core\Attribute\HttpRequest;
use Retrofit\Core\Attribute\ParameterAttribute;
use Retrofit\Core\Attribute\PartMap;
use Retrofit\Core\Internal\Encoding;
use Retrofit\Core\Internal\ParameterHandler\ParameterHandler;
use Retrofit\Core\Internal\ParameterHandler\PartMapParameterHandler;
use Retrofit\Core\Internal\Utils\Utils;
use Retrofit\Core\Type;
use Override;

/**
 * @extends AbstractParameterHandlerFactory<PartMap>
 *
 * @internal
 */
readonly class PartMapParameterHandlerFactory extends AbstractParameterHandlerFactory
{
    /** @param PartMap $param */
    #[Override]
    public function create(
        ParameterAttribute $param,
        HttpRequest $httpRequest,
        ?Encoding $encoding,
        ReflectionMethod $reflectionMethod,
        int $position,
        Type $type,
    ): ParameterHandler
    {
        if ($encoding !== Encoding::MULTIPART) {
            throw Utils::parameterException($reflectionMethod, $position, '#[PartMap] parameters can only be used with multipart.');
        }

        $converter = $this->converterProvider->getRequestBodyConverter($type);

        return new PartMapParameterHandler($param->encoding(), $converter, $reflectionMethod, $position);
    }
}
