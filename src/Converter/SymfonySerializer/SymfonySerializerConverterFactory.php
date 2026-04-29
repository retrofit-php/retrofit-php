<?php

declare(strict_types=1);

namespace Retrofit\Converter\SymfonySerializer;

use Retrofit\Core\Converter\ConverterFactory;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Converter\ResponseBodyConverter;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Type;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Override;

/**
 * {@link https://symfony.com/doc/current/components/serializer.html Symfony Serializer} converter factory implementation.
 *
 * @see ConverterFactory
 *
 * @api
 */
readonly class SymfonySerializerConverterFactory implements ConverterFactory
{
    public function __construct(
        private SerializerInterface $serializer,
        private DecoderInterface $decoder,
        private SymfonySerializerFormat $symfonySerializerFormat = SymfonySerializerFormat::JSON,
    )
    {
    }

    #[Override]
    public function requestBodyConverter(Type $type): ?RequestBodyConverter
    {
        return new SymfonySerializerRequestBodyConverter($this->serializer, $this->symfonySerializerFormat, $type);
    }

    #[Override]
    public function responseBodyConverter(Type $type): ?ResponseBodyConverter
    {
        return new SymfonySerializerResponseBodyConverter($this->serializer, $this->decoder, $this->symfonySerializerFormat, $type);
    }

    #[Override]
    public function stringConverter(Type $type): ?StringConverter
    {
        return null;
    }
}
