<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\DNS\DNSProviderInterface;
use Estratos\DomainApiInterface\DTO\DNSRecord;
use Estratos\DomainApiInterface\Enum\RecordType;
use Estratos\DomainApiInterface\Request\AddDnsRecordRequest;
use Estratos\DomainApiInterface\Request\DeleteDnsRecordRequest;
use Estratos\DomainApiInterface\Request\UpdateDnsRecordRequest;
use Estratos\NameSiloBundle\Event\DnsRecordAddedEvent;
use Estratos\NameSiloBundle\Event\DnsRecordDeletedEvent;
use Estratos\NameSiloBundle\Event\DnsRecordUpdatedEvent;
use Estratos\NameSiloBundle\Event\NameSiloEvents;

final class DNSService extends AbstractService implements DNSProviderInterface
{
    public function listRecords(string $domain, ?string $type = null): array
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('dnsListRecords', [
            'domain' => $domain,
        ]);

        $records = $reply['resource_record'] ?? null;
        if (null === $records) {
            $records = is_array($reply['records'] ?? null)
                ? ($reply['records']['resource_record'] ?? $reply['records'])
                : [];
        }

        $mapped = [];

        foreach ($this->normalizeList($records) as $record) {
            $mapped[] = $this->mapper->toDnsRecord($record);
        }

        if (null !== $type && '' !== trim($type)) {
            $type = strtoupper(trim($type));
            $mapped = array_values(array_filter(
                $mapped,
                static fn (DNSRecord $record): bool => $record->type->value === $type
            ));
        }

        return $mapped;
    }

    public function addRecord(AddDnsRecordRequest $request): DNSRecord
    {
        $domain = $this->normalizeDomain($request->domain);

        $params = [
            'domain' => $domain,
            'rrtype' => $request->type->value,
            'rrhost' => $request->host,
            'rrvalue' => $this->recordValue($request->type, $request->value, $request->weight, $request->port),
            'rrttl' => $request->ttl,
        ];

        if (RecordType::MX === $request->type && null !== $request->priority) {
            $params['rrdistance'] = $request->priority;
        }

        $reply = $this->client->request('dnsAddRecord', $params);
        $record = $this->recordFromRequest(
            $domain,
            $this->stringValue($reply['record_id'] ?? $reply['rrid'] ?? ''),
            $request
        );

        $this->dispatch(NameSiloEvents::DNS_RECORD_ADDED, new DnsRecordAddedEvent($domain, $record));

        return $record;
    }

    public function updateRecord(UpdateDnsRecordRequest $request): DNSRecord
    {
        $domain = $this->normalizeDomain($request->domain);
        $recordId = $this->normalizeRecordId($request->recordId);

        $params = [
            'domain' => $domain,
            'rrid' => $recordId,
            'rrhost' => $request->host,
            'rrvalue' => $this->recordValue($request->type, $request->value, $request->weight, $request->port),
            'rrttl' => $request->ttl,
        ];

        if (RecordType::MX === $request->type && null !== $request->priority) {
            $params['rrdistance'] = $request->priority;
        }

        $reply = $this->client->request('dnsUpdateRecord', $params);
        $record = $this->recordFromRequest(
            $domain,
            $this->stringValue($reply['record_id'] ?? $reply['rrid'] ?? $recordId, $recordId),
            $request
        );

        $this->dispatch(NameSiloEvents::DNS_RECORD_UPDATED, new DnsRecordUpdatedEvent($domain, $record));

        return $record;
    }

    public function deleteRecord(DeleteDnsRecordRequest $request): bool
    {
        $domain = $this->normalizeDomain($request->domain);
        $recordId = $this->normalizeRecordId($request->recordId);

        $this->client->request('dnsDeleteRecord', [
            'domain' => $domain,
            'rrid' => $recordId,
        ]);

        $this->dispatch(NameSiloEvents::DNS_RECORD_DELETED, new DnsRecordDeletedEvent($domain, $recordId));

        return true;
    }

    private function recordValue(RecordType $type, string $value, ?int $weight, ?int $port): string
    {
        if (RecordType::SRV === $type) {
            return sprintf('%d:%d:%s', $weight ?? 0, $port ?? 0, $value);
        }

        return $value;
    }

    private function recordFromRequest(
        string $domain,
        string $recordId,
        AddDnsRecordRequest|UpdateDnsRecordRequest $request
    ): DNSRecord {
        return new DNSRecord(
            id: $recordId,
            host: $request->host,
            type: $request->type,
            value: $request->value,
            ttl: $request->ttl,
            domain: $domain,
            priority: $request->priority,
            port: $request->port,
            weight: $request->weight,
        );
    }
}
