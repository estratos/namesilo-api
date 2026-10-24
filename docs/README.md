# NameSilo Bundle — Documentación

Documentación técnica de `estratos/namesilo-api`, un bundle de Symfony que implementa los contratos de `estratos/domain-api-interface` sobre la API de NameSilo.

> **Estado:** implementación funcional. El cliente HTTP, el mapper, los 7 servicios, los eventos y la caché están implementados y cubiertos por tests. El paquete está publicado en Packagist como `estratos/namesilo-api`; la etiqueta `1.0.0` es anterior a esta reparación, así que hay que usar `dev-main` hasta publicar una nueva versión.

| Campo | Valor |
|---|---|
| Repositorio | `C:\Users\julio\local-repos\namesilo-api` |
| Paquete Composer | `estratos/namesilo-api` |
| PHP | `>= 8.3` |
| Symfony | `^7.0` |
| Contratos | `estratos/domain-api-interface` `^1.0` |
| Última verificación | 2026-10-23: 30 tests, 116 aserciones, PHPStan `max` sin errores |

## 1. Qué es

El bundle es el adaptador entre una aplicación Symfony y la API de NameSilo. La aplicación consume interfaces genéricas de registrar (`DomainProviderInterface`, `DNSProviderInterface`, etc.) y el bundle se encarga de:

1. Traducir cada llamada del contrato a una operación HTTP de NameSilo.
2. Convertir la respuesta de NameSilo a DTOs inmutables del paquete de contratos.
3. Traducir errores de la API a excepciones tipadas.
4. Aplicar timeout, reintentos, caché, logging y eventos.

La separación de capas es correcta: los contratos y DTOs viven en `estratos/domain-api-interface`; el bundle solo aporta la implementación específica de NameSilo.

## 2. Resumen ejecutivo

### 2.1 Implementado

- `NameSiloBundle` y `NameSiloExtension` con configuración, parámetros y wiring único.
- `NameSiloClient`:
  - GET a NameSilo con `version`, `type` y `key`;
  - respuestas JSON y XML;
  - timeout, reintentos configurables y logging con enmascarado de la API key;
  - caché PSR-6 para endpoints de lectura;
  - modo sandbox.
- `ResponseMapper` para los 11 DTOs del contrato.
- Los 7 servicios de contrato: dominio, DNS, contacto, cuenta, privacidad, lock y nameservers.
- 13 clases de evento y despacho condicionado por `events.enabled` / `events_to_dispatch`.
- `ApiExceptionFactory` con mapeo de códigos NameSilo a excepciones.
- Tests unitarios y de integración: 30 tests, 116 aserciones.
- PHPStan nivel `max` sin errores.

### 2.2 Pendiente

- Publicar una nueva etiqueta en Packagist con la reparación (la `1.0.0` actual es anterior).
- Validar las operaciones reales contra el sandbox de NameSilo (los tests usan fixtures/mocks).
- Revisar los enums `ApiResponseCode`, `DomainStatus`, `LockStatus` y `PrivacyStatus`: existen, pero siguen sin usarse en el flujo principal y no mapean directamente los valores de NameSilo.
- Añadir CI (GitHub Actions) con PHP 8.3/8.4 y Symfony 7.x.
- Completar la cobertura de casos límite de la API (paginación, contactos especiales, TLDs con reglas propias).

### 2.3 Bloqueos

Ninguno para ejecutar el bundle. El paquete está publicado, pero la versión estable `1.0.0` es anterior a la reparación; usa `dev-main` o espera a la nueva etiqueta.

## 3. Estado por componente

| Componente | Estado | Detalle |
|---|---|---|
| `NameSiloBundle` | ✅ | PSR-4 correcto y extensión registrada. |
| `NameSiloExtension` | ✅ | Configuración, parámetros y carga de `services.php`. |
| `Configuration` | ✅ | Árbol completo, con defaults, rangos y validaciones. |
| `NameSiloClientInterface` | ✅ | Contrato de transporte definido. |
| `NameSiloClient` | ✅ | JSON/XML, reintentos, caché, logging y sandbox. |
| `ResponseMapper` | ✅ | 11 DTOs y normalización de datos. |
| Servicios de contrato | ✅ | 7 servicios, 23 métodos. |
| Eventos | ✅ | 13 eventos y despacho configurable. |
| Enums | 🟡 | Existen, pero no se usan en el flujo principal ni mapean los códigos numéricos de NameSilo. |
| Excepciones | ✅ | Jerarquía + fábrica de errores + `getApiCode()`. |
| Tests | ✅ | 30 tests, 116 aserciones. |
| README | ✅ | Reescrito para reflejar el estado real. |

Leyenda: ✅ implementado · 🟡 implementado con deuda · ❌ no existe.

## 4. Mapa de la documentación

| Documento | Contenido |
|---|---|
| [01-arquitectura.md](01-arquitectura.md) | Capas, componentes, ciclo de vida Symfony, wiring y convenciones. |
| [02-configuracion.md](02-configuracion.md) | Referencia completa de `namesilo.yaml`, parámetros, servicios y notas. |
| [03-contratos-y-api.md](03-contratos-y-api.md) | Los 23 métodos, DTOs, requests, protocolo NameSilo y mapeo. |
| [04-excepciones-y-enums.md](04-excepciones-y-enums.md) | Excepciones, códigos, enums y estrategia de errores. |
| [05-estado-y-roadmap.md](05-estado-y-roadmap.md) | Estado, pendientes, riesgos y próximos pasos. |

## 5. Instalación

Mientras la nueva etiqueta no esté publicada:

```bash
composer require estratos/namesilo-api:dev-main
```

Cuando se publique una versión con la reparación, bastará con:

```bash
composer require estratos/namesilo-api
```

Después:

1. Registrar el bundle en `config/bundles.php`:

```php
return [
    // ...
    Estratos\NameSiloBundle\NameSiloBundle::class => ['all' => true],
];
```

2. Crear `config/packages/namesilo.yaml`:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
```

3. Definir `NAMESILO_API_KEY` en `.env` o en los secretos de Symfony.

4. Inyectar los contratos (`DomainProviderInterface`, `DNSProviderInterface`, etc.) en los servicios de la aplicación.

## 6. Convenciones de esta documentación

- **Hechos de código:** se describen con referencia al archivo y línea.
- **Diseño:** cuando se explica una decisión, se indica el motivo.
- **API NameSilo:** las operaciones y parámetros se contrastaron con la [documentación oficial](https://www.namesilo.com/api-reference); los tests usan mocks, no llamadas reales.
- **Estado:** los iconos ✅/🟡/❌ resumen el estado, no una valoración de calidad.

## 7. Glosario

| Término | Significado |
|---|---|
| Contrato | Interfaz de `estratos/domain-api-interface` que el bundle implementa. |
| Proveedor | Servicio que implementa un contrato (p. ej. `DomainService`). |
| Cliente | `NameSiloClient`: capa de transporte HTTP contra NameSilo. |
| Mapper | `ResponseMapper`: convierte la respuesta cruda en DTOs. |
| DTO | Objeto inmutable de transferencia definido en el paquete de contratos. |
| Request | Objeto de entrada validado para una operación de negocio. |
| Operación | Método de la API de NameSilo (p. ej. `registerDomain`). |
