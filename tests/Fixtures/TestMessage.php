<?php

declare(strict_types=1);

namespace Koco\Kafka\Tests\Fixtures;

class TestMessage
{
    public ?string $data;

    public function __construct(?string $data = null)
    {
        $this->data = $data;
    }
}
