<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Estratos\DomainApiInterface\DTO\AccountBalance;
use Estratos\DomainApiInterface\DTO\Contact;
use Estratos\DomainApiInterface\DTO\DNSRecord;
use Estratos\DomainApiInterface\DTO\DomainAvailability;
use Estratos\DomainApiInterface\DTO\DomainInfo;
use Estratos\DomainApiInterface\DTO\DomainSummary;
use Estratos\DomainApiInterface\DTO\LockStatus;
use Estratos\DomainApiInterface\DTO\PriceList;
use Estratos\DomainApiInterface\DTO\PrivacyStatus;
use Estratos\DomainApiInterface\DTO\RegistrationResult;
use Estratos\DomainApiInterface\DTO\TransferStatus;
use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\NameSiloBundle\Exception\ApiException;
use Psr\Log\LoggerInterface;

/**
 * Converts NameSilo reply payloads into the DTOs defined by domain-api-interface.
 */
final class ResponseMapper
{
    private readonly DateTimeZone $utc;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toDomainAvailability(string $domain, array $reply): DomainAvailability
    {
        $available = false;
        $reason = null;

        foreach ($this->normalizeList($reply['available'] ?? []) as $item) {
            $candidate = is_array($item)
                ? $this->stringValue($item['domain'] ?? '')
                : $this->stringValue($item);

            if (0 === strcasecmp($candidate, $domain)) {
                $available = true;
                break;
            }
        }

        if (array_key_exists('available', $reply) && !is_array($reply['available'])) {
            $available = $this->toBool($reply['available'], $available);
        }

        if (!$available) {
            foreach ($this->normalizeList($reply['unavailable'] ?? []) as $item) {
                $candidate = is_array($item)
                    ? $this->stringValue($item['domain'] ?? '')
                    : $this->stringValue($item);

                if (0 === strcasecmp($candidate, $domain)) {
                    $reason = $this->string($reply, ['detail'], 'Domain is not available for registration.');
                    break;
                }
            }
        }

        return new DomainAvailability(
            domain: $domain,
            available: $available,
            reason: $reason,
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toDomainInfo(string $domain, array $reply): DomainInfo
    {
        $createdAt = $this->date($reply, ['created', 'registration_date', 'registrationDate']);
        $expiresAt = $this->date($reply, ['expires', 'expiration_date', 'expirationDate'], $createdAt);
        $updatedAt = $this->date($reply, ['updated', 'updated_at', 'last_updated'], $createdAt);
        $contactIds = $this->contactIds($reply);

        return new DomainInfo(
            domain: $this->string($reply, ['domain'], $domain),
            status: $this->status($reply),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            updatedAt: $updatedAt,
            isLocked: $this->toBool(
                $reply['locked'] ?? $reply['lock'] ?? null,
                $this->codeIs($reply, 252)
            ),
            isPrivacyEnabled: $this->toBool(
                $reply['private'] ?? $reply['privacy'] ?? null,
                $this->codeIs($reply, 255)
            ),
            isAutoRenewEnabled: $this->toBool(
                $reply['auto_renew'] ?? $reply['autoRenew'] ?? null
            ),
            nameServers: $this->nameServers($reply),
            registrantContactId: $this->nullableString($contactIds['registrant'] ?? null),
            adminContactId: $this->nullableString($contactIds['administrative'] ?? null),
            techContactId: $this->nullableString($contactIds['technical'] ?? null),
            billingContactId: $this->nullableString($contactIds['billing'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toDomainSummary(array $reply): DomainSummary
    {
        $createdAt = $this->date($reply, ['created', 'registration_date', 'registrationDate']);
        $expiresAt = $this->date($reply, ['expires', 'expiration_date', 'expirationDate'], $createdAt);

        return new DomainSummary(
            domain: $this->string($reply, ['domain']),
            status: $this->status($reply),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            isLocked: $this->toBool($reply['locked'] ?? $reply['lock'] ?? null),
            isPrivacyEnabled: $this->toBool($reply['private'] ?? $reply['privacy'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toRegistrationResult(string $domain, array $reply): RegistrationResult
    {
        $registrationDate = $this->date($reply, ['registration_date', 'registrationDate', 'created']);
        $expirationDate = $this->date(
            $reply,
            ['expiration_date', 'expirationDate', 'expires'],
            $registrationDate
        );

        $orderId = $this->string(
            $reply,
            ['order_id', 'orderid', 'order', 'transaction_id', 'transactionId']
        );

        if ('' === $orderId) {
            $this->logger->warning('NameSilo did not return an order identifier.', [
                'domain' => $domain,
            ]);
        }

        return new RegistrationResult(
            success: true,
            domain: $this->string($reply, ['domain'], $domain),
            orderId: $orderId,
            registrationDate: $registrationDate,
            expirationDate: $expirationDate,
            transactionId: $this->nullableString(
                $reply['transaction_id'] ?? $reply['transactionId'] ?? null
            ),
            errorMessage: null,
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toTransferStatus(string $domain, array $reply): TransferStatus
    {
        $status = $this->status($reply);
        $status = match ($status) {
            'success', 'successful', 'complete' => 'completed',
            'in_progress', 'processing' => 'pending',
            default => $status,
        };

        $initiatedAt = $this->date($reply, ['initiated', 'initiated_at', 'created']);
        $completedAt = $this->nullableDate($reply, ['completed', 'completed_at', 'completedAt']);
        $expiresAt = $this->nullableDate($reply, ['expires', 'expiration_date', 'expiresAt']);

        return new TransferStatus(
            domain: $this->string($reply, ['domain'], $domain),
            status: $status,
            initiatedAt: $initiatedAt,
            completedAt: $completedAt,
            expiresAt: $expiresAt,
            message: $this->nullableString($reply['detail'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toDnsRecord(array $reply): DNSRecord
    {
        $type = strtoupper($this->string($reply, ['type', 'rrtype']));
        $recordType = RecordType::tryFrom($type);

        if (null === $recordType) {
            throw new ApiException(sprintf('Unsupported NameSilo DNS record type "%s".', $type));
        }

        $value = $this->string($reply, ['value', 'rrvalue']);
        $port = $this->nullableInt($reply['port'] ?? $reply['rrport'] ?? null);
        $weight = $this->nullableInt($reply['weight'] ?? $reply['rrweight'] ?? null);

        if (RecordType::SRV === $recordType
            && preg_match('/^(\d+):(\d+):(.+)$/', $value, $matches) === 1
        ) {
            $weight = (int) $matches[1];
            $port = (int) $matches[2];
            $value = $matches[3];
        }

        return new DNSRecord(
            id: $this->string($reply, ['record_id', 'rrid', 'id']),
            host: $this->string($reply, ['host', 'rrhost']),
            type: $recordType,
            value: $value,
            ttl: $this->toInt($reply['ttl'] ?? $reply['rrttl'] ?? null, 3600),
            domain: $this->nullableString($reply['domain'] ?? null),
            priority: $this->nullableInt(
                $reply['priority'] ?? $reply['rrdistance'] ?? $reply['distance'] ?? null
            ),
            port: $port,
            weight: $weight,
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toContact(array $reply): Contact
    {
        return new Contact(
            id: $this->string($reply, ['contact_id', 'contactId', 'id']),
            firstName: $this->string($reply, ['first_name', 'firstName', 'fn']),
            lastName: $this->string($reply, ['last_name', 'lastName', 'ln']),
            email: $this->string($reply, ['email', 'em']),
            phone: $this->string($reply, ['phone', 'ph']),
            organization: $this->string($reply, ['organization', 'org', 'cp']),
            address1: $this->string($reply, ['address1', 'address', 'ad']),
            city: $this->string($reply, ['city', 'cy']),
            state: $this->string($reply, ['state', 'st']),
            postalCode: $this->string($reply, ['postal_code', 'postalCode', 'zip', 'zp']),
            country: $this->string($reply, ['country', 'ct']),
            address2: $this->nullableString($reply['address2'] ?? $reply['ad2'] ?? null),
            fax: $this->nullableString($reply['fax'] ?? $reply['fx'] ?? null),
            isDefault: $this->nullableBool($reply['is_default'] ?? $reply['isDefault'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toAccountBalance(array $reply): AccountBalance
    {
        $balance = $this->toFloat($reply['balance'] ?? 0.0);
        $available = $this->toFloat($reply['available'] ?? $reply['available_balance'] ?? $balance);
        $reserved = $this->toFloat($reply['reserved'] ?? 0.0);

        return new AccountBalance(
            balance: $balance,
            currency: $this->string($reply, ['currency'], 'USD'),
            available: $available,
            reserved: $reserved,
            creditLimit: $this->nullableFloat($reply['credit_limit'] ?? $reply['creditLimit'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toPriceList(array $reply): PriceList
    {
        $prices = [];
        $source = $reply['prices'] ?? $reply['price'] ?? null;

        if (null === $source) {
            $source = array_diff_key($reply, array_flip(['code', 'detail', 'currency', 'request']));
        }

        if (is_array($source)) {
            $this->flattenPrices($source, $prices);
        }

        return new PriceList(
            prices: $prices,
            currency: $this->string($reply, ['currency'], 'USD'),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toPrivacyStatus(string $domain, array $reply, ?bool $defaultEnabled = null): PrivacyStatus
    {
        $status = $this->status($reply);
        $enabled = $this->toBool($reply['private'] ?? $reply['enabled'] ?? null, $defaultEnabled ?? false);

        return new PrivacyStatus(
            domain: $this->string($reply, ['domain'], $domain),
            enabled: $enabled,
            status: '' === $status ? null : $status,
            message: $this->nullableString($reply['detail'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    public function toLockStatus(string $domain, array $reply, ?bool $defaultLocked = null): LockStatus
    {
        $status = $this->status($reply);
        $locked = $this->toBool($reply['locked'] ?? $reply['lock'] ?? null, $defaultLocked ?? false);

        return new LockStatus(
            domain: $this->string($reply, ['domain'], $domain),
            locked: $locked,
            status: '' === $status ? null : $status,
            message: $this->nullableString($reply['detail'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    private function status(array $reply): string
    {
        $status = $this->string($reply, ['status', 'transfer_status', 'domain_status', 'detail']);
        $status = strtolower(trim($status));
        $status = str_replace([' ', '-'], '_', $status);

        return $status;
    }

    /**
     * @param array<string, mixed> $reply
     * @return array<string, string>
     */
    private function contactIds(array $reply): array
    {
        $ids = $reply['contact_ids'] ?? $reply['contactIds'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        return [
            'registrant' => $this->stringValue($ids['registrant'] ?? ''),
            'administrative' => $this->stringValue($ids['administrative'] ?? $ids['admin'] ?? ''),
            'technical' => $this->stringValue($ids['technical'] ?? $ids['tech'] ?? ''),
            'billing' => $this->stringValue($ids['billing'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $reply
     * @return list<string>
     */
    private function nameServers(array $reply): array
    {
        $servers = $reply['nameservers'] ?? $reply['name_servers'] ?? [];
        $result = [];

        foreach ($this->normalizeList($servers) as $server) {
            if (is_array($server)) {
                $value = $server['nameserver'] ?? $server['host'] ?? reset($server);
            } else {
                $value = $server;
            }

            if (is_scalar($value) && '' !== trim((string) $value)) {
                $result[] = trim((string) $value);
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string>        $keys
     */
    private function string(array $data, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function date(array $data, array $keys, ?DateTimeImmutable $default = null): DateTimeImmutable
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $parsed = $this->parseDate($data[$key]);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        if (null !== $default) {
            return $default;
        }

        $this->logger->warning('NameSilo response is missing a date; using the current time.', [
            'keys' => $keys,
        ]);

        return new DateTimeImmutable('now', $this->utc);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function nullableDate(array $data, array $keys): ?DateTimeImmutable
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $parsed = $this->parseDate($data[$key]);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = trim($value);

        foreach (['!Y-m-d', '!Y-m-d H:i:s', 'Y-m-d\TH:i:sP'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $this->utc);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        if (false !== $timestamp) {
            return (new DateTimeImmutable('@' . $timestamp))->setTimezone($this->utc);
        }

        $this->logger->warning('Unable to parse a NameSilo date.', ['value' => $value]);

        return null;
    }

    private function toFloat(mixed $value, float $default = 0.0): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        $value = str_replace([',', '$', ' '], '', trim($value));

        return is_numeric($value) ? (float) $value : $default;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = str_replace([',', '$', ' '], '', trim($value));

        return is_numeric($value) ? (float) $value : null;
    }

    private function toBool(mixed $value, bool $default = false): bool
    {
        if (null === $value) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return 0.0 !== (float) $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return $default;
    }

    private function nullableBool(mixed $value): ?bool
    {
        return null === $value ? null : $this->toBool($value);
    }

    private function toInt(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeList(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            return [$value];
        }

        if (array_is_list($value)) {
            return $value;
        }

        foreach (['domain', 'contact', 'resource_record', 'nameserver', 'price'] as $key) {
            if (array_key_exists($key, $value)) {
                return $this->normalizeList($value[$key]);
            }
        }

        return [$value];
    }

    /**
     * @param array<string, mixed> $reply
     */
    private function codeIs(array $reply, int $code): bool
    {
        return $this->toInt($reply['code'] ?? 0) === $code;
    }

    /**
     * @param array<mixed> $source
     * @param array<string, float> $prices
     */
    private function flattenPrices(array $source, array &$prices): void
    {
        foreach ($source as $key => $value) {
            if (!is_array($value)) {
                if (!is_numeric($key) && is_numeric($value)) {
                    $prices[(string) $key] = (float) $value;
                }
                continue;
            }

            $tld = $this->string($value, ['tld', 'domain', 'extension', 'name']);
            $price = $this->findPrice($value);

            if ('' !== $tld && null !== $price) {
                $prices[$tld] = $price;
                continue;
            }

            if (is_string($key) && null !== $price) {
                $prices[$key] = $price;
                continue;
            }

            $this->flattenPrices($value, $prices);
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function findPrice(array $row): ?float
    {
        foreach (['registration', 'registration_price', 'renew', 'renewal', 'transfer', 'price', 'amount'] as $key) {
            if (array_key_exists($key, $row)) {
                $price = $this->nullableFloat($row[$key]);
                if (null !== $price) {
                    return $price;
                }
            }
        }

        foreach ($row as $value) {
            if (is_array($value)) {
                $price = $this->findPrice($value);
                if (null !== $price) {
                    return $price;
                }
            } elseif (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
