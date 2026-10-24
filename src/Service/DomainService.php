<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\Domain\DomainProviderInterface;
use Estratos\DomainApiInterface\DTO\DomainAvailability;
use Estratos\DomainApiInterface\DTO\DomainInfo;
use Estratos\DomainApiInterface\DTO\DomainSummary;
use Estratos\DomainApiInterface\DTO\RegistrationResult;
use Estratos\DomainApiInterface\DTO\TransferStatus;
use Estratos\DomainApiInterface\Request\RegisterDomainRequest;
use Estratos\DomainApiInterface\Request\RenewDomainRequest;
use Estratos\DomainApiInterface\Request\TransferDomainRequest;
use Estratos\NameSiloBundle\Event\DomainRegisteredEvent;
use Estratos\NameSiloBundle\Event\DomainRenewedEvent;
use Estratos\NameSiloBundle\Event\DomainTransferredEvent;
use Estratos\NameSiloBundle\Event\NameSiloEvents;

final class DomainService extends AbstractService implements DomainProviderInterface
{
    public function checkAvailability(string $domain): DomainAvailability
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('checkRegisterAvailability', [
            'domains' => $domain,
        ]);

        return $this->mapper->toDomainAvailability($domain, $reply);
    }

    public function register(RegisterDomainRequest $request): RegistrationResult
    {
        $params = [
            'domain' => $this->normalizeDomain($request->domain),
            'years' => $request->years,
            'private' => $request->private ? 1 : 0,
            'auto_renew' => $request->autoRenew ? 1 : 0,
        ];

        foreach ([1, 2, 3, 4] as $index) {
            $property = 'nameserver' . $index;
            $nameServer = $request->{$property};

            if (null !== $nameServer && '' !== trim($nameServer)) {
                $params['ns' . $index] = trim($nameServer);
            }
        }

        $reply = $this->client->request('registerDomain', $params);
        $result = $this->mapper->toRegistrationResult($params['domain'], $reply);

        $this->dispatch(NameSiloEvents::DOMAIN_REGISTERED, new DomainRegisteredEvent(
            domain: $result->domain,
            orderId: $result->orderId,
            registrationDate: $result->registrationDate,
            expirationDate: $result->expirationDate,
        ));

        return $result;
    }

    public function renew(RenewDomainRequest $request): RegistrationResult
    {
        $domain = $this->normalizeDomain($request->domain);
        $reply = $this->client->request('renewDomain', [
            'domain' => $domain,
            'years' => $request->years,
        ]);

        $result = $this->mapper->toRegistrationResult($domain, $reply);

        $this->dispatch(NameSiloEvents::DOMAIN_RENEWED, new DomainRenewedEvent(
            domain: $result->domain,
            orderId: $result->orderId,
            newExpirationDate: $result->expirationDate,
        ));

        return $result;
    }

    public function transfer(TransferDomainRequest $request): RegistrationResult
    {
        $domain = $this->normalizeDomain($request->domain);

        $reply = $this->client->request('transferDomain', [
            'domain' => $domain,
            'years' => $request->years,
            'auth' => $request->authorizationCode,
            'private' => $request->private ? 1 : 0,
            'auto_renew' => $request->autoRenew ? 1 : 0,
        ]);

        $result = $this->mapper->toRegistrationResult($domain, $reply);

        $this->dispatch(NameSiloEvents::DOMAIN_TRANSFERRED, new DomainTransferredEvent(
            domain: $result->domain,
            orderId: $result->orderId,
            status: 'pending',
        ));

        return $result;
    }

    public function getTransferStatus(string $domain): TransferStatus
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('checkTransferStatus', [
            'domain' => $domain,
        ]);

        return $this->mapper->toTransferStatus($domain, $reply);
    }

    public function getInfo(string $domain): DomainInfo
    {
        $domain = $this->normalizeDomain($domain);
        $reply = $this->client->request('getDomainInfo', [
            'domain' => $domain,
        ]);

        return $this->mapper->toDomainInfo($domain, $reply);
    }

    public function list(): array
    {
        $reply = $this->client->request('listDomains');
        $domains = $reply['domains'] ?? $reply['domain'] ?? [];

        return array_map(
            fn (array $domain): DomainSummary => $this->mapper->toDomainSummary($domain),
            $this->normalizeList($domains)
        );
    }

    public function listByExpirationDate(): array
    {
        $reply = $this->client->request('listDomainsByExpirationDate');
        $domains = $reply['domains'] ?? $reply['domain'] ?? [];

        return array_map(
            fn (array $domain): DomainSummary => $this->mapper->toDomainSummary($domain),
            $this->normalizeList($domains)
        );
    }
}
