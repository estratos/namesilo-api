# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-24

First functional release. The published `1.0.0` was a skeleton: the classes registered by the extension did not exist and the Symfony container could not compile.

### Added

- `NameSiloClient` with:
  - GET requests to the NameSilo API;
  - JSON and XML responses;
  - timeout, exponential-backoff retries and PSR-3 logging;
  - API key masking in logs;
  - PSR-6 cache for read-only endpoints, invalidated after mutations;
  - sandbox mode.
- `ResponseMapper` for the 11 DTOs from `estratos/domain-api-interface`.
- The 7 contract services:
  - `DomainService`;
  - `DNSService`;
  - `ContactService`;
  - `AccountService`;
  - `PrivacyService`;
  - `LockService`;
  - `NameServerService`.
- 13 domain, DNS, contact, privacy and lock events.
- `ApiExceptionFactory` to map NameSilo numeric codes to typed exceptions.
- `ApiException::getApiCode()` to preserve the original API code.
- Unit and integration tests with `MockHttpClient`.
- PHPStan level `max`, PHP-CS-Fixer, `phpunit.xml.dist` and `phpstan.neon`.
- Complete technical documentation in `docs/`.

### Changed

- The package name changes from `estratos/namesilo-bundle` to `estratos/namesilo-api`, matching the name published on Packagist.
- Service wiring is centralized in `src/Resources/config/services.php`; services are registered by FQCN and exposed through contract aliases.
- `composer.json` adds `symfony/cache` and `psr/cache`; `ext-simplexml` is suggested for `response_type: xml`.
- README and documentation now reflect the actual state and Packagist installation.

### Fixed

- The bundle file is now `src/NameSiloBundle.php`, complying with PSR-4.
- `NameSiloBundle::getContainerExtension()` explicitly returns `NameSiloExtension`, required on Symfony 7.4.
- Fixed the invalid `->example(30)` configuration example.
- Fixed contact mapping (`cp`, `ad2`, `fx`) and DNS mapping (`rrdistance`, SRV `weight:port:target`).
- Removed duplicate service definitions and overlapping aliases.

### Removed

- `src/Resources/config/services.yaml`, which belonged to another project (`Estratos\DomainNameApi`) and was never loaded.
- `src/Service/RequestService.php`, a legacy class with an old namespace and cURL.

### Breaking changes

- **Package name:** from `estratos/namesilo-bundle` to `estratos/namesilo-api`.
  - Replace `composer require estratos/namesilo-bundle` with `composer require estratos/namesilo-api`.
  - If you added a VCS entry for `estratos/namesilo-bundle`, remove it.
- **`RequestService` removed:** it was not part of the bundle's public API; use `NameSiloClientInterface`.
- **Services by FQCN:** the `namesilo.*` IDs remain as aliases, but the primary definitions use the class name.
- The `Estratos\NameSiloBundle` namespace is unchanged; `config/bundles.php` stays the same.

### Verification

- 30 tests, 116 assertions, OK.
- PHPStan `max` with no errors.
- Symfony container compiled in the integration test.
- `composer validate` passes.
- `php -l` passes for `src/` and `tests/`.

### Known limitations

- Tests use mocks; a real flow against the NameSilo sandbox still needs to be validated.
- The `ApiResponseCode`, `DomainStatus`, `LockStatus` and `PrivacyStatus` enums are not used in the main flow.
- Some DTO fields are derived or defaulted (`orderId`, `updatedAt`, `available`, `reserved`, `creditLimit`).
- `isDefault` on contacts has no NameSilo equivalent and is ignored with a warning.
- Pagination for `listDomains` and a possible N+1 in `DomainSummary` still need review.

## [1.0.0] - 2026-06-29

### Added

- Initial package publication on Packagist.
- Base contracts, configuration, enums and exceptions.
- Skeleton of the NameSilo integration.

[1.1.0]: https://github.com/estratos/namesilo-api/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/estratos/namesilo-api/releases/tag/1.0.0
