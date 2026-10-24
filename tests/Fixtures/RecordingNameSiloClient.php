<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Fixtures;

use Estratos\NameSiloBundle\Client\NameSiloClientInterface;

final class RecordingNameSiloClient implements NameSiloClientInterface
{
    /** @var list<array{endpoint: string, params: array<string, mixed>, method: string}> */
    public array $calls = [];

    /** @var array<string, array<string, mixed>> */
    public array $responses = [];

    public function request(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $this->calls[] = [
            'endpoint' => $endpoint,
            'params' => $params,
            'method' => $method,
        ];

        return $this->responses[$endpoint] ?? ['code' => 300, 'detail' => 'success'];
    }

    public function isSandbox(): bool
    {
        return false;
    }

    public function getBaseUrl(): string
    {
        return 'https://www.namesilo.com/api/';
    }

    public function getVersion(): string
    {
        return '1';
    }

    public function getResponseType(): string
    {
        return 'json';
    }
}
