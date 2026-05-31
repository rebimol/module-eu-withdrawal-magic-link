<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model\Token;

use MageMe\EUWithdrawal\Api\Token\MagicLinkServiceInterface;
use MageMe\EUWithdrawalMagicLink\Api\Data\MagicLinkInterface;
use MageMe\EUWithdrawalMagicLink\Model\Config\MagicLinkConfig;
use MageMe\EUWithdrawalMagicLink\Model\MagicLinkFactory;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink as MagicLinkResource;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink\CollectionFactory;
use Magento\Framework\Event\ManagerInterface;

/**
 * Magic-link issuance + resolution. Tokens are stored only as SHA-256
 * hashes (never plaintext) — once a customer leaves their email inbox,
 * the only proof of a token is the hash on `mm_eu_withdrawal_magic_link`.
 *
 * All persisted timestamps are UTC (`gmdate()`); same-row time-window
 * comparisons therefore work even if the DB server's wall-clock differs
 * from the app server's. Bad timestamps coming back from the DB (manual
 * edits, partial-restore scenarios) are coerced to 0 via the strict
 * `parseUtc()` helper so the comparison fails closed — a corrupt row can
 * never widen a window, only narrow it.
 */
class MagicLinkService implements MagicLinkServiceInterface
{
    public const REVOKE_GRACE_SECONDS = 300;

    /**
     * Constructor.
     *
     * @param MagicLinkFactory $modelFactory
     * @param MagicLinkResource $resource
     * @param CollectionFactory $collectionFactory
     * @param ManagerInterface $eventManager
     * @param MagicLinkConfig $config
     */
    public function __construct(
        private readonly MagicLinkFactory $modelFactory,
        private readonly MagicLinkResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly ManagerInterface $eventManager,
        private readonly MagicLinkConfig $config,
    ) {
    }

    /**
     * Issue for order.
     *
     * @param int $orderEntityId
     * @return string
     */
    public function issueForOrder(int $orderEntityId): string
    {
        $plain = $this->generatePlainToken();
        $hash = hash('sha256', $plain);

        $now = $this->utcNow();
        $ttlSeconds = $this->config->getLifetimeDays() * 86400;
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        /** @var \MageMe\EUWithdrawalMagicLink\Model\MagicLink $row */
        $row = $this->modelFactory->create();
        $row->setOrderId($orderEntityId);
        $row->setTokenHash($hash);
        $row->setIssuedAt($now);
        $row->setExpiresAt($expiresAt);
        $this->resource->save($row);

        $this->eventManager->dispatch('mageme_eu_withdrawal_audit_token_issued', [
            'order_id'    => $orderEntityId,
            'token'       => $plain,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return $plain;
    }

    /*
     * Revoke-and-reissue semantics: plain tokens exist only as SHA-256 hashes
     * in token_hash, so the original plaintext cannot be resurfaced on a
     * re-send. Revoking stale usable rows before issuing a fresh one preserves
     * DB hygiene across unbounded email re-sends.
     *
     * A REVOKE_GRACE_SECONDS window (5 minutes) on issued_at prevents a
     * close-spaced send from killing the prior email's token mid-flight — e.g.
     * order-confirmation + shipment-notification fired seconds apart; the
     * customer opens the confirmation email hours later and the link still
     * works. Distant re-sends (hours/days) still get their old rows revoked.
     *
     * Do NOT "optimise" this to reuse an existing row — that would require
     * storing plaintext, which violates the hash-only storage model.
     */
    public function issueOrReuseForOrder(int $orderEntityId): string
    {
        $this->revokeStaleUsableRowsForOrder($orderEntityId);
        return $this->issueForOrder($orderEntityId);
    }

    /**
     * Revoke stale usable rows for order.
     *
     * @param int $orderEntityId
     * @return void
     */
    private function revokeStaleUsableRowsForOrder(int $orderEntityId): void
    {
        $now = time();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(MagicLinkInterface::ORDER_ID, $orderEntityId)
            ->addFieldToFilter(MagicLinkInterface::REVOKED_AT, ['null' => true])
            ->addFieldToFilter(MagicLinkInterface::USED_AT, ['null' => true])
            ->addFieldToFilter(MagicLinkInterface::EXPIRES_AT, ['gt' => gmdate('Y-m-d H:i:s', $now)])
            ->addFieldToFilter(MagicLinkInterface::ISSUED_AT, ['lt' => gmdate('Y-m-d H:i:s', $now - self::REVOKE_GRACE_SECONDS)]);

        $revokedAt = gmdate('Y-m-d H:i:s', $now);
        foreach ($collection as $row) {
            /** @var \MageMe\EUWithdrawalMagicLink\Model\MagicLink $row */
            $row->setRevokedAt($revokedAt);
            $this->resource->save($row);
        }
    }

    /**
     * Resolve order.
     *
     * @param string $plainToken
     * @return ?int
     */
    public function resolveOrder(string $plainToken): ?int
    {
        $row = $this->findByPlainToken($plainToken);
        if ($row === null) {
            return null;
        }
        if ($row->getRevokedAt() !== null || $row->getUsedAt() !== null) {
            return null;
        }

        $now = time();
        $expires = $this->parseUtc((string) $row->getExpiresAt());
        if ($now > $expires) {
            return null;
        }

        $nowStr = gmdate('Y-m-d H:i:s', $now);
        if ($row->getFirstAccessedAt() === null) {
            $row->setFirstAccessedAt($nowStr);
        }
        $row->setLastAccessedAt($nowStr);
        $this->resource->save($row);

        $this->eventManager->dispatch('mageme_eu_withdrawal_audit_token_used', [
            'order_id' => (int) $row->getOrderId(),
            'token'    => $plainToken,
            'used_at'  => gmdate('c'),
        ]);

        return $row->getOrderId();
    }

    /**
     * Revoke.
     *
     * @param int $orderEntityId
     * @return void
     */
    public function revoke(int $orderEntityId): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(MagicLinkInterface::ORDER_ID, $orderEntityId);
        $collection->addFieldToFilter(MagicLinkInterface::REVOKED_AT, ['null' => true]);
        $now = $this->utcNow();
        foreach ($collection as $row) {
            /** @var \MageMe\EUWithdrawalMagicLink\Model\MagicLink $row */
            $row->setRevokedAt($now);
            $this->resource->save($row);
        }
    }

    /**
     * Find by plain token.
     *
     * @param string $plainToken
     * @return ?\MageMe\EUWithdrawalMagicLink\Model\MagicLink
     */
    private function findByPlainToken(string $plainToken): ?\MageMe\EUWithdrawalMagicLink\Model\MagicLink
    {
        if ($plainToken === '') {
            return null;
        }
        $hash = hash('sha256', $plainToken);
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(MagicLinkInterface::TOKEN_HASH, $hash);
        /** @var \MageMe\EUWithdrawalMagicLink\Model\MagicLink $row */
        $row = $collection->getFirstItem();
        return $row->getTokenId() === null ? null : $row;
    }

    /**
     * Generate plain token.
     *
     * @return string
     */
    private function generatePlainToken(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Utc now.
     *
     * @return string
     */
    private function utcNow(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Strict UTC parser. Returns 0 for malformed input (manual DB edits,
     * partial restore) so window comparisons fail closed — a corrupt row
     * can never widen a window, only narrow it.
     */
    private function parseUtc(string $mysqlDt): int
    {
        if ($mysqlDt === '') {
            return 0;
        }
        try {
            return (new \DateTimeImmutable($mysqlDt, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
