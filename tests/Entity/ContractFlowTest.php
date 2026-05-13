<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contract;
use PHPUnit\Framework\TestCase;

final class ContractFlowTest extends TestCase
{
    public function testContractMovesToActiveWhenFullySignedThenInProgressOnFunding(): void
    {
        $contract = new Contract();
        $contract->setStatus(Contract::STATUS_PENDING_SIGN);

        $contract->signByClient('client-sign', '127.0.0.1');
        self::assertSame(Contract::STATUS_PENDING_SIGN, $contract->getStatus());

        $contract->signByWorker('worker-sign', '127.0.0.2');
        self::assertTrue($contract->isFullySigned());
        self::assertSame(Contract::STATUS_ACTIVE, $contract->getStatus());

        $contract->markUpfrontPaid();
        self::assertTrue($contract->isUpfrontPaid());
        self::assertSame(Contract::STATUS_IN_PROGRESS, $contract->getStatus());
    }
}

