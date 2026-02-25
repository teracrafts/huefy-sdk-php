<?php

declare(strict_types=1);

namespace Huefy\Http;

use Huefy\Errors\HuefyException;

/**
 * Circuit breaker implementation using a state machine pattern.
 *
 * States:
 * - CLOSED: Normal operation, requests pass through.
 * - OPEN: Requests are rejected immediately.
 * - HALF_OPEN: A limited number of probe requests are allowed through.
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private float $lastFailureTime = 0.0;
    private int $halfOpenAttempts = 0;

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $resetTimeoutMs = 60_000,
        private readonly int $halfOpenMaxRequests = 1,
    ) {
    }

    /**
     * Executes the given callable through the circuit breaker.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     *
     * @throws HuefyException If the circuit is open or the operation fails.
     */
    public function execute(callable $fn): mixed
    {
        $this->checkState();

        try {
            $result = $fn();
            $this->onSuccess();

            return $result;
        } catch (HuefyException $e) {
            if ($e->isRecoverable()) {
                $this->onFailure();
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->onFailure();

            throw $e;
        }
    }

    /**
     * Returns the current circuit breaker state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Returns the current failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Resets the circuit breaker to its initial closed state.
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = 0.0;
        $this->halfOpenAttempts = 0;
    }

    private function checkState(): void
    {
        switch ($this->state) {
            case self::STATE_OPEN:
                $elapsedMs = (microtime(true) - $this->lastFailureTime) * 1000;
                if ($elapsedMs >= $this->resetTimeoutMs) {
                    $this->state = self::STATE_HALF_OPEN;
                    $this->halfOpenAttempts = 0;
                } else {
                    $remainingMs = (int) ($this->resetTimeoutMs - $elapsedMs);

                    throw HuefyException::circuitBreakerOpen(
                        "Circuit breaker is open. Retry after {$remainingMs}ms."
                    );
                }
                break;

            case self::STATE_HALF_OPEN:
                if ($this->halfOpenAttempts >= $this->halfOpenMaxRequests) {
                    throw HuefyException::circuitBreakerOpen(
                        'Circuit breaker is half-open. Max probe requests reached.'
                    );
                }
                $this->halfOpenAttempts++;
                break;

            case self::STATE_CLOSED:
            default:
                // Allow through.
                break;
        }
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->halfOpenAttempts = 0;
        $this->state = self::STATE_CLOSED;
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
    }
}
