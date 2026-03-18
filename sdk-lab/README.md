# Huefy PHP SDK Lab

A standalone verification runner that exercises core SDK infrastructure.

## Run

```bash
composer lab
# or directly:
php sdk-lab/run.php
```

## Scenarios

1. Initialization — create client with dummy key, no exception
2. Config validation — empty API key throws
3. HMAC signing — 64-char hex signature
4. Error sanitization — IP and email redacted
5. PII detection — email/ssn identified
6. Circuit breaker state — new breaker starts closed
7. Health check — GET /health (PASS regardless of network)
8. Cleanup — close client, no exception
