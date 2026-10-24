<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\NameServer\NameServerProviderInterface;

final class NameServerService extends AbstractService implements NameServerProviderInterface
{
    /**
     * @param array<mixed> $nameServers
     */
    public function changeNameServers(string $domain, array $nameServers): bool
    {
        $domain = $this->normalizeDomain($domain);
        $nameServers = $this->normalizeNameServers($nameServers);

        $params = ['domain' => $domain];
        foreach ($nameServers as $index => $nameServer) {
            $params['ns' . ($index + 1)] = $nameServer;
        }

        $this->client->request('changeNameServers', $params);

        return true;
    }
}
