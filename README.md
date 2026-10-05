# Basic Data Transfer Object for Laravel

A small DTO base class for populating declared public properties from arrays, Laravel requests and Eloquent models.

This branch prepares **2.0.0**, an unreleased major version. See [CHANGELOG.md](CHANGELOG.md) for breaking changes.

## Requirements

| Laravel | PHP | Testbench (development) |
| --- | --- | --- |
| 12 | 8.2–8.5 | 10 |
| 13 | 8.3–8.5 | 11 |

The package requires PHP `^8.2`, Illuminate Database and HTTP `^12.0 || ^13.0`, and Carbon `^3.8.4`. CI covers the PHP versions listed above.

## Installation

Once version 2 is released:

```sh
composer require kfoobar/laravel-dto:^2.0
```

No service provider or configuration is needed. To work with this unreleased checkout, run `composer install` in the repository.

## Usage

```php
use Carbon\CarbonImmutable;
use KFoobar\Data\DataTransferObject;

class PostData extends DataTransferObject
{
    public int $id;
    public string $title;
    public string $status = 'draft';
    public array $tags = [];
    public ?CarbonImmutable $published_at = null;
}

$postData = PostData::fromArray([
    'id' => '42',
    'title' => 'Example post',
    'published_at' => '2026-10-05T12:30:00+02:00',
]);

$postData->id;           // 42
$postData->status;       // 'draft'
$postData->published_at; // CarbonImmutable instance
```

All factories return the concrete DTO subclass:

```php
$postData = new PostData(['id' => 42, 'title' => 'Example post']);
$postData = PostData::fromArray(['id' => 42, 'title' => 'Example post']);
$postData = PostData::fromRequest($request);
$postData = PostData::fromModel($post);
```

`fromRequest()` uses `$request->all()`. It does not validate or limit input to validated fields. With a Form Request, use:

```php
$postData = PostData::fromArray($request->validated());
```

`fromModel()` uses `$model->toArray()`, including Eloquent casts, visible/appended attributes and already loaded relationships. Hidden attributes stay excluded. Nested arrays are not automatically converted into other DTOs.

For a JSON response, use `response()->json($postData)`.

## Missing values and null

Only keys present in the input are assigned. Unknown keys, static properties and non-public properties are ignored.

- Missing keys preserve declared defaults, including `false`, `0`, `''`, `[]` and `null`.
- A typed property without a default remains uninitialized when its key is missing, even if its type is nullable. Reading it raises PHP's normal `Error`.
- Explicit `null` is assigned only when the property accepts it, including nullable, `mixed` and untyped properties. A non-nullable property raises `TypeError`.
- Untyped and `mixed` properties retain the supplied value without conversion.

Declare a default when a field should always be readable:

```php
class OptionalData extends DataTransferObject
{
    public ?string $note = null;
    public array $tags = [];
}
```

## Type conversion

For a single named type, `int`, `float`, `string`, `bool`, `array` and `object` use PHP's corresponding explicit cast. Nullable named types use the same conversion for non-null input. In particular, `array` now correctly converts a scalar to a one-element array.

Casts are not validation: `(bool) 'false'` is `true`, `(bool) '0'` is `false`, and `(int) 'invalid'` is `0`. Validate request data before constructing the DTO when these conversions are inappropriate.

Union types use PHP's native coercive property-assignment rules, preserving an already matching type:

```php
class IdentifierData extends DataTransferObject
{
    public int|string $id;
    public int|float $amount;
}

$data = IdentifierData::fromArray(['id' => '0042', 'amount' => '4.5']);

$data->id;     // '0042', preserving the matching string type
$data->amount; // 4.5
```

Incompatible values raise `TypeError`. Unions do not apply the package's single-type array/object casts or Carbon parsing. For example, `Carbon|string` retains a string, while `Carbon|CarbonImmutable` requires an instance of one of those classes. Intersection and DNF types also use PHP's native assignment checks.

Other class and interface types require compatible objects; the package does not instantiate arbitrary classes. Inherited public properties and uninitialized public readonly properties are supported.

Conversions run during construction and factory calls. Later direct assignments to public properties follow PHP's normal rules and do not run DTO conversions.

## Carbon dates

Declare `Carbon\Carbon`, `Carbon\CarbonImmutable`, or a subclass such as `Illuminate\Support\Carbon` to enable date conversion. Nullable declarations such as `?CarbonImmutable` are supported.

- Strings are parsed with the declared Carbon class's `parse()` method.
- `DateTimeInterface` instances are converted with `instance()`, retaining their timestamp, microseconds and timezone.
- An instance already matching the declared class is preserved.
- Nullable `null` stays `null`; it is never parsed as the current time.
- Invalid date strings propagate Carbon's parsing exception. Other incompatible input raises `TypeError`.

Use an instance for properties typed as `CarbonInterface` or as a union of date classes.

## Upgrading from 1.x

1. Upgrade to PHP 8.2+ and Laravel 12, or PHP 8.3+ and Laravel 13. Carbon 2 is no longer supported.
2. Import `KFoobar\Data\DataTransferObject`. The previous README's `KFoobar\LaravelData\Objects\DataTransferObject` namespace was incorrect.
3. Remove `new` before static factory calls: use `PostData::fromArray(...)`.
4. Add explicit defaults to fields that must remain readable when omitted. Missing typed properties no longer receive an implicit `null`, and defaults are preserved.
5. Review code relying on the broken untyped/array behavior or magic access to protected/private properties. Magic access now respects the public instance-property boundary; unknown writes are still ignored and unknown reads return `null`.
6. Update overridden methods to match the base signatures. Factories return `static`; `setPropertyType()` now accepts `?ReflectionType` and `mixed`, and returns `mixed`.

## Development

```sh
composer install
composer validate --strict
composer test
```

The PHPUnit 11 / Orchestra Testbench suite covers hydration, defaults, nullability, scalar casts, unions, other PHP property types, Carbon, requests, Form Request validation and persisted Eloquent models. Integration tests use an isolated SQLite `:memory:` database.

To resolve a specific Laravel version locally:

```sh
composer update --with 'laravel/framework:^12.0' --with 'orchestra/testbench:^10.0'
composer test
```

Use Laravel `^13.0` with Testbench `^11.0` on PHP 8.3+ for the other supported major. GitHub Actions resolves dependencies and runs the full suite across all seven supported PHP/Laravel combinations. The library's `composer.lock` is intentionally not committed.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
