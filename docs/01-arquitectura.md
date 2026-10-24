# Arquitectura

## 1. Vista general

El bundle ocupa la capa de infraestructura de un diseño por contratos:

```text
┌──────────────────────────────────────────────────────────────┐
│ Aplicación Symfony                                          │
│  inyecta DomainProviderInterface, DNSProviderInterface, ...  │
└──────────────────────────────┬───────────────────────────────┘
                               │ consume
┌──────────────────────────────▼───────────────────────────────┐
│ estratos/domain-api-interface                               │
│  contratos + DTOs + Request objects + RecordType            │
└──────────────────────────────▲───────────────────────────────┘
                               │ implementan
┌──────────────────────────────┴───────────────────────────────┐
│ estratos/namesilo-api (este repositorio)                    │
│                                                              │
│  DomainService  DNSService  ContactService  AccountService   │
│  PrivacyService  LockService  NameServerService              │
│            │                   │                             │
│            ▼                   ▼                             │
│     NameSiloClient       ResponseMapper                      │
│            │                   ▲                             │
│            ▼                   │                             │
│      HTTP GET ─────────────────┘                             │
│            │                                                 │
│            ├── retry / timeout / logging / cache             │
│            └── EventDispatcher                               │
└──────────────────────────────┬───────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────┐
│ API de NameSilo                                              │
│  https://www.namesilo.com/api/                               │
└──────────────────────────────────────────────────────────────┘
```

La aplicación no conoce NameSilo: conoce los contratos. El bundle es reemplazable por otro proveedor sin cambiar el código de negocio.

## 2. Estructura actual

```text
namesilo-api/
├── composer.json
├── README.md
├── LICENSE
├── phpunit.xml.dist
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── src/
│   ├── NameSiloBundle.php
│   ├── Client/
│   │   ├── NameSiloClient.php
│   │   └── NameSiloClientInterface.php
│   ├── DependencyInjection/
│   │   ├── Configuration.php
│   │   └── NameSiloExtension.php
│   ├── Enum/
│   │   ├── ApiResponseCode.php
│   │   ├── DomainStatus.php
│   │   ├── LockStatus.php
│   │   └── PrivacyStatus.php
│   ├── Event/
│   │   ├── NameSiloEvents.php
│   │   ├── DomainRegisteredEvent.php
│   │   ├── DomainRenewedEvent.php
│   │   ├── DomainTransferredEvent.php
│   │   ├── DnsRecordAddedEvent.php
│   │   ├── DnsRecordUpdatedEvent.php
│   │   ├── DnsRecordDeletedEvent.php
│   │   ├── ContactAddedEvent.php
│   │   ├── ContactUpdatedEvent.php
│   │   ├── ContactDeletedEvent.php
│   │   ├── PrivacyAddedEvent.php
│   │   ├── PrivacyRemovedEvent.php
│   │   ├── DomainLockedEvent.php
│   │   └── DomainUnlockedEvent.php
│   ├── Exception/
│   │   ├── ApiException.php
│   │   ├── ApiExceptionFactory.php
│   │   ├── AuthenticationException.php
│   │   ├── DNSException.php
│   │   ├── InsufficientFundsException.php
│   │   ├── InvalidContactException.php
│   │   ├── InvalidDomainException.php
│   │   ├── RateLimitException.php
│   │   ├── TimeoutException.php
│   │   └── ValidationException.php
│   ├── Mapper/
│   │   └── ResponseMapper.php
│   ├── Resources/config/
│   │   └── services.php
│   └── Service/
│       ├── AbstractService.php
│       ├── AccountService.php
│       ├── ContactService.php
│       ├── DNSService.php
│       ├── DomainService.php
│       ├── LockService.php
│       ├── NameServerService.php
│       └── PrivacyService.php
├── tests/
│   ├── bootstrap.php
│   ├── Fixtures/
│   ├── Integration/
│   └── Unit/
└── docs/
```

## 3. Componentes

### 3.1 `NameSiloBundle`

[`src/NameSiloBundle.php`](../src/NameSiloBundle.php)

- Extiende `AbstractBundle`.
- Devuelve explícitamente `NameSiloExtension` desde `getContainerExtension()`.
- Declara la ruta raíz del bundle con `dirname(__DIR__)`.

La sobrescritura de `getContainerExtension()` es necesaria porque, en Symfony 7.4, `AbstractBundle` devuelve un `BundleExtension` genérico y no descubre automáticamente `DependencyInjection/NameSiloExtension.php`.

### 3.2 `NameSiloExtension`

[`src/DependencyInjection/NameSiloExtension.php`](../src/DependencyInjection/NameSiloExtension.php)

1. Procesa la configuración `namesilo`.
2. Publica los parámetros `namesilo.*`.
3. Carga `Resources/config/services.php`.

No registra servicios manualmente. Toda la definición está en `services.php`; así hay una sola fuente de verdad.

### 3.3 `Configuration`

[`src/DependencyInjection/Configuration.php`](../src/DependencyInjection/Configuration.php)

Árbol `namesilo` con `api_key` obligatorio y el resto con defaults. La referencia completa está en [02-configuracion.md](02-configuracion.md).

### 3.4 `NameSiloClient`

[`src/Client/NameSiloClient.php`](../src/Client/NameSiloClient.php)

Responsabilidades:

- Construir la URL `base_url + endpoint + query`.
- Añadir `version`, `type` y `key`.
- Forzar GET (la API solo admite GET).
- Enviar con `HttpClientInterface`.
- Decodificar JSON o XML.
- Normalizar la envoltura `reply` y devolver `array<string, mixed>`.
- Mapear errores a excepciones mediante `ApiExceptionFactory`.
- Aplicar timeout, reintentos y logging.
- Cachear respuestas de lectura y limpiar la caché tras una mutación.

El cliente no conoce DTOs ni reglas de negocio; solo transporte y normalización.

### 3.5 `ResponseMapper`

[`src/Mapper/ResponseMapper.php`](../src/Mapper/ResponseMapper.php)

Convierte `reply` en DTOs:

- fechas `Y-m-d` → `DateTimeImmutable` UTC;
- `Yes`/`No` → `bool`;
- precios y saldos con comas → `float`;
- listas que pueden venir como objeto único o array;
- `nameservers` y `contact_ids`;
- valores SRV `weight:port:target`;
- `rrdistance` como prioridad MX.

No hace HTTP y es testeable con fixtures.

### 3.6 Servicios

[`src/Service`](../src/Service)

| Servicio | Contrato | Operaciones |
|---|---|---|
| `DomainService` | `DomainProviderInterface` | availability, register, renew, transfer, transfer status, info, list, list by expiration |
| `DNSService` | `DNSProviderInterface` | list, add, update, delete |
| `ContactService` | `ContactProviderInterface` | list, add, update, delete |
| `AccountService` | `AccountProviderInterface` | balance, prices |
| `PrivacyService` | `PrivacyProviderInterface` | add, remove |
| `LockService` | `LockProviderInterface` | lock, unlock |
| `NameServerService` | `NameServerProviderInterface` | change name servers |

Todos heredan de `AbstractService`, que aporta:

- referencia al cliente, mapper, dispatcher, logger y configuración de eventos;
- `dispatch()` con comprobación de `enabled` y `events_to_dispatch`;
- normalización de dominio, IDs y nameservers;
- `normalizeList()` para respuestas con objeto único o array.

### 3.7 Eventos

[`src/Event`](../src/Event)

13 eventos, alineados con los nombres de `events_to_dispatch`:

```text
domain_registered, domain_renewed, domain_transferred,
dns_record_added, dns_record_updated, dns_record_deleted,
contact_added, contact_updated, contact_deleted,
privacy_added, privacy_removed,
domain_locked, domain_unlocked
```

Se despachan solo después de una mutación exitosa y solo si el nombre está habilitado en la configuración.

### 3.8 Excepciones y enums

Ver [04-excepciones-y-enums.md](04-excepciones-y-enums.md). `ApiExceptionFactory` centraliza el mapeo de códigos numéricos de NameSilo; `ApiException::getApiCode()` expone el código original.

## 4. Ciclo de vida en Symfony

1. **Registro:** la aplicación añade `NameSiloBundle::class` a `config/bundles.php`.
2. **Extensión:** `getContainerExtension()` devuelve `NameSiloExtension` con alias `namesilo`.
3. **Configuración:** Symfony procesa `config/packages/namesilo.yaml` con `Configuration`.
4. **Parámetros:** se publican `namesilo.*`.
5. **Servicios:** `services.php` registra cliente, mapper, 7 servicios, eventos y alias.
6. **Compilación:** el contenedor valida clases, argumentos y referencias.
7. **Runtime:** la aplicación inyecta un contrato y ejecuta la operación.

## 5. Wiring

[`src/Resources/config/services.php`](../src/Resources/config/services.php)

| ID | Clase | Alias |
|---|---|---|
| `namesilo.http_client` | `HttpClientInterface` | — |
| `namesilo.cache` | `FilesystemAdapter` | — |
| `NameSiloClient::class` | `NameSiloClient` | `NameSiloClientInterface`, `namesilo.client` |
| `ResponseMapper::class` | `ResponseMapper` | `namesilo.mapper` |
| `DomainService::class` | `DomainService` | `DomainProviderInterface`, `namesilo.service.domain` |
| `DNSService::class` | `DNSService` | `DNSProviderInterface`, `namesilo.service.dns` |
| `ContactService::class` | `ContactService` | `ContactProviderInterface`, `namesilo.service.contact` |
| `AccountService::class` | `AccountService` | `AccountProviderInterface`, `namesilo.service.account` |
| `PrivacyService::class` | `PrivacyService` | `PrivacyProviderInterface`, `namesilo.service.privacy` |
| `LockService::class` | `LockService` | `LockProviderInterface`, `namesilo.service.lock` |
| `NameServerService::class` | `NameServerService` | `NameServerProviderInterface`, `namesilo.service.nameserver` |

Los servicios se registran por FQCN, de modo que el autowiring por clase concreta también funciona. Los alias de contrato son públicos para que una aplicación pueda pedirlos al contenedor en tests o comandos.

Argumentos del cliente:

```text
NameSiloClient(
    HttpClientInterface,
    LoggerInterface,
    string apiKey,
    bool sandbox,
    string baseUrl,
    int timeout,
    string version,
    string responseType,
    array logging,
    array retry,
    array cache,
    CacheItemPoolInterface
)
```

Argumentos de los servicios:

```text
(client, mapper, event_dispatcher, logger, events)
```

## 6. Decisiones y puntos finos

### 6.1 Un solo wiring

Se eliminó el `services.yaml` obsoleto que pertenecía a `Estratos\DomainNameApi` y se eliminó el `RequestService` legado. No hay dos grafos de servicios.

### 6.2 Caché

- `FilesystemAdapter` con namespace `namesilo` y directorio `%kernel.cache_dir%/namesilo`.
- Solo cachea endpoints de `cache.cacheable_endpoints`.
- La clave incluye endpoint, parámetros y hash de la API key.
- Tras una operación mutadora se limpia la caché.
- Si el pool falla, el cliente sigue funcionando (solo registra un warning).

### 6.3 Reintentos

- Solo por códigos HTTP configurados o errores de transporte.
- Backoff exponencial con límite de 10 s.
- No se reintentan errores de negocio de NameSilo (HTTP 200 con `reply.code` de error) ni operaciones de compra.

### 6.4 Sandbox

- Si `sandbox: true` y `base_url` es el default de producción, se usa `https://sandbox.namesilo.com/api/`.
- Si se configura un `base_url` propio, se respeta tal cual.
- Esto evita que un flag mal configurado golpee producción sin querer, pero permite usar un proxy o mock.

### 6.5 XML

- Se soporta con `SimpleXML`.
- Si `response_type: xml` y la extensión no está disponible, el cliente lanza una excepción clara.
- JSON sigue siendo el formato recomendado.

### 6.6 Logging

- `logging.enabled` y `logging.level` controlan el nivel.
- La URL se enmascara (`key=***`) antes de registrarse.
- No se registran cuerpos completos de respuestas.

### 6.7 Enums

Los enums siguen sin integrarse en el flujo principal. El mapper normaliza estados a strings/booleanos; los enums quedan como vocabulario de dominio disponible. Ver [04-excepciones-y-enums.md](04-excepciones-y-enums.md#4-enums).

## 7. Convenciones

- `declare(strict_types=1)` en todos los archivos.
- Clases `final` salvo las que se heredan (`AbstractService`, excepciones).
- Propiedades `readonly` en eventos y excepciones.
- PSR-12; PHPStan `max`; PHPUnit 10+.
- El cliente no conoce DTOs; el mapper no hace HTTP; los servicios no construyen URLs.
- Las mutaciones despachan eventos solo después del éxito.

## 8. Dependencias

### 8.1 Runtime

| Paquete | Uso |
|---|---|
| `php >= 8.3` | Enums, readonly, constructor promotion. |
| `estratos/domain-api-interface ^1.0` | Contratos, DTOs y requests. |
| `psr/cache ^3.0` | Pool PSR-6. |
| `psr/log ^3.0` | Logging. |
| `symfony/cache ^7.0` | `FilesystemAdapter`. |
| `symfony/config ^7.0` | Árbol de configuración. |
| `symfony/dependency-injection ^7.0` | Extensión y contenedor. |
| `symfony/event-dispatcher ^7.0` | Eventos. |
| `symfony/framework-bundle ^7.0` | Integración Symfony. |
| `symfony/http-client ^7.0` | Cliente HTTP. |
| `symfony/http-kernel ^7.0` | `AbstractBundle`. |

`ext-simplexml` es opcional y solo se necesita para `response_type: xml`.

### 8.2 Desarrollo

| Paquete | Uso |
|---|---|
| `phpunit/phpunit ^10.5` | Tests. |
| `phpstan/phpstan ^1.10` | Análisis estático. |
| `friendsofphp/php-cs-fixer ^3.49` | Estilo. |
| `symfony/phpunit-bridge ^7.0` | Integración PHPUnit/Symfony. |
| `symfony/stopwatch ^7.0` | Perfilado en tests. |
| `php-http/mock-client ^1.6` | Mock HTTP alternativo. |

## 9. Qué falta

El detalle está en [05-estado-y-roadmap.md](05-estado-y-roadmap.md). En resumen:

1. Publicar una nueva etiqueta con la reparación.
2. Probar contra el sandbox real.
3. Integrar o eliminar los enums.
4. Añadir CI.
5. Cubrir casos límite de la API.
