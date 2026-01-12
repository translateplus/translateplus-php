<?php

require __DIR__ . '/../vendor/autoload.php';

use TranslatePlus\Client;

// Initialize client
$client = new Client([
    'api_key' => getenv('TRANSLATEPLUS_API_KEY') ?: 'your-api-key-here'
]);

echo "=== Basic Translation ===\n";
$result = $client->translate([
    'text' => 'Hello, world!',
    'source' => 'en',
    'target' => 'fr'
]);
echo "Translation: " . $result['translations']['translation'] . "\n\n";

echo "=== Batch Translation ===\n";
$result = $client->translateBatch([
    'texts' => ['Hello', 'Goodbye', 'Thank you'],
    'source' => 'en',
    'target' => 'fr'
]);
foreach ($result['translations'] as $translation) {
    echo "- " . $translation['translation'] . "\n";
}
echo "\n";

echo "=== Language Detection ===\n";
$result = $client->detectLanguage('Bonjour le monde');
echo "Detected language: " . $result['language_detection']['language'] . "\n";
echo "Confidence: " . $result['language_detection']['confidence'] . "\n\n";

echo "=== Supported Languages ===\n";
$result = $client->getSupportedLanguages();
echo "Total languages: " . count($result['supported_languages']) . "\n";
echo "Sample languages:\n";
$sample = array_slice($result['supported_languages'], 0, 5, true);
foreach ($sample as $code => $name) {
    echo "  {$code}: {$name}\n";
}
echo "\n";

echo "=== Account Summary ===\n";
$summary = $client->getAccountSummary();
echo "Credits remaining: " . $summary['credits_remaining'] . "\n";
echo "Plan: " . $summary['plan_name'] . "\n";
echo "Concurrency limit: " . $summary['concurrency_limit'] . "\n";
