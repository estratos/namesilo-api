<?php

declare(strict_types=1);

namespace Estratos\NameSiloBundle\Exception;

class InsufficientFundsException extends ApiException
{
    private readonly float $requiredAmount;
    private readonly float $availableBalance;

    public function __construct(
        float $requiredAmount,
        float $availableBalance,
        string $message = 'Insufficient funds in account.',
        ?string $endpoint = null,
        ?string $requestId = null,
        ?\Throwable $previous = null
    ) {
        $this->requiredAmount = $requiredAmount;
        $this->availableBalance = $availableBalance;
        
        $message = sprintf(
            'Insufficient funds. Required: $%s, Available: $%s',
            number_format($requiredAmount, 2),
            number_format($availableBalance, 2)
        );
        
        parent::__construct($message, $endpoint, 402, $requestId, $previous);
    }

    public function getRequiredAmount(): float
    {
        return $this->requiredAmount;
    }

    public function getAvailableBalance(): float
    {
        return $this->availableBalance;
    }
}