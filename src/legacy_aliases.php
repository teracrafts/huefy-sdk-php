<?php

declare(strict_types=1);

$aliases = [
    'Teracrafts\\Huefy\\HuefyClient' => 'Huefy\\HuefyClient',
    'Teracrafts\\Huefy\\HuefyConfig' => 'Huefy\\HuefyConfig',
    'Teracrafts\\Huefy\\HuefyEmailClient' => 'Huefy\\HuefyEmailClient',
    'Teracrafts\\Huefy\\RateLimitInfo' => 'Huefy\\RateLimitInfo',
    'Teracrafts\\Huefy\\Errors\\ErrorCode' => 'Huefy\\Errors\\ErrorCode',
    'Teracrafts\\Huefy\\Errors\\ErrorSanitizer' => 'Huefy\\Errors\\ErrorSanitizer',
    'Teracrafts\\Huefy\\Errors\\HuefyException' => 'Huefy\\Errors\\HuefyException',
    'Teracrafts\\Huefy\\Http\\CircuitBreaker' => 'Huefy\\Http\\CircuitBreaker',
    'Teracrafts\\Huefy\\Http\\HttpClient' => 'Huefy\\Http\\HttpClient',
    'Teracrafts\\Huefy\\Http\\RetryHandler' => 'Huefy\\Http\\RetryHandler',
    'Teracrafts\\Huefy\\Models\\BulkRecipient' => 'Huefy\\Models\\BulkRecipient',
    'Teracrafts\\Huefy\\Models\\EmailProvider' => 'Huefy\\Models\\EmailProvider',
    'Teracrafts\\Huefy\\Models\\HealthResponse' => 'Huefy\\Models\\HealthResponse',
    'Teracrafts\\Huefy\\Models\\RecipientStatus' => 'Huefy\\Models\\RecipientStatus',
    'Teracrafts\\Huefy\\Models\\SendBulkEmailsRequest' => 'Huefy\\Models\\SendBulkEmailsRequest',
    'Teracrafts\\Huefy\\Models\\SendBulkEmailsResponse' => 'Huefy\\Models\\SendBulkEmailsResponse',
    'Teracrafts\\Huefy\\Models\\SendEmailRecipient' => 'Huefy\\Models\\SendEmailRecipient',
    'Teracrafts\\Huefy\\Models\\SendEmailRequest' => 'Huefy\\Models\\SendEmailRequest',
    'Teracrafts\\Huefy\\Models\\SendEmailResponse' => 'Huefy\\Models\\SendEmailResponse',
    'Teracrafts\\Huefy\\Models\\ValidateTemplateRequest' => 'Huefy\\Models\\ValidateTemplateRequest',
    'Teracrafts\\Huefy\\Models\\ValidateTemplateResponse' => 'Huefy\\Models\\ValidateTemplateResponse',
    'Teracrafts\\Huefy\\Models\\ValidateTemplateResponseData' => 'Huefy\\Models\\ValidateTemplateResponseData',
    'Teracrafts\\Huefy\\Security\\Security' => 'Huefy\\Security\\Security',
    'Teracrafts\\Huefy\\Utils\\ConsoleLogger' => 'Huefy\\Utils\\ConsoleLogger',
    'Teracrafts\\Huefy\\Utils\\Logger' => 'Huefy\\Utils\\Logger',
    'Teracrafts\\Huefy\\Utils\\NullLogger' => 'Huefy\\Utils\\NullLogger',
    'Teracrafts\\Huefy\\Utils\\Version' => 'Huefy\\Utils\\Version',
    'Teracrafts\\Huefy\\Validators\\EmailValidators' => 'Huefy\\Validators\\EmailValidators',
];

foreach ($aliases as $new => $old) {
    if (class_exists($new) || enum_exists($new) || interface_exists($new)) {
        class_alias($new, $old);
    }
}
