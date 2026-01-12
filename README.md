# TranslatePlus PHP Client

Official PHP client library for the TranslatePlus API - Professional translation service for text, HTML, emails, subtitles, and i18n files.

[![Packagist Version](https://img.shields.io/packagist/v/translateplus/translateplus-php)](https://packagist.org/packages/translateplus/translateplus-php)
[![PHP Version](https://img.shields.io/packagist/php-v/translateplus/translateplus-php)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/translateplus/translateplus-php)](LICENSE)

## Features

- ✅ **Full API Support** - All endpoints including text, batch, HTML, email, subtitles, and i18n translation
- ✅ **Error Handling** - Comprehensive exception handling with detailed error messages
- ✅ **Retry Logic** - Automatic retry with exponential backoff for failed requests
- ✅ **Concurrency Control** - Built-in support for parallel translations with configurable concurrency limits
- ✅ **Type Safety** - Full PHPDoc annotations for better IDE support
- ✅ **Production Ready** - Connection pooling, rate limiting, and robust error handling

## Installation

Install via Composer:

```bash
composer require translateplus/translateplus-php
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use TranslatePlus\Client;

// Initialize client
$client = new Client([
    'api_key' => 'your-api-key'
]);

// Translate a single text
$result = $client->translate([
    'text' => 'Hello, world!',
    'source' => 'en',
    'target' => 'fr'
]);

echo $result['translations']['translation']; // 'Bonjour le monde !'
```

## API Reference

### Client Options

```php
$client = new Client([
    'api_key' => 'your-api-key',        // Required: Your TranslatePlus API key
    'base_url' => 'https://api.translateplus.io', // Optional: API base URL
    'timeout' => 30,                     // Optional: Request timeout in seconds (default: 30)
    'max_retries' => 3,                  // Optional: Maximum retries (default: 3)
    'max_concurrent' => 5,               // Optional: Max concurrent requests (default: 5)
]);
```

### Translation Methods

#### Translate Text

```php
$result = $client->translate([
    'text' => 'Hello, world!',
    'source' => 'en',  // Optional, defaults to 'auto'
    'target' => 'fr'   // Required
]);

echo $result['translations']['translation'];
```

#### Batch Translation

```php
$result = $client->translateBatch([
    'texts' => ['Hello', 'Goodbye', 'Thank you'],
    'source' => 'en',
    'target' => 'fr'
]);

foreach ($result['translations'] as $translation) {
    echo $translation['translation'] . "\n";
}
```

#### Translate HTML

```php
$result = $client->translateHTML([
    'html' => '<p>Hello <b>world</b></p>',
    'source' => 'en',
    'target' => 'fr'
]);

echo $result['html']; // '<p>Bonjour <b>monde</b></p>'
```

#### Translate Email

```php
$result = $client->translateEmail([
    'subject' => 'Welcome',
    'email_body' => '<p>Thank you for signing up!</p>',
    'source' => 'en',
    'target' => 'fr'
]);

echo $result['subject'];     // 'Bienvenue'
echo $result['html_body'];   // '<p>Merci de vous être inscrit!</p>'
```

#### Translate Subtitles

```php
$result = $client->translateSubtitles([
    'content' => "1\n00:00:01,000 --> 00:00:02,000\nHello world\n",
    'format' => 'srt',  // or 'vtt'
    'source' => 'en',
    'target' => 'fr'
]);

echo $result['content'];
```

### Language Methods

#### Detect Language

```php
$result = $client->detectLanguage('Bonjour le monde');
echo $result['language_detection']['language']; // 'fr'
echo $result['language_detection']['confidence']; // 0.95
```

#### Get Supported Languages

```php
$result = $client->getSupportedLanguages();
print_r($result['supported_languages']);
// Array (
//     [en] => English
//     [fr] => French
//     [es] => Spanish
//     ...
// )
```

### Account Methods

#### Get Account Summary

```php
$summary = $client->getAccountSummary();
echo "Credits remaining: " . $summary['credits_remaining'];
echo "Plan: " . $summary['plan_name'];
echo "Concurrency limit: " . $summary['concurrency_limit'];
```

### i18n Translation Jobs

#### Create i18n Job

```php
$result = $client->createI18nJob([
    'file_path' => '/path/to/locales/en.json',
    'target_languages' => ['fr', 'es', 'de'],
    'source_language' => 'en',
    'webhook_url' => 'https://example.com/webhook' // Optional
]);

echo "Job ID: " . $result['job_id'];
```

#### Get Job Status

```php
$status = $client->getI18nJobStatus('job-123');
echo "Status: " . $status['status']; // 'pending', 'processing', 'completed', 'failed'
echo "Progress: " . $status['progress'] . "%";
```

#### List Jobs

```php
$jobs = $client->listI18nJobs([
    'page' => 1,
    'page_size' => 20
]);

foreach ($jobs['results'] as $job) {
    echo "Job {$job['id']}: {$job['status']}\n";
}
```

## Error Handling

The client throws specific exceptions for different error types:

```php
use TranslatePlus\TranslatePlusError;
use TranslatePlus\TranslatePlusAPIError;
use TranslatePlus\TranslatePlusAuthenticationError;
use TranslatePlus\TranslatePlusRateLimitError;
use TranslatePlus\TranslatePlusInsufficientCreditsError;
use TranslatePlus\TranslatePlusValidationError;

try {
    $result = $client->translate([
        'text' => 'Hello',
        'target' => 'fr'
    ]);
} catch (TranslatePlusAuthenticationError $e) {
    echo "Authentication failed: " . $e->getMessage();
} catch (TranslatePlusInsufficientCreditsError $e) {
    echo "Insufficient credits: " . $e->getMessage();
    echo "Status code: " . $e->getStatusCode();
} catch (TranslatePlusRateLimitError $e) {
    echo "Rate limit exceeded: " . $e->getMessage();
} catch (TranslatePlusAPIError $e) {
    echo "API error: " . $e->getMessage();
    echo "Status code: " . $e->getStatusCode();
    print_r($e->getResponse());
} catch (TranslatePlusValidationError $e) {
    echo "Validation error: " . $e->getMessage();
}
```

## Advanced Usage

### Concurrent Translations

The client automatically handles concurrency limits. You can configure the maximum concurrent requests:

```php
$client = new Client([
    'api_key' => 'your-api-key',
    'max_concurrent' => 10  // Allow up to 10 concurrent requests
]);
```

### Custom Timeout and Retries

```php
$client = new Client([
    'api_key' => 'your-api-key',
    'timeout' => 60,        // 60 second timeout
    'max_retries' => 5      // Retry up to 5 times
]);
```

## Requirements

- PHP 7.4 or higher
- `ext-json` extension
- `ext-curl` extension
- Composer

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: [https://docs.translateplus.io](https://docs.translateplus.io)
- **Issues**: [GitHub Issues](https://github.com/translateplus/translateplus-php/issues)
- **Email**: support@translateplus.io

## Related Libraries

- **Python**: [translateplus-python](https://pypi.org/project/translateplus-python/)
- **JavaScript/TypeScript**: [translateplus-js](https://www.npmjs.com/package/translateplus-js)
