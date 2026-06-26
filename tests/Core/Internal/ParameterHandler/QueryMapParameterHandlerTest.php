<?php

declare(strict_types=1);

namespace Retrofit\Tests\Core\Internal\ParameterHandler;

use GuzzleHttp\Psr7\Uri;
use Ouzo\Tests\CatchException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Retrofit\Core\Attribute\GET;
use Retrofit\Core\Internal\BuiltInConverters;
use Retrofit\Core\Internal\ParameterHandler\QueryMapParameterHandler;
use Retrofit\Core\Internal\RequestBuilder;
use Retrofit\Tests\Fixtures\Api\MockMethod;
use RuntimeException;

class QueryMapParameterHandlerTest extends TestCase
{
    private RequestBuilder $requestBuilder;

    private ReflectionMethod $reflectionMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestBuilder = new RequestBuilder(new Uri('https://example.com'), new GET('/users'));
        $this->reflectionMethod = new ReflectionMethod(MockMethod::class, 'mockMethod');
    }

    #[Test]
    public function shouldSkipWhenValueIsNull(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        $queryMapParameterHandler->apply($this->requestBuilder, null);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame('https://example.com/users', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldThrowExceptionWhenValueIsNotArray(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($queryMapParameterHandler)->apply($this->requestBuilder, 'some-string-value');

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. Parameter should be an array.');
    }

    #[Test]
    public function shouldThrowExceptionWhenKeyInArrayIsNull(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($queryMapParameterHandler)->apply($this->requestBuilder, [null => 'value']);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage('Method MockMethod::mockMethod() parameter #1. Query map contained empty key.');
    }

    #[Test]
    public function shouldThrowExceptionWhenValueInArrayIsNull(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        CatchException::when($queryMapParameterHandler)->apply($this->requestBuilder, ['key' => null]);

        // then
        CatchException::assertThat()
            ->isInstanceOf(RuntimeException::class)
            ->hasMessage("Method MockMethod::mockMethod() parameter #1. Query map contained null value for key 'key'.");
    }

    #[Test]
    public function shouldAddEncodedQueries(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        $queryMapParameterHandler->apply($this->requestBuilder, ['name' => 'jon+doe', 'age' => 34, 'registered' => false]);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame('https://example.com/users?name=jon%2Bdoe&age=34&registered=false', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddNotEncodedQueries(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(true, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        $queryMapParameterHandler->apply($this->requestBuilder, ['name' => 'jon+doe', 'age' => 34, 'registered' => false]);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame('https://example.com/users?name=jon+doe&age=34&registered=false', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddSingleElementArrayValueAsRepeatedQueryParam(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        $queryMapParameterHandler->apply($this->requestBuilder, ['ids' => [671]]);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame('https://example.com/users?ids=671', $request->getUri()->__toString());
    }

    #[Test]
    public function shouldAddMultipleElementArrayValueAsRepeatedQueryParams(): void
    {
        // given
        $queryMapParameterHandler = new QueryMapParameterHandler(false, BuiltInConverters::ToStringConverter(), $this->reflectionMethod, 0);

        // when
        $queryMapParameterHandler->apply($this->requestBuilder, ['ids' => [671, 672, 673]]);

        // then
        $request = $this->requestBuilder->build();
        $this->assertSame('https://example.com/users?ids=671&ids=672&ids=673', $request->getUri()->__toString());
    }
}
