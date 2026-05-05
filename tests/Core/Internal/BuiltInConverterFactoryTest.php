<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Retrofit\Core\Converter\RequestBodyConverter;
use Retrofit\Core\Converter\ResponseBodyConverter;
use Retrofit\Core\Converter\StringConverter;
use Retrofit\Core\Internal\BuiltInConverterFactory;
use Retrofit\Core\Type;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use stdClass;

class BuiltInConverterFactoryTest extends TestCase
{
    private BuiltInConverterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new BuiltInConverterFactory();
    }

    #[Test]
    #[TestWith(['string'])]
    #[TestWith(['int'])]
    #[TestWith(['float'])]
    #[TestWith(['bool'])]
    public function stringConverterShouldReturnConverterForScalarType(string $rawType): void
    {
        // when
        $converter = $this->factory->stringConverter(new Type($rawType));

        // then
        $this->assertInstanceOf(StringConverter::class, $converter);
    }

    #[Test]
    public function stringConverterShouldReturnConverterForArrayType(): void
    {
        // when
        $converter = $this->factory->stringConverter(new Type('array'));

        // then
        $this->assertInstanceOf(StringConverter::class, $converter);
    }

    #[Test]
    public function stringConverterShouldReturnConverterForArrayWithParametrizedType(): void
    {
        // when
        $converter = $this->factory->stringConverter(new Type('array', 'string'));

        // then
        $this->assertInstanceOf(StringConverter::class, $converter);
    }

    #[Test]
    public function stringConverterShouldReturnConverterForObjectType(): void
    {
        // when
        $converter = $this->factory->stringConverter(new Type(stdClass::class));

        // then
        $this->assertInstanceOf(StringConverter::class, $converter);
    }

    #[Test]
    public function stringConverterShouldReturnConverterThatHandlesArrayValue(): void
    {
        // given
        $converter = $this->factory->stringConverter(new Type('array'));

        // when
        $value = $converter->convert(['one', 'two']);

        // then
        $this->assertSame(serialize(['one', 'two']), $value);
    }

    #[Test]
    public function stringConverterShouldReturnConverterThatHandlesScalarValue(): void
    {
        // given
        $converter = $this->factory->stringConverter(new Type('int'));

        // when
        $value = $converter->convert(42);

        // then
        $this->assertSame('42', $value);
    }

    #[Test]
    public function requestBodyConverterShouldReturnStreamInterfaceConverterForStreamInterfaceType(): void
    {
        // when
        $converter = $this->factory->requestBodyConverter(new Type(StreamInterface::class));

        // then
        $this->assertInstanceOf(RequestBodyConverter::class, $converter);
    }

    #[Test]
    public function requestBodyConverterShouldReturnJsonEncodeConverterForNonScalarType(): void
    {
        // when
        $converter = $this->factory->requestBodyConverter(new Type('array'));

        // then
        $this->assertInstanceOf(RequestBodyConverter::class, $converter);
    }

    #[Test]
    public function requestBodyConverterShouldReturnJsonEncodeConverterForObjectType(): void
    {
        // when
        $converter = $this->factory->requestBodyConverter(new Type(UserRequest::class));

        // then
        $this->assertInstanceOf(RequestBodyConverter::class, $converter);
    }

    #[Test]
    #[TestWith(['string'])]
    #[TestWith(['int'])]
    #[TestWith(['float'])]
    #[TestWith(['bool'])]
    public function requestBodyConverterShouldReturnNullForScalarType(string $rawType): void
    {
        // when
        $converter = $this->factory->requestBodyConverter(new Type($rawType));

        // then
        $this->assertNull($converter);
    }

    #[Test]
    public function responseBodyConverterShouldReturnStreamInterfaceConverterForStreamInterfaceType(): void
    {
        // when
        $converter = $this->factory->responseBodyConverter(new Type(StreamInterface::class));

        // then
        $this->assertInstanceOf(ResponseBodyConverter::class, $converter);
    }

    #[Test]
    public function responseBodyConverterShouldReturnVoidConverterForVoidType(): void
    {
        // when
        $converter = $this->factory->responseBodyConverter(new Type('void'));

        // then
        $this->assertInstanceOf(ResponseBodyConverter::class, $converter);
    }

    #[Test]
    #[TestWith(['string'])]
    #[TestWith(['int'])]
    #[TestWith(['array'])]
    #[TestWith([stdClass::class])]
    public function responseBodyConverterShouldReturnNullForUnsupportedType(string $rawType): void
    {
        // when
        $converter = $this->factory->responseBodyConverter(new Type($rawType));

        // then
        $this->assertNull($converter);
    }
}
