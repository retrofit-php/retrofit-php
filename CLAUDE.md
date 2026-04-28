# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A PHP port of [square/retrofit](https://github.com/square/retrofit) — turns a PHP interface decorated with attributes (`#[GET]`, `#[Path]`, `#[Body]`, …) into an HTTP client. Public entry points are `Retrofit::Builder()` → `Retrofit::create($interface)`.

Requires PHP **>= 8.4**.

## Commands

PHPUnit, PHPStan, and PHP-CS-Fixer live in separate composer roots (vendor-bin) so their deps don't leak into the main package.

```bash
# Initial setup
composer install
composer --working-dir=vendor-bin/phpstan install
composer --working-dir=vendor-bin/php-cs-fixer install

# Tests
./vendor/bin/phpunit --configuration ./phpunit.xml.dist
./vendor/bin/phpunit --filter <TestName>          # single test
./vendor/bin/phpunit tests/Core/RetrofitTest.php  # single file

# Static analysis (PHPStan level: max)
./vendor-bin/phpstan/vendor/bin/phpstan --configuration=./phpstan.neon.dist --autoload-file=./vendor/autoload.php analyse

# Lint / autofix
./vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer fix
```

PHPUnit runs with `failOnDeprecation`, `failOnRisky`, `failOnWarning` and random execution order — flaky or order-dependent tests will be caught.

## Repository layout

This is a **monorepo published as three subsplits** (see `config.subsplit-publish.json`). Each subdirectory has its own `composer.json` with its own dependency set; do not pull cross-package deps that aren't already declared.

- `src/Core` → `retrofit-php/retrofit-php-core` — the engine. Public API + internals.
- `src/Client/Guzzle7` → `retrofit-php/retrofit-php-client-guzzle7` — `HttpClient` impl using Guzzle 7. Depends only on `guzzlehttp/guzzle`, NOT on Core (loose coupling via interface).
- `src/Converter/SymfonySerializer` → `retrofit-php/retrofit-php-converter-symfony-serializer` — `ConverterFactory` impl using `symfony/serializer`.

Top-level `composer.json` aggregates everything for development; downstream users install only the splits they need.

## Core architecture

The flow when a user calls a method on a generated service:

1. **`RetrofitBuilder` → `Retrofit`**: user supplies `HttpClient`, `baseUrl`, optional `ConverterFactory`s. The built-in `BuiltInConverterFactory` is always appended last as a fallback.
2. **`Retrofit::create($interface)`**: validates the target is an interface, then delegates to `DefaultProxyFactory`.
3. **`DefaultProxyFactory` (src/Core/Internal/Proxy)**: this is the magic. It uses `nikic/php-parser` to **build a class AST** that implements the target interface, pretty-prints it to source, and `eval()`s it into existence under namespace `Retrofit\Proxy\<original-namespace>`. Every method body is a single `return $this->serviceMethodFactory->create(<service>, __FUNCTION__)->invoke(func_get_args());`. Method attributes and parameter attributes are copied verbatim so the runtime can still introspect them via reflection.
4. **`ServiceMethodFactory` (src/Core/Internal)**: per call, reflects the proxy method, extracts the single `HttpRequest` attribute (`#[GET]`, `#[POST]`, …), the optional encoding (`#[FormUrlEncoded]` | `#[Multipart]`), `#[Headers]`, `#[ResponseBody]`/`#[ErrorBody]`/`#[Streaming]`, and builds one `ParameterHandler` per parameter via `ParameterHandlerFactoryProvider`. Returns an anonymous `ServiceMethod` whose `invoke()` builds the `RequestInterface` and wraps it in `HttpClientCall`.
5. **`HttpClientCall`**: implements `Call`. `execute()` sends sync; `enqueue()` sends async; on response, applies `responseBodyConverter` for 2xx or `errorBodyConverter` otherwise.

Hot paths use reflection but `ServiceMethodFactory::create()` is invoked **per method call**, not cached — keep that in mind when changing it.

## Adding a new attribute

The codebase has a tight pattern. To add a new parameter attribute (e.g. `#[Cookie]`):

1. Define the attribute class in `src/Core/Attribute/` implementing `ParameterAttribute`.
2. Implement a `ParameterHandler` in `src/Core/Internal/ParameterHandler/` — it gets a `RequestBuilder` and the runtime arg, mutates the builder.
3. Implement a factory in `src/Core/Internal/ParameterHandler/Factory/` extending `AbstractParameterHandlerFactory`.
4. Register the factory in `ParameterHandlerFactoryProvider`.
5. If the attribute affects parameter ordering, update `Utils::sortParameterAttributesByPriorities()`.

Method-level attributes (`#[FormUrlEncoded]`, `#[Multipart]`, `#[Headers]`, `#[Streaming]`, `#[ResponseBody]`, `#[ErrorBody]`) are read directly in `ServiceMethodFactory`.

HTTP method attributes (`HTTP`, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD`) all extend `HttpRequest`. Exactly one is required per service method.

## Adding a converter

Implement `ConverterFactory` returning `RequestBodyConverter` / `ResponseBodyConverter` / `StringConverter` for given `Type`s, or `null` to defer to the next factory. Order matters: `ConverterProvider` walks factories in registration order and falls back to `BuiltInConverters`.

## Tests

- `tests/Core/` mirrors `src/Core/` structure.
- `tests/Fixtures/Api/` contains interface fixtures used to drive proxy generation in tests — when you add new attribute behavior, add a fixture interface here rather than mocking reflection.
- `tests/Fixtures/Model/`, `tests/Fixtures/Converter/`, `tests/Fixtures/file/` hold sample DTOs / converters / files used by request/response tests.
- `WithFixtureFile` trait loads files from `tests/Fixtures/file/`.

## CI

GitHub Actions: `ci.yml` (PHPUnit), `static.yml` (PHPStan), `php-cs-fixer.yml` (auto-commits fixes on PRs). Currently runs on PHP 8.4 only. `publish-subsplits.yml` pushes the three subdirectories to their standalone repos on release.
