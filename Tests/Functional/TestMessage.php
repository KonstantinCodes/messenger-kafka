<?php

namespace Koco\Kafka\Tests\Functional;

class TestMessage
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}
