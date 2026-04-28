<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal\ParameterHandler;

use GuzzleHttp\Psr7\Uri;
use Ouzo\Tests\CatchException;
use Ouzo\Utilities\Strings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Retrofit\Core\Attribute\POST;
use Retrofit\Core\Internal\BuiltInConverters;
use Retrofit\Core\Internal\ParameterHandler\PartParameterHandler;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Core\MimeEncoding;
use Retrofit\Core\Multipart\MultipartBody;
use Retrofit\Tests\Fixtures\Api\MockMethod;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use RuntimeException;

class PartParameterHandlerTest extends TestCase
{
    private RequestBuilder $requestBuilder;

    private ReflectionMethod $reflectionMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestBuilder = new RequestBuilder(new Uri('https://example.com'), new POST('/users'));
        $this->reflectionMethod = new ReflectionMethod(MockMethod::class, 'mockMethod');
    }

    #[Test]
    public function shouldSkipNullValues(): void
    {
        // given
        $partParameterHandler = new PartParameterHandler('part-nane', MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        $partParameterHandler->apply($this->requestBuilder, null);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame(Strings::EMPTY, $request->getBody()->getContents());
    }

    #[Test]
    public function shouldThrowExceptionWhenNameIsBlankAndTypeIsNotPartInterface(): void
    {
        // given
        $partParameterHandler = new PartParameterHandler('', MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($partParameterHandler)->apply($this->requestBuilder, 'some-value');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. #[Part] attribute must supply a name or use MultipartBody.Part parameter type.');
    }

    #[Test]
    public function shouldThrowExceptionWhenNameIsNotBlankAndTypeIsPartInterface(): void
    {
        // given
        $partParameterHandler = new PartParameterHandler('part-name', MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);
        $part = MultipartBody::Part()::createFromData('part-name-from-object', 'body');

        // when
        CatchException::when($partParameterHandler)->apply($this->requestBuilder, $part);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. #[Part] attribute using the MultipartBody.Part must not include a part name in the attribute.');
    }

    #[Test]
    public function shouldAddPart(): void
    {
        // given
        $partParameterHandler = new PartParameterHandler('some-part-name', MimeEncoding::BIT_7, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);
        $part = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $partParameterHandler->apply($this->requestBuilder, $part);

        // then
        $request = $this->requestBuilder->build();
        $part = "Content-Transfer-Encoding: 7bit\r\nContent-Disposition: form-data; name=\"some-part-name\"\r\nContent-Length: 26\r\n\r\n{\"id\":1,\"login\":\"jon-doe\"}";
        $this->assertStringContainsString($part, $request->getBody()->getContents());
    }

    #[Test]
    public function shouldAddPartUsingPartInterface(): void
    {
        // given
        $partParameterHandler = new PartParameterHandler(null, MimeEncoding::BINARY, BuiltInConverters::JsonEncodeRequestBodyConverter(), $this->reflectionMethod, 0);
        $part = MultipartBody::Part()::createFromData('part-name-from-object', 'body');

        // when
        $partParameterHandler->apply($this->requestBuilder, $part);

        // then
        $request = $this->requestBuilder->build();
        $part = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part-name-from-object\"\r\nContent-Length: 4\r\n\r\nbody";
        $this->assertStringContainsString($part, $request->getBody()->getContents());
    }
}
