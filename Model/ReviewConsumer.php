<?php

declare(strict_types=1);

namespace Majerome\Sentimate\Model;

use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class ReviewConsumer
{
    /**
     * Constructor for queue consumer.
     *
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param ReviewSentimentFactory $reviewSentimentFactory
     * @param RapidApi $rapidApi
     * @param ReviewSentimentService $reviewSentimentService
     */
    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly SerializerInterface    $serializer,
        private readonly ReviewSentimentFactory $reviewSentimentFactory,
        private readonly RapidApi               $rapidApi,
        private readonly ReviewSentimentService $reviewSentimentService,
    ) {
    }

    /**
     * Process method to consume queued reviews.
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        $text = $this->prepDataForApi($message);
        $sentimentAnalysisResults = $this->rapidApi->getSentimentAnalysis($text);

        if ($this->rapidApi->areSentimentAnalysisResultsValid($sentimentAnalysisResults)) {
            $deserializedMessage = $this->serializer->unserialize($message);
            $reviewSentiment = $this->reviewSentimentFactory->create();
            $reviewSentiment->setData([
                'review_id' => $deserializedMessage['review_id'],
                'type' => $sentimentAnalysisResults['type'],
                'score' => $sentimentAnalysisResults['score'],
                'ratio' => $sentimentAnalysisResults['ratio'],
            ]);
            $this->reviewSentimentService->save($reviewSentiment);
        }
    }

    /**
     * @param string $message
     * @return string
     */
    private function prepDataForApi(
        string $message,
    ): string {
        $result = '';
        try {
            $deserializedMessage = $this->serializer->unserialize($message);
            $title = $deserializedMessage['title'];
            $detail = $deserializedMessage['detail'];
            $result = "$title: $detail";
        } catch (Exception $exception) {
            $this->logger->error(__('Failed to deserialize sentiment analysis results: %1', $exception->getMessage()));
        }
        return $result;
    }
}
