<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Api\Data;

interface MagicLinkInterface
{
    public const TOKEN_ID = 'token_id';
    public const ORDER_ID = 'order_id';
    public const TOKEN_HASH = 'token_hash';
    public const ISSUED_AT = 'issued_at';
    public const EXPIRES_AT = 'expires_at';
    public const FIRST_ACCESSED_AT = 'first_accessed_at';
    public const LAST_ACCESSED_AT = 'last_accessed_at';
    public const USED_AT = 'used_at';
    public const REVOKED_AT = 'revoked_at';

    /**
     * Get token id.
     *
     * @return ?int
     */
    public function getTokenId(): ?int;
    /**
     * Get order id.
     *
     * @return int
     */
    public function getOrderId(): int;
    /**
     * Get token hash.
     *
     * @return string
     */
    public function getTokenHash(): string;
    /**
     * Get issued at.
     *
     * @return string
     */
    public function getIssuedAt(): string;
    /**
     * Get expires at.
     *
     * @return string
     */
    public function getExpiresAt(): string;
    /**
     * Get first accessed at.
     *
     * @return ?string
     */
    public function getFirstAccessedAt(): ?string;
    /**
     * Get last accessed at.
     *
     * @return ?string
     */
    public function getLastAccessedAt(): ?string;
    /**
     * Get used at.
     *
     * @return ?string
     */
    public function getUsedAt(): ?string;
    /**
     * Get revoked at.
     *
     * @return ?string
     */
    public function getRevokedAt(): ?string;

    /**
     * Set order id.
     *
     * @param int $orderId
     * @return void
     */
    public function setOrderId(int $orderId): void;
    /**
     * Set token hash.
     *
     * @param string $hash
     * @return void
     */
    public function setTokenHash(string $hash): void;
    /**
     * Set issued at.
     *
     * @param string $ts
     * @return void
     */
    public function setIssuedAt(string $ts): void;
    /**
     * Set expires at.
     *
     * @param string $ts
     * @return void
     */
    public function setExpiresAt(string $ts): void;
    /**
     * Set first accessed at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setFirstAccessedAt(?string $ts): void;
    /**
     * Set last accessed at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setLastAccessedAt(?string $ts): void;
    /**
     * Set used at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setUsedAt(?string $ts): void;
    /**
     * Set revoked at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setRevokedAt(?string $ts): void;
}
