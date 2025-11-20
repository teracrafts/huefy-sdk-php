<?php

declare(strict_types=1);

namespace Huefy\SDK;

use Huefy\SDK\Config\HuefyConfig;
use Huefy\SDK\Exceptions\HuefyException;
use Huefy\SDK\Exceptions\NetworkException;
use Huefy\SDK\Exceptions\TimeoutException;
use Huefy\SDK\Exceptions\ValidationException;
use JsonException;
use RuntimeException;

/**
 * Kernel client for communicating with the Huefy kernel binary.
 *
 * This class spawns the kernel binary as a subprocess and communicates
 * with it via JSON over stdin/stdout.
 */
class KernelClient
{
    private string $apiKey;
    private string $endpoint;
    private int $timeout;
    private string $kernelBinaryPath;

    public function __construct(string $apiKey, HuefyConfig $config)
    {
        $this->apiKey = $apiKey;
        $this->endpoint = $this->determineEndpoint($config);
        $this->timeout = (int) ($config->getTimeout() * 1000); // Convert to milliseconds
        $this->kernelBinaryPath = $this->getKernelBinaryPath();

        if (empty(trim($this->apiKey))) {
            throw new ValidationException('API key cannot be empty');
        }

        if (!file_exists($this->kernelBinaryPath)) {
            throw new RuntimeException("Kernel binary not found at: {$this->kernelBinaryPath}");
        }

        if (!is_executable($this->kernelBinaryPath)) {
            throw new RuntimeException("Kernel binary is not executable: {$this->kernelBinaryPath}");
        }
    }

    /**
     * Send an email using the kernel binary.
     */
    public function sendEmail(array $requestData): array
    {
        $command = [
            'command' => 'sendEmail',
            'config' => [
                'apiKey' => $this->apiKey,
                'endpoint' => $this->endpoint,
                'timeout' => $this->timeout,
            ],
            'data' => $requestData,
        ];

        return $this->executeKernelCommand($command);
    }

    /**
     * Send bulk emails using the kernel binary.
     */
    public function sendBulkEmails(array $requests): array
    {
        $command = [
            'command' => 'sendBulkEmails',
            'config' => [
                'apiKey' => $this->apiKey,
                'endpoint' => $this->endpoint,
                'timeout' => $this->timeout,
            ],
            'data' => $requests,
        ];

        return $this->executeKernelCommand($command);
    }

    /**
     * Check API health using the kernel binary.
     */
    public function healthCheck(): array
    {
        $command = [
            'command' => 'healthCheck',
            'config' => [
                'apiKey' => $this->apiKey,
                'endpoint' => $this->endpoint,
                'timeout' => $this->timeout,
            ],
        ];

        return $this->executeKernelCommand($command);
    }

    /**
     * Execute a command using the kernel binary.
     */
    private function executeKernelCommand(array $command): array
    {
        try {
            $commandJson = json_encode($command, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HuefyException('Failed to encode command as JSON: ' . $e->getMessage(), 0, $e);
        }

        // Prepare the command to execute
        $cmd = escapeshellarg($this->kernelBinaryPath);

        // Create descriptors for pipes
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Open the process
        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new NetworkException('Failed to start kernel binary process');
        }

        try {
            // Send command to stdin
            fwrite($pipes[0], $commandJson);
            fclose($pipes[0]);

            // Set timeout for reading
            $timeoutSeconds = ceil($this->timeout / 1000);
            stream_set_timeout($pipes[1], $timeoutSeconds);
            stream_set_timeout($pipes[2], $timeoutSeconds);

            // Read stdout and stderr
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            // Wait for the process to finish
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new NetworkException("Kernel binary exited with code {$exitCode}: {$stderr}");
            }

            if (empty($stdout)) {
                throw new HuefyException('Empty response from kernel binary');
            }

            try {
                $response = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new HuefyException('Failed to decode kernel response: ' . $e->getMessage(), 0, $e);
            }

            // Check if the kernel returned an error
            if (!isset($response['success']) || $response['success'] !== true) {
                $error = $response['error'] ?? [];
                $errorCode = $error['code'] ?? 'KERNEL_ERROR';
                $errorMessage = $error['message'] ?? 'Unknown kernel error';
                throw new HuefyException("Kernel error: {$errorMessage}", 0, null, $errorCode);
            }

            return $response['data'] ?? [];

        } catch (Exception $e) {
            // Make sure to close the process if it's still running
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            throw $e;
        }
    }

    /**
     * Determine the gRPC endpoint based on configuration.
     */
    private function determineEndpoint(HuefyConfig $config): string
    {
        // Check if a custom base URL is configured
        $baseUrl = $config->getBaseUrl();
        if ($baseUrl && $baseUrl !== 'https://api.huefy.dev/api/v1/sdk') {
            return $baseUrl;
        }

        // Default endpoints for gRPC
        $isProduction = getenv('APP_ENV') === 'production' || getenv('NODE_ENV') === 'production';
        return $isProduction ? 'api.huefy.dev:50051' : 'localhost:50051';
    }

    /**
     * Get the path to the kernel binary based on the current platform.
     */
    private function getKernelBinaryPath(): string
    {
        $platform = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $binaryName = match (true) {
            $platform === 'Darwin' && $arch === 'arm64' => 'kernel-cli-darwin-arm64',
            $platform === 'Darwin' => 'kernel-cli-darwin-amd64',
            $platform === 'Linux' && str_contains($arch, 'aarch64') => 'kernel-cli-linux-arm64',
            $platform === 'Linux' => 'kernel-cli-linux-amd64',
            $platform === 'Windows' => 'kernel-cli-windows-amd64.exe',
            default => throw new RuntimeException("Unsupported platform: {$platform}")
        };

        // Look for binary in the package's bin directory
        $packageDir = dirname(__DIR__);
        return $packageDir . '/bin/' . $binaryName;
    }

    /**
     * Get the current endpoint.
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the current timeout in milliseconds.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}