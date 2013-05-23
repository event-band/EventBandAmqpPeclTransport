<?php
/*
 * Copyright (c)
 * Kirill chEbba Chebunin <iam@chebba.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 */

namespace EventBand\Transport\PeclAmqp;

use EventBand\Transport\Amqp\Driver\AmqpDriver;
use EventBand\Transport\Amqp\Driver\DriverException;
use EventBand\Transport\Amqp\Driver\MessageDelivery;
use EventBand\Transport\Amqp\Driver\MessagePublication;

/**
 * AMQP Driver for {@link http://pecl.php.net/package/amqp pecl amqp extension}
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 * @license http://opensource.org/licenses/mit-license.php MIT
 */
class PeclAmqpDriver implements AmqpDriver
{
    private $connection;
    private $channel;
    private $exchanges = [];
    private $queues = [];

    public function __construct(AmqpConnectionFactory $connectionFactory)
    {
        $this->connection = $connectionFactory->getConnection();
    }

    protected function getChannel()
    {
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        if (!$this->channel) {
            $this->channel = new \AMQPChannel($this->connection);
        }

        return $this->channel;
    }

    protected function closeChannel()
    {
        if ($this->channel) {
            $this->channel = null;
            $this->queues = [];
            $this->exchanges = [];
        }
    }

    /**
     * @param string $name
     *
     * @return \AMQPExchange
     */
    protected function getExchange($name)
    {
        if (!isset($this->exchanges[$name])) {
            $exchange = new \AMQPExchange($this->getChannel());
            $exchange->setName($name);
            $this->exchanges[$name] = $exchange;
        }

        return $this->exchanges[$name];
    }

    /**
     * @param string $name
     *
     * @return \AMQPQueue
     */
    protected function getQueue($name)
    {
        if (!isset($this->queues[$name])) {
            $queue = new \AMQPQueue($this->getChannel());
            $queue->setName($name);
            $this->queues[$name] = $queue;
        }

        return $this->queues[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function publish(MessagePublication $publication, $exchange, $routingKey = '')
    {
        $flags = AMQP_NOPARAM;
        if ($publication->isImmediate()) {
            $flags |= AMQP_IMMEDIATE;
        }
        if ($publication->isMandatory()) {
            $flags |= AMQP_MANDATORY;
        }

        $this->getExchange($exchange)->publish(
            $publication->getMessage()->getBody(),
            $routingKey,
            $flags,
            PeclMessageUtils::getPublishAttributes($publication->getMessage())
        );
    }

    /**
     * {@inheritDoc}
     */
    public function consume($queue, callable $callback, $timeout)
    {
        $oldTimeout = $this->connection->getReadTimeout();
        $this->connection->setReadTimeout($timeout);

        try {
            $this->getQueue($queue)->consume(function (\AMQPEnvelope $envelope) use ($callback, $queue) {
                return call_user_func($callback, PeclMessageUtils::createDelivery($envelope, $queue));
            });
            $this->connection->setReadTimeout($oldTimeout);
            $this->closeChannel();
        } catch (\AMQPConnectionException $e) {
            $this->connection->setReadTimeout($oldTimeout);

            $expectedErrors = array('interrupted system call', 'resource temporarily unavailable');
            foreach ($expectedErrors as $expectedError) {
                if (stripos($e->getMessage(), $expectedError) !== false) {
                    return;
                }
            }

            throw new DriverException('Unexpected error while consuming', $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function ack(MessageDelivery $delivery)
    {
        $this->getQueue($delivery->getQueue())->ack($delivery->getTag());
    }

    /**
     * {@inheritDoc}
     */
    public function reject(MessageDelivery $delivery)
    {
        $this->getQueue($delivery->getQueue())->nack($delivery->getTag(), AMQP_REQUEUE);
    }
}
