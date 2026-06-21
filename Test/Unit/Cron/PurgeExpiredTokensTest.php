<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Cron;

use MageMe\EUWithdrawalMagicLink\Cron\PurgeExpiredTokens;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink as MagicLinkResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class PurgeExpiredTokensTest extends TestCase
{
    public function testExecuteDeletesTerminalRowsFromMainTable(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quote')->willReturnCallback(static fn($v): string => "'" . $v . "'");

        $capturedTable = null;
        $capturedWhere = null;
        $connection->expects(self::once())
            ->method('delete')
            ->willReturnCallback(function ($table, $where) use (&$capturedTable, &$capturedWhere): int {
                $capturedTable = $table;
                $capturedWhere = $where;
                return 0;
            });

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('mm_eu_withdrawal_magic_link');

        (new PurgeExpiredTokens($resource))->execute();

        self::assertSame('mm_eu_withdrawal_magic_link', $capturedTable);
        self::assertStringContainsString('expires_at <', $capturedWhere);
        self::assertStringContainsString('revoked_at <', $capturedWhere);
        self::assertStringContainsString('used_at <', $capturedWhere);
    }
}
