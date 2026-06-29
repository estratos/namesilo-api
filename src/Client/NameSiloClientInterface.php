<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Client;

interface NameSiloClientInterface
{
    /**
     * Send a request to the NameSilo API.
     *
     * @param string $endpoint The API endpoint (e.g., 'registerDomain')
     * @param array<string, mixed> $params Query parameters
     * @param string $method HTTP method (GET, POST, etc.)
     * @return array<string, mixed> The parsed response
     * @throws \Estratos\NameSiloBundle\Exception\ApiException
     */
    public function request(string $endpoint, array $params = [], string $method = 'GET'): array;
    
    /**
     * Check if the client is in sandbox mode.
     */
    public function isSandbox(): bool;
    
    /**
     * Get the base URL for the API.
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