# Estado y próximos pasos

## 1. Resumen

El bundle fue reparado y ahora es funcional en una aplicación Symfony que lo instale por VCS:

- Se corrigió el nombre PSR-4 del bundle (`src/NameSiloBundle.php`).
- Se unificó el wiring en `services.php`.
- Se eliminaron `services.yaml` (de otro proyecto) y `RequestService` (legado con cURL).
- Se implementaron `NameSiloClient`, `ResponseMapper` y los 7 servicios de contrato.
- Se añadieron 13 eventos, `ApiExceptionFactory`, caché, reintentos, logging y soporte XML.
- Se añadieron tests, PHPUnit, PHPStan y PHP-CS-Fixer.

## 2. Verificación

| Comprobación | Resultado |
|---|---|
| `php -l` sobre `src/` y `tests/` | Sin errores |
| PHPUnit | 30 tests, 116 aserciones, OK |
| PHPStan | Nivel `max`, sin errores |
| `composer validate` | `composer.json` válido |
| Contenedor Symfony | Compila; los 7 contratos y `NameSiloClient` se resuelven |
| Autoload PSR-4 | `NameSiloBundle`, cliente, mapper y servicios se cargan correctamente |

Los tests usan `MockHttpClient` y un cliente de grabación; no hacen llamadas reales a NameSilo.

## 3. Hallazgos resueltos

| Antes | Ahora |
|---|---|
| Faltaban 9 clases registradas por la extensión | Implementadas |
| `NameSiloBundle` no era autoloadable | Archivo renombrado y PSR-4 correcto |
| Doble wiring (`services.php` + extensión) | Una sola fuente: `services.php` |
| `services.yaml` de `Estratos\DomainNameApi` | Eliminado |
| `RequestService` con namespace antiguo y cURL | Eliminado |
| Sin tests ni herramientas | PHPUnit, PHPStan y CS-Fixer configurados |
| `ApiResponseCode` no mapeaba códigos numéricos | `ApiExceptionFactory` + `getApiCode()` |
| Caché y reintentos solo de configuración | Implementados en el cliente |
| Eventos sin clases | 13 eventos implementados |
| XML no soportado | Soportado con SimpleXML |
| README con estado falso | Reescrito |

## 4. Pendientes

### P1 — Distribución

- **Publicar en Packagist.** Hoy `composer require estratos/namesilo-bundle` falla porque el paquete no existe en Packagist. Mientras tanto, la instalación por VCS está documentada en [docs/README.md](README.md#5-instalación).
- **Validar en sandbox.** Ejecutar un flujo real de consulta, registro, DNS y lock contra el sandbox de NameSilo. Los tests actuales son unitarios/integración con mocks.

### P2 — Robustez de la API

- **Paginación de `listDomains` / `listDomainsByExpirationDate`.** El contrato no recibe página; si NameSilo pagina, el servicio debe iterar o documentar el límite.
- **`DomainSummary` y N+1.** Si `listDomains` no devuelve lock/privacidad/fechas, el mapper usa valores derivados. Evaluar si conviene llamar a `getDomainInfo` por dominio o aceptar el coste.
- **`orderId` y `transactionId`.** NameSilo no siempre devuelve un identificador de orden; el servicio devuelve cadena vacía si no existe. Definir si se generará un UUID local.
- **`AccountBalance` derivado.** `available = balance`, `reserved = 0.0`, `creditLimit = null` cuando NameSilo no los envía.
- **Contactos especiales.** `isDefault` se ignora con warning; `organization`, `address2` y `fax` se mapean a `cp`, `ad2` y `fx`. Verificar en sandbox que NameSilo los acepta.
- **Transferencias.** Confirmar el nombre del parámetro del código EPP y los estados reales de `checkTransferStatus`.

### P2 — Enums

Los enums `ApiResponseCode`, `DomainStatus`, `LockStatus` y `PrivacyStatus` no se usan en el flujo principal:

- `ApiResponseCode` sigue siendo simbólico y no representa los códigos numéricos de NameSilo.
- `DomainStatus`, `LockStatus` y `PrivacyStatus` colisionan en nombre con DTOs del contrato.

Opciones:

1. Integrarlos en el mapper y usarlos como vocabulario de dominio.
2. Eliminarlos para evitar duplicación.

Recomendación: mantener los que aporten valor (`DomainStatus`) y eliminar los que solo dupliquen DTOs, o documentar claramente que son internos.

### P2 — Calidad continua

- Añadir GitHub Actions con PHP 8.3/8.4 y Symfony 7.x.
- Ejecutar PHPUnit, PHPStan y PHP-CS-Fixer en CI.
- Añadir cobertura y un umbral mínimo.
- Añadir tests de integración opcionales contra sandbox, excluidos por defecto.

### P3 — Casos límite

- TLDs con reglas especiales (`.us`, `.ca`, `.eu`).
- Contactos con campos opcionales y validaciones por país.
- Respuestas con un solo registro vs. lista.
- Errores HTTP sin cuerpo JSON.
- Timeouts y reintentos en operaciones largas.
- Precios con estructuras anidadas distintas.

## 5. Riesgos

1. **Duplicar compras por reintentos.** El cliente no reintenta errores de negocio ni operaciones de compra, pero una capa superior podría hacerlo. Documentar la idempotencia.
2. **Filtrar la API key.** Viaja en la query string; el cliente enmascara la URL en logs, pero la aplicación no debe registrar URLs completas.
3. **Variación de la API real.** Los tests usan fixtures; el sandbox puede devolver estructuras distintas. Validar antes de producción.
4. **Caché obsoleta.** El TTL por defecto es 300 s; para saldo/precios conviene bajarlo. Las mutaciones limpian la caché del namespace.
5. **Datos derivados.** `updatedAt`, `orderId`, `available`, `reserved` y `creditLimit` pueden no venir de NameSilo; están documentados como derivados.

## 6. Próximos pasos recomendados

1. Ejecutar un flujo real contra el sandbox con credenciales de prueba.
2. Ajustar el mapper si la respuesta real difiere de los fixtures.
3. Publicar el paquete en Packagist o mantener la instalación VCS.
4. Añadir CI.
5. Decidir el futuro de los enums.
6. Revisar la paginación y el N+1 en listados.

## 7. Criterios de aceptación

- [x] Contenedor compila y expone los 7 contratos.
- [x] Los 23 métodos están implementados.
- [x] Cliente, mapper y servicios tienen tests.
- [x] PHPStan `max` sin errores.
- [x] `composer validate` correcto.
- [x] README y documentación reflejan el estado real.
- [ ] Paquete publicado en Packagist (o instalación VCS definitiva).
- [ ] Flujo validado contra sandbox.
- [ ] CI configurada.
- [ ] Enums integrados o eliminados.
- [ ] Paginación/N+1 resueltos o documentados.

## 8. Decisiones abiertas

| Decisión | Opciones | Recomendación |
|---|---|---|
| Publicación | Packagist vs. VCS | Packagist si el repo es público. |
| Enums | Integrar vs. eliminar | Mantener `DomainStatus`; eliminar duplicados de DTOs. |
| Paginación | Iterar todo vs. límite documentado | Iterar con límite configurable. |
| `orderId` | Campo de NameSilo vs. UUID local | UUID local si NameSilo no lo garantiza. |
| Caché | TTL global vs. por endpoint | TTL por endpoint en una próxima versión. |
| Contactos | Ignorar campos no soportados vs. rechazar | Rechazar explícitamente lo que NameSilo no puede representar. |
| Sandbox | Tests opcionales vs. suite separada | Suite separada, excluida por defecto. |
