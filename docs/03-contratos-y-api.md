# Contratos, DTOs y API de NameSilo

Este documento conecta tres piezas:

1. Los contratos que el bundle debe implementar (`estratos/domain-api-interface`).
2. Los objetos de datos que recibe y devuelve la aplicación.
3. Las operaciones reales de la API de NameSilo que hay detrás.

> **Importante:** el cliente, el mapper y los 7 servicios están implementados. Las firmas de los contratos y los DTOs son reales (v1.0.0); el mapeo a operaciones NameSilo se contrastó con la [documentación oficial](https://www.namesilo.com/api-reference) y está cubierto por tests con mocks. La validación contra el sandbox real queda pendiente.

## 1. Fuente de los contratos

| Campo | Valor |
|---|---|
| Paquete | `estratos/domain-api-interface` |
| Versión | `1.0.0` (publicada en Packagist) |
| Commit de referencia | `92e6cb5` |
| Namespace raíz | `Estratos\DomainApiInterface\` |
| Licencia | MIT |

Instalación en la aplicación consumidora:

```bash
composer require estratos/domain-api-interface
```

El bundle ya la declara como dependencia runtime (`^1.0`).

## 2. Resumen de contratos

| Contrato | Namespace | Métodos |
|---|---|---|
| `DomainProviderInterface` | `Estratos\DomainApiInterface\Domain` | 8 |
| `DNSProviderInterface` | `Estratos\DomainApiInterface\DNS` | 4 |
| `ContactProviderInterface` | `Estratos\DomainApiInterface\Contact` | 4 |
| `AccountProviderInterface` | `Estratos\DomainApiInterface\Account` | 2 |
| `PrivacyProviderInterface` | `Estratos\DomainApiInterface\Privacy` | 2 |
| `LockProviderInterface` | `Estratos\DomainApiInterface\Lock` | 2 |
| `NameServerProviderInterface` | `Estratos\DomainApiInterface\NameServer` | 1 |
| **Total** | | **23** |

## 3. Contratos y mapeo a NameSilo

### 3.1 `DomainProviderInterface`

Propósito: registro, renovación, transferencia y consulta de dominios.

```php
public function checkAvailability(string $domain): DomainAvailability;
public function register(RegisterDomainRequest $request): RegistrationResult;
public function renew(RenewDomainRequest $request): RegistrationResult;
public function transfer(TransferDomainRequest $request): RegistrationResult;
public function getTransferStatus(string $domain): TransferStatus;
public function getInfo(string $domain): DomainInfo;
public function list(): array;                    // DomainSummary[]
public function listByExpirationDate(): array;    // DomainSummary[]
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `checkAvailability` | `checkRegisterAvailability` | `domains` | Acepta hasta 200 dominios; el contrato consulta uno. Respuesta con `available`/`unavailable`. |
| `register` | `registerDomain` | `domain`, `years`, `private`, `auto_renew`, `ns1..ns4` | Código `301` = registrado con warning de nameservers; no es fallo total. |
| `renew` | `renewDomain` | `domain`, `years` | Verificar límites de años del TLD. |
| `transfer` | `transferDomain` | `domain`, `years`, `auth`, `private`, `auto_renew` | El nombre exacto del parámetro del código EPP debe confirmarse (`auth` en clientes de referencia). |
| `getTransferStatus` | `checkTransferStatus` | `domain` | Mapear estados a `pending/completed/failed/cancelled`. |
| `getInfo` | `getDomainInfo` | `domain` | Devuelve fechas, estado, lock, privacidad, auto-renew, nameservers y contact IDs. |
| `list` | `listDomains` | `pageSize`, `withBid` (opcionales) | Puede paginar; el contrato no recibe página. |
| `listByExpirationDate` | `listDomainsByExpirationDate` | — | Verificar parámetros opcionales y formato de la respuesta. |

### 3.2 `DNSProviderInterface`

Propósito: gestión de registros DNS.

```php
public function listRecords(string $domain, ?string $type = null): array; // DNSRecord[]
public function addRecord(AddDnsRecordRequest $request): DNSRecord;
public function updateRecord(UpdateDnsRecordRequest $request): DNSRecord;
public function deleteRecord(DeleteDnsRecordRequest $request): bool;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `listRecords` | `dnsListRecords` | `domain` | El filtro `type` no parece ser nativo; aplicar en cliente. |
| `addRecord` | `dnsAddRecord` | `domain`, `rrtype`, `rrhost`, `rrvalue`, `rrttl`, `rrdistance` | La prioridad MX va en `rrdistance`; SRV se codifica como `weight:port:target` en `rrvalue`. |
| `updateRecord` | `dnsUpdateRecord` | `domain`, `rrid`, `rrhost`, `rrvalue`, `rrttl`, `rrdistance` | NameSilo no permite cambiar el tipo; el request incluye `type` solo para el DTO de retorno. |
| `deleteRecord` | `dnsDeleteRecord` | `domain`, `rrid` | Confirmar que el `recordId` del DTO es el `rrid`. |

### 3.3 `ContactProviderInterface`

Propósito: perfiles de contacto.

```php
public function list(): array;                    // Contact[]
public function add(AddContactRequest $request): Contact;
public function update(UpdateContactRequest $request): Contact;
public function delete(string $contactId): bool;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `list` | `contactList` | — | Puede devolver un objeto único o una lista; normalizar. |
| `add` | `contactAdd` | `fn`, `ln`, `ad`, `cy`, `st`, `zp`, `ct`, `em`, `ph`, `cp`, `ad2`, `fx` | `organization` → `cp`, `address2` → `ad2`, `fax` → `fx`; `isDefault` no tiene parámetro y se ignora con warning. |
| `update` | `contactUpdate` | `contact_id` + campos | Mismos campos que `contactAdd`. |
| `delete` | `contactDelete` | `contact_id` | Confirmar comportamiento si el contacto está asociado a un dominio. |

### 3.4 `AccountProviderInterface`

Propósito: saldo y precios.

```php
public function getBalance(): AccountBalance;
public function getPrices(?string $domainType = null): PriceList;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `getBalance` | `getAccountBalance` | — | Devuelve `balance` como string; `available`, `reserved` y `creditLimit` no vienen directamente. |
| `getPrices` | `getPrices` | `type` (opcional) | La respuesta es anidada; hay que aplanarla a `array<string, float>`. |

### 3.5 `PrivacyProviderInterface`

Propósito: privacidad WHOIS.

```php
public function addPrivacy(string $domain, string $contactId): PrivacyStatus;
public function removePrivacy(string $domain): PrivacyStatus;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `addPrivacy` | `addPrivacy` | `domain` | El `contactId` del contrato no parece tener parámetro directo en NameSilo; verificar si se ignora o se usa para asociación local. |
| `removePrivacy` | `removePrivacy` | `domain` | — |

### 3.6 `LockProviderInterface`

Propósito: bloqueo de transferencia.

```php
public function lock(string $domain): LockStatus;
public function unlock(string $domain): LockStatus;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `lock` | `domainLock` | `domain` | Código `252` = ya bloqueado; tratarlo como éxito idempotente. |
| `unlock` | `domainUnlock` | `domain` | Código `253` = ya desbloqueado; tratarlo como éxito idempotente. |

### 3.7 `NameServerProviderInterface`

Propósito: cambiar nameservers.

```php
public function changeNameServers(string $domain, array $nameServers): bool;
```

| Método | Operación NameSilo | Parámetros principales | Notas |
|---|---|---|---|
| `changeNameServers` | `changeNameServers` | `domain`, `ns1..ns4` | NameSilo permite lotes de hasta 200 dominios; el contrato es de uno. Validar entre 2 y 4 nameservers. |

## 4. DTOs

Todos son `final readonly` y viven en `Estratos\DomainApiInterface\DTO`.

### 4.1 `DomainAvailability`

| Campo | Tipo | Notas |
|---|---|---|
| `domain` | `string` | Dominio consultado. |
| `available` | `bool` | Disponibilidad. |
| `reason` | `?string` | Motivo si no está disponible. |
| `suggestedAlternative` | `?string` | Alternativa sugerida; NameSilo no la devuelve directamente. |

Métodos: `isAvailable()`, `isUnavailable()`.

### 4.2 `DomainInfo`

| Campo | Tipo | Notas |
|---|---|---|
| `domain` | `string` | |
| `status` | `string` | NameSilo devuelve `Active`, `Expired`, etc.; normalizar. |
| `createdAt` | `DateTimeImmutable` | `created` de NameSilo. |
| `expiresAt` | `DateTimeImmutable` | `expires`. |
| `updatedAt` | `DateTimeImmutable` | NameSilo puede no devolverlo; usar `expires`/`created` o la fecha de la consulta como fallback documentado. |
| `isLocked` | `bool` | `locked` (`Yes`/`No`). |
| `isPrivacyEnabled` | `bool` | `private` (`Yes`/`No`). |
| `isAutoRenewEnabled` | `bool` | `auto_renew` (`Yes`/`No`). |
| `nameServers` | `array` | Lista de nameservers. |
| `registrantContactId` | `?string` | `contact_ids.registrant`. |
| `adminContactId` | `?string` | `contact_ids.administrative`. |
| `techContactId` | `?string` | `contact_ids.technical`. |
| `billingContactId` | `?string` | `contact_ids.billing`. |

Métodos: `isActive()`, `isExpired()`, `daysUntilExpiration()`, `isLocked()`, `isPrivacyEnabled()`, `hasCustomNameServers()`.

### 4.3 `DomainSummary`

| Campo | Tipo |
|---|---|
| `domain` | `string` |
| `status` | `string` |
| `createdAt` | `DateTimeImmutable` |
| `expiresAt` | `DateTimeImmutable` |
| `isLocked` | `bool` |
| `isPrivacyEnabled` | `bool` |

Métodos: `isActive()`, `isExpired()`.

### 4.4 `RegistrationResult`

| Campo | Tipo | Notas |
|---|---|---|
| `success` | `bool` | |
| `domain` | `string` | |
| `orderId` | `string` | NameSilo puede no devolver un order ID; usar el identificador de respuesta o generar uno documentado. |
| `registrationDate` | `DateTimeImmutable` | |
| `expirationDate` | `DateTimeImmutable` | |
| `transactionId` | `?string` | |
| `errorMessage` | `?string` | |

Métodos: `isSuccess()`, `isFailure()`.

### 4.5 `TransferStatus`

| Campo | Tipo |
|---|---|
| `domain` | `string` |
| `status` | `string` |
| `initiatedAt` | `DateTimeImmutable` |
| `completedAt` | `?DateTimeImmutable` |
| `expiresAt` | `?DateTimeImmutable` |
| `message` | `?string` |

Métodos: `isPending()`, `isCompleted()`, `isFailed()`, `isCancelled()`.

### 4.6 `DNSRecord`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | `string` | `record_id` de NameSilo. |
| `host` | `string` | |
| `type` | `RecordType` | Enum del paquete de contratos. |
| `value` | `string` | |
| `ttl` | `int` | |
| `domain` | `?string` | |
| `priority` | `?int` | MX/SRV. |
| `port` | `?int` | SRV. |
| `weight` | `?int` | SRV. |

Métodos: `isA()`, `isAAAA()`, `isCNAME()`, `isMX()`, `hasPriority()`.

### 4.7 `Contact`

| Campo | Tipo |
|---|---|
| `id` | `string` |
| `firstName` | `string` |
| `lastName` | `string` |
| `email` | `string` |
| `phone` | `string` |
| `organization` | `string` |
| `address1` | `string` |
| `city` | `string` |
| `state` | `string` |
| `postalCode` | `string` |
| `country` | `string` |
| `address2` | `?string` |
| `fax` | `?string` |
| `isDefault` | `?bool` |

Métodos: `getFullName()`, `getFullAddress()`.

### 4.8 `AccountBalance`

| Campo | Tipo | Notas |
|---|---|---|
| `balance` | `float` | `balance` de NameSilo, convertir a float. |
| `currency` | `string` | NameSilo suele usar USD; confirmar. |
| `available` | `float` | No viene directamente; puede ser igual a `balance`. |
| `reserved` | `float` | No viene; usar `0.0` si no hay dato. |
| `creditLimit` | `?float` | No viene; `null`. |

Métodos: `isPositive()`, `isNegative()`, `hasSufficientFunds(float $amount)`.

### 4.9 `PriceList`

| Campo | Tipo | Notas |
|---|---|---|
| `prices` | `array` | Mapa TLD/precio; aplanar la respuesta anidada de NameSilo. |
| `currency` | `string` | |

Métodos: `getPrice(string $tld)`, `getPricesByCategory(string $category)`.

### 4.10 `PrivacyStatus` (DTO)

| Campo | Tipo |
|---|---|
| `domain` | `string` |
| `enabled` | `bool` |
| `status` | `?string` |
| `message` | `?string` |

Métodos: `isEnabled()`, `isDisabled()`.

### 4.11 `LockStatus` (DTO)

| Campo | Tipo |
|---|---|
| `domain` | `string` |
| `locked` | `bool` |
| `status` | `?string` |
| `message` | `?string` |

Métodos: `isLocked()`, `isUnlocked()`.

## 5. Request objects

Todos son `final readonly` y validan en el constructor; lanzan `InvalidArgumentException` si los datos no son válidos.

### 5.1 `RegisterDomainRequest`

| Campo | Tipo | Default | Validación |
|---|---|---|---|
| `domain` | `string` | — | No vacío |
| `years` | `int` | — | `1..10` |
| `contactId` | `string` | — | No vacío |
| `private` | `bool` | `false` | — |
| `autoRenew` | `bool` | `false` | — |
| `nameserver1..4` | `?string` | `null` | — |

### 5.2 `RenewDomainRequest`

| Campo | Tipo | Validación |
|---|---|---|
| `domain` | `string` | No vacío |
| `years` | `int` | `1..10` |

### 5.3 `TransferDomainRequest`

| Campo | Tipo | Default | Validación |
|---|---|---|---|
| `domain` | `string` | — | No vacío |
| `authorizationCode` | `string` | — | No vacío |
| `years` | `int` | `1` | `1..10` |
| `private` | `bool` | `false` | — |
| `autoRenew` | `bool` | `false` | — |

### 5.4 `AddDnsRecordRequest`

| Campo | Tipo | Default | Validación |
|---|---|---|---|
| `domain` | `string` | — | No vacío |
| `host` | `string` | — | No vacío |
| `type` | `RecordType` | — | — |
| `value` | `string` | — | No vacío |
| `ttl` | `int` | `3600` | `60..86400` |
| `priority` | `?int` | `null` | Obligatorio para MX/SRV |
| `port` | `?int` | `null` | Obligatorio para SRV |
| `weight` | `?int` | `null` | Obligatorio para SRV |

### 5.5 `UpdateDnsRecordRequest`

Mismos campos que `AddDnsRecordRequest` más `recordId` (string, no vacío).

### 5.6 `DeleteDnsRecordRequest`

| Campo | Tipo | Validación |
|---|---|---|
| `domain` | `string` | No vacío |
| `recordId` | `string` | No vacío |

### 5.7 `AddContactRequest`

| Campo | Tipo | Default | Validación |
|---|---|---|---|
| `firstName` | `string` | — | No vacío |
| `lastName` | `string` | — | No vacío |
| `email` | `string` | — | Formato de email |
| `phone` | `string` | — | No vacío |
| `organization` | `string` | — | No vacío |
| `address1` | `string` | — | No vacío |
| `city` | `string` | — | No vacío |
| `state` | `string` | — | No vacío |
| `postalCode` | `string` | — | No vacío |
| `country` | `string` | — | No vacío |
| `address2` | `?string` | `null` | — |
| `fax` | `?string` | `null` | — |
| `isDefault` | `bool` | `false` | — |

### 5.8 `UpdateContactRequest`

Mismos campos que `AddContactRequest` más `contactId` (string, no vacío) como primer argumento.

## 6. Protocolo de NameSilo

### 6.1 Formato de petición

```text
https://www.namesilo.com/api/OPERATION?version=VERSION&type=TYPE&key=YOURAPIKEY
```

- **Solo GET.** La documentación oficial lo marca como obligatorio.
- `version=1` actualmente.
- `type=json` o `type=xml`.
- La API key viaja en la query string (`key`).
- Los parámetros de cada operación se añaden a la misma query string.

Sandbox: NameSilo ofrece credenciales de prueba separadas. Los clientes de referencia usan `http://sandbox.namesilo.com/api/`; verifica la URL y el esquema con NameSilo antes de usarla en producción. El bundle solo tiene `base_url` y `sandbox`, así que la decisión de host debe quedar explícita.

### 6.2 Envoltura de respuesta

JSON:

```json
{
    "reply": {
        "code": 300,
        "detail": "success",
        "domain": "example.com"
    }
}
```

XML:

```xml
<namesilo>
    <reply>
        <code>300</code>
        <detail>success</detail>
        <domain>example.com</domain>
    </reply>
</namesilo>
```

El cliente debe devolver el contenido de `reply` (incluyendo `code` y `detail`) para que los servicios puedan comprobar el resultado.

### 6.3 Códigos

`code` es numérico. Los códigos `300`, `301` y `302` son éxitos:

| Código | Significado | Tratamiento |
|---|---|---|
| `300` | Operación exitosa | Éxito. |
| `301` | Registro exitoso, pero no todos los nameservers eran válidos; se usaron los de NameSilo | Éxito con warning; no lanzar excepción. |
| `302` | Orden exitosa, pero el contacto era inválido y se usó el perfil por defecto | Éxito con warning; registrar el aviso. |

Códigos de error relevantes:

| Código | Significado | Excepción propuesta |
|---|---|---|
| `108` | Faltan parámetros | `ValidationException` |
| `110` | API key inválida | `AuthenticationException` |
| `114` | Sintaxis de dominio inválida | `InvalidDomainException` |
| `119` | Fondos insuficientes | `InsufficientFundsException` |
| `200` | Dominio no activo o no pertenece al usuario | `InvalidDomainException` / `ApiException` |
| `280` | Error de modificación DNS | `DNSException` |
| `400` | Error de petición a la API | `ApiException` |

La lista completa ronda los 40 códigos (versiones, tipo, operación, IP, contacto, renovación, transferencia, etc.). Los clientes de referencia los mapean en una tabla; conviene replicar esa tabla en una clase dedicada en lugar de repartir `if` por los servicios.

### 6.4 Formatos de datos

| Dato | Formato NameSilo | Conversión |
|---|---|---|
| Fechas | `YYYY-MM-DD` | `DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'))` |
| Booleanos | `Yes` / `No` | Comparación case-insensitive |
| Precios/saldos | String con comas (`1,234.56`) | Quitar comas y castear a `float` |
| Listas | Objeto único o array | Normalizar a array |
| Nameservers | Lista de objetos `nameserver` | Extraer valores |
| Contact IDs | Objeto `contact_ids` con claves `registrant`, `administrative`, `technical`, `billing` | Mapear a los campos del DTO |

Ejemplo de normalización de lista:

```php
$records = $payload['resource_record'] ?? [];
if (isset($records['record_id'])) {
    $records = [$records]; // un solo registro
}
```

### 6.5 Codificación

- Usa `http_build_query($params, '', '&', PHP_QUERY_RFC3986)` para que los espacios se codifiquen como `%20`.
- No envíes la API key en el cuerpo: NameSilo espera GET.
- No reutilices una URL con la key en logs, caché ni excepciones.

## 7. Riesgos de mapeo

Estos puntos deben resolverse durante la implementación y cubrirse con fixtures/tests:

1. **`DomainSummary` puede requerir N+1.** `listDomains` no necesariamente devuelve lock, privacidad y fechas completas. Si faltan, el servicio tendrá que llamar a `getDomainInfo` por dominio o documentar que los campos no disponibles se devuelven con valores derivados.
2. **`updatedAt` no siempre existe.** `getDomainInfo` devuelve `created` y `expires`, pero no un `updated` claro. Define un fallback explícito (por ejemplo, `expires` o la fecha de consulta) y documéntalo.
3. **`orderId` y `transactionId`.** NameSilo puede devolver `order_amount` y `domain`, pero no un identificador de orden estable. Define cómo se rellenará `RegistrationResult::orderId` (campo de la respuesta, UUID local o cadena vacía documentada).
4. **`AccountBalance` derivado.** `available`, `reserved` y `creditLimit` no vienen en `getAccountBalance`. Usa `available = balance`, `reserved = 0.0`, `creditLimit = null` y documenta que son valores derivados.
5. **`PriceList` anidado.** `getPrices` devuelve estructuras por categoría/TLD. Aplana a `array<string, float>` y define la clave (TLD con o sin punto).
6. **Contactos con campos no soportados.** `organization`, `address2` y `fax` se mapean a `cp`, `ad2` y `fx`; `isDefault` no tiene equivalente y se ignora con un warning. Si NameSilo rechaza algún campo, habrá que ajustar el mapeo.
7. **Privacidad con `contactId`.** `addPrivacy` de NameSilo solo recibe `domain`. El `contactId` del contrato puede no usarse; no lo inventes en la query.
8. **DNS con prioridad/puerto/peso.** La prioridad MX usa `rrdistance`; los valores SRV se codifican en `rrvalue` como `weight:port:target`. El mapper los separa al leer.
9. **Transferencias.** Confirma el nombre del parámetro del código EPP y los estados de `checkTransferStatus`.
10. **Paginación.** `listDomains` y `listDomainsByExpirationDate` pueden paginar; el contrato no recibe página. Decide si el servicio itera todas las páginas (coste) o si documenta un límite.
11. **Errores idempotentes.** `252` (ya bloqueado), `253` (ya desbloqueado), `255` (ya privado), `256` (ya no privado) deberían tratarse como éxito idempotente, no como fallo.
12. **Reintentos.** NameSilo devuelve errores de negocio con HTTP 200; no reintentes a ciegas operaciones que cobran.

## 8. Estrategia de implementación sugerida

### 8.1 Cliente

```php
final class NameSiloClient implements NameSiloClientInterface
{
    public function request(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        // 1. Validar method === GET.
        // 2. Construir query con version, type, key + params.
        // 3. Enviar con HttpClientInterface (inyectado).
        // 4. Decodificar JSON (o XML si se soporta).
        // 5. Devolver reply como array.
    }
}
```

Requisitos:

- Inyectar `HttpClientInterface` y `LoggerInterface`; no crear el cliente dentro.
- Aplicar timeout, reintentos y logging con las reglas de [02-configuracion.md](02-configuracion.md).
- No lanzar excepciones de negocio dentro del cliente si se quiere que el mapper decida; o lanzar `ApiException` con código/endpoint y centralizar el mapeo. Elige una estrategia y sé consistente.
- Devolver `reply` con `code` y `detail` intactos.

### 8.2 Mapper

Métodos sugeridos (nombres orientativos):

```php
toDomainAvailability(string $domain, array $reply): DomainAvailability
toDomainInfo(string $domain, array $reply): DomainInfo
toDomainSummary(array $reply): DomainSummary
toRegistrationResult(array $reply): RegistrationResult
toTransferStatus(string $domain, array $reply): TransferStatus
toDnsRecord(array $reply): DNSRecord
toContact(array $reply): Contact
toAccountBalance(array $reply): AccountBalance
toPriceList(array $reply): PriceList
toPrivacyStatus(string $domain, array $reply): PrivacyStatus
toLockStatus(string $domain, array $reply): LockStatus
```

Cada método debe ser puro (sin HTTP) y testeable con arrays de fixture.

### 8.3 Servicios

Patrón para una operación:

```php
public function getInfo(string $domain): DomainInfo
{
    $reply = $this->client->request('getDomainInfo', ['domain' => $domain]);
    $info = $this->mapper->toDomainInfo($domain, $reply);

    return $info;
}
```

Para mutaciones, añadir después del mapper:

```php
if ($this->eventsEnabled('domain_registered')) {
    $this->dispatcher->dispatch(new DomainRegisteredEvent(...));
}
```

Nunca despachar antes de confirmar el éxito de NameSilo.

### 8.4 Pruebas

- **Unitarias del cliente:** `MockHttpClient` con respuestas JSON de fixture; verificar URL, query, timeout, retry y logging.
- **Unitarias del mapper:** fixtures de NameSilo → DTO esperado; casos de listas simples, booleanos `Yes`/`No`, fechas, precios con comas.
- **Unitarias de servicios:** mock de `NameSiloClientInterface` + mapper real; verificar operación, parámetros y eventos.
- **Contract tests:** una suite que recorra los 7 contratos y compruebe que cada servicio los implementa (reflexión) y que los 23 métodos existen.
- **Integración opcional:** sandbox de NameSilo, marcada y excluida por defecto.

## 9. Ejemplo de flujo completo (ilustrativo)

```php
// Aplicación
$result = $domainProvider->register(new RegisterDomainRequest(
    domain: 'example.com',
    years: 1,
    contactId: '12345',
    private: true,
    autoRenew: true,
));

// DomainService
$reply = $this->client->request('registerDomain', [
    'domain' => $request->domain,
    'years' => $request->years,
    'private' => $request->private ? 1 : 0,
    'auto_renew' => $request->autoRenew ? 1 : 0,
]);

$result = $this->mapper->toRegistrationResult($reply);

if ($this->eventsEnabled('domain_registered')) {
    $this->dispatcher->dispatch(new DomainRegisteredEvent(
        domain: $result->domain,
        orderId: $result->orderId,
        registrationDate: $result->registrationDate,
        expirationDate: $result->expirationDate,
    ));
}

return $result;
```

Este código refleja la implementación actual; los nombres exactos pueden variar, pero el flujo es el que ejecutan los servicios.
