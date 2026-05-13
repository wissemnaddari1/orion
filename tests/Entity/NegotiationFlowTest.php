<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Negotiation;
use PHPUnit\Framework\TestCase;

final class NegotiationFlowTest extends TestCase
{
    public function testNegotiationOpenToAcceptedTransition(): void
    {
        $negotiation = new Negotiation();
        $negotiation->setStatus('OPEN');
        self::assertSame('OPEN', $negotiation->getStatus());

        $negotiation->setStatus('ACCEPTED');
        self::assertSame('ACCEPTED', $negotiation->getStatus());
    }
}

