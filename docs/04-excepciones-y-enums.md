# Excepciones y enums

## 1. Jerarquía de excepciones

```text
RuntimeException
└── ApiException
    ├── AuthenticationException        (401)
    ├── InsufficientFundsException     (402)
    ├── RateLimitException             (429)
    ├── TimeoutException               (408)
    ├── ValidationException            (422)
    │   ├── InvalidDomainException
    │   └── InvalidContactException
    └── DNSException                   (422)
```

Todas viven en `Estratos\NameSiloBundle\Exception` y extienden `ApiException`, que a su vez extiende `\RuntimeException`.

## 2. `ApiException`

Archivo: [`src/Exception/ApiException.php`](../src/Exception/ApiException.php)

```php
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $endpoint = null,
        private readonly ?int $statusCode = null,
        private readonly ?string $requestId = null,
        ?\Throwable $previous = null,
        private readonly ?int $apiCode = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

| Miembro | Tipo | Uso |
|---|---|---|
| `$message` | string | Mensaje legible. |
| `$endpoint` | `?string` | Operación de NameSilo (`registerDomain`, etc.). |
| `$statusCode` | `?int` | Código HTTP-like o código de API asignado por la subclase. |
| `$requestId` | `?string` | Identificador de correlación generado por el cliente. |
| `$apiCode` | `?int` | Código numérico original de NameSilo (`119`, `280`, etc.). |
| `getCode()` | int | Siempre `0`; el estado va en `getStatusCode()`. |
| `getApiCode()` | `?int` | Código original de NameSilo, si está disponible. |

**Observación:** el código de excepción de PHP siempre es `0`. Si la aplicación espera `getCode()` para distinguir errores, no funcionará; debe usar `getStatusCode()` o el tipo de excepción.

## 3. Excepciones concretas

| Excepción | `statusCode` | Datos extra | Cuándo lanzarla (propuesta) |
|---|---|---|---|
| `AuthenticationException` | `401` | — | API key inválida, IP no permitida, usuario inválido. |
| `InsufficientFundsException` | `402` | `requiredAmount`, `availableBalance` | Saldo insuficiente para registrar/renovar/transferir. |
| `RateLimitException` | `429` | `retryAfter` | HTTP 429 o límite de peticiones de NameSilo. |
| `TimeoutException` | `408` | `timeout` | Timeout de conexión/lectura. |
| `ValidationException` | `422` | `errors` | Parámetros inválidos o incompletos. |
| `InvalidDomainException` | `422` | `errors['domain']` | Dominio mal formado, no disponible, no activo o no propiedad del usuario. |
| `InvalidContactException` | `422` | `errors['contactId']` | Contacto inexistente o inválido. |
| `DNSException` | `422` | — | Error específico de modificación DNS. |

### 3.1 Firmas

```php
new AuthenticationException(
    string $message = 'Authentication failed. Please check your API key.',
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new InsufficientFundsException(
    float $requiredAmount,
    float $availableBalance,
    string $message = 'Insufficient funds in account.',
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new RateLimitException(
    string $message = 'Rate limit exceeded. Please try again later.',
    ?int $retryAfter = null,
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new TimeoutException(
    int $timeout,
    string $message = 'Request timed out.',
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new ValidationException(
    string $message,
    array $errors = [],
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new InvalidDomainException(
    string $domain,
    string $reason = 'Invalid domain name format.',
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new InvalidContactException(
    string $contactId,
    string $reason = 'Invalid contact ID.',
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);

new DNSException(
    string $message,
    ?string $endpoint = null,
    ?string $requestId = null,
    ?\Throwable $previous = null
);
```

### 3.2 Comportamientos a tener en cuenta

1. **`InsufficientFundsException` construye el mensaje con los montos y añade el `$message` recibido.** El mensaje base es:

   ```text
   Insufficient funds. Required: $X.XX, Available: $Y.YY
   ```

   Si se pasa un mensaje con contexto extra, se concatena al final. Asume dólares (`$`).

2. **`TimeoutException` construye el mensaje con el timeout y añade el `$message` recibido.** El mensaje base es:

   ```text
   Request timed out after N seconds.
   ```

3. **`InvalidDomainException` e `InvalidContactException` guardan el motivo en `errors`.** `getErrors()` devuelve `['domain' => $reason]` o `['contactId' => $reason]`, no una lista de errores.

4. **`DNSException` no extiende `ValidationException`.** Aunque su `statusCode` es `422`, un `catch (ValidationException $e)` no captura errores DNS. Si se quiere esa semántica, hay que cambiar la jerarquía.

5. **No hay excepción para “ya bloqueado/ya desbloqueado/ya privado”.** Esos códigos de NameSilo son idempotentes; el servicio debería devolver el estado actual en lugar de lanzar una excepción.

6. **El código numérico original está en `getApiCode()`.** `statusCode` sigue siendo el estado HTTP-like de la excepción; `apiCode` guarda el `code` de NameSilo cuando la excepción se crea desde una respuesta.

## 4. Enums

### 4.1 `ApiResponseCode`

Archivo: [`src/Enum/ApiResponseCode.php`](../src/Enum/ApiResponseCode.php)

```php
enum ApiResponseCode: string
{
    case SUCCESS = 'SUCCESS';
    case FAILURE = 'FAILURE';
    case AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case DOMAIN_NOT_AVAILABLE = 'DOMAIN_NOT_AVAILABLE';
    case DOMAIN_ALREADY_REGISTERED = 'DOMAIN_ALREADY_REGISTERED';
    case DOMAIN_NOT_FOUND = 'DOMAIN_NOT_FOUND';
    case CONTACT_NOT_FOUND = 'CONTACT_NOT_FOUND';
    case DNS_RECORD_NOT_FOUND = 'DNS_RECORD_NOT_FOUND';
    case INVALID_PARAMETER = 'INVALID_PARAMETER';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case SYSTEM_ERROR = 'SYSTEM_ERROR';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
}
```

Métodos:

| Método | Comportamiento |
|---|---|
| `isSuccess()` | `true` solo para `SUCCESS`. |
| `isFailure()` | `true` para todo lo que no sea `SUCCESS`. |
| `isAuthenticationError()` | `true` solo para `AUTHENTICATION_ERROR`. |
| `isRateLimitExceeded()` | `true` solo para `RATE_LIMIT_EXCEEDED`. |

**Problema principal:** NameSilo devuelve códigos **numéricos** (`300`, `110`, `119`, `280`, ...). Este enum usa etiquetas simbólicas en mayúsculas que **no existen en la API**. No hay ningún método `fromNameSilo(int $code)` ni tabla de conversión. Si se usa directamente para interpretar una respuesta, siempre fallará.

Opciones:

- **A. Mantenerlo como categoría interna** y añadir una fábrica que traduzca el código numérico a la categoría y a la excepción.
- **B. Reemplazarlo por un enum `int`** con los códigos reales (`SUCCESS = 300`, etc.) y métodos que consideren `300`, `301`, `302` como éxito. Es más cercano al transporte, pero mezcla códigos de API con semántica de dominio.

Recomendación: **A**. El enum de dominio no debe depender de los números de un proveedor; la traducción ya está en `ApiExceptionFactory`.

### 4.2 `DomainStatus`

Archivo: [`src/Enum/DomainStatus.php`](../src/Enum/DomainStatus.php)

| Caso | Valor | Significado |
|---|---|---|
| `ACTIVE` | `active` | Dominio activo. |
| `PENDING` | `pending` | Pendiente. |
| `EXPIRED` | `expired` | Expirado. |
| `CANCELLED` | `cancelled` | Cancelado. |
| `TRANSFERRED_OUT` | `transferred_out` | Transferido fuera. |
| `SUSPENDED` | `suspended` | Suspendido. |
| `REDEMPTION` | `redemption` | Periodo de redención. |
| `PENDING_DELETE` | `pending_delete` | Pendiente de borrado. |

Métodos:

- `isActive()`, `isExpired()`.
- `canBeRenewed()`: `ACTIVE`, `EXPIRED`, `REDEMPTION`.
- `canBeTransferred()`: solo `ACTIVE`.
- `getOrder()`: orden numérico para priorizar estados.

**Problema:** NameSilo devuelve `Active` (capitalizado) y posiblemente variantes con espacios (`Transferred Out`). El mapper debe normalizar (`strtolower`, reemplazar espacios por `_`) antes de usar `DomainStatus::tryFrom()`. Además, el DTO `DomainInfo` guarda `status` como `string`, así que este enum no se usa en la práctica.

### 4.3 `LockStatus`

Archivo: [`src/Enum/LockStatus.php`](../src/Enum/LockStatus.php)

| Caso | Valor |
|---|---|
| `LOCKED` | `locked` |
| `UNLOCKED` | `unlocked` |
| `PENDING_LOCK` | `pending_lock` |
| `PENDING_UNLOCK` | `pending_unlock` |

Métodos: `isLocked()`, `isUnlocked()`, `isPending()`.

**Problema:** NameSilo expone `locked` como `Yes`/`No` (y el DTO `DomainInfo` usa `bool`). Los estados `pending_*` no están documentados en la API; si no existen, son casos muertos. Hay una colisión de nombre con `Estratos\DomainApiInterface\DTO\LockStatus` (el DTO que devuelve el contrato). Si un archivo importa ambos, necesita alias.

### 4.4 `PrivacyStatus`

Archivo: [`src/Enum/PrivacyStatus.php`](../src/Enum/PrivacyStatus.php)

| Caso | Valor |
|---|---|
| `ENABLED` | `enabled` |
| `DISABLED` | `disabled` |
| `PENDING_ENABLE` | `pending_enable` |
| `PENDING_DISABLE` | `pending_disable` |

Métodos: `isEnabled()`, `isDisabled()`, `isPending()`.

**Problema:** mismo caso que `LockStatus`: NameSilo devuelve `private` como `Yes`/`No`; los estados `pending_*` pueden no existir. Colisión con `Estratos\DomainApiInterface\DTO\PrivacyStatus`.

## 5. Mapeo de errores propuesto

### 5.1 Códigos NameSilo → excepción

| Código | Excepción | Notas |
|---|---|---|
| `108` | `ValidationException` | Faltan parámetros. |
| `110` | `AuthenticationException` | API key inválida. |
| `114` | `InvalidDomainException` | Sintaxis de dominio. |
| `119` | `InsufficientFundsException` | Requiere montos; si la respuesta no los trae, consultar saldo o pasar `0.0`. |
| `200` | `InvalidDomainException` / `ApiException` | Dominio no activo o de otro usuario. |
| `252` | — | Ya bloqueado: éxito idempotente. |
| `253` | — | Ya desbloqueado: éxito idempotente. |
| `255` | — | Ya privado: éxito idempotente. |
| `256` | — | Ya sin privacidad: éxito idempotente. |
| `280` | `DNSException` | Error de modificación DNS. |
| `300` | — | Éxito. |
| `301` | — | Éxito con warning de nameservers. |
| `302` | — | Éxito con warning de contacto. |
| `400` | `ApiException` | Error de petición. |

Para el resto de códigos, usa `ApiException` con el `detail` de NameSilo y el `endpoint`; evita inventar una excepción específica sin semántica clara.

### 5.2 HTTP → excepción

| Situación HTTP | Excepción |
|---|---|
| `401` | `AuthenticationException` |
| `402` | `InsufficientFundsException` |
| `408` / timeout de transporte | `TimeoutException` |
| `422` | `ValidationException` o `DNSException` según contexto |
| `429` | `RateLimitException` (leer `Retry-After`) |
| `5xx` | `ApiException` con `statusCode` |
| Error de red | `ApiException` o `TimeoutException` con `previous` |

NameSilo suele devolver `200 OK` con un `code` de error en el cuerpo; no bases el manejo en el status HTTP únicamente.

### 5.3 Fábrica implementada

[`src/Exception/ApiExceptionFactory.php`](../src/Exception/ApiExceptionFactory.php) centraliza la traducción:

```php
public static function fromReply(
    array $reply,
    string $endpoint,
    array $params = [],
    ?string $requestId = null
): ?ApiException
```

- `300`, `301`, `302` → `null` (éxito, con o sin warning).
- `252`, `253`, `255`, `256` → `null` (estado idempotente ya aplicado).
- `108` → `ValidationException`.
- `110` → `AuthenticationException`.
- `114`, `200`, `261`, `262`, `267` → `InvalidDomainException`.
- `119` → `InsufficientFundsException` (lee `required_amount` y `balance` si vienen).
- `280` → `DNSException`.
- Resto → `ApiException` con `getApiCode()`.

El cliente llama a la fábrica después de decodificar la respuesta y antes de devolver el `reply` a los servicios.

## 6. Ejemplo de uso

```php
use Estratos\NameSiloBundle\Exception\ApiException;
use Estratos\NameSiloBundle\Exception\AuthenticationException;
use Estratos\NameSiloBundle\Exception\InsufficientFundsException;
use Estratos\NameSiloBundle\Exception\InvalidDomainException;
use Estratos\NameSiloBundle\Exception\RateLimitException;
use Estratos\NameSiloBundle\Exception\TimeoutException;
use Estratos\NameSiloBundle\Exception\ValidationException;

try {
    $result = $domainProvider->register($request);
} catch (AuthenticationException $e) {
    // API key inválida
} catch (InsufficientFundsException $e) {
    $required = $e->getRequiredAmount();
    $available = $e->getAvailableBalance();
} catch (InvalidDomainException $e) {
    $errors = $e->getErrors();
} catch (RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
} catch (TimeoutException $e) {
    $timeout = $e->getTimeout();
} catch (ValidationException $e) {
    $errors = $e->getErrors();
} catch (ApiException $e) {
    $endpoint = $e->getEndpoint();
    $status = $e->getStatusCode();
    $requestId = $e->getRequestId();
}
```

El orden importa: las excepciones más específicas van antes que `ApiException`. `InvalidDomainException` e `InvalidContactException` deben ir antes que `ValidationException`; `DNSException` no es un `ValidationException`, así que necesita su propio `catch` si se quiere distinguir.

## 7. Recomendaciones

1. **Una sola fábrica de errores.** Ya implementada en `ApiExceptionFactory`.
2. **Código original.** `ApiException::getApiCode()` ya expone el código numérico de NameSilo.
3. **Mensajes.** `InsufficientFundsException` y `TimeoutException` conservan su mensaje base y añaden el contexto recibido.
4. **Definir el `requestId`.** Si NameSilo no lo devuelve, generar un UUID por petición y registrarlo en logs; así se correlacionan incidencias.
5. **Normalizar estados.** Mapear `Active`, `Yes`, `No`, etc. a enums o booleanos en el mapper, no en los servicios.
6. **Evitar la colisión de nombres.** Usar alias al importar `Enum\LockStatus` y `DTO\LockStatus` (o eliminar los enums si no se usan).
7. **Códigos idempotentes.** Tratar `252`, `253`, `255`, `256` como éxito; no convertirlos en excepciones.
8. **No usar `ApiResponseCode` como si fueran los códigos de NameSilo.** Agregar la traducción explícita.
