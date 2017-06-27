<?php
/**
 * This file is a part of the Event-Band project
 * @author Kirill chEbba Chebunin
 * @author Vasil coylOne Kulakov <kulakov@vasiliy.pro>
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.
 */

namespace EventBand\Transport\PeclAmqp\Tests\Functional;

use EventBand\Transport\Amqp\Driver\Test\DriverFunctionalTestCase;
use EventBand\Transport\PeclAmqp\PeclAmqpDriver;

/**
 * Class PeclAmqpDriverTest
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
class PeclAmqpDriverTest extends DriverFunctionalTestCase
{
    /**
     * @var \AMQPConnection
     */
    private $conn;

    /**
     * {@inheritDoc}
     */
    protected function createDriver()
    {
        $factory = $this->getMock('EventBand\Transport\PeclAmqp\AmqpConnectionFactory');
        $factory
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->conn))
        ;

        return new PeclAmqpDriver($factory);
    }

    /**
     * {@inheritDoc}
     */
    protected function setUpAmqp()
    {
        $this->conn = new \AMQPConnection([
            'host'     => $_SERVER['AMQP_HOST'],
            'port'     => $_SERVER['AMQP_PORT'],
            'login'    => $_SERVER['AMQP_USER'],
            'password' => $_SERVER['AMQP_PASS'],
            'vhost'    => $_SERVER['AMQP_VHOST']
        ]);
        $this->conn->connect();
        $channel = new \AMQPChannel($this->conn);

        $exchange = new \AMQPExchange($channel);
        $exchange->setName('event_band.test.exchange');
        $exchange->setFlags(AMQP_AUTODELETE);
        $exchange->setType(AMQP_EX_TYPE_TOPIC);
        $exchange->declareExchange();

        $queue = new \AMQPQueue($channel);
        $queue->setName('event_band.test.event');
        $queue->declareQueue();
        $queue->bind('event_band.test.exchange', 'event.#');
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDownAmqp()
    {
        $this->conn->disconnect();
    }
}
