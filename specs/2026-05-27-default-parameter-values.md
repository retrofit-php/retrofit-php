# Default parameter values in generated service methods

**Status:** PENDING. Implemented on `main` working tree; flip to DONE when the PR merges

Allow a service method parameter to declare a PHP default value and have that
default applied when the caller omits the argument:

```php
interface Service
{
    #[GET('/v1/personFields?limit=1000')]
    #[ResponseBody('void')]
    public function fields(#[Query('offset')] int $offset = 100): Call;
}

$service->fields();    // GET /v1/personFields?limit=1000&offset=100
$service->fields(50);  // GET /v1/personFields?limit=1000&offset=50
```

## Context

The proxy generator already copies default values into the generated method
signature, but the generated method body discards them at runtime.

`DefaultProxyFactory::appendMethodParameters()` faithfully reproduces each
parameter, including its default and its nullability:

- `src/Core/Internal/Proxy/DefaultProxyFactory.php:255-257` — `if ($parameter->isDefaultValueAvailable()) { $paramBuilder->setDefault(...); }`
- `src/Core/Internal/Proxy/DefaultProxyFactory.php:270-275` — rebuilds
  `?T` via `NullableType` when `$reflectionType->allowsNull()`.

So the generated proxy method *signature* is correct (verified today by
`tests/Core/Internal/Proxy/DefaultProxyFactoryTest.php:219-234`,
`shouldHandleParameterWithDefaultValue`, against fixture
`tests/Fixtures/Api/VariousParameters.php:15-17`).

The problem is the generated method **body**. Every method is emitted as a
single statement built by `createServiceMethodInvokeReturnStmt()`:

- `src/Core/Internal/Proxy/DefaultProxyFactory.php:206-223` — produces
  `...->create(<service>, __FUNCTION__)->invoke(func_get_args())`
- `src/Core/Internal/Proxy/DefaultProxyFactory.php:168` — this statement is
  built **once per service** and the same AST node is reused for every method
  (`appendMethods()`), so it cannot depend on a method's parameters.

`func_get_args()` returns only the arguments **actually passed** by the caller —
it never includes defaulted parameters. The args then flow positionally into:

- `src/Core/Internal/RequestFactory.php:35-37` —
  `$parameterHandler->apply($requestBuilder, $args[$i])`

So calling `fields()` with no argument yields `func_get_args() === []`, then
`$args[0]` is an undefined key (PHP warning + `null`), and
`QueryParameterHandler::apply()` short-circuits on `is_null($value)` and drops
the query param entirely:

- `src/Core/Internal/ParameterHandler/QueryParameterHandler.php:30-38`

Net current behavior for `fields()`: `offset` is silently omitted **and** an
`Undefined array key 0` warning is emitted (which `failOnWarning` would flag in
tests). The declared default `100` is never used.

Positional alignment between `$args[$i]` and the parameter at position `i` is
preserved by `ServiceMethodFactory::getParameterHandlers()` — handlers are
priority-sorted for processing but re-keyed by original position and
`ksort`+`array_values`'d back into declaration order
(`src/Core/Internal/ServiceMethodFactory.php:242,259-261`). This invariant
matters for the chosen fix.

## Proposed changes

**Stop using `func_get_args()`; emit an explicit argument array built from the
method's own parameter variables.** Because the generated proxy method already
declares the defaults (and nullability) in its signature, PHP binds the default
into the parameter variable when the caller omits it. Reading those bound
variables captures defaults for free — the engine, `RequestFactory`, and every
`ParameterHandler` stay untouched.

Generated body changes from:

```php
public function fields(#[Query('offset')] int $offset = 100): Call {
    return $this->serviceMethodFactory->create('\\...\\Service', __FUNCTION__)
        ->invoke(func_get_args());
}
```

to:

```php
public function fields(#[Query('offset')] int $offset = 100): Call {
    return $this->serviceMethodFactory->create('\\...\\Service', __FUNCTION__)
        ->invoke([$offset]);
}
```

### Concrete edits (all in `src/Core/Internal/Proxy/DefaultProxyFactory.php`)

1. **Build the invoke statement per method, not per service.** In
   `appendMethods()` (`:166-193`), drop the hoisted
   `$serviceMethodInvokeReturnStmt` at `:168` and instead call
   `createServiceMethodInvokeReturnStmt($service, $method)` inside the loop, so
   the args array reflects the current method's parameters.

2. **Rewrite `createServiceMethodInvokeReturnStmt()` (`:206-223`)** to accept
   the `ReflectionMethod` and replace the `func_get_args()` `FuncCall` with a
   `PhpParser\Node\Expr\Array_` whose items are one
   `PhpParser\Node\ArrayItem(new Variable($param->name))` per parameter, in
   declaration order. For a variadic parameter, set the item's `unpack` flag so
   it spreads:
   `new ArrayItem(new Variable($name), null, false, [], unpack: true)`
   (`ArrayItem` ctor in php-parser 5.7:
   `(Expr $value, ?Expr $key = null, bool $byRef = false, array $attributes = [], bool $unpack = false)`).

3. **Imports:** remove `use PhpParser\Node\Expr\FuncCall;` (`:14`, its only use
   is the call being replaced) and add
   `use PhpParser\Node\Expr\Array_;` + `use PhpParser\Node\ArrayItem;`.

4. **Update the class docblock example** (`:47-66`) which currently shows
   `invoke(func_get_args())`, so the documentation matches the emitted code.

### Why this approach

- **Defaults resolve where PHP already resolves them** — at parameter binding —
  rather than re-deriving them via reflection in a runtime hot path
  (`ServiceMethodFactory::create()` runs per call). No new reflection, no engine
  changes.
- **Positional parity is exact.** `[$p0, $p1, ...$variadic]` reproduces what
  `func_get_args()` returned (including the flattening of a trailing variadic),
  so the `$args[$i] ↔ position i` invariant at `RequestFactory.php:36` holds.
- **The args array is now always full-length** — one entry per parameter,
  each either the passed value or the bound default. This is what removes the
  `Undefined array key` warnings and makes defaults (and nullable defaults)
  reliable.
- **Bonus: named arguments start working.** `$service->fields(offset: 50)` binds
  `$offset = 50`, which the explicit array picks up — whereas `func_get_args()`
  handling of named args was unreliable. Not the goal, but a free correctness win
  worth a test.

### Nullable parameters

Nullable is fully covered, and is actually the case the current code breaks
worst. PHP 8.4 deprecates implicit nullable (`int $x = null`), so the idiom is
the explicit `?T $x = null`; `appendMethodParameters()` already regenerates
`?int $offset = null` correctly (`:270-275` + `:255-257`).

Handlers already have well-defined null semantics, unchanged by this fix:

- **Skip → param omitted** (`return` on null): `Query`, `QueryMap`,
  `QueryName`, `Field`, `FieldMap`, `Header`, `HeaderMap`, `Part`, `PartMap`.
- **Throw → value required**: `Body` ("Body was null"), `Url`, `Path`
  ("value must not be null") — see e.g.
  `src/Core/Internal/ParameterHandler/PathParameterHandler.php:31-35`.

What changes is only *which value the handler receives when the arg is omitted*
— the declared default instead of an undefined-key `null`:

| call | signature | today (`func_get_args`) | after (`[$offset]`) |
|---|---|---|---|
| `fields()` | `?int $offset = null` | `[]` → undefined → `null` **+ warning** → omitted | `[null]` → omitted, no warning |
| `fields()` | `?int $offset = 100` | `[]` → undefined → `null` → omitted **(loses 100!)** | `[100]` → `offset=100` |
| `fields(null)` | `?int $offset = 100` | `[null]` → omitted (explicit clear) | `[null]` → omitted (explicit clear) |
| `fields(50)` | `?int $offset = 100` | `[50]` → `offset=50` | `[50]` → `offset=50` |

So `?T $x = null` becomes a proper "optional, omitted when absent" parameter,
and a nullable parameter with a non-null default can be explicitly cleared by
passing `null`. A nullable parameter bound to a required handler (`Path`/`Url`/
`Body`) still throws on `null`, exactly as today.

### Variadic semantics are preserved, not fixed

Today a variadic method has exactly one `ParameterHandler` (at the variadic
position), so `RequestFactory` only consumes `$args[0]` and ignores extra
variadic values. `invoke([...$ids])` reproduces this exactly — same pre-existing
limitation, no regression. Improving variadic fan-out is out of scope here.

### Rejected alternative

Keep `func_get_args()` and back-fill missing trailing positions from
`ReflectionParameter::getDefaultValue()` inside `RequestFactory`/`ServiceMethod`.
Rejected: it re-implements PHP's own default resolution, threads default values
through another layer, adds reflection to the per-call path, and still wouldn't
fix named arguments. The proxy already carries the defaults in its signature —
reading the bound variables is strictly simpler.

## Testing and validation

The existing runtime tests in `ServiceMethodFactoryTest` call `invoke([...])`
**directly** with an explicit array, so they bypass the generated body and
cannot catch this defect. The new behavior must be exercised **through the
generated proxy** (call the method, then read `->request()`), which is how
`HttpClientCall` exposes the built `RequestInterface` without sending.

1. **Fixtures** — add to `tests/Fixtures/Api/VariousParameters.php`:
   - a Query-with-default method mirroring the user scenario:
     ```php
     #[GET('/v1/personFields?limit=1000')]
     #[ResponseBody('void')]
     public function defaultQueryValue(#[Query('offset')] int $offset = 100): Call;
     ```
   - a nullable Query-with-default method:
     ```php
     #[GET('/v1/personFields?limit=1000')]
     #[ResponseBody('void')]
     public function nullableDefaultQueryValue(#[Query('offset')] ?int $offset = 100): Call;
     ```

2. **Runtime default applied (the core behavior)** — in
   `DefaultProxyFactoryTest`, build the proxy and assert:
   - `$impl->defaultQueryValue()->request()->getUri()` →
     `https://.../v1/personFields?limit=1000&offset=100`
   - `$impl->defaultQueryValue(50)->request()->getUri()` →
     `https://.../v1/personFields?limit=1000&offset=50`
   - And via the existing `defaultValue(#[Path('id')] int $id = 100)` fixture:
     `$impl->defaultValue()->request()->getUri()` ends in `/users/100`;
     `$impl->defaultValue(5)` ends in `/users/5`.

3. **Nullable matrix** — against `nullableDefaultQueryValue`:
   - `$impl->nullableDefaultQueryValue()` → `...&offset=100` (non-null default applied)
   - `$impl->nullableDefaultQueryValue(null)` → `...?limit=1000` (offset omitted)
   - `$impl->nullableDefaultQueryValue(7)` → `...&offset=7`

4. **No-warning guarantee** — since PHPUnit runs with `failOnWarning`, a passing
   `$impl->defaultQueryValue()` / `nullableDefaultQueryValue()` call is itself
   proof the `Undefined array key 0` warning is gone.

5. **Regression parity** — call a by-reference method through the proxy
   (`shouldForwardByReferenceParameterValue`) to confirm the explicit-array body
   matches prior `func_get_args()` behavior. The variadic `...$ids` unpack is
   covered implicitly: the whole proxy class is `eval()`'d in one shot, so a
   malformed variadic body would break every `VariousParameters` test. A
   dedicated variadic *value* assertion is intentionally omitted — fan-out
   beyond the first variadic value is a separate pre-existing limitation, not
   something to codify here. Existing signature-level tests
   (`shouldHandleParameterWithDefaultValue`, `...PassedByReference`,
   `...Variadic`) stay unchanged and still pass.

6. **Named-argument bonus** — optional: assert `$impl->defaultQueryValue(offset: 7)`
   produces `...&offset=7`.

7. **Full suite + static analysis**, run via Docker (no local PHP toolchain):

   ```bash
   docker run --rm -v "$PWD:/app" -w /app php:8.4-cli \
     ./vendor/bin/phpunit --configuration ./phpunit.xml.dist
   docker run --rm -v "$PWD:/app" -w /app php:8.4-cli \
     ./vendor-bin/phpstan/vendor/bin/phpstan \
     --configuration=./phpstan.neon.dist --autoload-file=./vendor/autoload.php analyse
   ```

## Parallelization

Not warranted. This is a single-file core change (`DefaultProxyFactory.php`)
plus one fixture and a handful of assertions in one test class — tightly coupled
and well under a day. Splitting it across sub-agents would add coordination
overhead with no wall-clock benefit.
