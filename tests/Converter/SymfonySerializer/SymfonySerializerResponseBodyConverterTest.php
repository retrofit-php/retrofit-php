<?php
declare(strict_types=1);

namespace Retrofit\Tests\Converter\SymfonySerializer;

use GuzzleHttp\Psr7\Utils;
use Ouzo\Tests\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerFormat;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerResponseBodyConverter;
use Retrofit\Core\Type;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SymfonySerializerResponseBodyConverterTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $normalizers = [new JsonSerializableNormalizer(), new ObjectNormalizer(), new ArrayDenormalizer()];
        $encoders = [new JsonEncoder(), new XmlEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    #[Test]
    public function shouldReturnTypeOfStreamInterface(): void
    {
        // given
        $type = new Type(StreamInterface::class);
        $symfonySerializerResponseBodyConverter = new SymfonySerializerResponseBodyConverter($this->serializer, $this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor('empty');

        // when
        $result = $symfonySerializerResponseBodyConverter->convert($stream);

        // then
        $this->assertSame($stream, $result);
    }

    #[Test]
    public function shouldUnmarshallObject(): void
    {
        // given
        $type = new Type(UserRequest::class);
        $symfonySerializerResponseBodyConverter = new SymfonySerializerResponseBodyConverter($this->serializer, $this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor('{"id":1,"login":"jon-doe"}');

        // when
        $result = $symfonySerializerResponseBodyConverter->convert($stream);

        // then
        $this->assertInstanceOf(UserRequest::class, $result);
    }

    #[Test]
    public function shouldUnmarshallArrayOfObjects(): void
    {
        // given
        $type = new Type('array', UserRequest::class);
        $symfonySerializerResponseBodyConverter = new SymfonySerializerResponseBodyConverter($this->serializer, $this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor('[{"id":1,"login":"jon-doe"},{"id":2,"login":"bill-smith"}]');

        // when
        $result = $symfonySerializerResponseBodyConverter->convert($stream);

        // then
        Assert::thatArray($result)
            ->hasSize(2)
            ->extracting(fn(UserRequest $u) => $u->getId(), fn(UserRequest $u) => $u->getLogin())
            ->containsOnly(
                [1, 'jon-doe'],
                [2, 'bill-smith'],
            );
    }

    #[Test]
    #[TestWith(['string', 'OK', 'OK'])]
    #[TestWith(['int', '100', 100])]
    #[TestWith(['float', '10.32', 10.32])]
    public function shouldReturnDecodedValue(string $rawType, mixed $content, mixed $expected): void
    {
        // given
        $type = new Type($rawType);
        $symfonySerializerResponseBodyConverter = new SymfonySerializerResponseBodyConverter($this->serializer, $this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor($content);

        // when
        $result = $symfonySerializerResponseBodyConverter->convert($stream);

        // then
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function shouldReturnDecodedArrayOrStrings(): void
    {
        // given
        $type = new Type('array', 'string');
        $symfonySerializerResponseBodyConverter = new SymfonySerializerResponseBodyConverter($this->serializer, $this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor('["value1", "value2"]');

        // when
        $result = $symfonySerializerResponseBodyConverter->convert($stream);

        // then
        $this->assertSame(['value1', 'value2'], $result);
    }
}
