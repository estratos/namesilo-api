<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Tests\Unit\Client;

use Estratos\NameSiloBundle\Client\NameSiloClient;
use Estratos\NameSiloBundle\Exception\ApiException;
use Estratos\NameSiloBundle\Exception\InsufficientFundsException;
use Estratos\NameSiloBundle\Exception\TimeoutException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NameSiloClientTest extends TestCase
{
    public function testItBuildsAGetRequestAndReturnsTheReply(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringContainsString('/getDomainInfo?', $url);

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            self::assertSame('1', $query['version']);
            self::assertSame('json', $query['type']);
            self::assertSame('api-key', $query['key']);
            self::assertSame('example.com', $query['domain']);

            return new MockResponse(json_encode([
                'reply' => [
                    'code' => 300,
                    'detail' => 'success',
                    'domain' => 'example.com',
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $client = $this->createClient($httpClient);
        $reply = $client->request('getDomainInfo', ['domain' => 'example.com']);

        self::assertSame(300, $reply['code']);
        self::assertSame('example.com', $reply['domain']);
    }

    public function testItThrowsATypedExceptionForApiErrors(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'reply' => [
                'code' => 119,
                'detail' => 'Insufficient funds',
                'required_amount' => '12.50',
                'balance' => '2.00',
            ],
        ], JSON_THROW_ON_ERROR)));

        $client = $this->createClient($httpClient);

        $this->expectException(InsufficientFundsException::class);
        $this->expectExceptionMessage('Insufficient funds. Required: $12.50, Available: $2.00');

        $client->request('registerDomain', ['domain' => 'example.com']);
    }

    public function testItRetriesRetryableHttpErrors(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse(json_encode([
                'reply' => ['code' => 300, 'detail' => 'success'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->createClient($httpClient, [
            'retry' => [
                'max_retries' => 1,
                'delay_ms' => 0,
                'http_codes' => [503],
            ],
        ]);

        $reply = $client->request('getAccountBalance');

        self::assertSame(300, $reply['code']);
    }

    public function testItCachesCacheableEndpoints(): void
    {
        $requests = 0;

        $httpClient = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode([
                'reply' => [
                    'code' => 300,
                    'detail' => 'success',
                    'balance' => '10.00',
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $client = $this->createClient($httpClient, [
            'cache' => [
                'enabled' => true,
                'ttl_seconds' => 300,
                'cacheable_endpoints' => ['getAccountBalance'],
            ],
            'cache_pool' => new ArrayAdapter(),
        ]);

        $first = $client->request('getAccountBalance');
        $second = $client->request('getAccountBalance');

        self::assertSame(1, $requests);
        self::assertSame($first, $second);
    }

    public function testItClearsCacheAfterAMutation(): void
    {
        $requests = 0;

        $httpClient = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse(json_encode([
                'reply' => ['code' => 300, 'detail' => 'success'],
            ], JSON_THROW_ON_ERROR));
        });

        $client = $this->createClient($httpClient, [
            'cache' => [
                'enabled' => true,
                'ttl_seconds' => 300,
                'cacheable_endpoints' => ['getAccountBalance'],
            ],
            'cache_pool' => new ArrayAdapter(),
        ]);

        $client->request('getAccountBalance');
        $client->request('registerDomain', ['domain' => 'example.com']);
        $client->request('getAccountBalance');

        self::assertSame(3, $requests);
    }

    public function testItParsesXmlResponses(): void
    {
        $xml = <<<'XML'
<namesilo>
    <reply>
        <code>300</code>
        <detail>success</detail>
        <domain>example.com</domain>
    </reply>
</namesilo>
XML;

        $httpClient = new MockHttpClient(new MockResponse($xml));

        $client = $this->createClient($httpClient, ['response_type' => 'xml']);
        $reply = $client->request('getDomainInfo', ['domain' => 'example.com']);

        self::assertSame(300, $reply['code']);
        self::assertSame('example.com', $reply['domain']);
    }

    public function testItExposesTheOriginalApiCodeForUnknownErrors(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'reply' => [
                'code' => 999,
                'detail' => 'Unexpected API error',
            ],
        ], JSON_THROW_ON_ERROR)));

        $client = $this->createClient($httpClient);

        try {
            $client->request('getDomainInfo', ['domain' => 'example.com']);
            self::fail('An ApiException should have been thrown.');
        } catch (ApiException $exception) {
            self::assertSame(999, $exception->getApiCode());
            self::assertSame('getDomainInfo', $exception->getEndpoint());
        }
    }

    public function testTransportErrorsDoNotLeakTheApiKey(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException(
                'SSL problem for "https://www.namesilo.com/api/getAccountBalance?key=api-key"'
            );
        });

        $client = $this->createClient($httpClient);

        try {
            $client->request('getAccountBalance');
            self::fail('A TimeoutException should have been thrown.');
        } catch (TimeoutException $exception) {
            self::assertStringNotContainsString('api-key', $exception->getMessage());
            self::assertSame('getAccountBalance', $exception->getEndpoint());
        }
    }

    public function testItRejectsNonGetMethods(): void
    {
        $client = $this->createClient(new MockHttpClient());

        $this->expectException(ApiException::class);

        $client->request('registerDomain', [], 'POST');
    }

    public function testSandboxModeUsesTheSandboxBaseUrl(): void
    {
        $client = $this->createClient(new MockHttpClient(), ['sandbox' => true]);

        self::assertTrue($client->isSandbox());
        self::assertSame('https://sandbox.namesilo.com/api/', $client->getBaseUrl());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createClient(MockHttpClient $httpClient, array $overrides = []): NameSiloClient
    {
        $baseUrl = $overrides['base_url'] ?? 'https://www.namesilo.com/api/';
        $responseType = $overrides['response_type'] ?? 'json';

        /** @var array<string, mixed> $retry */
        $retry = is_array($overrides['retry'] ?? null) ? $overrides['retry'] : [
            'max_retries' => 0,
            'delay_ms' => 0,
            'http_codes' => [],
        ];

        /** @var array<string, mixed> $cache */
        $cache = is_array($overrides['cache'] ?? null) ? $overrides['cache'] : [
            'enabled' => false,
            'ttl_seconds' => 300,
            'cacheable_endpoints' => [],
        ];

        $cachePool = $overrides['cache_pool'] ?? null;

        return new NameSiloClient(
            httpClient: $httpClient,
            logger: new NullLogger(),
            apiKey: 'api-key',
            sandbox: (bool) ($overrides['sandbox'] ?? false),
            baseUrl: is_string($baseUrl) ? $baseUrl : 'https://www.namesilo.com/api/',
            timeout: 5,
            version: '1',
            responseType: is_string($responseType) ? $responseType : 'json',
            logging: ['enabled' => false],
            retry: $retry,
            cache: $cache,
            cachePool: $cachePool instanceof \Psr\Cache\CacheItemPoolInterface ? $cachePool : null,
        );
    }
}
