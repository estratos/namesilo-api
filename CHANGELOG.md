# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-24

Primera versión funcional del bundle. La `1.0.0` publicada en Packagist era un esqueleto: faltaban las clases que la extensión registraba y el contenedor de Symfony no podía compilarse.

### Added

- `NameSiloClient` con:
  - peticiones GET a la API de NameSilo;
  - respuestas JSON y XML;
  - timeout, reintentos con backoff exponencial y logging PSR-3;
  - enmascarado de la API key en logs;
  - caché PSR-6 para endpoints de lectura e invalidación tras mutaciones;
  - modo sandbox.
- `ResponseMapper` para los 11 DTOs de `estratos/domain-api-interface`.
- Los 7 servicios de contrato:
  - `DomainService`;
  - `DNSService`;
  - `ContactService`;
  - `AccountService`;
  - `PrivacyService`;
  - `LockService`;
  - `NameServerService`.
- 13 eventos de dominio, DNS, contacto, privacidad y lock.
- `ApiExceptionFactory` para mapear los códigos numéricos de NameSilo a excepciones tipadas.
- `ApiException::getApiCode()` para conservar el código original de la API.
- Tests unitarios y de integración con `MockHttpClient`.
- PHPStan nivel `max`, PHP-CS-Fixer, `phpunit.xml.dist` y `phpstan.neon`.
- Documentación técnica completa en `docs/`.

### Changed

- El paquete pasa de `estratos/namesilo-bundle` a `estratos/namesilo-api`, alineado con el nombre publicado en Packagist.
- El wiring de servicios se centraliza en `src/Resources/config/services.php`; los servicios se registran por FQCN y se exponen alias de contrato.
- `composer.json` añade `symfony/cache` y `psr/cache`; `ext-simplexml` queda como sugerencia para `response_type: xml`.
- El README y la documentación reflejan el estado real y la instalación desde Packagist.

### Fixed

- El archivo del bundle pasa a `src/NameSiloBundle.php`, cumpliendo PSR-4.
- `NameSiloBundle::getContainerExtension()` devuelve explícitamente `NameSiloExtension`, necesario en Symfony 7.4.
- Se corrige el ejemplo inválido `->example(30)` del árbol de configuración.
- Se corrige el mapeo de contactos (`cp`, `ad2`, `fx`) y de DNS (`rrdistance`, SRV `weight:port:target`).
- Se corrige la doble definición de servicios y los alias solapados.

### Removed

- `src/Resources/config/services.yaml`, que pertenecía a otro proyecto (`Estratos\DomainNameApi`) y no se cargaba.
- `src/Service/RequestService.php`, legado con namespace antiguo y cURL.

### Breaking changes

- **Nombre del paquete:** cambia de `estratos/namesilo-bundle` a `estratos/namesilo-api`.
  - Sustituye `composer require estratos/namesilo-bundle` por `composer require estratos/namesilo-api`.
  - Si usabas una entrada VCS para `estratos/namesilo-bundle`, elimínala.
- **`RequestService` eliminado:** no formaba parte del API público del bundle; usa `NameSiloClientInterface`.
- **Servicios por FQCN:** los IDs `namesilo.*` se mantienen como alias, pero las definiciones principales usan el nombre de la clase.
- El namespace `Estratos\NameSiloBundle` no cambia; `config/bundles.php` se mantiene igual.

### Verification

- 30 tests, 116 aserciones, OK.
- PHPStan `max` sin errores.
- Contenedor Symfony compilado en el test de integración.
- `composer validate` correcto.
- `php -l` sin errores en `src/` y `tests/`.

### Known limitations

- Los tests usan mocks; falta validar un flujo real contra el sandbox de NameSilo.
- Los enums `ApiResponseCode`, `DomainStatus`, `LockStatus` y `PrivacyStatus` no se usan en el flujo principal.
- Algunos campos de los DTOs se derivan o tienen valores por defecto (`orderId`, `updatedAt`, `available`, `reserved`, `creditLimit`).
- `isDefault` en contactos no tiene equivalente en NameSilo y se ignora con un warning.
- Paginación de `listDomains` y posible N+1 en `DomainSummary` pendientes de revisar.

## [1.0.0] - 2026-06-29

### Added

- Publicación inicial del paquete en Packagist.
- Contratos, configuración, enums y excepciones base.
- Esqueleto de la integración con NameSilo.

[1.1.0]: https://github.com/estratos/namesilo-api/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/estratos/namesilo-api/releases/tag/1.0.0
