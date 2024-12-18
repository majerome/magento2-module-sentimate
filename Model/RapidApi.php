<?php

declare(strict_types=1);

namespace Majerome\Sentimate\Model;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Uri\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class RapidApi
{
    public const API_KEY_CONFIG_PATH = 'majerome_sentimate/rapidapi/api_key';

    /**
     * RapidApi Constructor.
     *
     * @param GuzzleClient $guzzleClient
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param Http $http
     */
    public function __construct(
        private readonly GuzzleClient         $guzzleClient,
        private readonly LoggerInterface      $logger,
        private readonly SerializerInterface  $serializer,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface   $encryptor,
        private readonly Http                 $http,
    )
    {
    }

    /**
     * Genreric function to call the RapidApi API.
     *
     * @param string $endpoint
     * @param array $formParams
     * @return array
     */
    private function callApi(
        string $endpoint,
        array  $formParams = [],
    ): array
    {
        $apiKey = $this->getApiKey();
        $url = $this->http->parse($endpoint);
        $apiHost = $url->getHost();

        try {
            $response = $this->guzzleClient->request('POST', $endpoint, [
                'form_params' => $formParams,
                'headers' => [
                    'X-RapidApi-Host' => $apiHost,
                    'X-RapidApi-Key' => $apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $body = $response->getBody();
            $result = $this->serializer->unserialize($body);
        } catch (GuzzleException $exception) {
            $this->logger->error(__("$endpoint returned an error: %1", $exception->getMessage()));
        }

        return $result ?? [];
    }

    /**
     * Get the API Key
     *
     * @return string
     */
    private function getApiKey(): string
    {
        $apiKey = $this->scopeConfig->getValue(self::API_KEY_CONFIG_PATH);
        return $this->encryptor->decrypt($apiKey);
    }

    /**
     * Call the Sentiment Analysis API through RapidAPI
     *
     * @param string $text
     * @return array
     */
    public function getSentimentAnalysis(
        string $text,
    ): array
    {
        $result = $this->callApi('https://twinword-sentiment-analysis.p.rapidapi.com/analyze/', [
            'text' => $text,
        ]);

        $this->logInvalidSentimentAnalysisResults($result);

        return $result;
    }

    /**
     * Log invalid Sentiment Analysis API results.
     *
     * @param array $result
     * @return void
     */
    private function logInvalidSentimentAnalysisResults(
        array $result,
    ): void
    {
        if (!$this->areSentimentAnalysisResultsValid($result)) {
            $stringResponse = implode(', ', $result);
            $this->logger->error(
                __('Sentiment Analysis API did not return expected results: %1', $stringResponse)
            );
        }
    }

    /**
     * Checks if Sentiment Analysis API results are valid.
     *
     * @param array $result
     * @return bool
     */
    public function areSentimentAnalysisResultsValid(
        array $result,
    ): bool
    {
        return isset($result['type'], $result['score'], $result['ratio']);
    }
}
