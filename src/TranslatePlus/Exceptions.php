<?php

namespace TranslatePlus;

/**
 * Base exception for all TranslatePlus errors.
 */
class TranslatePlusError extends \Exception
{
}

/**
 * Exception raised for API errors.
 */
class TranslatePlusAPIError extends TranslatePlusError
{
    protected $statusCode;
    protected $response;

    public function __construct(string $message, ?int $statusCode = null, ?array $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}

/**
 * Exception raised for authentication errors (401, 403).
 */
class TranslatePlusAuthenticationError extends TranslatePlusAPIError
{
}

/**
 * Exception raised for rate limit errors (429).
 */
class TranslatePlusRateLimitError extends TranslatePlusAPIError
{
}

/**
 * Exception raised for insufficient credits (402).
 */
class TranslatePlusInsufficientCreditsError extends TranslatePlusAPIError
{
}

/**
 * Exception raised for validation errors.
 */
class TranslatePlusValidationError extends TranslatePlusError
{
}
