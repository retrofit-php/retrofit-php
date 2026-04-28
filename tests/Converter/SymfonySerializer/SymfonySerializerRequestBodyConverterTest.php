<?php
declare(strict_types=1);

namespace Retrofit\Tests\Converter\SymfonySerializer;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerFormat;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerRequestBodyConverter;
use Retrofit\Core\Type;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SymfonySerializerRequestBodyConverterTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $normalizers = [new JsonSerializableNormalizer(), new ObjectNormalizer(), new ArrayDenormalizer()];
        $encoders = [new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    #[Test]
    public function shouldMarshallObject(): void
    {
        // given
        $type = new Type(UserRequest::class);
        $symfonySerializerRequestBodyConverter = new SymfonySerializerRequestBodyConverter($this->serializer, SymfonySerializerFormat::JSON, $type);

        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $result = $symfonySerializerRequestBodyConverter->convert($userRequest);

        // then
        $this->assertInstanceOf(StreamInterface::class, $result);
        $this->assertJsonStringEqualsJsonString('{"id":1,"login":"jon-doe"}', $result->getContents());
    }

    #[Test]
    public function shouldMarshallArrayOfObjects(): void
    {
        // given
        $type = new Type('array', UserRequest::class);
        $symfonySerializerRequestBodyConverter = new SymfonySerializerRequestBodyConverter($this->serializer, SymfonySerializerFormat::JSON, $type);

        $userRequests = [
            new UserRequest()
                ->setId(1)
                ->setLogin('jon-doe'),
            new UserRequest()
                ->setId(2)
                ->setLogin('bill-smith'),
        ];

        // when
        $result = $symfonySerializerRequestBodyConverter->convert($userRequests);

        // then
        $this->assertInstanceOf(StreamInterface::class, $result);
        $this->assertJsonStringEqualsJsonString('[{"id":1,"login":"jon-doe"},{"id":2,"login":"bill-smith"}]', $result->getContents());
    }

    #[Test]
    public function shouldHandleStreamInterface(): void
    {
        // given
        $type = new Type(StreamInterface::class);
        $symfonySerializerRequestBodyConverter = new SymfonySerializerRequestBodyConverter($this->serializer, SymfonySerializerFormat::JSON, $type);

        $stream = Utils::streamFor('empty');

        // when
        $result = $symfonySerializerRequestBodyConverter->convert($stream);

        // then
        $this->assertSame($stream, $result);
    }
}
