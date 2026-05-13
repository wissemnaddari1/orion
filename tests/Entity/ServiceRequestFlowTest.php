<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ServiceRequest;
use PHPUnit\Framework\TestCase;

final class ServiceRequestFlowTest extends TestCase
{
    public function testServiceRequestMovesFromOpenToInProgress(): void
    {
        $serviceRequest = new ServiceRequest();
        self::assertSame('OPEN', $serviceRequest->getStatus());

        $serviceRequest->setStatus('IN_PROGRESS');
        self::assertSame('IN_PROGRESS', $serviceRequest->getStatus());
    }
}

