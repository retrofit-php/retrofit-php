<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Ouzo\Tests\Assert;
use Ouzo\Tests\CatchException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Retrofit\Core\Attribute\GET;
use Retrofit\Core\Attribute\POST;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Tests\WithFixtureFile;
use RuntimeException;

class RequestBuilderTest extends TestCase
{
    use WithFixtureFile;

    #[Test]
    public function shouldCreateRequest(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users'));

        // when
        $request = $requestBuilder->build();

        // then
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://example.com/users', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldKeepStaticQueryStringFromPath(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/v1/personFields?limit=1000'));

        // when
        $request = $requestBuilder->build();

        // then
        $this->assertSame('https://example.com/v1/personFields?limit=1000', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldCombineStaticQueryStringWithDynamicQueryParam(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/v1/personFields?limit=1000'));

        // when
        $requestBuilder->addQueryParam('offset', '50', false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/v1/personFields?limit=1000&offset=50', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldCombineStaticQueryStringWithMultipleDynamicQueryParams(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/v1/personFields?limit=1000'));

        // when
        $requestBuilder->addQueryParam('offset', '50', false);
        $requestBuilder->addQueryParam('sort', ['name', 'created'], false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/v1/personFields?limit=1000&offset=50&sort=name&sort=created', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldCombineStaticQueryStringWithPathParameter(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users/{id}?expand=profile'));

        // when
        $requestBuilder->addPathParam('id', '42', false);
        $requestBuilder->addQueryParam('lang', 'pl', false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/users/42?expand=profile&lang=pl', $request->getUri()->__toString());
    }

    #[Test]
    #[TestWith(['https://foo.bar/api/users'])]
    #[TestWith([new Uri('https://foo.bar/api/users')])]
    public function shouldSetNewBaseUrl(Uri|string $uri): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->setBaseUrl($uri);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://foo.bar/api/users', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldReplacePathParameter(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users/{id}'));

        // when
        $requestBuilder->addPathParam('id', '1', false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/users/1', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldReplaceEncodedPathParameter(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users/{login}'));

        // when
        $requestBuilder->addPathParam('login', 'Jon+Doe', true);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/users/Jon+Doe', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldReplaceMultiplePathParameters(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users/{login}/tickets/{id}'));

        // when
        $requestBuilder->addPathParam('login', 'Jon+Doe', false);
        $requestBuilder->addPathParam('id', '1', false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com/users/Jon%2BDoe/tickets/1', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldThrowExceptionWhenPathNameDoesNotPresentInUrl(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new POST('/users/{login}'));

        // when
        $requestBuilder->addPathParam('not-matching', 'joe', false);
        CatchException::when($requestBuilder)->build();

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage("URL '/users/{login}' does not contain 'not-matching'.");
    }

    #[Test]
    public function shouldSetNewBaseUrlAndReplacePathParameters(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addPathParam('login', 'Jon+Doe', false);
        $requestBuilder->setBaseUrl('https://foo.bar/api/users/{login}');

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://foo.bar/api/users/Jon%2BDoe', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddQueryParameter(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addQueryParam('groups', 'new,old', false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com?groups=new%2Cold', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddEncodedQueryParameter(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addQueryParam('groups', 'new,old', true);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com?groups=new,old', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddArrayOfQueryParameters(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addQueryParam('groups', ['new', 'old'], false);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com?groups=new&groups=old', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddEncodedArrayOfQueryParameters(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addQueryParam('groups', ['new+users', 'old'], true);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('https://example.com?groups=new+users&groups=old', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddHeaderToRequest(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addHeader('x-custom', 'value');

        // then
        $request = $requestBuilder->build();
        Assert::thatArray($request->getHeaders())->containsKeyAndValue(['x-custom' => ['value']]);
    }

    #[Test]
    public function shouldSanitizeHeaderName(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET());

        // when
        $requestBuilder->addHeader('X-CusTom', 'value');

        // then
        $request = $requestBuilder->build();
        Assert::thatArray($request->getHeaders())->containsKeyAndValue(['x-custom' => ['value']]);
    }

    #[Test]
    public function shouldAddDefaultHeaders(): void
    {
        // when
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['X-CusTom' => 'value']);

        // then
        $request = $requestBuilder->build();
        Assert::thatArray($request->getHeaders())->containsKeyAndValue(['x-custom' => ['value']]);
    }

    #[Test]
    public function shouldAddOverrideDefaultHeaders(): void
    {
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);

        // when
        $requestBuilder->addHeader('x-age', '20');

        // then
        $request = $requestBuilder->build();
        Assert::thatArray($request->getHeaders())->containsKeyAndValue(['x-age' => ['20']]);
    }

    #[Test]
    public function shouldAddFormFields(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);

        // when
        $requestBuilder->addFormField('x-name', 'Jon+Doe', false);
        $requestBuilder->addFormField('filter', 'user+admin', true);

        // then
        $request = $requestBuilder->build();
        $this->assertSame('x-name=Jon%2BDoe&filter=user+admin', $request->getBody()->getContents());
    }

    #[Test]
    #[DataProvider('multipartProvider')]
    public function shouldAddPart(string $name, StreamInterface|string $body, array $headers, ?string $filename, array $expectedLines): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);

        // when
        $requestBuilder->addPart($name, $body, $headers, $filename);

        // then
        $request = $requestBuilder->build();
        $contents = $request->getBody()->getContents();
        foreach ($expectedLines as $expectedLine) {
            $this->assertStringContainsString($expectedLine, $contents);
        }
    }

    #[Test]
    public function shouldSetBody(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);

        // when
        $requestBuilder->setBody(Utils::streamFor('some-body'));

        // then
        $request = $requestBuilder->build();
        $this->assertSame('some-body', $request->getBody()->getContents());
    }

    #[Test]
    public function bodyShouldHasHighestPrecedenceThanForm(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);
        $requestBuilder->addFormField('field-name', 'field-value', true);

        // when
        $requestBuilder->setBody(Utils::streamFor('some-body'));

        // then
        $request = $requestBuilder->build();
        $this->assertSame('some-body', $request->getBody()->getContents());
    }

    #[Test]
    public function bodyShouldHasHighestPrecedenceThanMultipart(): void
    {
        // given
        $requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET(), ['x-age' => '10']);
        $requestBuilder->addPart('multipart', Utils::streamFor('body-from-multipart'));

        // when
        $requestBuilder->setBody(Utils::streamFor('some-body'));

        // then
        $request = $requestBuilder->build();
        $this->assertSame('some-body', $request->getBody()->getContents());
    }

    public static function multipartProvider(): array
    {
        $dir = __DIR__;
        $streamInterface = Utils::streamFor(fopen("{$dir}/../../Fixtures/file/sample-image.jpg", 'r'));
        return [
            [
                'part-string',
                'some-string-value',
                [
                    'Content-Transfer-Encoding' => '8-bit',
                ],
                null,
                [
                    'Content-Transfer-Encoding: 8-bit',
                    'Content-Disposition: form-data; name="part-string"',
                    'Content-Length: 17',
                    'some-string-value',
                ],
            ],
            [
                'part-stream-interface',
                $streamInterface,
                [
                    'Content-Transfer-Encoding' => 'binary',
                ],
                null,
                [
                    'Content-Transfer-Encoding: binary',
                    'Content-Disposition: form-data; name="part-stream-interface"; filename="sample-image.jpg"',
                    'Content-Length: 9155',
                    'Content-Type: image/jpeg',
                ],
            ],
            [
                'part-with-custom-filename',
                $streamInterface,
                [
                    'Content-Transfer-Encoding' => 'binary',
                ],
                'custom-filename.jpg',
                [
                    'Content-Transfer-Encoding: binary',
                    'Content-Disposition: form-data; name="part-with-custom-filename"; filename="custom-filename.jpg"',
                    'Content-Length: 9155',
                    'Content-Type: image/jpeg',
                ],
            ],
        ];
    }
}
