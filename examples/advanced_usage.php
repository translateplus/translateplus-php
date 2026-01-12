<?php

require __DIR__ . '/../vendor/autoload.php';

use TranslatePlus\Client;
use TranslatePlus\TranslatePlusError;

// Initialize client with custom options
$client = new Client([
    'api_key' => getenv('TRANSLATEPLUS_API_KEY') ?: 'your-api-key-here',
    'timeout' => 60,
    'max_retries' => 5,
    'max_concurrent' => 10
]);

echo "=== HTML Translation ===\n";
$result = $client->translateHTML([
    'html' => '<div><h1>Welcome</h1><p>This is a <strong>test</strong>.</p></div>',
    'source' => 'en',
    'target' => 'fr'
]);
echo "Translated HTML:\n" . $result['html'] . "\n\n";

echo "=== Email Translation ===\n";
$result = $client->translateEmail([
    'subject' => 'Welcome to our service',
    'email_body' => '<p>Thank you for signing up! We are excited to have you.</p>',
    'source' => 'en',
    'target' => 'es'
]);
echo "Subject: " . $result['subject'] . "\n";
echo "Body: " . $result['html_body'] . "\n\n";

echo "=== Error Handling Example ===\n";
try {
    $result = $client->translate([
        'text' => 'Hello',
        'target' => 'invalid-language-code'
    ]);
} catch (\TranslatePlus\TranslatePlusAPIError $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo "Status Code: " . $e->getStatusCode() . "\n";
} catch (\TranslatePlus\TranslatePlusValidationError $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
} catch (TranslatePlusError $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
