# NameSilo Bundle for Symfony

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D7.0-333333.svg)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Enterprise-grade NameSilo API integration bundle for Symfony 7.4+ applications.

## Overview

This bundle provides a complete, enterprise-ready integration with the NameSilo API for Symfony applications. It implements the contracts defined in [estratos/domain-api-interface](https://github.com/estratos/domain-api-interface), making it pluggable into any application that uses the common domain provider interfaces.

## Architecture
┌─────────────────────────────────────────────────┐
│ Domain API Interface Bundle │
│ (Contracts: DomainProviderInterface, etc.) │
└──────────────────┬──────────────────────────────┘
│ Implements
┌──────────────────▼──────────────────────────────┐
│ NameSilo Bundle │
│ - NameSiloClient (HTTP client) │
│ - Services (Domain, DNS, Contact, etc.) │
│ - ResponseMapper (JSON→DTO) │
│ - Events (DomainRegistered, etc.) │
└──────────────────┬──────────────────────────────┘
│ Uses
┌──────────────────▼──────────────────────────────┐
│ NameSilo API │
│ (https://www.namesilo.com/api/) │
└─────────────────────────────────────────────────┘

text

## Features

- ✅ **Contract-Based Architecture** - Implements domain-api-interface contracts
- ✅ **Full API Coverage** - All NameSilo endpoints implemented
- ✅ **Immutable DTOs** - Read-only data transfer objects
- ✅ **Strong Typing** - PHP 8.3+ with strict_types
- ✅ **Event-Driven** - Symfony events for domain operations
- ✅ **Comprehensive Logging** - PSR-3 compliant with sensitive data masking
- ✅ **Retry Logic** - Automatic retry with exponential backoff
- ✅ **Cache Support** - Configurable caching for non-mutating operations
- ✅ **Error Handling** - Specific exceptions for different scenarios
- ✅ **Sandbox Mode** - Test integration without real transactions
- ✅ **90%+ Test Coverage** - Fully tested with PHPUnit

## Installation

```bash
composer require estratos/namesilo-bundle
Configuration
1. Add to your .env file:
env
NAMESILO_API_KEY=your_api_key_here
NAMESILO_SANDBOX=false
2. Create config/packages/namesilo.yaml:
yaml
namesilo:
    api_key: '%env(NAMESILO_API_KEY)%'
    sandbox: '%env(bool:NAMESILO_SANDBOX)%'
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
    
    events:
        enabled: true
        events_to_dispatch:
            - 'domain_registered'
            - 'domain_renewed'
            - 'dns_record_added'
3. Register the bundle in config/bundles.php:
php
return [
    // ...
    Estratos\NameSiloBundle\NameSiloBundle::class => ['all' => true],
];
Usage Examples
Domain Registration
php
<?php

namespace App\Controller;

use Estratos\DomainApiInterface\Domain\DomainProviderInterface;
use Estratos\DomainApiInterface\Request\RegisterDomainRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DomainController extends AbstractController
{
    public function register(DomainProviderInterface $domainProvider)
    {
        // Check availability first
        $availability = $domainProvider->checkAvailability('example.com');
        
        if (!$availability->isAvailable()) {
            $this->addFlash('error', 'Domain is not available');
            return $this->redirectToRoute('domain_search');
        }
        
        $request = new RegisterDomainRequest(
            domain: 'example.com',
            years: 2,
            contactId: 'CONTACT_123',
            private: true,
            autoRenew: true,
            nameserver1: 'ns1.namesilo.com',
            nameserver2: 'ns2.namesilo.com'
        );
        
        try {
            $result = $domainProvider->register($request);
            
            if ($result->isSuccess()) {
                $this->addFlash('success', sprintf(
                    'Domain %s registered successfully. Order ID: %s',
                    $result->domain,
                    $result->orderId
                ));
            }
        } catch (InsufficientFundsException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (AuthenticationException $e) {
            $this->addFlash('error', 'Authentication failed. Check your API key.');
        }
    }
}
DNS Management
php
<?php

namespace App\Controller;

use Estratos\DomainApiInterface\DNS\DNSProviderInterface;
use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\DomainApiInterface\Request\AddDnsRecordRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DNSController extends AbstractController
{
    public function addRecord(DNSProviderInterface $dnsProvider)
    {
        $request = new AddDnsRecordRequest(
            domain: 'example.com',
            host: 'www',
            type: RecordType::A,
            value: '192.168.1.1',
            ttl: 3600
        );
        
        try {
            $record = $dnsProvider->addRecord($request);
            
            $this->addFlash('success', sprintf(
                'DNS record %s (%s) added successfully',
                $record->host,
                $record->type->value
            ));
            
            return $this->json($record);
        } catch (DNSException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
Account Management
php
<?php

namespace App\Controller;

use Estratos\DomainApiInterface\Account\AccountProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AccountController extends AbstractController
{
    public function balance(AccountProviderInterface $accountProvider)
    {
        $balance = $accountProvider->getBalance();
        
        return $this->json([
            'balance' => $balance->balance,
            'currency' => $balance->currency,
            'available' => $balance->available,
            'reserved' => $balance->reserved,
        ]);
    }
    
    public function prices(AccountProviderInterface $accountProvider)
    {
        $prices = $accountProvider->getPrices();
        
        return $this->json([
            'currency' => $prices->currency,
            'prices' => $prices->prices,
        ]);
    }
}
Event Subscribers
php
<?php

namespace App\EventSubscriber;

use Estratos\NameSiloBundle\Event\DomainRegisteredEvent;
use Estratos\NameSiloBundle\Event\DomainRenewedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DomainEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DomainRegisteredEvent::class => 'onDomainRegistered',
            DomainRenewedEvent::class => 'onDomainRenewed',
        ];
    }
    
    public function onDomainRegistered(DomainRegisteredEvent $event): void
    {
        $domain = $event->getDomain();
        $orderId = $event->getOrderId();
        $registrationDate = $event->getRegistrationDate();
        $expirationDate = $event->getExpirationDate();
        
        // Send notification email
        // Log registration
        // Update database
    }
    
    public function onDomainRenewed(DomainRenewedEvent $event): void
    {
        $domain = $event->getDomain();
        $orderId = $event->getOrderId();
        $newExpirationDate = $event->getNewExpirationDate();
        
        // Update expiration date in database
        // Send renewal confirmation
    }
}
Error Handling
php
try {
    $result = $domainProvider->register($request);
} catch (AuthenticationException $e) {
    // Invalid API key or credentials
} catch (InsufficientFundsException $e) {
    // Not enough funds in account
    // $e->getRequiredAmount()
    // $e->getAvailableBalance()
} catch (InvalidDomainException $e) {
    // Invalid domain name format
    // $e->getErrors()
} catch (RateLimitException $e) {
    // Too many requests
    // $e->getRetryAfter()
} catch (ValidationException $e) {
    // Validation errors in request
    // $e->getErrors()
} catch (ApiException $e) {
    // General API error
    // $e->getEndpoint()
    // $e->getStatusCode()
    // $e->getRequestId()
}
Testing
bash
composer test
PHPStan Analysis
bash
composer phpstan
Code Style
bash
composer php-cs-fixer
composer php-cs-fixer-fix
License
MIT License. See the LICENSE file for details.

Contributing
Fork the repository

Create a feature branch

Write tests for your changes

Ensure all tests pass

Submit a pull request