<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Ouzo\Tests\Assert;
use Ouzo\Tests\CatchException;
use Ouzo\Tests\Mock\Mock;
use Ouzo\Tests\Mock\MockInterface;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Retrofit\Core\HttpClient;
use Retrofit\Core\Internal\BuiltInConverterFactory;
use Retrofit\Core\Internal\ConverterProvider;
use Retrofit\Core\Internal\Encoding;
use Retrofit\Core\Internal\ParameterHandler\Factory\AbstractParameterHandlerFactory;
use Retrofit\Core\Internal\ParameterHandler\Factory\ParameterHandlerFactoryProvider;
use Retrofit\Core\Internal\ParameterHandler\ParameterHandler;
use Retrofit\Core\Internal\Proxy\DefaultProxyFactory;
use Retrofit\Core\Internal\ServiceMethodFactory;
use Retrofit\Core\Multipart\MultipartBody;
use Retrofit\Core\Retrofit;
use Retrofit\Core\Type;
use Retrofit\Tests\Fixtures\Api\AllHttpRequestMethods;
use Retrofit\Tests\Fixtures\Api\FullyValidApi;
use Retrofit\Tests\Fixtures\Api\InvalidMethods;
use Retrofit\Tests\Fixtures\Api\InvalidRequestBody;
use Retrofit\Tests\Fixtures\Api\TypeResolverApi;
use Retrofit\Tests\Fixtures\Converter\TestConverterFactory;
use Retrofit\Tests\Fixtures\Model\UserRequest;
use Retrofit\Tests\WithFixtureFile;
use RuntimeException;
use stdClass;

class ServiceMethodFactoryTest extends TestCase
{
    use WithFixtureFile;

    private HttpClient|MockInterface $httpClient;

    private Retrofit $retrofit;

    private ParameterHandlerFactoryProvider|MockInterface $parameterHandlerFactoryProvider;

    private ServiceMethodFactory $serviceMethodFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = Mock::create(HttpClient::class);
        $baseUrl = new Uri('https://example.com');
        $converterProvider = new ConverterProvider([new BuiltInConverterFactory(), new TestConverterFactory()]);
        $proxyFactory = new DefaultProxyFactory(new BuilderFactory(), new Standard());

        $this->retrofit = new Retrofit($this->httpClient, $baseUrl, $converterProvider, $proxyFactory);

        $this->parameterHandlerFactoryProvider = new ParameterHandlerFactoryProvider($this->retrofit->converterProvider);
        $this->serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);
    }

    #[Test]
    public function shouldThrowExceptionWhenMethodDoesNotHaveHttpAttribute(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'withoutHttpAttribute');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::withoutHttpAttribute(). HTTP method annotation is required (e.g., #[GET], #[POST], etc.).');
    }

    #[Test]
    public function shouldThrowExceptionWhenMethodHasMultipleHttpAttributes(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'multipleHttpAttribute');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::multipleHttpAttribute(). Only one HTTP method is allowed. Found: [Retrofit\Core\Attribute\GET, Retrofit\Core\Attribute\HTTP].');
    }

    #[Test]
    public function shouldThrowExceptionWhenMultipleUrlAttributesFound(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'multipleUrlAttributes');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::multipleUrlAttributes() parameter #2. Multiple #[Url] method attributes found.');
    }

    #[Test]
    #[TestWith(['delete'])]
    #[TestWith(['get'])]
    #[TestWith(['head'])]
    #[TestWith(['options'])]
    #[TestWith(['patch'])]
    #[TestWith(['post'])]
    #[TestWith(['put'])]
    public function shouldInvokeAllHttpMethods(string $method): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(AllHttpRequestMethods::class, $method);

        // then
        $request = $serviceMethod->invoke([])->request();
        $this->assertSame(strtoupper($method), $request->getMethod());
        $this->assertSame("https://example.com/{$method}", $request->getUri()->__toString());
    }

    #[Test]
    public function shouldProcessUrlBeforePath(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'pathIsBeforeUrl');

        // then
        $request = $serviceMethod->invoke(['jon', 'https://example.com/users/{login}'])->request();
        $this->assertSame('https://example.com/users/jon', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddQueryStringToUrlAttribute(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'urlWithQuery');

        // then
        $request = $serviceMethod->invoke(['new', 'https://example.com/users'])->request();
        $this->assertSame('https://example.com/users?group=new', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddPathAndQueryString(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'pathAndQuery');

        // then
        $request = $serviceMethod->invoke(['jon', 'new'])->request();
        $this->assertSame('https://example.com/users/jon?group=new', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddPathAndQueryName(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addQueryName');

        // then
        $request = $serviceMethod->invoke(['user(jon)'])->request();
        $this->assertSame('https://example.com/users?user(jon)', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddPathAndQueryMap(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addQueryMap');

        // then
        $request = $serviceMethod->invoke([['name' => 'jon+doe', 'age' => 34, 'registered' => false]])->request();
        $this->assertSame('https://example.com/users?name=jon%2Bdoe&age=34&registered=false', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddHeader(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addHeader');

        // then
        $request = $serviceMethod->invoke(['some-custom-value'])->request();
        Assert::thatArray($request->getHeaders())->containsKeyAndValue(['x-custom' => ['some-custom-value']]);
    }

    #[Test]
    public function shouldAddHeaderMap(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addHeaderMap');

        // then
        $request = $serviceMethod->invoke([['x-custom' => 'jon+doe', 'x-age' => 34, 'Content-Type' => 'application/json']])->request();
        Assert::thatArray($request->getHeaders())
            ->containsKeyAndValue(['x-custom' => ['jon+doe'], 'x-age' => ['34'], 'content-type' => ['application/json']]);
    }

    #[Test]
    public function shouldSetDefaultHeaders(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addHeaders');

        // then
        $request = $serviceMethod->invoke([['x-custom' => 'jon+doe', 'x-age' => 34, 'Content-Type' => 'application/json']])->request();
        Assert::thatArray($request->getHeaders())
            ->containsKeyAndValue(['x-custom' => ['jon+doe'], 'x-age' => ['34'], 'content-type' => ['application/json']]);
    }

    #[Test]
    public function shouldParameterHeaderHaveHighestPrecedenceThanMethodHeaders(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addHeadersWithParameterHeader');

        // then
        $request = $serviceMethod->invoke([100])->request();
        Assert::thatArray($request->getHeaders())
            ->containsKeyAndValue(['x-custom' => ['jon+doe'], 'x-age' => ['100'], 'content-type' => ['application/json']]);
    }

    #[Test]
    public function shouldThrowExceptionWhenKeyInHeadersAttributeIsNull(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'headersKeyIsNull');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::headersKeyIsNull(). Headers map contained empty key.');
    }

    #[Test]
    public function shouldThrowExceptionWhenValueInHeadersAttributeIsNull(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'headersValueIsNull');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage("Method InvalidMethods::headersValueIsNull(). Headers map contained null value for key 'key'.");
    }

    #[Test]
    public function shouldThrowExceptionWhenMultipleEncodingDefined(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'multipleEncodings');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::multipleEncodings(). Only one encoding annotation is allowed.');
    }

    #[Test]
    public function shouldPassNullEncodingWhenMethodIsNotImplementingAny(): void
    {
        // given
        $factory = Mock::create(AbstractParameterHandlerFactory::class);
        Mock::when($factory)->create(Mock::anyArgList())->thenReturn(Mock::create(ParameterHandler::class));

        $this->parameterHandlerFactoryProvider = Mock::create(ParameterHandlerFactoryProvider::class);
        Mock::when($this->parameterHandlerFactoryProvider)->get(Mock::anyArgList())->thenReturn($factory);

        $serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);

        // when
        $serviceMethodFactory->create(FullyValidApi::class, 'addHeader');

        // then
        Mock::verify($factory)->create(Mock::any(), Mock::any(), null, Mock::any(), Mock::any(), Mock::any());
    }

    #[Test]
    public function shouldPassFormUrlEncodedEncodingWhenMethodIsNotImplementingFormUrlEncodedAttribute(): void
    {
        // given
        $factory = Mock::create(AbstractParameterHandlerFactory::class);
        Mock::when($factory)->create(Mock::anyArgList())->thenReturn(Mock::create(ParameterHandler::class));

        $this->parameterHandlerFactoryProvider = Mock::create(ParameterHandlerFactoryProvider::class);
        Mock::when($this->parameterHandlerFactoryProvider)->get(Mock::anyArgList())->thenReturn($factory);

        $serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);

        // when
        $serviceMethodFactory->create(FullyValidApi::class, 'formUrlEncoded');

        // then
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Encoding::FORM_URL_ENCODED, Mock::any(), Mock::any(), Mock::any());
    }

    #[Test]
    public function shouldPassMultipartEncodingWhenMethodIsNotImplementingFormUrlEncodedAttribute(): void
    {
        // given
        $factory = Mock::create(AbstractParameterHandlerFactory::class);
        Mock::when($factory)->create(Mock::anyArgList())->thenReturn(Mock::create(ParameterHandler::class));

        $this->parameterHandlerFactoryProvider = Mock::create(ParameterHandlerFactoryProvider::class);
        Mock::when($this->parameterHandlerFactoryProvider)->get(Mock::anyArgList())->thenReturn($factory);

        $serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);

        // when
        $serviceMethodFactory->create(FullyValidApi::class, 'multipart');

        // then
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Encoding::MULTIPART, Mock::any(), Mock::any(), Mock::any());
    }

    #[Test]
    public function shouldThrowExceptionWhenMultipartIsNotForHttpMethodWithBody(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'multipartForHttpMethodWithoutBody');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::multipartForHttpMethodWithoutBody(). #[Multipart] can only be specified on HTTP methods with request body (e.g., #[POST]).');
    }

    #[Test]
    public function shouldThrowExceptionWhenFormUrlEncodedIsNotForHttpMethodWithBody(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'formUrlEncodedForHttpMethodWithoutBody');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::formUrlEncodedForHttpMethodWithoutBody(). #[FormUrlEncoded] can only be specified on HTTP methods with request body (e.g., #[POST]).');
    }

    #[Test]
    public function shouldAddField(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addField');

        // then
        $request = $serviceMethod->invoke(['jon+doe', 'users+admin'])->request();
        $this->assertSame('x-login=jon%2Bdoe&filters=users+admin', $request->getBody()->getContents());
    }

    #[Test]
    public function shouldAddFieldMap(): void
    {
        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addFieldMap');

        // then
        $request = $serviceMethod->invoke([['x-login' => 'jon+doe', 'filters' => 'users+admin']])->request();
        $this->assertSame('x-login=jon+doe&filters=users+admin', $request->getBody()->getContents());
    }

    #[Test]
    public function shouldThrowExceptionWhenFormUrlEncodedDoesNotHaveAtLeastOneFieldAttribute(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'formUrlEncodedDoesNotHaveAtLeastOneFieldAttribute');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::formUrlEncodedDoesNotHaveAtLeastOneFieldAttribute(). #[FormUrlEncoded] method must contain at least one #[Field] or #[FieldMap].');
    }

    #[Test]
    public function shouldThrowExceptionWhenMultipartDoesNotHaveAtLeastOnePartAttribute(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'multipartDoesNotHaveAtLeastOnePartAttribute');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::multipartDoesNotHaveAtLeastOnePartAttribute(). #[Multipart] method must contain at least one #[Part] or #[PartMap].');
    }

    #[Test]
    public function shouldAddPart(): void
    {
        // given
        $fileResource = $this->getFileResource('sample-image.jpg');

        $string = 'some-string';
        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');
        $partInterface = MultipartBody::Part()::createFromData('part-iface', Utils::streamFor($fileResource), [], 'image.png');
        $streamInterface = Utils::streamFor($fileResource);

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addPart');

        // then
        $request = $serviceMethod->invoke([$string, $userRequest, $partInterface, $streamInterface])->request();

        $contents = $request->getBody()->getContents();

        $part1 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"string\"\r\nContent-Length: 13\r\n\r\n\"some-string\"";
        $this->assertStringContainsString($part1, $contents);

        $part2 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"userRequest\"\r\nContent-Length: 26\r\n\r\n{\"id\":1,\"login\":\"jon-doe\"}\r\n";
        $this->assertStringContainsString($part2, $contents);

        $part3 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part-iface\"; filename=\"image.png\"\r\nContent-Length: 9155\r\nContent-Type: image/png\r\n\r\n";
        $this->assertStringContainsString($part3, $contents);

        $part4 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"stream\"; filename=\"sample-image.jpg\"\r\nContent-Length: 9155\r\nContent-Type: image/jpeg";
        $this->assertStringContainsString($part4, $contents);
    }

    #[Test]
    public function shouldAddPartMap(): void
    {
        // given
        $part1 = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        $fileResource = $this->getFileResource('sample-image.jpg');

        $part2 = MultipartBody::Part()::createFromData('part-iface', Utils::streamFor($fileResource), [], 'image.png');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'addPartMap');

        // then
        $request = $serviceMethod->invoke([['part1' => $part1, 'part2' => $part2]])->request();

        $contents = $request->getBody()->getContents();

        $expectedPart1 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part1\"\r\nContent-Length: 26\r\n\r\n{\"id\":1,\"login\":\"jon-doe\"}";
        $this->assertStringContainsString($expectedPart1, $contents);

        $expectedPart2 = "Content-Transfer-Encoding: binary\r\nContent-Disposition: form-data; name=\"part-iface\"; filename=\"image.png\"\r\nContent-Length: 9155\r\nContent-Type: image/png\r\n\r\n";
        $this->assertStringContainsString($expectedPart2, $contents);
    }

    #[Test]
    public function shouldThrowExceptionWhenParameterDoesNotHaveType(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'parameterWithoutType');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::parameterWithoutType() parameter #1. Type is required.');
    }

    #[Test]
    public function shouldThrowExceptionWhenNotFoundRetrofitAttributesForParameter(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidMethods::class, 'parameterWithoutAttribute');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method InvalidMethods::parameterWithoutAttribute() parameter #1. No Retrofit attribute found.');
    }

    #[Test]
    public function shouldHandleScalarTypes(): void
    {
        // given
        $factory = Mock::create(AbstractParameterHandlerFactory::class);
        Mock::when($factory)->create(Mock::anyArgList())->thenReturn(Mock::create(ParameterHandler::class));

        $this->parameterHandlerFactoryProvider = Mock::create(ParameterHandlerFactoryProvider::class);
        Mock::when($this->parameterHandlerFactoryProvider)->get(Mock::anyArgList())->thenReturn($factory);

        $serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);

        // when
        $serviceMethodFactory->create(TypeResolverApi::class, 'scalarTypes');

        // then
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), new Type('bool'));
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), new Type('float'));
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), new Type('int'));
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), new Type('mixed'));
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), new Type('string'));
    }

    #[Test]
    public function shouldHandleArrayOfCustomClass(): void
    {
        // given
        $factory = Mock::create(AbstractParameterHandlerFactory::class);
        Mock::when($factory)->create(Mock::anyArgList())->thenReturn(Mock::create(ParameterHandler::class));

        $this->parameterHandlerFactoryProvider = Mock::create(ParameterHandlerFactoryProvider::class);
        Mock::when($this->parameterHandlerFactoryProvider)->get(Mock::anyArgList())->thenReturn($factory);

        $serviceMethodFactory = new ServiceMethodFactory($this->retrofit, $this->parameterHandlerFactoryProvider);

        // when
        $serviceMethodFactory->create(TypeResolverApi::class, 'arrayOfCustomClass');

        // then
        $type = new Type('array', UserRequest::class);
        Mock::verify($factory)->create(Mock::any(), Mock::any(), Mock::any(), Mock::any(), Mock::any(), $type);
    }

    #[Test]
    public function shouldSetBody(): void
    {
        // given
        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'setBody');

        // then
        $request = $serviceMethod->invoke([$userRequest])->request();

        $this->assertStringContainsString('{"id":1,"login":"jon-doe"}', $request->getBody()->getContents());
    }

    #[Test]
    #[TestWith(['string'])]
    #[TestWith([100])]
    #[TestWith([123.123])]
    #[TestWith([true])]
    public function shouldHandleBodyAsScalar(mixed $body): void
    {
        // given
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], Utils::streamFor($body)));

        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'returnScalar');

        // then
        $execute = $serviceMethod->invoke([$userRequest])->execute();
        $this->assertSame((string)$body, $execute->body());
    }

    #[Test]
    public function shouldHandleVoidResponseBody(): void
    {
        // given
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], Utils::streamFor('sample body')));

        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'returnVoid');

        // then
        $execute = $serviceMethod->invoke([$userRequest])->execute();
        $this->assertNull($execute->body());
    }

    #[Test]
    public function shouldHandleMissingResponseBodyAttribute(): void
    {
        // given
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], Utils::streamFor('sample body')));

        $userRequest = new UserRequest()
            ->setId(1)
            ->setLogin('jon-doe');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'fireAndForget');

        // then
        $execute = $serviceMethod->invoke([$userRequest])->execute();
        $this->assertNull($execute->body());
    }

    #[Test]
    public function shouldHandleStdClassResponseBody(): void
    {
        // given
        $stream = Utils::streamFor('{"login":"jon-doe"}');
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], $stream));

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'returnStdClass');

        // then
        $execute = $serviceMethod->invoke([])->execute();
        $body = $execute->body();
        $this->assertInstanceOf(stdClass::class, $body);
        $this->assertSame('jon-doe', $body->login);
    }

    #[Test]
    #[TestWith(['["first string", "second string"]', ['first string', 'second string']])]
    #[TestWith(['{"key1":"value1"}', ['key1' => 'value1']])]
    #[TestWith(['[]', []])]
    public function shouldHandleBodyAsArrayOfScalar(string $body, array $expectedBody): void
    {
        // given
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], Utils::streamFor($body)));

        $userRequest = new UserRequest()
            ->setLogin('jon-doe');

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'returnArrayOfScalar');

        // then
        $execute = $serviceMethod->invoke([$userRequest])->execute();
        $this->assertSame($expectedBody, $execute->body());
    }

    #[Test]
    public function shouldHandleBodyAsArrayOfStdClass(): void
    {
        // given
        $stream = Utils::streamFor('[{"key":"value1"},{"key":"value2"}]');
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], $stream));

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'returnArrayOfStdClass');

        // then
        $execute = $serviceMethod->invoke([])->execute();
        Assert::thatArray($execute->body())
            ->extracting(fn(stdClass $c): string => $c->key)
            ->containsOnly('value1', 'value2');
    }

    #[Test]
    public function shouldHandleErrorBody(): void
    {
        // given
        $stream = Utils::streamFor('{"result":false, "message":"invalid-request"}');
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(400, [], $stream));

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'testErrorBody');

        // then
        $execute = $serviceMethod->invoke([])->execute();

        $this->assertNull($execute->body());

        $errorBody = $execute->errorBody();
        $this->assertFalse($errorBody->result);
        $this->assertSame('invalid-request', $errorBody->message);
    }

    #[Test]
    public function shouldHandleErrorBodyWhenErrorBodyConverterIsNotSet(): void
    {
        // given
        $stream = Utils::streamFor('{"result":false, "message"":"invalid-request"}');
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(400, [], $stream));

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'testErrorBodyWithoutMapping');

        // then
        $execute = $serviceMethod->invoke([])->execute();
        $this->assertNull($execute->body());
        $this->assertNull($execute->errorBody());
    }

    #[Test]
    public function shouldThrowExceptionWhenNotArrayTypeHasParametrizedType(): void
    {
        // when
        CatchException::when($this->serviceMethodFactory)->create(InvalidRequestBody::class, 'notArrayTypeHasParametrizedType');

        // then
        CatchException::assertThat()
            ->isInstanceOf(InvalidArgumentException::class)
            ->hasMessage('Parametrized type can be set only for array raw type.');
    }

    #[Test]
    public function shouldHandleStreamInterfaceAsResponseBodyUsingStreamingAttribute(): void
    {
        // given
        $stream = Utils::streamFor('[{"key":"value1"},{"key":"value2"}]');
        Mock::when($this->httpClient)->send(Mock::any())->thenReturn(new Response(200, [], $stream));

        // when
        $serviceMethod = $this->serviceMethodFactory->create(FullyValidApi::class, 'streamInterfaceAsResponseBody');

        // then
        $execute = $serviceMethod->invoke([])->execute();
        $body = $execute->body();
        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertSame($stream, $body);
    }
}
