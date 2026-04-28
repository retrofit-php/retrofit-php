<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal\ParameterHandler;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Ouzo\Tests\CatchException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Retrofit\Core\Attribute\POST;
use Retrofit\Core\Internal\BuiltInConverters;
use Retrofit\Core\Internal\ParameterHandler\PartMapParameterHandler;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\MimeEncoding;
use Retrofit\Core\Multipart\MultipartBody;
use Retrofit\Tests\Fixtures\Api\MockMethod;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use Retrofit\Tests\WithFixtureFile;
use RuntimeException;

class PartMapParameterHandlerTest extends TestCase
{
    use WithFixtureFile;

    private RequestBuilder $requestBuilder;

    private ReflectionMethod $reflectionMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestBuilder = new RequestBuilder(new Uri('https://example.com'), new POST('/users'));
        $this->reflectionMethod = new ReflectionMethod(MockMethod::class, 'mockMethod');
    }

    #[Test]
    public function shouldThrowExceptionOnNullValues(): void
    {
        // given
        $partMapParameterHandler = new PartMapParameterHandler(MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($partMapParameterHandler)->apply($this->requestBuilder, null);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. Part map was null.');
    }

    #[Test]
    public function shouldThrowExceptionWhenValueIsNotArray(): void
    {
        // given
        $partMapParameterHandler = new PartMapParameterHandler(MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($partMapParameterHandler)->apply($this->requestBuilder, 'some-string-value');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. Parameter should be an array.');
    }

    #[Test]
    public function shouldThrowExceptionWhenKeyInArrayIsNull(): void
    {
        // given
        $partMapParameterHandler = new PartMapParameterHandler(MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($partMapParameterHandler)->apply($this->requestBuilder, [null => 'value']);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. Part map contained empty key.');
    }

    #[Test]
    public function shouldThrowExceptionWhenValueInArrayIsNull(): void
    {
        // given
        $partMapParameterHandler = new PartMapParameterHandler(MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($partMapParameterHandler)->apply($this->requestBuilder, ['key' => null]);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage("Method MockMethod::mockMethod() parameter #1. Part map contained null value for key 'key'.");
    }

    #[Test]
    public function shouldAddPart(): void
    {
        // given
        $fileResource = $this->getFileResource('sample-image.jpg');

        $partMapParameterHandler = new PartMapParameterHandler(MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);
        $part1 = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        $part2 = MultipartBody::Part()::createFromData('part-iface', Utils::streamFor($fileResource), [], 'image.png');

        // when
        $partMapParameterHandler->apply($this->requestBuilder, ['part1' => $part1, 'part2' => $part2]);

        // then
        $request = $this->requestBuilder->build();
        $contents = $request->getBody()->getContents();

        $expectedPart1 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part1\"\r\nContent-Length: 26\r\n\r\n{\"id\":1,\"login\":\"jon-doe\"}";
        $this->assertStringContainsString($expectedPart1, $contents);

        $expectedPart2 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part-iface\"; filename=\"image.png\"\r\nContent-Length: 9155\r\nContent-Type: image/png\r\n\r\n";
        $this->assertStringContainsString($expectedPart2, $contents);
    }
}
