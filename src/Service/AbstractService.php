<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\NameSiloBundle\Client\NameSiloClientInterface;
use Estratos\NameSiloBundle\Exception\InvalidDomainException;
use Estratos\NameSiloBundle\Exception\ValidationException;
use Estratos\NameSiloBundle\Mapper\ResponseMapper;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractService
{
    /**
     * @param array<string, mixed> $events
     */
    public function __construct(
        protected readonly NameSiloClientInterface $client,
        protected readonly ResponseMapper $mapper,
        protected readonly EventDispatcherInterface $dispatcher,
        protected readonly LoggerInterface $logger,
        protected readonly array $events = [],
    ) {
    }

    protected function dispatch(string $name, object $event): void
    {
        if (!(bool) ($this->events['enabled'] ?? true)) {
            return;
        }

        $configured = (array) ($this->events['events_to_dispatch'] ?? []);
        if (!in_array($name, $configured, true)) {
            return;
        }

        $this->dispatcher->dispatch($event);
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ('' === $domain) {
            throw new InvalidDomainException($domain, 'Domain cannot be empty.');
        }

        return $domain;
    }

    protected function normalizeRecordId(string $recordId): string
    {
        $recordId = trim($recordId);

        if ('' === $recordId) {
            throw new ValidationException('DNS record ID cannot be empty.', [
                'recordId' => 'DNS record ID cannot be empty.',
            ]);
        }

        return $recordId;
    }

    protected function normalizeContactId(string $contactId): string
    {
        $contactId = trim($contactId);

        if ('' === $contactId) {
            throw new ValidationException('Contact ID cannot be empty.', [
                'contactId' => 'Contact ID cannot be empty.',
            ]);
        }

        return $contactId;
    }

    /**
     * @param array<mixed> $nameServers
     * @return list<string>
     */
    protected function normalizeNameServers(array $nameServers): array
    {
        $normalized = [];

        foreach ($nameServers as $nameServer) {
            if (!is_string($nameServer) || '' === trim($nameServer)) {
                throw new ValidationException('Name servers must be non-empty strings.', [
                    'nameServers' => 'Each name server must be a non-empty string.',
                ]);
            }

            $normalized[] = trim($nameServer);
        }

        $normalized = array_values(array_unique($normalized));

        if (count($normalized) < 1 || count($normalized) > 4) {
            throw new ValidationException('A domain must have between 1 and 4 name servers.', [
                'nameServers' => 'A domain must have between 1 and 4 name servers.',
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeList(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            $rows = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $rows[] = $item;
                }
            }

            return $rows;
        }

        foreach (['domain', 'contact', 'resource_record', 'nameserver'] as $key) {
            if (array_key_exists($key, $value)) {
                return $this->normalizeList($value[$key]);
            }
        }

        /** @var array<string, mixed> $value */
        return [$value];
    }

    protected function stringValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
