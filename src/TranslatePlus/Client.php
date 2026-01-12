<?php

namespace TranslatePlus;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

/**
 * Official PHP client for TranslatePlus API.
 *
 * This client provides a simple and intuitive interface to all TranslatePlus
 * translation endpoints including text, batch, HTML, email, subtitles, and i18n translation.
 *
 * @example
 * ```php
 * $client = new TranslatePlus\Client([
 *     'api_key' => 'your-api-key'
 * ]);
 * $result = $client->translate([
 *     'text' => 'Hello, world!',
 *     'source' => 'en',
 *     'target' => 'fr'
 * ]);
 * echo $result['translations']['translation']; // 'Bonjour le monde !'
 * ```
 */
class Client
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;
    private int $maxConcurrent;
    private int $activeRequests = 0;
    private GuzzleClient $httpClient;

    /**
     * Create a new TranslatePlus client.
     *
     * @param array $options Client options:
     *   - api_key (string, required): Your TranslatePlus API key
     *   - base_url (string, optional): Base URL for the API (default: https://api.translateplus.io)
     *   - timeout (int, optional): Request timeout in seconds (default: 30)
     *   - max_retries (int, optional): Maximum number of retries for failed requests (default: 3)
     *   - max_concurrent (int, optional): Maximum number of concurrent requests (default: 5)
     *
     * @throws TranslatePlusValidationError If API key is missing
     */
    public function __construct(array $options)
    {
        if (empty($options['api_key'])) {
            throw new TranslatePlusValidationError('API key is required');
        }

        $this->apiKey = $options['api_key'];
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.translateplus.io', '/');
        $this->timeout = $options['timeout'] ?? 30;
        $this->maxRetries = $options['max_retries'] ?? 3;
        $this->maxConcurrent = $options['max_concurrent'] ?? 5;

        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Make an HTTP request to the API.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array|null $data Request body data
     * @param array|null $files Files to upload (for multipart/form-data)
     * @param array|null $params URL query parameters
     * @return array Response JSON as array
     * @throws TranslatePlusAPIError For API errors
     * @throws TranslatePlusAuthenticationError For authentication errors
     * @throws TranslatePlusRateLimitError For rate limit errors
     * @throws TranslatePlusInsufficientCreditsError For insufficient credits
     */
    private function makeRequest(
        string $method,
        string $endpoint,
        ?array $data = null,
        ?array $files = null,
        ?array $params = null
    ): array {
        // Wait for semaphore (concurrency control)
        while ($this->activeRequests >= $this->maxConcurrent) {
            usleep(10000); // 10ms
        }

        $this->activeRequests++;

        try {
            $url = ltrim($endpoint, '/');
            $options = [];

            // Prepare headers
            $headers = [
                'X-API-KEY' => $this->apiKey,
                'User-Agent' => 'translateplus-php/1.0.0',
            ];

            // Handle multipart/form-data for file uploads
            if ($files !== null) {
                $multipart = [];
                // Add form data fields
                foreach ($data ?? [] as $key => $value) {
                    $multipart[] = [
                        'name' => $key,
                        'contents' => (string)$value,
                    ];
                }
                // Add file uploads
                foreach ($files as $key => $filePath) {
                    if (!file_exists($filePath)) {
                        throw new TranslatePlusValidationError("File not found: {$filePath}");
                    }
                    $multipart[] = [
                        'name' => $key,
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath),
                    ];
                }
                $options['multipart'] = $multipart;
                // Don't set Content-Type for multipart - Guzzle will set it automatically
            } else {
                // JSON request
                $headers['Content-Type'] = 'application/json';
                if ($data !== null) {
                    $options['json'] = $data;
                }
            }

            $options['headers'] = $headers;

            // Query parameters
            if ($params !== null) {
                $options['query'] = $params;
            }

            $lastError = null;
            for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
                try {
                    $response = $this->httpClient->request($method, $url, $options);
                    $statusCode = $response->getStatusCode();
                    $body = json_decode($response->getBody()->getContents(), true);

                    if ($statusCode >= 200 && $statusCode < 300) {
                        return $body ?? [];
                    }

                    // Handle error responses
                    $errorMessage = $body['detail'] ?? "API request failed with status {$statusCode}";
                    $errorData = $body;

                    if ($statusCode === 401 || $statusCode === 403) {
                        throw new TranslatePlusAuthenticationError($errorMessage, $statusCode, $errorData);
                    } elseif ($statusCode === 402) {
                        throw new TranslatePlusInsufficientCreditsError($errorMessage, $statusCode, $errorData);
                    } elseif ($statusCode === 429) {
                        throw new TranslatePlusRateLimitError($errorMessage, $statusCode, $errorData);
                    } else {
                        throw new TranslatePlusAPIError($errorMessage, $statusCode, $errorData);
                    }
                } catch (RequestException $e) {
                    $response = $e->getResponse();
                    if ($response !== null) {
                        $statusCode = $response->getStatusCode();
                        $body = json_decode($response->getBody()->getContents(), true);
                        $errorMessage = $body['detail'] ?? $e->getMessage();
                        $errorData = $body;

                        if ($statusCode === 401 || $statusCode === 403) {
                            throw new TranslatePlusAuthenticationError($errorMessage, $statusCode, $errorData);
                        } elseif ($statusCode === 402) {
                            throw new TranslatePlusInsufficientCreditsError($errorMessage, $statusCode, $errorData);
                        } elseif ($statusCode === 429) {
                            throw new TranslatePlusRateLimitError($errorMessage, $statusCode, $errorData);
                        } else {
                            throw new TranslatePlusAPIError($errorMessage, $statusCode, $errorData);
                        }
                    }

                    // Network/connection errors - retry
                    if ($e instanceof ConnectException) {
                        $lastError = $e;
                        if ($attempt < $this->maxRetries) {
                            // Exponential backoff
                            usleep(pow(2, $attempt) * 1000000); // microseconds
                            continue;
                        }
                    }

                    throw new TranslatePlusAPIError("Request failed: " . $e->getMessage());
                }
            }

            // If we get here, all retries failed
            throw new TranslatePlusAPIError(
                "Request failed after {$this->maxRetries} retries: " . ($lastError ? $lastError->getMessage() : 'Unknown error')
            );
        } finally {
            $this->activeRequests--;
        }
    }

    /**
     * Translate a single text.
     *
     * @param array $options Translation options:
     *   - text (string, required): Text to translate
     *   - source (string, optional): Source language code (default: 'auto')
     *   - target (string, required): Target language code
     * @return array Translation result
     *
     * @example
     * ```php
     * $result = $client->translate([
     *     'text' => 'Hello, world!',
     *     'source' => 'en',
     *     'target' => 'fr'
     * ]);
     * echo $result['translations']['translation']; // 'Bonjour le monde !'
     * ```
     */
    public function translate(array $options): array
    {
        return $this->makeRequest('POST', '/v2/translate', [
            'text' => $options['text'],
            'source' => $options['source'] ?? 'auto',
            'target' => $options['target'],
        ]);
    }

    /**
     * Translate multiple texts in a single request.
     *
     * @param array $options Batch translation options:
     *   - texts (array, required): Array of texts to translate
     *   - source (string, optional): Source language code (default: 'auto')
     *   - target (string, required): Target language code
     * @return array Batch translation result
     *
     * @example
     * ```php
     * $result = $client->translateBatch([
     *     'texts' => ['Hello', 'Goodbye', 'Thank you'],
     *     'source' => 'en',
     *     'target' => 'fr'
     * ]);
     * foreach ($result['translations'] as $translation) {
     *     echo $translation['translation'] . "\n";
     * }
     * ```
     */
    public function translateBatch(array $options): array
    {
        if (empty($options['texts']) || !is_array($options['texts'])) {
            throw new TranslatePlusValidationError('Texts array cannot be empty');
        }
        if (count($options['texts']) > 100) {
            throw new TranslatePlusValidationError('Maximum 100 texts allowed per batch request');
        }

        return $this->makeRequest('POST', '/v2/translate/batch', [
            'texts' => $options['texts'],
            'source' => $options['source'] ?? 'auto',
            'target' => $options['target'],
        ]);
    }

    /**
     * Translate HTML content while preserving all tags and structure.
     *
     * @param array $options HTML translation options:
     *   - html (string, required): HTML content to translate
     *   - source (string, optional): Source language code (default: 'auto')
     *   - target (string, required): Target language code
     * @return array Translated HTML content
     *
     * @example
     * ```php
     * $result = $client->translateHTML([
     *     'html' => '<p>Hello <b>world</b></p>',
     *     'source' => 'en',
     *     'target' => 'fr'
     * ]);
     * echo $result['html']; // '<p>Bonjour <b>monde</b></p>'
     * ```
     */
    public function translateHTML(array $options): array
    {
        return $this->makeRequest('POST', '/v2/translate/html', [
            'html' => $options['html'],
            'source' => $options['source'] ?? 'auto',
            'target' => $options['target'],
        ]);
    }

    /**
     * Translate email subject and HTML body.
     *
     * @param array $options Email translation options:
     *   - subject (string, required): Email subject
     *   - email_body (string, required): Email HTML body
     *   - source (string, optional): Source language code (default: 'auto')
     *   - target (string, required): Target language code
     * @return array Translated email
     *
     * @example
     * ```php
     * $result = $client->translateEmail([
     *     'subject' => 'Welcome',
     *     'email_body' => '<p>Thank you for signing up!</p>',
     *     'source' => 'en',
     *     'target' => 'fr'
     * ]);
     * echo $result['subject']; // 'Bienvenue'
     * ```
     */
    public function translateEmail(array $options): array
    {
        return $this->makeRequest('POST', '/v2/translate/email', [
            'subject' => $options['subject'],
            'email_body' => $options['email_body'],
            'source' => $options['source'] ?? 'auto',
            'target' => $options['target'],
        ]);
    }

    /**
     * Translate subtitle files (SRT or VTT format).
     *
     * @param array $options Subtitle translation options:
     *   - content (string, required): Subtitle content
     *   - format (string, required): Format ('srt' or 'vtt')
     *   - source (string, optional): Source language code (default: 'auto')
     *   - target (string, required): Target language code
     * @return array Translated subtitle content
     *
     * @example
     * ```php
     * $result = $client->translateSubtitles([
     *     'content' => "1\n00:00:01,000 --> 00:00:02,000\nHello world\n",
     *     'format' => 'srt',
     *     'source' => 'en',
     *     'target' => 'fr'
     * ]);
     * ```
     */
    public function translateSubtitles(array $options): array
    {
        if (!in_array($options['format'], ['srt', 'vtt'])) {
            throw new TranslatePlusValidationError("Format must be 'srt' or 'vtt'");
        }

        return $this->makeRequest('POST', '/v2/translate/subtitles', [
            'content' => $options['content'],
            'format' => $options['format'],
            'source' => $options['source'] ?? 'auto',
            'target' => $options['target'],
        ]);
    }

    /**
     * Detect the language of a text.
     *
     * @param string $text Text to detect language from
     * @return array Language detection result
     *
     * @example
     * ```php
     * $result = $client->detectLanguage('Bonjour le monde');
     * echo $result['language_detection']['language']; // 'fr'
     * ```
     */
    public function detectLanguage(string $text): array
    {
        return $this->makeRequest('POST', '/v2/language_detect', [
            'text' => $text,
        ]);
    }

    /**
     * Get list of all supported languages.
     *
     * @return array Supported languages
     *
     * @example
     * ```php
     * $result = $client->getSupportedLanguages();
     * print_r($result['supported_languages']);
     * ```
     */
    public function getSupportedLanguages(): array
    {
        return $this->makeRequest('GET', '/v2/supported_languages');
    }

    /**
     * Get account summary (credits, plan, etc.).
     *
     * @return array Account summary
     *
     * @example
     * ```php
     * $summary = $client->getAccountSummary();
     * echo "Credits remaining: " . $summary['credits_remaining'];
     * ```
     */
    public function getAccountSummary(): array
    {
        return $this->makeRequest('GET', '/v2/account/summary');
    }

    /**
     * Create an i18n translation job.
     *
     * @param array $options i18n job options:
     *   - file_path (string, required): Path to the i18n file
     *   - target_languages (array, required): Array of target language codes
     *   - source_language (string, optional): Source language code (default: 'auto')
     *   - webhook_url (string, optional): Webhook URL for job completion notification
     * @return array Job creation result
     *
     * @example
     * ```php
     * $result = $client->createI18nJob([
     *     'file_path' => '/path/to/file.json',
     *     'target_languages' => ['fr', 'es', 'de'],
     *     'source_language' => 'en'
     * ]);
     * echo "Job ID: " . $result['job_id'];
     * ```
     */
    public function createI18nJob(array $options): array
    {
        if (empty($options['file_path']) || !file_exists($options['file_path'])) {
            throw new TranslatePlusValidationError("File not found: {$options['file_path']}");
        }
        if (empty($options['target_languages']) || !is_array($options['target_languages'])) {
            throw new TranslatePlusValidationError('target_languages must be a non-empty array');
        }

        $formData = [
            'source_language' => $options['source_language'] ?? 'auto',
            'target_languages' => implode(',', $options['target_languages']),
        ];
        if (!empty($options['webhook_url'])) {
            $formData['webhook_url'] = $options['webhook_url'];
        }

        return $this->makeRequest('POST', '/v2/i18n/create_job', $formData, [
            'file' => $options['file_path'],
        ]);
    }

    /**
     * Get i18n job status.
     *
     * @param string $jobId Job ID
     * @return array Job status
     *
     * @example
     * ```php
     * $status = $client->getI18nJobStatus('job-123');
     * echo "Status: " . $status['status'];
     * ```
     */
    public function getI18nJobStatus(string $jobId): array
    {
        return $this->makeRequest('GET', "/v2/i18n/job/{$jobId}");
    }

    /**
     * List i18n jobs.
     *
     * @param array $options List options:
     *   - page (int, optional): Page number (default: 1)
     *   - page_size (int, optional): Page size (default: 10)
     * @return array List of jobs
     *
     * @example
     * ```php
     * $jobs = $client->listI18nJobs(['page' => 1, 'page_size' => 20]);
     * foreach ($jobs['results'] as $job) {
     *     echo "Job {$job['id']}: {$job['status']}\n";
     * }
     * ```
     */
    public function listI18nJobs(array $options = []): array
    {
        return $this->makeRequest('GET', '/v2/i18n/jobs', null, null, [
            'page' => $options['page'] ?? 1,
            'page_size' => $options['page_size'] ?? 10,
        ]);
    }
}
