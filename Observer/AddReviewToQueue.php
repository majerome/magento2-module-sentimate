<?php

declare(strict_types=1);

namespace Majerome\Sentimate\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Review\Model\Review;
use Magento\Framework\MessageQueue\PublisherInterface;

class AddReviewToQueue implements ObserverInterface
{

    /**
     * Constructor
     *
     * @param PublisherInterface $publisher
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly PublisherInterface  $publisher,
        private readonly SerializerInterface $serializer,
    )
    {

    }

    /**
     * Adds a review to the queue
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(
        Observer $observer,
    ): void
    {
        /*  @var Review $review */
        $review = $observer->getEvent()->getData('object');
        if ($review->isObjectNew()) {
            $reviewData = $review->getData();
            $serializedReviewData = $this->serializer->serialize($reviewData);
            $this->publisher->publish('majerome.sentimate.reviews', $serializedReviewData);
        }
    }
}
