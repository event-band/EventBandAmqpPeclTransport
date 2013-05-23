<?php
/*
 * Copyright (c)
 * Kirill chEbba Chebunin <iam@chebba.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 */

namespace EventBand\Transport\PeclAmqp;

use EventBand\Transport\Amqp\Driver\AmqpMessage;
use EventBand\Transport\Amqp\Driver\CustomAmqpMessage;
use EventBand\Transport\Amqp\Driver\MessageDelivery;

/**
 * Description of PeclAmqpMessage
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 * @license http://opensource.org/licenses/mit-license.php MIT
 */
class PeclMessageUtils
{
    private static $ATTRIBUTE_MAP = [
        'headers' => 'headers',
        'contentType' => 'content_type',
        'contentEncoding' => 'content_encoding',
        'messageId' => 'message_id',
        'userId' => 'user_id',
        'appId' => 'app_id',
        'priority' => 'priority',
        'timestamp' => 'timestamp',
        'expiration' => 'expiration',
        'type' => 'type',
        'replyTo' => 'reply_to',
    ];

    /**
     * Create message from amqp envelope
     *
     * @param \AMQPEnvelope $envelope
     *
     * @return AmqpMessage
     */
    public static function createMessage(\AMQPEnvelope $envelope)
    {
        $properties = [];
        foreach (CustomAmqpMessage::getPropertyNames() as $name) {
            $properties[$name] = $envelope->{'get'.ucfirst($name)}();
        }

        return CustomAmqpMessage::fromProperties($properties);
    }

    /**
     * Create delivery object from amqp envelope
     *
     * @param \AMQPEnvelope $envelope
     * @param string        $query
     *
     * @return MessageDelivery
     */
    public static function createDelivery(\AMQPEnvelope $envelope, $query)
    {
        return new MessageDelivery(
            self::createMessage($envelope),
            $envelope->getDeliveryTag(),
            $envelope->getExchangeName(),
            $query,
            $envelope->getRoutingKey(),
            $envelope->isRedelivery()
        );
    }

    /**
     * Get publish attributes from message
     *
     * @param AmqpMessage $message
     *
     * @return array
     */
    public static function getPublishAttributes(AmqpMessage $message)
    {
        $attributes = [];
        foreach (self::$ATTRIBUTE_MAP as $property => $attribute) {
            $value = $message->{'get'.ucfirst($property)}();
            if ($value !== null) {
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }
}
