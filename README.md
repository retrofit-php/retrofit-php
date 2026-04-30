# Retrofit PHP

A type-safe HTTP client for PHP.

This is a PHP port of [square/retrofit](https://github.com/square/retrofit).

## Installation

Retrofit requires PHP >=8.4

```
composer require retrofit-php/retrofit-php-core
```

Please make sure you also install an HTTP client implementation.

HTTP clients:

* [Guzzle7](https://github.com/retrofit-php/retrofit-php-client-guzzle7)

```
composer require retrofit-php/retrofit-php-client-guzzle7
```

To handle more advanced request and responses install a converter.

Converters:

* [Symfony Serializer](https://github.com/retrofit-php/retrofit-php-converter-symfony-serializer)

```
composer require retrofit-php/retrofit-php-converter-symfony-serializer
```

## Introduction

Retrofit turns your PHP interface into an HTTP API.

```php
interface GitHubService
{
    #[GET('/users/{user}/repos')]
    #[ResponseBody('array', 'Repo')]
    public function listRepos(#[Path('user')] string $user): Call;
}
```

The `Retrofit` class generates an implementation of the `GitHubService` interface.

```php
// Symfony's Serializer implements both SerializerInterface and DecoderInterface,
// so the same instance can be passed for both arguments.
$serializer = new Serializer();

$retrofit = Retrofit::Builder()
    ->baseUrl('https://api.github.com')
    ->client(new Guzzle7HttpClient(new Client()))
    ->addConverterFactory(new SymfonySerializerConverterFactory($serializer, $serializer))
    ->build();

$service = $retrofit->create(GitHubService::class);
```

Each `Call` from the created `GitHubService` can make a synchronous or asynchronous HTTP request to the remote
webserver.

```php
$call = $service->listRepos('octocat');

// synchronous request
$response = $call->execute();

// asynchronous request
$callback = new class () implements Callback {
    public function onResponse(Call $call, Response $response): void
    {
    }

    public function onFailure(Call $call, Throwable $t): void
    {
    }
};
$call->enqueue($callback);
$call->wait();
```

## Attributes API

Attributes on the interface methods and its parameters indicate how a request will be handled.

### Request method

Every method must have an HTTP attribute that provides the request method and path. There are eight built-in
attributes: `HTTP`, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` and `HEAD`. The path of the resource is specified
in the attribute.

```php
#[GET('/users/list')]
```

You can also specify query parameters in the URL.

```php
#[GET('/users/list?sort=desc')]
```

For non-standard HTTP verbs (or when you need to send a body with a verb that normally has none, e.g. `DELETE`) use the
generic `#[HTTP]` attribute.

```php
#[HTTP('CUSTOM', '/custom/endpoint')]
public function customEndpoint(): Call;

#[HTTP('DELETE', '/items/{id}', hasBody: true)]
public function remove(#[Path('id')] int $id, #[Body] DeletePayload $payload): Call;
```

### URL manipulation

A request URL can be updated dynamically using replacement blocks and parameters on the method. A replacement block is
an alphanumeric string surrounded by `{` and `}`. A corresponding parameter must have attribute `#[Path]` using the same
string.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId): Call;
```

If the full URL is only known at runtime, omit the path from the HTTP attribute and pass it as a `#[Url]` parameter. The
value is resolved against `baseUrl` (absolute URLs override the base).

```php
#[GET]
#[ResponseBody('array', 'User')]
public function listUsers(#[Url] string $url): Call;
```

### Query parameters

Single query parameters can be added with `#[Query]`.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId, #[Query('sort')] string $sort): Call;
```

For complex query parameter combinations a map can be used.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId, #[QueryMap] array $options): Call;
```

For valueless query parameters (the parameter name itself is the value) use `#[QueryName]`. Variadic parameters yield
one query item per non-null value.

```php
#[GET('/friends')]
public function friends(#[QueryName] string ...$filter): Call;
// $service->friends('contains(Bob)', 'age(42)') -> /friends?contains(Bob)&age(42)
```

`#[Query]`, `#[QueryMap]` and `#[QueryName]` URL-encode keys and values by default. Pass `encoded: true` to disable that.

### Request body

An object can be specified for use as an HTTP request body with the `#[Body]` attribute.

```php
#[POST('/users/new')]
#[ResponseBody('User')]
public function createUser(#[Body] User $user): Call;
```

The object will be serialized using a `ConverterFactory` registered on the `Retrofit` instance. If no factory matches,
the built-in converter (`json_encode`) is used as a fallback. Body parameters may not be `null`.

### Form-encoded and Multipart

Methods can also be declared to send form-encoded and Multipart data.

Form-encoded data is sent when `#[FormUrlEncoded]` is present on the method. Each key-value pair uses the `#[Field]`
attribute with the field name; the parameter provides the value.

```php
#[FormUrlEncoded]
#[POST('/user/edit')]
#[ResponseBody('User')]
public function updateUser(#[Field('first_name')] string $first, #[Field('last_name')] string $last): Call;
```

For multiple fields supplied at once, use `#[FieldMap]`.

```php
#[FormUrlEncoded]
#[POST('/things')]
public function things(#[FieldMap] array $fields): Call;
// $service->things(['foo' => 'bar', 'kit' => 'kat']) -> body: foo=bar&kit=kat
```

Multipart requests are used when `#[Multipart]` is present on the method. Parts are declared using the `#[Part]`
attribute.

```php
#[Multipart]
#[PUT('/user/photo')]
#[ResponseBody('User')]
public function updateUser(#[Part] PartInterface $photo, #[Part('description')] string $description): Call;
```

Multipart parts use one of `Retrofit`'s converters, or they can implement `PartInterface` to handle their own
serialization (`getName()`, `getBody()`, `getFilename()`, `getHeaders()`). When using `PartInterface`, omit the part
name in the attribute — it is read from `getName()`.

For multiple parts supplied at once, use `#[PartMap]`.

```php
#[Multipart]
#[POST('/upload')]
public function upload(#[Part('file')] PartInterface $file, #[PartMap] array $params): Call;
```

Both `#[Part]` and `#[PartMap]` accept an optional `MimeEncoding` (default `BINARY`).

### Header manipulation

You can set static headers for a method using the `#[Headers]` attribute.

```php
#[Headers(['Cache-Control' => 'max-age=640000'])]
#[GET('/widget/list')]
#[ResponseBody('array', 'Widget')]
public function widgetList(): Call;
```

```php
#[Headers([
    'Accept' => 'application/vnd.github.v3.full+json',
    'User-Agent' => 'Retrofit-Sample-App',
])]
#[GET('/users/{username}')]
#[ResponseBody('User')]
public function getUser(#[Path('username')] string $username): Call;
```

A request header can be updated dynamically using the `#[Header]` attribute. A corresponding parameter must be provided
to the `#[Header]`. If the value is `null`, the header will be omitted.

```php
#[GET('/user')]
#[ResponseBody('User')]
public function getUser(#[Header('Authorization')] string $authorization): Call;
```

Similar to query parameters, for complex header combinations, a map can be used.

```php
#[GET('/user')]
#[ResponseBody('User')]
public function getUser(#[HeaderMap] array $headers): Call;
```

## Response handling

### `#[ResponseBody]` and `#[ErrorBody]`

`#[ResponseBody]` declares the type that a successful (`2xx`) body should be deserialized into. `#[ErrorBody]` does the
same for non-2xx responses. Both accept the same shape: a raw type and an optional parametrized type used only when the
raw type is `array`.

```php
#[GET('/users/{id}')]
#[ResponseBody('User')]                       // -> User
#[ErrorBody('ApiError')]                      // -> ApiError on 4xx/5xx
public function getUser(#[Path('id')] int $id): Call;

#[GET('/users')]
#[ResponseBody('array', 'User')]              // -> User[]
public function listUsers(): Call;

#[GET('/count')]
#[ResponseBody('int')]                        // -> int
public function count(): Call;
```

If a method has no `#[ResponseBody]`, the body is returned as the converter's default for the raw stream (typically
`StreamInterface` from the built-in converters).

### `#[Streaming]`

For large or open-ended responses, mark the method with `#[Streaming]` to skip body conversion entirely and receive the
raw `StreamInterface`.

```php
#[Streaming]
#[GET('/large/file')]
public function download(): Call;
```

### `Call`, `Response`, and `Callback`

`Call` represents a single HTTP exchange and exposes both synchronous and asynchronous APIs:

```php
interface Call
{
    public function execute(): Response;                 // sync
    public function enqueue(Callback $callback): Call;   // async, queue for dispatch
    public function wait(): void;                        // dispatch all queued requests
    public function request(): RequestInterface;         // raw PSR-7 request
}
```

`Response` wraps the PSR-7 response and the deserialized body:

```php
$response = $call->execute();

$response->isSuccessful();   // bool — code in [200, 300)
$response->code();           // int
$response->message();        // string
$response->headers();        // array<string, string[]>
$response->body();           // mixed — deserialized via #[ResponseBody]
$response->errorBody();      // mixed — deserialized via #[ErrorBody], for non-2xx
$response->raw();            // PSR-7 ResponseInterface
```

`Callback` receives async results. `onResponse` fires for any HTTP response (success or not — check
`Response::isSuccessful()`); `onFailure` fires for transport-level errors.

```php
$call->enqueue(new class () implements Callback {
    public function onResponse(Call $call, Response $response): void { /* ... */ }
    public function onFailure(Call $call, Throwable $t): void { /* ... */ }
});

$call->wait(); // executes all enqueued requests
```

## HTTP clients

Retrofit talks to the network through the `Retrofit\Core\HttpClient` interface (PSR-7 in, PSR-7 out, plus an async
`sendAsync` / `wait` pair). Any implementation can be plugged in.

### Guzzle 7

`Guzzle7HttpClient` wraps `guzzlehttp/guzzle` 7. The optional `concurrency` argument (default `5`) caps the number of
simultaneous async requests dispatched by `wait()`.

```php
use GuzzleHttp\Client;
use Retrofit\Client\Guzzle7\Guzzle7HttpClient;

$retrofit = Retrofit::Builder()
    ->baseUrl('https://api.example.com')
    ->client(new Guzzle7HttpClient(new Client(), concurrency: 10))
    ->build();
```

`RequestException`s carrying a response (i.e. server returned an HTTP error) are unwrapped so the response — including
its non-2xx status — flows through to your `#[ErrorBody]` converter. Transport errors without a response are rethrown.

## Converters

A `ConverterFactory` produces three kinds of converters from a `Type`:

| Method | Used for |
|---|---|
| `requestBodyConverter()` | `#[Body]`, `#[Part]`, `#[PartMap]` values written to the request body |
| `responseBodyConverter()` | `#[ResponseBody]` and `#[ErrorBody]` deserialization |
| `stringConverter()` | `#[Field]`, `#[FieldMap]`, `#[Header]`, `#[HeaderMap]`, `#[Path]`, `#[Query]`, `#[QueryMap]` |

Factories are tried in registration order; the first non-`null` converter wins. The internal `BuiltInConverterFactory`
is appended last and handles common shapes out of the box: `json_encode` for request bodies, `StreamInterface`
pass-through for streaming, `stdClass` / `array` / scalar deserialization via `json_decode`, and `(string)`-style
coercion for path/query/header values.

### Symfony Serializer

`SymfonySerializerConverterFactory` delegates to `symfony/serializer`. By default it works with JSON; XML and YAML are
also supported via the `SymfonySerializerFormat` enum.

```php
use Retrofit\Converter\SymfonySerializer\SymfonySerializerConverterFactory;
use Retrofit\Converter\SymfonySerializer\SymfonySerializerFormat;

$retrofit = Retrofit::Builder()
    ->baseUrl('https://api.example.com')
    ->client(new Guzzle7HttpClient(new Client()))
    ->addConverterFactory(new SymfonySerializerConverterFactory(
        $serializer,
        $serializer,
        SymfonySerializerFormat::XML,
    ))
    ->build();
```

`Serializer` implements both `SerializerInterface` and `DecoderInterface`, so the same instance can be passed twice.
This factory does not provide a `stringConverter` — string coercion falls through to the built-in.

### Custom converters

Implement `ConverterFactory` and return a converter for the types you handle, or `null` to defer to the next factory in
the chain. Register it with `RetrofitBuilder::addConverterFactory()`.

## License

MIT — see [LICENSE](LICENSE).
