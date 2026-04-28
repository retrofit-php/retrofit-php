<?php

declare(strict_types=1);

namespace Retrofit\Core\Internal;

use GuzzleHttp\Psr7\Utils;
use Iterator;
use Ouzo\Utilities\Strings;
use Psr\Http\Message\StreamInterface;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Converter\ResponseBodyConverter;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Type;
use RuntimeException;
use stdClass;
use Override;

/**
 * @internal
 */
readonly class BuiltInConverters
{
    public static function JsonEncodeRequestBodyConverter(): RequestBodyConverter
    {
        return new class () implements RequestBodyConverter {
            #[Override]
            public function convert(mixed $value): StreamInterface
            {
                return Utils::streamFor(json_encode($value));
            }
        };
    }

    public static function StreamInterfaceRequestBodyConverter(): RequestBodyConverter
    {
        return new class () implements RequestBodyConverter {
            /**
             * @param bool|callable|float|int|Iterator|StreamInterface|resource|string|null $value
             * @return StreamInterface
             */
            #[Override]
            public function convert(mixed $value): StreamInterface
            {
                return Utils::streamFor($value);
            }
        };
    }

    public static function StreamInterfaceResponseBodyConverter(): ResponseBodyConverter
    {
        return new class () implements ResponseBodyConverter {
            #[Override]
            public function convert(StreamInterface $value): StreamInterface
            {
                return $value;
            }
        };
    }

    public static function StdClassResponseBodyConverter(): ResponseBodyConverter
    {
        return new class () implements ResponseBodyConverter {
            #[Override]
            public function convert(StreamInterface $value): stdClass
            {
                $response = json_decode($value->getContents());
                if ($response instanceof stdClass) {
                    return $response;
                }
                throw new RuntimeException('Response is not a stdClass.');
            }
        };
    }

    public static function ArrayResponseBodyConverter(Type $type): ResponseBodyConverter
    {
        return new class ($type) implements ResponseBodyConverter {
            public function __construct(private readonly Type $type)
            {
            }

            /** @return array<mixed> */
            #[Override]
            public function convert(StreamInterface $value): array
            {
                $result = json_decode($value->getContents(), $this->type->parametrizedTypeIsScalar());
                if (is_array($result)) {
                    return $result;
                }
                throw new RuntimeException('Response is not an array.');
            }
        };
    }

    public static function VoidResponseBodyConverter(): ResponseBodyConverter
    {
        return new class () implements ResponseBodyConverter {
            #[Override]
            public function convert(StreamInterface $value): null
            {
                return null;
            }
        };
    }

    public static function ScalarTypeResponseBodyConverter(Type $type): ResponseBodyConverter
    {
        return new class ($type) implements ResponseBodyConverter {
            public function __construct(private readonly Type $type)
            {
            }

            #[Override]
            public function convert(StreamInterface $value): mixed
            {
                $contents = $value->getContents();

                return match ($this->type->getRawType()) {
                    'bool' => (bool)$contents,
                    'float' => (float)$contents,
                    'int' => (int)$contents,
                    'string' => (string)$contents,
                    default => null,
                };
            }
        };
    }

    public static function ToStringConverter(): StringConverter
    {
        return new class () implements StringConverter {
            #[Override]
            public function convert(mixed $value): string
            {
                // If it's an array or object, just serialize it.
                if (is_array($value) || is_object($value)) {
                    return serialize($value);
                }

                if ($value === true) {
                    return 'true';
                }

                if ($value === false) {
                    return 'false';
                }

                if (is_scalar($value)) {
                    return (string)$value;
                }

                if (is_null($value)) {
                    return Strings::EMPTY;
                }

                $type = gettype($value);
                throw new RuntimeException("Cannot convert to string type '{$type}'.");
            }
        };
    }
}
