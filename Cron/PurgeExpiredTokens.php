<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Cron;

use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink as MagicLinkResource;

/**
 * Daily purge of magic-link rows that can no longer authenticate anyone:
 * expired, revoked, or (reserved) used, kept for a short grace window past
 * that point. Expiry is already enforced at read time, so deletion is pure
 * housekeeping / data-minimisation — the table would otherwise grow one row
 * per email send forever.
 */
class PurgeExpiredTokens
{
    public const RETENTION_GRACE_DAYS = 30;

    /**
     * Constructor.
     *
     * @param MagicLinkResource $resource
     */
    public function __construct(
        private readonly MagicLinkResource $resource,
    ) {
    }

    /**
     * Execute.
     *
     * @return void
     */
    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $cutoff = $connection->quote(gmdate('Y-m-d H:i:s', time() - self::RETENTION_GRACE_DAYS * 86400));
        $connection->delete(
            $this->resource->getMainTable(),
            sprintf('expires_at < %1$s OR revoked_at < %1$s OR used_at < %1$s', $cutoff),
        );
    }
}
