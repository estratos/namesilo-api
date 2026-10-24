<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

/**
 * Maps NameSilo numeric response codes to typed exceptions.
 */
final class ApiExceptionFactory
{
    /** Codes that mean the operation succeeded, possibly with a warning. */
    private const SUCCESS_CODES = [300, 301, 302];

    /** Codes that mean the desired state was already in place. */
    private const IDEMPOTENT_CODES = [252, 253, 255, 256];

    /**
     * @param array<string, mixed> $reply
     * @param array<string, mixed> $params
     */
    public static function fromReply(
        array $reply,
        string $endpoint,
        array $params = [],
        ?string $requestId = null
    ): ?ApiException {
        $code = self::intValue($reply['code'] ?? 0);

        if (in_array($code, self::SUCCESS_CODES, true)
            || in_array($code, self::IDEMPOTENT_CODES, true)
        ) {
            return null;
        }

        $detail = trim(self::stringValue($reply['detail'] ?? ''));
        if ('' === $detail) {
            $detail = sprintf('NameSilo API error %d.', $code);
        }

        $domain = self::stringValue($params['domain'] ?? $params['domains'] ?? '');

        return match ($code) {
            108 => new ValidationException(
                $detail,
                ['code' => $code],
                $endpoint,
                $requestId
            ),
            110 => new AuthenticationException($detail, $endpoint, $requestId),
            114, 200, 261, 262, 267 => new InvalidDomainException(
                $domain,
                $detail,
                $endpoint,
                $requestId
            ),
            119 => new InsufficientFundsException(
                self::readFloat($reply, ['required_amount', 'required']),
                self::readFloat($reply, ['balance', 'available_balance', 'available']),
                endpoint: $endpoint,
                requestId: $requestId
            ),
            280 => new DNSException($detail, $endpoint, $requestId),
            default => new ApiException(
                $detail,
                $endpoint,
                null,
                $requestId,
                null,
                $code
            ),
        };
    }

    /**
     * @param array<string, mixed> $reply
     * @param list<string>         $keys
     */
    private static function readFloat(array $reply, array $keys): float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $reply)) {
                continue;
            }

            $value = $reply[$key];
            if (is_int($value) || is_float($value)) {
                return (float) $value;
            }

            if (is_string($value)) {
                return (float) str_replace([',', '$', ' '], '', $value);
            }
        }

        return 0.0;
    }

    private static function intValue(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private static function stringValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }
}
