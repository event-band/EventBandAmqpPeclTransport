<?php
/**
 * @LICENSE_TEXT
 */

namespace EventBand\Transport\PeclAmqp\Tests;

use EventBand\Transport\Amqp\Driver\CustomAmqpMessage;
use EventBand\Transport\PeclAmqp\PeclMessageUtils;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Class PeclMessageUtilsTest
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
class PeclMessageUtilsTest extends TestCase
{
    /**
     * @test createMessage creates new message with properties from envelope
     */
    public function copyPropertiesFromEnvelope()
    {
        $properties = [
            'body' => 'body message',
            'headers' => [
                'x-header-1' => 'value1',
                'x-header-2' => 'value2'
            ],
            'contentType' => 'application/json',
            'contentEncoding' => 'utf-8',
            'messageId' => '100',
            'appId' => '2',
            'userId' => '10',
            'priority' => 3,
            'timestamp' => strtotime('2012-12-18 09:45:11'),
            'expiration' => 1000,
            'type' => 'type_str',
            'replyTo' => 'foo.bar'
        ];
        $envelope = $this->createEnvelope($properties);

        $message = PeclMessageUtils::createMessage($envelope);

        $this->assertEquals($properties, CustomAmqpMessage::getMessageProperties($message));
    }

    /**
     * @test delivery is created from envelope properties
     */
    public function deliveryFromEnvelope()
    {
        $properties = [
            'deliveryTag' => 'tag-value',
            'exchangeName' => 'exchange.name',
            'routingKey' => 'routing.key'
        ];

        $envelope = $this->createEnvelope($properties);
        $envelope
            ->expects($this->any())
            ->method('isRedelivery')
            ->will($this->returnValue(true))
        ;

        $delivery = PeclMessageUtils::createDelivery($envelope, 'query');

        $this->assertEquals($properties['deliveryTag'], $delivery->getTag());
        $this->assertEquals($properties['exchangeName'], $delivery->getExchange());
        $this->assertEquals($properties['routingKey'], $delivery->getRoutingKey());
        $this->assertTrue($delivery->isRedelivered());
        $this->assertEquals('query', $delivery->getQueue());
    }

    /**
     * @test getPublishAttributes get properties from message
     */
    public function publishAttributesFromMessage()
    {
        $message = $this->getMock('EventBand\Transport\Amqp\Driver\AmqpMessage');
        $properties = [
            'headers' => [
                'x-header-1' => 'value1',
                'x-header-2' => 'value2'
            ],
            'contentType' => 'application/json',
            'contentEncoding' => 'utf-8',
            'messageId' => '100',
            'appId' => '2',
            'userId' => '10',
            'priority' => 3,
            'timestamp' => strtotime('2012-12-18 09:45:11'),
            'expiration' => 1000,
            'type' => 'type_str',
            'replyTo' => 'foo.bar'
        ];
        foreach ($properties as $property => $value) {
            $message
                ->expects($this->any())
                ->method('get'.ucfirst($property))
                ->will($this->returnValue($value))
            ;
        }

        $attributes = PeclMessageUtils::getPublishAttributes($message);

        $this->assertEquals($properties['headers'], $attributes['headers']);
        $this->assertEquals($properties['contentType'], $attributes['content_type']);
        $this->assertEquals($properties['contentEncoding'], $attributes['content_encoding']);
        $this->assertEquals($properties['messageId'], $attributes['message_id']);
        $this->assertEquals($properties['appId'], $attributes['app_id']);
        $this->assertEquals($properties['userId'], $attributes['user_id']);
        $this->assertEquals($properties['priority'], $attributes['priority']);
        $this->assertEquals($properties['timestamp'], $attributes['timestamp']);
        $this->assertEquals($properties['expiration'], $attributes['expiration']);
        $this->assertEquals($properties['type'], $attributes['type']);
        $this->assertEquals($properties['replyTo'], $attributes['reply_to']);
    }

    /**
     * @param array $properties
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\AMQPEnvelope
     */
    private function createEnvelope(array $properties = [])
    {
        $envelope = $this->getMock('AMQPEnvelope');
        foreach ($properties as $property => $value) {
            $envelope
                ->expects($this->any())
                ->method('get'.ucfirst($property))
                ->will($this->returnValue($value))
            ;
        }

        return $envelope;
    }
}
