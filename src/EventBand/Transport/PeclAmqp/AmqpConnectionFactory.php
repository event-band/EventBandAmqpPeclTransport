<?php
/**
 * @LICENSE_TEXT
 */

namespace EventBand\Transport\PeclAmqp;

/**
 * Class AmqpConnectionFactory
 *
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
interface AmqpConnectionFactory
{
    /**
     * @return \AMQPConnection
     */
    public function getConnection();
}