<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use Ouzo\Utilities\Strings;
use ReflectionMethod;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\Internal\Utils\Utils;
use Retrofit\Core\MimeEncoding;
use Retrofit\Core\Multipart\PartInterface;
use Override;

/**
 * @internal
 */
readonly class PartParameterHandler implements ParameterHandler
{
    use WithPartInterfaceHandle;

    public function __construct(
        private ?string $name,
        private MimeEncoding $encoding,
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
            return;
        }

        if (Strings::isBlank($this->name) && !$value instanceof PartInterface) {
            throw Utils::parameterException(
                $this->reflectionMethod,
                $this->position,
                '#[Part] attribute must supply a name or use MultipartBody.Part parameter type.',
            );
        }

        if (Strings::isNotBlank($this->name) && $value instanceof PartInterface) {
            throw Utils::parameterException(
                $this->reflectionMethod,
                $this->position,
                '#[Part] attribute using the MultipartBody.Part must not include a part name in the attribute.',
            );
        }

        if ($value instanceof PartInterface) {
            $this->handle($requestBuilder, $value);
            return;
        }

        $value = $this->converter->convert($value);
        $headers[self::CONTENT_TRANSFER_ENCODING_HEADER] = $this->encoding->value;
        $requestBuilder->addPart((string)$this->name, $value, $headers);
    }
}
