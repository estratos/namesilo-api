# NameSilo Bundle for Symfony

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D7.0-333333.svg)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

NameSilo API integration for Symfony, built on top of the contracts from [`estratos/domain-api-interface`](https://github.com/estratos/domain-api-interface).

> **Status:** the core implementation is complete: HTTP client, response mapper, the 7 contract services, events, cache, retries and tests. The package is published on Packagist as [`estratos/namesilo-api`](https://packagist.org/packages/estratos/namesilo-api). The latest tagged release (`1.0.0`) predates this repair, so use `dev-main` until a new tag is published.

## Overview

The bundle adapts the NameSilo API to a provider-agnostic domain API:

- Domain registration, renewal, transfer and queries.
- DNS record management.
- Contact profiles.
- Account balance and prices.
- WHOIS privacy.
- Domain lock/unlock.
- Name server changes.

Applications depend on the interfaces, not on NameSilo. Another provider can replace this bundle without changing business logic.

## Architecture

```text
Application
    │ injects DomainProviderInterface, DNSProviderInterface, ...
    ▼
estratos/domain-api-interface
    │ contracts, DTOs and request objects
    ▼
estratos/namesilo-api
    ├── DomainService, DNSService, ContactService, AccountService,
    │   PrivacyService, LockService, NameServerService
    ├── ResponseMapper
    ├── NameSiloClient (HTTP, JSON/XML, retry, cache, logging)
    └── EventDispatcher
    │
    ▼
NameSilo API (https://www.namesilo.com/api/)
```

## Features

- ✅ **Contract-based** — implements 23 methods across 7 interfaces.
- ✅ **Full transport layer** — GET requests, JSON and XML responses, error mapping.
- ✅ **Typed errors** — authentication, insufficient funds, invalid domain/contact, rate limit, timeout, DNS and validation exceptions.
- ✅ **Events** — 13 domain/DNS/contact/privacy/lock events.
- ✅ **Cache** — PSR-6 cache for read-only endpoints, with invalidation after mutations.
- ✅ **Retries** — configurable HTTP retries with exponential backoff.
- ✅ **Logging** — PSR-3 with API key masking.
- ✅ **Sandbox** — switches to the NameSilo sandbox host when requested.
- ✅ **Tests** — PHPUnit suite with `MockHttpClient`; PHPStan level `max`.

## Requirements

- PHP 8.3 or newer.
- Symfony 7.x.
- `ext-simplexml` only if you use `response_type: xml`.

## Installation

```bash
composer require estratos/namesilo-api:dev-main
```

Once a new release containing the repaired implementation is tagged, the stable command will be:

```bash
composer require estratos/namesilo-api
```

Register the bundle:

```php
// config/bundles.php
return [
    // ...
    Estratos\NameSiloBundle\NameSiloBundle::class => ['all' => true],
];
```

Add the API key to `.env` or to Symfony secrets:

```dotenv
NAMESILO_API_KEY=your_api_key_here
NAMESILO_SANDBOX=false
```

Create `config/packages/namesilo.yaml`:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: '%env(bool:NAMESILO_SANDBOX)%'
```

That is the minimal configuration. Every other option has a default.

## Configuration

Full reference:

```yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: false
    base_url: 'https://www.namesilo.com/api/'
    timeout: 30
    version: '1'
    response_type: 'json' # json or xml

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

See the [configuration reference](docs/02-configuracion.md) for defaults, ranges and caveats.

## Usage

### Domain registration

```php
use Estratos\DomainApiInterface\Domain\DomainProviderInterface;
use Estratos\DomainApiInterface\Request\RegisterDomainRequest;

final class DomainController
{
    public function register(DomainProviderInterface $domainProvider): void
    {
        $availability = $domainProvider->checkAvailability('example.com');

        if (!$availability->isAvailable()) {
            throw new \RuntimeException('Domain is not available.');
        }

        $result = $domainProvider->register(new RegisterDomainRequest(
            domain: 'example.com',
            years: 1,
            contactId: '12345',
            private: true,
            autoRenew: true,
        ));

        if (!$result->isSuccess()) {
            throw new \RuntimeException($result->errorMessage ?? 'Registration failed.');
        }
    }
}
```

### DNS records

```php
use Estratos\DomainApiInterface\DNS\DNSProviderInterface;
use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\DomainApiInterface\Request\AddDnsRecordRequest;

final class DnsController
{
    public function addRecord(DNSProviderInterface $dnsProvider): void
    {
        $record = $dnsProvider->addRecord(new AddDnsRecordRequest(
            domain: 'example.com',
            host: 'www',
            type: RecordType::A,
            value: '192.0.2.1',
            ttl: 3600,
        ));
    }
}
```

### Account balance

```php
use Estratos\DomainApiInterface\Account\AccountProviderInterface;

final class AccountController
{
    public function balance(AccountProviderInterface $accountProvider): array
    {
        $balance = $accountProvider->getBalance();

        return [
            'balance' => $balance->balance,
            'currency' => $balance->currency,
            'available' => $balance->available,
        ];
    }
}
```

### Events

```php
use Estratos\NameSiloBundle\Event\DomainRegisteredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DomainSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DomainRegisteredEvent::class => 'onDomainRegistered',
        ];
    }

    public function onDomainRegistered(DomainRegisteredEvent $event): void
    {
        $domain = $event->getDomain();
        $orderId = $event->getOrderId();
        $expiresAt = $event->getExpirationDate();
    }
}
```

## Error handling

```php
use Estratos\DomainApiInterface\Request\RegisterDomainRequest;
use Estratos\NameSiloBundle\Exception\ApiException;
use Estratos\NameSiloBundle\Exception\AuthenticationException;
use Estratos\NameSiloBundle\Exception\InsufficientFundsException;
use Estratos\NameSiloBundle\Exception\InvalidDomainException;
use Estratos\NameSiloBundle\Exception\RateLimitException;
use Estratos\NameSiloBundle\Exception\TimeoutException;
use Estratos\NameSiloBundle\Exception\ValidationException;

$request = new RegisterDomainRequest(
    domain: 'example.com',
    years: 1,
    contactId: '12345',
);

try {
    $result = $domainProvider->register($request);
} catch (AuthenticationException $e) {
    // Invalid API key or IP not allowed.
} catch (InsufficientFundsException $e) {
    // $e->getRequiredAmount(), $e->getAvailableBalance()
} catch (InvalidDomainException $e) {
    // $e->getErrors()
} catch (RateLimitException $e) {
    // $e->getRetryAfter()
} catch (TimeoutException $e) {
    // $e->getTimeout()
} catch (ValidationException $e) {
    // $e->getErrors()
} catch (ApiException $e) {
    // $e->getEndpoint(), $e->getStatusCode(), $e->getApiCode(), $e->getRequestId()
}
```

`ApiException::getApiCode()` returns the original numeric NameSilo code when available (`119`, `280`, ...).

## Testing

```bash
composer test
```

The suite uses `MockHttpClient`; no real API calls are made.

## Static analysis and style

```bash
composer phpstan
composer php-cs-fixer
composer php-cs-fixer-fix
```

## Documentation

Detailed technical documentation (Spanish):

- [Architecture](docs/01-arquitectura.md)
- [Configuration reference](docs/02-configuracion.md)
- [Contracts and NameSilo API mapping](docs/03-contratos-y-api.md)
- [Exceptions and enums](docs/04-excepciones-y-enums.md)
- [Current state and next steps](docs/05-estado-y-roadmap.md)

## Notes and current limitations

- The latest Packagist release (`1.0.0`) predates this repair; use `dev-main` or a new tag.
- Read-only flows (balance, prices, domains, contacts, availability and domain info) were validated against the production API on 2026-10-24. Mutation flows and the sandbox remain unvalidated.
- NameSilo does not expose every field required by the contracts. The mapper derives or defaults some values (`orderId`, `updatedAt`, `available`, `reserved`, `creditLimit`).
- `isDefault` on contacts has no NameSilo equivalent and is ignored with a warning.
- MX priority uses `rrdistance`; SRV values are encoded as `weight:port:target`.

## License

MIT License. See [LICENSE](LICENSE).
