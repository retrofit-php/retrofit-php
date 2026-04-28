<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal\ParameterHandler;

use Psr\Http\Message\StreamInterface;
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
readonly class PartMapParameterHandler implements ParameterHandler
{
    use WithMapParameter;
    use WithPartInterfaceHandle;

    private const CONTENT_TRANSFER_ENCODING_HEADER = 'Content-Transfer-Encoding';

    public function __construct(
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
            throw Utils::parameterException($this->reflectionMethod, $this->position, 'Part map was null.');
        }

        $this->validateAndApply($value, 'Part', $this->converter, function (string $entryKey, StreamInterface $entryValue, mixed $originalValue) use ($requestBuilder): void {
            if ($originalValue instanceof PartInterface) {
                $this->handle($requestBuilder, $originalValue);
                return;
            }

            $headers[self::CONTENT_TRANSFER_ENCODING_HEADER] = $this->encoding->value;
            $requestBuilder->addPart($entryKey, $entryValue, $headers);
        });
    }
}
