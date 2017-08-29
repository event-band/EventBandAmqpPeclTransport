<?php
/**
 * This file is a part of the Event-Band project
 * @author Kirill chEbba Chebunin
 * @author Vasil coylOne Kulakov <kulakov@vasiliy.pro>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 */

namespace EventBand\Transport\PeclAmqp;

use EventBand\Transport\Amqp\Definition\ExchangeDefinition;
use EventBand\Transport\Amqp\Definition\ExchangeType;
use EventBand\Transport\Amqp\Definition\QueueDefinition;
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
    public function consume($queue, callable $callback, $idleTimeout, $timeout = null)
    {
        $oldTimeout = $this->connection->getReadTimeout();
        $this->connection->setReadTimeout($timeout ? min($timeout, $idleTimeout) : $idleTimeout);

        try {
            $this->getQueue($queue)->consume(function (\AMQPEnvelope $envelope) use ($callback, $queue) {
                return (bool) call_user_func($callback, PeclMessageUtils::createDelivery($envelope, $queue));
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

    /**
     * {@inheritDoc}
     */
    public function declareExchange(ExchangeDefinition $exchange)
    {
        static $typeMap = [
            ExchangeType::DIRECT => AMQP_EX_TYPE_DIRECT,
            ExchangeType::TOPIC => AMQP_EX_TYPE_TOPIC,
            ExchangeType::FANOUT => AMQP_EX_TYPE_FANOUT,
            ExchangeType::HEADERS => AMQP_EX_TYPE_HEADERS
        ];

        $exchangeObject = $this->getExchange($exchange->getName());
        $exchangeObject->setType($typeMap[$exchange->getType()]);

        $flags = (AMQP_DURABLE * $exchange->isDurable())
                |(AMQP_AUTODELETE * $exchange->isAutoDeleted())
                |(AMQP_INTERNAL * $exchange->isInternal());
        $exchangeObject->setFlags($flags);

        $exchangeObject->declareExchange();
    }

    /**
     * {@inheritDoc}
     */
    public function bindExchange($target, $source, $routingKey = '')
    {
        $this->getExchange($target)->bind($source, $routingKey);
    }

    /**
     * {@inheritDoc}
     */
    public function declareQueue(QueueDefinition $queue)
    {
        $queueObject = $this->getQueue($queue->getName());
        $flags = (AMQP_DURABLE * $queue->isDurable())
            |(AMQP_AUTODELETE * $queue->isAutoDeleted())
            |(AMQP_EXCLUSIVE * $queue->isExclusive());
        $queueObject->setFlags($flags);

        $queueObject->declareQueue();
    }

    /**
     * {@inheritDoc}
     */
    public function bindQueue($queue, $exchange, $routingKey = '')
    {
        $this->getQueue($queue)->bind($exchange, $routingKey);
    }

    public function deleteExchange(ExchangeDefinition $exchange, $ifUnused = false, $nowait = false)
    {
        $this->deleteExchange($exchange->getName(), $ifUnused, $nowait);
    }

    public function deleteQueue(QueueDefinition $queue, $ifUnused = false, $ifEmpty = false, $nowait = false)
    {
        $this->deleteQueue($queue->getName(), $ifUnused, $ifEmpty, $nowait);
    }


}
