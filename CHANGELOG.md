# Changelog

## 2.0.0 — Unreleased

### Breaking changes

- Require PHP `^8.2`, Illuminate Database/HTTP `^12.0 || ^13.0`, and Carbon `^3.8.4`. Laravel 13 requires PHP 8.3+.
- Leave missing typed properties uninitialized when they have no default; retain declared defaults. Explicit null still fails for non-nullable properties.
- Restrict hydration and magic access to public instance properties. Static, protected and private properties are not populated or exposed.
- Add parameter and return types to the base API, including `static` factory return types. Change the protected `setPropertyType()` argument from a type-name string to `?ReflectionType`.

### Fixes

- Preserve supplied values for untyped and mixed properties.
- Handle named types without assuming every reflection type has `getName()`.
- Use native PHP assignment for union/intersection/DNF types, retaining matching values and rejecting incompatible input.
- Correct array casting and remove the duplicate boolean branch.
- Replace the unreachable `date` branch with actual mutable/immutable Carbon and subclass support, including nullable dates and `DateTimeInterface` conversion.
- Support inherited public properties and initialization of public readonly properties.
- Correct Composer package type to `library` and remove development minimum stability, using Composer's stable default.
- Correct the README namespace, factory syntax, requirements and upgrade instructions.

### Development

- Add PHPUnit 11 and Orchestra Testbench 10/11 regression and Laravel integration tests with an isolated in-memory database.
- Add GitHub Actions for Laravel 12 on PHP 8.2–8.5 and Laravel 13 on PHP 8.3–8.5, including strict Composer validation and platform checks.
