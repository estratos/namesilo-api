<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class InvalidContactException extends ValidationException
{
    public function __construct(
        string $contactId,
        string $reason = 'Invalid contact ID.',
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Invalid contact "%s": %s', $contactId, $reason),
            ['contactId' => $reason],
            $endpoint,
            $requestId,
            $previous
        );
    }
}