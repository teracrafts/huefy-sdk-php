<?php

declare(strict_types=1);

namespace Teracrafts\Huefy\Errors;

/**
 * Base exception for all Huefy SDK errors.
 */
class HuefyException extends \RuntimeException
{
    private string $errorCode;
    private ?int $statusCode;
    private bool $recoverable;
    private ?string $requestId;
    /** @var array<string, string> */
    private array $context;

    /**
     * @param string               $message    Error message.
     * @param string               $errorCode  Structured error code from ErrorCode.
     * @param ?int                  $statusCode HTTP status code, if applicable.
     * @param bool                  $recoverable Whether the error is potentially recoverable.
     * @param ?string               $requestId  Request ID from X-Request-Id header.
     * @param array<string, string> $context    Additional error context.
     * @param ?\Throwable           $previous   Previous exception for chaining.
     */
    public function __construct(
        string $message,
        string $errorCode = ErrorCode::UNKNOWN_ERROR,
        ?int $statusCode = null,
        bool $recoverable = false,
        ?string $requestId = null,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->recoverable = $recoverable;
        $this->requestId = $requestId;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }

    /**
     * @return array<string, string>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function __toString(): string
    {
        $parts = ["HuefyException[{$this->errorCode}]", $this->getMessage()];
        if ($this->statusCode !== null) {
            $parts[] = "status={$this->statusCode}";
        }
        if ($this->requestId !== null) {
            $parts[] = "requestId={$this->requestId}";
        }
        if ($this->recoverable) {
            $parts[] = 'recoverable=true';
        }
        if (!empty($this->context)) {
            $parts[] = 'context=' . json_encode($this->context);
        }

        return implode(' | ', $parts);
    }

    // ---------------------------------------------------------------
    // Factory methods
    // ---------------------------------------------------------------

    public static function networkError(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            message: $message,
            errorCode: ErrorCode::NETWORK_ERROR,
            recoverable: true,
            previous: $previous,
        );
    }

    public static function authenticationError(string $message = 'Authentication failed'): self
    {
        return new self(
            message: $message,
            errorCode: ErrorCode::AUTHENTICATION_ERROR,
            statusCode: 401,
            recoverable: false,
        );
    }

    public static function rateLimitError(?int $retryAfterMs = null): self
    {
        $context = $retryAfterMs !== null ? ['retryAfterMs' => (string) $retryAfterMs] : [];

        return new self(
            message: 'Rate limit exceeded',
            errorCode: ErrorCode::RATE_LIMIT_ERROR,
            statusCode: 429,
            recoverable: true,
            context: $context,
        );
    }

    public static function validationError(string $message, ?string $field = null): self
    {
        $context = $field !== null ? ['field' => $field] : [];

        return new self(
            message: $message,
            errorCode: ErrorCode::VALIDATION_ERROR,
            statusCode: 400,
            recoverable: false,
            context: $context,
        );
    }

    public static function serverError(string $message, int $statusCode = 500): self
    {
        return new self(
            message: $message,
            errorCode: ErrorCode::SERVER_ERROR,
            statusCode: $statusCode,
            recoverable: true,
        );
    }

    public static function timeoutError(string $message = 'Request timed out'): self
    {
        return new self(
            message: $message,
            errorCode: ErrorCode::TIMEOUT_ERROR,
            recoverable: true,
        );
    }

    public static function circuitBreakerOpen(string $message = 'Circuit breaker is open'): self
    {
        return new self(
            message: $message,
            errorCode: ErrorCode::CIRCUIT_BREAKER_OPEN,
            recoverable: true,
        );
    }

    public static function fromStatusCode(int $statusCode, string $message, ?string $requestId = null, ?int $retryAfterMs = null): self
    {
        $exception = match (true) {
            $statusCode === 401 => self::authenticationError($message),
            $statusCode === 403 => new self(
                message: $message,
                errorCode: ErrorCode::FORBIDDEN_ERROR,
                statusCode: 403,
                recoverable: false,
            ),
            $statusCode === 404 => new self(
                message: $message,
                errorCode: ErrorCode::NOT_FOUND_ERROR,
                statusCode: 404,
                recoverable: false,
            ),
            $statusCode === 402 => new self(
                message: $message,
                errorCode: ErrorCode::INSUFFICIENT_QUOTA,
                statusCode: 402,
                recoverable: false,
            ),
            $statusCode === 429 => self::rateLimitError($retryAfterMs),
            $statusCode >= 500 && $statusCode <= 599 => self::serverError($message, $statusCode),
            default => new self(
                message: $message,
                errorCode: ErrorCode::UNKNOWN_ERROR,
                statusCode: $statusCode,
                recoverable: false,
            ),
        };

        if ($requestId !== null) {
            $exception->setRequestId($requestId);
        }

        return $exception;
    }
}
