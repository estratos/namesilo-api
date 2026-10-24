<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Client;

interface NameSiloClientInterface
{
    /**
     * Send a request to the NameSilo API.
     *
     * The returned array is the normalized `reply` payload. It always contains
     * the numeric `code` and the textual `detail`; the operation-specific
     * fields are preserved as returned by NameSilo.
     *
     * @param string               $endpoint The API operation (e.g. 'registerDomain')
     * @param array<string, mixed> $params   Query parameters for the operation
     * @param string               $method   NameSilo only supports GET
     *
     * @return array<string, mixed>
     *
     * @throws \Estratos\NameSiloBundle\Exception\ApiException
     */
    public function request(string $endpoint, array $params = [], string $method = 'GET'): array;

    /**
     * Check if the client is in sandbox mode.
     */
    public function isSandbox(): bool;

    /**
     * Get the effective base URL for the API.
     */
    public function getBaseUrl(): string;

    /**
     * Get the API version.
     */
    public function getVersion(): string;

    /**
     * Get the response type (json or xml).
     */
    public function getResponseType(): string;
}
