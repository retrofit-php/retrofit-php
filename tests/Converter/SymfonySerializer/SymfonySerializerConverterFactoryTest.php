<?php

declare(strict_types=1);

namespace Retrofit\Tests\Converter\SymfonySerializer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerConverterFactory;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerRequestBodyConverter;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerResponseBodyConverter;
use Retrofit\Core\Type;
use stdClass;
use Symfony\Component\Serializer\Serializer;

class SymfonySerializerConverterFactoryTest extends TestCase
{
    private SymfonySerializerConverterFactory $symfonySerializerConverterFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $serializer = new Serializer();
        $this->symfonySerializerConverterFactory = new SymfonySerializerConverterFactory($serializer, $serializer);
    }

    #[Test]
    public function shouldReturnSymfonyRequestBodyConverter(): void
    {
        // given
        $type = new Type(stdClass::class);

        // when
        $result = $this->symfonySerializerConverterFactory->requestBodyConverter($type);

        // then
        $this->assertInstanceOf(SymfonySerializerRequestBodyConverter::class, $result);
    }

    #[Test]
    public function shouldReturnSymfonySerializerResponseBodyConverter(): void
    {
        // given
        $type = new Type(stdClass::class);

        // when
        $result = $this->symfonySerializerConverterFactory->responseBodyConverter($type);

        // then
        $this->assertInstanceOf(SymfonySerializerResponseBodyConverter::class, $result);
    }

    #[Test]
    public function shouldReturnNullForStringConverter(): void
    {
        // given
        $type = new Type('string');

        // when
        $result = $this->symfonySerializerConverterFactory->stringConverter($type);

        // then
        $this->assertNull($result);
    }
}
