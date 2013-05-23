<?php
/**
 * @LICENSE_TEXT
 */

namespace EventBand\Transport\PeclAmqp;

use EventBand\Transport\Amqp\Definition\ConnectionDefinition;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;

/**
 * Class AmqpConnectionBuilder
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
class AmqpConnectionBuilder implements AmqpConnectionFactory
{
    private $connection;
    private $definition;

    public function setDefinition(ConnectionDefinition $definition)
    {
        $this->definition = $definition;

        return $this;
    }

    public function getDefinition()
    {
        return $this->definition;
    }

    public function setConnection(\AMQPConnection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection()
    {
        if (!$this->connection) {
            if (!$this->definition) {
                throw new \BadMethodCallException('Neither connection nor definition was set');
            }

            $this->connection = static::createDefinedConnection($this->definition);
        }

        return $this->connection;
    }

    public static function createDefinedConnection(ConnectionDefinition $definition)
    {
        return new \AMQPConnection([
            'host'     => $definition->getHost(),
            'port'     => $definition->getPort(),
            'login'    => $definition->getUser(),
            'password' => $definition->getPassword(),
            'vhost'    => $definition->getVirtualHost()
        ]);
    }
}