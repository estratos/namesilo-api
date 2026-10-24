# Configuración

Referencia del árbol `namesilo` definido en [`src/DependencyInjection/Configuration.php`](../src/DependencyInjection/Configuration.php).

## 1. Dónde se configura

La aplicación crea `config/packages/namesilo.yaml`:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
```

Y declara la variable en `.env` (o en los secretos de Symfony):

```dotenv
NAMESILO_API_KEY=tu_api_key
NAMESILO_SANDBOX=false
```

El bundle debe registrarse en `config/bundles.php`:

```php
return [
    // ...
    Estratos\NameSiloBundle\NameSiloBundle::class => ['all' => true],
];
```

> El bundle se registra con `Estratos\NameSiloBundle\NameSiloBundle::class`; la extensión usa el alias `namesilo`.

## 2. Configuración mínima

Solo `api_key` es obligatoria. Todo lo demás tiene valores por defecto:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
```

Equivale a:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: false
    base_url: 'https://www.namesilo.com/api/'
    timeout: 30
    version: '1'
    response_type: 'json'
    logging:
        enabled: true
        level: 'info'
        mask_sensitive: true
    retry:
        max_retries: 3
        delay_ms: 1000
        http_codes: [429, 500, 502, 503, 504]
    cache:
        enabled: true
        ttl_seconds: 300
        cacheable_endpoints:
            - 'getPrices'
            - 'getAccountBalance'
            - 'listDomains'
            - 'listDomainsByExpirationDate'
            - 'dnsListRecords'
            - 'contactList'
    events:
        enabled: true
        events_to_dispatch:
            - 'domain_registered'
            - 'domain_renewed'
            - 'domain_transferred'
            - 'dns_record_added'
            - 'dns_record_updated'
            - 'dns_record_deleted'
            - 'contact_added'
            - 'contact_updated'
            - 'contact_deleted'
            - 'privacy_added'
            - 'privacy_removed'
            - 'domain_locked'
            - 'domain_unlocked'
```

## 3. Referencia de opciones

### 3.1 Opciones principales

| Clave | Tipo | Default | Restricciones | Descripción |
|---|---|---|---|---|
| `api_key` | string | — | Obligatoria, no vacía | API key de NameSilo. |
| `sandbox` | bool | `false` | — | Activa el modo de pruebas. |
| `base_url` | string | `https://www.namesilo.com/api/` | — | URL base de la API. |
| `timeout` | int | `30` | `5..120` | Timeout HTTP en segundos. |
| `version` | string | `'1'` | — | Versión de la API. |
| `response_type` | string | `'json'` | `json` o `xml` | Formato de respuesta solicitado. |

### 3.2 Logging

| Clave | Tipo | Default | Restricciones | Descripción |
|---|---|---|---|---|
| `logging.enabled` | bool | `true` | — | Activa el log de peticiones y respuestas. |
| `logging.level` | string | `'info'` | Sin validación | Nivel PSR-3 usado por el cliente. |
| `logging.mask_sensitive` | bool | `true` | — | Oculta la API key y datos sensibles. |

### 3.3 Reintentos

| Clave | Tipo | Default | Restricciones | Descripción |
|---|---|---|---|---|
| `retry.max_retries` | int | `3` | `0..10` | Número máximo de reintentos. |
| `retry.delay_ms` | int | `1000` | `100..10000` | Espera base entre reintentos, en ms. |
| `retry.http_codes` | int[] | `[429, 500, 502, 503, 504]` | Enteros | Códigos HTTP que disparan un reintento. |

### 3.4 Caché

| Clave | Tipo | Default | Restricciones | Descripción |
|---|---|---|---|---|
| `cache.enabled` | bool | `true` | — | Activa la caché para operaciones de lectura. |
| `cache.ttl_seconds` | int | `300` | `60..3600` | TTL por defecto, en segundos. |
| `cache.cacheable_endpoints` | string[] | 6 endpoints | Strings | Operaciones que se pueden cachear. |

Endpoints cacheables por defecto:

```text
getPrices
getAccountBalance
listDomains
listDomainsByExpirationDate
dnsListRecords
contactList
```

### 3.5 Eventos

| Clave | Tipo | Default | Restricciones | Descripción |
|---|---|---|---|---|
| `events.enabled` | bool | `true` | — | Activa el dispatcher de eventos. |
| `events.events_to_dispatch` | string[] | 13 eventos | Strings | Eventos que se deben emitir. |

Nombres por defecto:

```text
domain_registered
domain_renewed
domain_transferred
dns_record_added
dns_record_updated
dns_record_deleted
contact_added
contact_updated
contact_deleted
privacy_added
privacy_removed
domain_locked
domain_unlocked
```

## 4. Parámetros del contenedor

La extensión publica estos parámetros (todos con prefijo `namesilo.`):

| Parámetro | Origen | Tipo |
|---|---|---|
| `namesilo.api_key` | `api_key` | string |
| `namesilo.sandbox` | `sandbox` | bool |
| `namesilo.base_url` | `base_url` | string |
| `namesilo.timeout` | `timeout` | int |
| `namesilo.version` | `version` | string |
| `namesilo.response_type` | `response_type` | string |
| `namesilo.logging` | `logging` | array |
| `namesilo.retry` | `retry` | array |
| `namesilo.cache` | `cache` | array |
| `namesilo.events` | `events` | array |

Los arrays se pasan además como argumentos al cliente y a los servicios. No hay objetos de configuración tipados (`LoggingConfig`, `RetryConfig`, etc.); si se quieren validar mejor, se pueden introducir más adelante.

## 5. Servicios y alias

Los servicios se registran por FQCN en `services.php` y se exponen mediante alias. Los alias `namesilo.*` se mantienen por comodidad:

| ID / alias | Clase | Alias de contrato |
|---|---|---|
| `namesilo.client` | `Client\NameSiloClient` | `Client\NameSiloClientInterface` |
| `namesilo.mapper` | `Mapper\ResponseMapper` | — |
| `namesilo.service.domain` | `Service\DomainService` | `Domain\DomainProviderInterface` |
| `namesilo.service.dns` | `Service\DNSService` | `DNS\DNSProviderInterface` |
| `namesilo.service.contact` | `Service\ContactService` | `Contact\ContactProviderInterface` |
| `namesilo.service.account` | `Service\AccountService` | `Account\AccountProviderInterface` |
| `namesilo.service.privacy` | `Service\PrivacyService` | `Privacy\PrivacyProviderInterface` |
| `namesilo.service.lock` | `Service\LockService` | `Lock\LockProviderInterface` |
| `namesilo.service.nameserver` | `Service\NameServerService` | `NameServer\NameServerProviderInterface` |

El contenedor también puede inyectar las clases concretas (`DomainService`, `DNSService`, etc.) porque el ID del servicio es el FQCN. El detalle del wiring está en [01-arquitectura.md](01-arquitectura.md#5-wiring).

## 6. Notas de comportamiento

### 6.1 `api_key`

- Es obligatoria; si falta, Symfony lanza una excepción de configuración al compilar.
- No la escribas en YAML. Usa `%env(NAMESILO_API_KEY)%`, un `.env.local` no versionado o los secretos de Symfony.
- Si `logging.mask_sensitive` es `true`, el cliente debe ocultar el parámetro `key` de las URLs y cualquier cabecera de autenticación en los logs.

### 6.2 `sandbox`

- Si `sandbox` es `true` y `base_url` sigue siendo el default de producción, el cliente usa `https://sandbox.namesilo.com/api/`.
- Si se configura un `base_url` propio, se respeta tal cual. Así se puede apuntar a un proxy, a un mock o a un host alternativo sin que el flag lo sobrescriba.
- El sandbox de NameSilo requiere credenciales separadas; no reutilices la API key de producción.

### 6.3 `base_url`

- El default incluye la barra final. El cliente debe normalizar (`rtrim($baseUrl, '/') . '/'`) para evitar `//` en las rutas.
- Si se usa un proxy o un mock HTTP, se puede apuntar a otra URL sin cambiar el código.

### 6.4 `timeout`

- Se pasa a `HttpClient::create()` y también al cliente. El cliente usa el `HttpClientInterface` inyectado y aplica el timeout en cada petición.
- Un timeout demasiado bajo puede cortar operaciones de registro/transferencia que tardan más de lo habitual.

### 6.5 `response_type`

- `json` es el valor recomendado.
- `xml` está soportado mediante `SimpleXML`; el cliente convierte el XML a un array normalizado antes de mapear.
- `ext-simplexml` no es una dependencia obligatoria: si se selecciona `xml` y la extensión no está instalada, el cliente lanza una excepción clara.
- El contrato `NameSiloClientInterface::request()` devuelve `array`, así que el parseo ocurre dentro del cliente.

### 6.6 `logging`

- `level` no se valida contra los niveles PSR-3. Un valor inválido puede hacer que `LoggerInterface::log()` falle según la implementación.
- `mask_sensitive` debe cubrir, como mínimo:
  - el parámetro `key` de la query string;
  - la URL completa si contiene la key;
  - cualquier cabecera `Authorization`.
- No registres cuerpos completos de respuestas con datos de contacto (emails, teléfonos, direcciones).

### 6.7 `retry`

- `http_codes` son códigos **HTTP**. NameSilo suele devolver `200 OK` incluso cuando la operación falla a nivel de API; el error viaja en `reply.code`.
- El cliente reintenta solo errores de transporte y códigos HTTP de la lista, con backoff exponencial y un límite de 10 s por espera.
- No se reintentan errores de negocio de NameSilo ni operaciones no idempotentes. **No reintentes** `registerDomain`, `renewDomain`, `transferDomain`, `contactAdd`, `addAccountFunds` ni `addPrivacy` sin una comprobación previa: un reintento puede duplicar una compra.
- `max_retries` es el número de reintentos adicionales, no el total de intentos.

### 6.8 `cache`

- Implementada con `FilesystemAdapter` en `%kernel.cache_dir%/namesilo`.
- Solo cachea endpoints de `cache.cacheable_endpoints`.
- La clave incluye endpoint, parámetros y un hash de la API key.
- Tras una operación mutadora, el cliente limpia la caché del namespace.
- Si el pool falla, el cliente sigue funcionando y solo registra un warning.
- Por defecto el TTL es 300 s; para datos como precios o saldo conviene un TTL corto.

### 6.9 `events`

- `events.enabled` y `events_to_dispatch` están implementados.
- Los servicios despachan eventos solo después de una mutación exitosa y solo si el nombre está en la lista.
- Los 13 nombres configurados tienen una clase de evento correspondiente en `src/Event/`.
- `AccountService` y `NameServerService` reciben el dispatcher, pero no emiten eventos porque la lista actual no incluye eventos de cuenta o nameservers.

### 6.10 `version`

- La API actual usa `version=1`. Es un string, no un entero, porque se concatena en la query string.
- Si NameSilo publica una versión nueva, se podrá cambiar sin tocar código, pero el mapper tendrá que soportar el nuevo formato.

## 7. Ejemplos

### 7.1 Desarrollo con sandbox

```yaml
# config/packages/namesilo.yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: true
    base_url: 'https://sandbox.namesilo.com/api/'  # verificar URL oficial
    timeout: 15

    retry:
        max_retries: 1
        delay_ms: 200
        http_codes: [429, 503]

    cache:
        enabled: false

    events:
        enabled: false
```

### 7.2 Producción conservadora

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: false
    base_url: 'https://www.namesilo.com/api/'
    timeout: 30
    response_type: 'json'

    logging:
        enabled: true
        level: 'warning'
        mask_sensitive: true

    retry:
        max_retries: 2
        delay_ms: 500
        http_codes: [429, 503]

    cache:
        enabled: true
        ttl_seconds: 600
        cacheable_endpoints:
            - 'getPrices'
            - 'getAccountBalance'
            - 'listDomains'

    events:
        enabled: true
        events_to_dispatch:
            - 'domain_registered'
            - 'domain_renewed'
            - 'domain_transferred'
```

### 7.3 Solo lectura (consultas)

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    cache:
        enabled: true
        ttl_seconds: 3600
    events:
        enabled: false
    retry:
        max_retries: 0
```

## 8. Checklist de configuración

- [ ] `NAMESILO_API_KEY` definida fuera del repositorio.
- [ ] `sandbox` y `base_url` coherentes entre sí.
- [ ] `response_type` es `json`, o `xml` con `ext-simplexml` disponible.
- [ ] `timeout` suficiente para las operaciones de registro/transferencia.
- [ ] `logging.mask_sensitive` activo en producción.
- [ ] `retry` desactivado o limitado para operaciones de compra.
- [ ] `%kernel.cache_dir%` es escribible para la caché de Namesilo.
- [ ] `events_to_dispatch` contiene solo eventos que la aplicación necesita.
