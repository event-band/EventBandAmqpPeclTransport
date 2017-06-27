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

interface AmqpConnectionFactory
{
    /**
     * @return \AMQPConnection
     */
    public function getConnection();
}