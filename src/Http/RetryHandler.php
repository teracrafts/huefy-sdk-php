<?php

declare(strict_types=1);

namespace Huefy\Http;

use Huefy\Errors\HuefyException;

/**
 * Handles retry logic with exponential backoff and jitter.
 */
class RetryHandler
{
    /**
     * @param int   $maxRetries           Maximum number of retry attempts.
     * @param int   $baseDelayMs          Base delay in milliseconds.
     * @param int   $maxDelayMs           Maximum delay in milliseconds.
     * @param int[] $retryableStatusCodes HTTP status codes that trigger retries.
     */
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 500,
        private readonly int $maxDelayMs = 10_000,
        private readonly array $retryableStatusCodes = [408, 429, 500, 502, 503, 504],
    ) {
    }

    /**
     * Executes the given callable with retry logic.
     *
     * On retryable failures, waits with exponential backoff plus jitter
     * before the next attempt. Non-retryable failures are thrown immediately.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     *
     * @throws HuefyException After all retry attempts are exhausted.
     */
    public function executeWithRetry(callable $fn): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $fn();
            } catch (HuefyException $e) {
                $lastException = $e;

                if (!$e->isRecoverable() || $attempt >= $this->maxRetries) {
                    throw $e;
                }

                $statusCode = $e->getStatusCode();
                if ($statusCode !== null && !in_array($statusCode, $this->retryableStatusCodes, true)) {
                    throw $e;
                }

                $delayMs = $this->calculateDelay($attempt);
                usleep($delayMs * 1000);
            }
        }

        throw $lastException ?? HuefyException::networkError('All retry attempts exhausted');
    }

    /**
     * Calculates the delay for a given attempt using exponential backoff
     * with multiplicative ±25% jitter.
     *
     * Formula: min(maxDelay, baseDelay * 2^attempt) * (0.8 + random * 0.4)
     */
    public function calculateDelay(int $attempt): int
    {
        $exponentialDelay = $this->baseDelayMs * (1 << min($attempt, 30));
        $cappedDelay = min($exponentialDelay, $this->maxDelayMs);
        $jitterFactor = 0.8 + (mt_rand() / mt_getrandmax()) * 0.4;

        return min((int) ($cappedDelay * $jitterFactor), $this->maxDelayMs);
    }
}
