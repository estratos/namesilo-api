<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Client;

use Estratos\NameSiloBundle\Exception\ApiException;
use Estratos\NameSiloBundle\Exception\ApiExceptionFactory;
use Estratos\NameSiloBundle\Exception\RateLimitException;
use Estratos\NameSiloBundle\Exception\TimeoutException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NameSiloClient implements NameSiloClientInterface
{
    private const DEFAULT_BASE_URL = 'https://www.namesilo.com/api/';
    private const SANDBOX_BASE_URL = 'https://sandbox.namesilo.com/api/';

    private const MUTATING_ENDPOINTS = [
        'registerDomain',
        'renewDomain',
        'transferDomain',
        'contactAdd',
        'contactUpdate',
        'contactDelete',
        'dnsAddRecord',
        'dnsUpdateRecord',
        'dnsDeleteRecord',
        'domainLock',
        'domainUnlock',
        'addPrivacy',
        'removePrivacy',
        'changeNameServers',
        'addAccountFunds',
    ];

    /**
     * @param array<string, mixed> $logging
     * @param array<string, mixed> $retry
     * @param array<string, mixed> $cache
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiKey,
        private readonly bool $sandbox,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $version,
        private readonly string $responseType,
        private readonly array $logging = [],
        private readonly array $retry = [],
        private readonly array $cache = [],
        private readonly ?CacheItemPoolInterface $cachePool = null,
    ) {
    }

    public function request(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        $method = strtoupper($method);
        if ('GET' !== $method) {
            throw new ApiException(
                'The NameSilo API only supports GET requests.',
                $endpoint,
                405
            );
        }

        $endpoint = trim($endpoint);
        if ('' === $endpoint) {
            throw new ApiException('The NameSilo endpoint cannot be empty.');
        }

        $requestId = bin2hex(random_bytes(8));

        if ($this->isCacheable($endpoint)) {
            $cached = $this->readCache($endpoint, $params);
            if (null !== $cached) {
                $this->log('debug', 'NameSilo cache hit', [
                    'endpoint' => $endpoint,
                    'request_id' => $requestId,
                ]);

                return $cached;
            }
        }

        $url = $this->buildUrl($endpoint, $params);
        $reply = $this->send($endpoint, $url, $requestId);

        $this->logResponse($endpoint, $reply, $requestId);

        $exception = ApiExceptionFactory::fromReply($reply, $endpoint, $params, $requestId);
        if (null !== $exception) {
            throw $exception;
        }

        if ($this->isCacheable($endpoint)) {
            $this->writeCache($endpoint, $params, $reply);
        } elseif (in_array($endpoint, self::MUTATING_ENDPOINTS, true)) {
            $this->clearCache();
        }

        return $reply;
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function getBaseUrl(): string
    {
        return $this->resolveBaseUrl();
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getResponseType(): string
    {
        return $this->responseType;
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $endpoint, string $url, string $requestId): array
    {
        $maxRetries = max(0, $this->intValue($this->retry['max_retries'] ?? 0));
        $delayMs = max(0, $this->intValue($this->retry['delay_ms'] ?? 0));
        $retryCodes = array_map(
            fn (mixed $code): int => $this->intValue($code),
            (array) ($this->retry['http_codes'] ?? [429, 500, 502, 503, 504])
        );

        $attempt = 0;

        while (true) {
            try {
                $this->log('debug', 'NameSilo request', [
                    'endpoint' => $endpoint,
                    'url' => $this->maskUrl($url),
                    'request_id' => $requestId,
                    'attempt' => $attempt + 1,
                ]);

                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => $this->timeout,
                    'headers' => [
                        'Accept' => 'application/' . $this->responseType,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $this->decode($body, $endpoint, $requestId);
                }

                if (in_array($statusCode, $retryCodes, true) && $attempt < $maxRetries) {
                    $this->waitBeforeRetry($attempt, $delayMs);
                    ++$attempt;
                    continue;
                }

                if (429 === $statusCode) {
                    throw new RateLimitException(
                        'NameSilo rate limit exceeded.',
                        $this->retryAfter($response),
                        $endpoint,
                        $requestId
                    );
                }

                throw new ApiException(
                    sprintf('NameSilo API returned HTTP %d.', $statusCode),
                    $endpoint,
                    $statusCode,
                    $requestId
                );
            } catch (TransportExceptionInterface $exception) {
                if ($attempt < $maxRetries) {
                    $this->waitBeforeRetry($attempt, $delayMs);
                    ++$attempt;
                    continue;
                }

                throw new TimeoutException(
                    $this->timeout,
                    sprintf('NameSilo request failed: %s', $exception->getMessage()),
                    $endpoint,
                    $requestId,
                    $exception
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body, string $endpoint, string $requestId): array
    {
        if ('' === trim($body)) {
            throw new ApiException(
                'NameSilo returned an empty response.',
                $endpoint,
                null,
                $requestId
            );
        }

        $data = 'xml' === strtolower($this->responseType)
            ? $this->decodeXml($body, $endpoint, $requestId)
            : $this->decodeJson($body, $endpoint, $requestId);

        $namesilo = $data['namesilo'] ?? null;
        $reply = $data['reply']
            ?? (is_array($namesilo) ? ($namesilo['reply'] ?? null) : null)
            ?? $data;

        if (!is_array($reply)) {
            throw new ApiException(
                'Unexpected NameSilo response structure.',
                $endpoint,
                null,
                $requestId
            );
        }

        /** @var array<string, mixed> $reply */
        if (!array_key_exists('code', $reply)) {
            throw new ApiException(
                'NameSilo response does not contain a reply code.',
                $endpoint,
                null,
                $requestId
            );
        }

        $reply['code'] = $this->intValue($reply['code']);
        $reply['detail'] = $this->stringValue($reply['detail'] ?? '');

        return $reply;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body, string $endpoint, string $requestId): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ApiException(
                'Unable to decode the NameSilo JSON response.',
                $endpoint,
                null,
                $requestId,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new ApiException(
                'Unexpected NameSilo JSON response.',
                $endpoint,
                null,
                $requestId
            );
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeXml(string $body, string $endpoint, string $requestId): array
    {
        if (!function_exists('simplexml_load_string')) {
            throw new ApiException(
                'XML responses require the SimpleXML extension.',
                $endpoint,
                null,
                $requestId
            );
        }

        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
        if (false === $xml) {
            throw new ApiException(
                'Unable to decode the NameSilo XML response.',
                $endpoint,
                null,
                $requestId
            );
        }

        try {
            $json = json_encode($xml, JSON_THROW_ON_ERROR);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ApiException(
                'Unable to normalize the NameSilo XML response.',
                $endpoint,
                null,
                $requestId,
                $exception
            );
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUrl(string $endpoint, array $params): string
    {
        return $this->resolveBaseUrl()
            . ltrim($endpoint, '/')
            . '?' . $this->buildQuery($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildQuery(array $params): string
    {
        $defaults = [
            'version' => $this->version,
            'type' => $this->responseType,
            'key' => $this->apiKey,
        ];

        $normalized = [];
        foreach ($params as $key => $value) {
            if (null === $value) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            if (is_array($value)) {
                $value = implode(',', array_map(
                    static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                    $value
                ));
            }

            $normalized[(string) $key] = $value;
        }

        $query = array_merge($normalized, $defaults);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = $this->baseUrl;

        if ($this->sandbox
            && rtrim($baseUrl, '/') === rtrim(self::DEFAULT_BASE_URL, '/')
        ) {
            $baseUrl = self::SANDBOX_BASE_URL;
        }

        return rtrim($baseUrl, '/') . '/';
    }

    private function isCacheable(string $endpoint): bool
    {
        if (null === $this->cachePool) {
            return false;
        }

        if (!(bool) ($this->cache['enabled'] ?? false)) {
            return false;
        }

        return in_array($endpoint, (array) ($this->cache['cacheable_endpoints'] ?? []), true);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function readCache(string $endpoint, array $params): ?array
    {
        if (null === $this->cachePool) {
            return null;
        }

        try {
            $item = $this->cachePool->getItem($this->cacheKey($endpoint, $params));
            if (!$item->isHit()) {
                return null;
            }

            $value = $item->get();

            if (!is_array($value)) {
                return null;
            }

            /** @var array<string, mixed> $value */
            return $value;
        } catch (\Throwable $exception) {
            $this->log('warning', 'NameSilo cache read failed', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $reply
     */
    private function writeCache(string $endpoint, array $params, array $reply): void
    {
        if (null === $this->cachePool) {
            return;
        }

        try {
            $item = $this->cachePool->getItem($this->cacheKey($endpoint, $params));
            $item->set($reply);
            $item->expiresAfter(max(1, $this->intValue($this->cache['ttl_seconds'] ?? 300)));
            $this->cachePool->save($item);
        } catch (\Throwable $exception) {
            $this->log('warning', 'NameSilo cache write failed', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function clearCache(): void
    {
        if (null === $this->cachePool) {
            return;
        }

        try {
            $this->cachePool->clear();
        } catch (\Throwable $exception) {
            $this->log('warning', 'NameSilo cache purge failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function cacheKey(string $endpoint, array $params): string
    {
        $payload = $this->apiKey . '|' . $endpoint . '|' . $this->buildQuery($params);

        return 'namesilo.' . substr(hash('sha256', $payload), 0, 48);
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        try {
            $headers = $response->getHeaders(false);
        } catch (\Throwable) {
            return null;
        }

        $value = $headers['retry-after'][0] ?? null;
        if (null === $value) {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, $this->intValue($value));
        }

        $timestamp = strtotime($value);

        return false === $timestamp ? null : max(0, $timestamp - time());
    }

    private function waitBeforeRetry(int $attempt, int $delayMs): void
    {
        if ($delayMs <= 0) {
            return;
        }

        $delay = min(10000, $delayMs * (2 ** $attempt));
        usleep($delay * 1000);
    }

    private function maskUrl(string $url): string
    {
        if (!(bool) ($this->logging['mask_sensitive'] ?? true)) {
            return $url;
        }

        return preg_replace('/([?&]key=)[^&]*/i', '$1***', $url) ?? $url;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!(bool) ($this->logging['enabled'] ?? true)) {
            return;
        }

        $this->logger->log(
            $this->stringValue($this->logging['level'] ?? 'info', 'info'),
            $message,
            $context
        );
    }

    /**
     * @param array<string, mixed> $reply
     */
    private function logResponse(string $endpoint, array $reply, string $requestId): void
    {
        $code = $this->intValue($reply['code'] ?? 0);
        $level = in_array($code, [301, 302], true) ? 'warning' : 'debug';

        $this->log($level, 'NameSilo response', [
            'endpoint' => $endpoint,
            'code' => $code,
            'detail' => $this->stringValue($reply['detail'] ?? ''),
            'request_id' => $requestId,
        ]);
    }

    private function intValue(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
