<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Service;

use Estratos\DomainApiInterface\Account\AccountProviderInterface;
use Estratos\DomainApiInterface\DTO\AccountBalance;
use Estratos\DomainApiInterface\DTO\PriceList;

final class AccountService extends AbstractService implements AccountProviderInterface
{
    public function getBalance(): AccountBalance
    {
        $reply = $this->client->request('getAccountBalance');

        return $this->mapper->toAccountBalance($reply);
    }

    public function getPrices(?string $domainType = null): PriceList
    {
        $params = [];

        if (null !== $domainType && '' !== trim($domainType)) {
            $params['type'] = trim($domainType);
        }

        $reply = $this->client->request('getPrices', $params);

        return $this->mapper->toPriceList($reply);
    }
}
