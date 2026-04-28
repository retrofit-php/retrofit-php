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
$retrofit = Retrofit::Builder()
    ->baseUrl('https://api.github.com')
    ->client(new Guzzle7HttpClient(new Client()))
    ->addConverterFactory(new SymfonySerializerConverterFactory(new Serializer()))
    ->build();

$service = $retrofit->create(GitHubService::class);
```

Each `Call` from the created `GitHubService` can make a synchronous or asynchronous HTTP request to the remote
webserver.

```php
$call = $service->listRepos('octocat);

// synchronous request
$call->execute();

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

### URL manipulation

A request URL can be updated dynamically using replacement blocks and parameters on the method. A replacement block is
an alphanumeric string surrounded by `{` and `}`. A corresponding parameter must hava attribute `#[Path]` using the same
string.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId): Call;
```

Query parameters can also be added.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId, #[Query('sort')] string $sort): Call;
```

For complex query parameter combinations an array map can be used.

```php
#[GET('/group/{id}/users')]
#[ResponseBody('array', 'User')]
public function groupList(#[Path('id')] int $groupId, #[QueryMap] string $options): Call;
```

### Request body

An object can be specified for use as an HTTP request body with the `#[Body]` attribute.

```php
#[POST('/users/new')]
#[ResponseBody('User')]
public function createUser(#[Body] User $user): Call;
```

The object will also be converted using a converter specified on the `Retrofit` instance. If no converter is added,
the build-in will be used.

### Form-encoded and Multipart

Methods can also be declared to send form-encoded and Multipart data.

Form-encoded data is sent when `#[FormUrlEncoded]` is present on the method. Each key-value pair has attribute with
`#[Field]` containing the name and the object providing the value.

```php
#[FormUrlEncoded]
#[POST('/user/edit')]
#[ResponseBody('User')]
public function updateUser(#[Field('first_name')] string $first, #[Field('last_name')] string $last): Call;
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
serialization.

### Header manipulation

You can set static headers for a method using the `#[Headers]` attribute.

```php
#[Headers(['Cache-Control' => 'max-age=640000')]
#[GET('/widget/list')]
#[ResponseBody('array', 'Widget')]
public function widgetList(): Call;
```

```php
#[Headers([
    'Accept' => 'application/vnd.github.v3.full+json',
    'User-Agent' => 'Retrofit-Sample-App'
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

Similar to query parameters, for complex header combinations, an array map can be used.

```php
#[GET('/user')]
#[ResponseBody('User')]
public function getUser(#[HeaderMap] array $headers): Call;
```
