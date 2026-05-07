# Huefy PHP SDK Lab

Verifies the core email contract through the real `HuefyEmailClient` without sending live email.

## Run

```bash
php sdk-lab/run.php
```

from `sdks/php/`.

## Scenarios

1. Initialization
2. Single email contract
3. Bulk email contract
4. Validation rejects invalid single recipient
5. Validation rejects invalid bulk request
6. Health check path
7. Cleanup

## Notes

- The lab uses an inline stub client rather than the live API.
- It checks serialized request bodies, typed response parsing, and validation boundaries.
