<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model;

use MageMe\EUWithdrawalMagicLink\Api\Data\MagicLinkInterface;
use Magento\Framework\Model\AbstractModel;

class MagicLink extends AbstractModel implements MagicLinkInterface
{
    /**
     * Construct.
     */
    protected function _construct()
    {
        $this->_init(\MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink::class);
    }

    /**
     * Get token id.
     *
     * @return ?int
     */
    public function getTokenId(): ?int
    {
        $v = $this->getData(self::TOKEN_ID);
        return $v === null ? null : (int) $v;
    }

    /**
     * Get order id.
     *
     * @return int
     */
    public function getOrderId(): int
    {
        return (int) $this->getData(self::ORDER_ID);
    }
    /**
     * Get token hash.
     *
     * @return string
     */
    public function getTokenHash(): string
    {
        return (string) $this->getData(self::TOKEN_HASH);
    }
    /**
     * Get issued at.
     *
     * @return string
     */
    public function getIssuedAt(): string
    {
        return (string) $this->getData(self::ISSUED_AT);
    }
    /**
     * Get expires at.
     *
     * @return string
     */
    public function getExpiresAt(): string
    {
        return (string) $this->getData(self::EXPIRES_AT);
    }
    /**
     * Get first accessed at.
     *
     * @return ?string
     */
    public function getFirstAccessedAt(): ?string
    {
        return $this->getData(self::FIRST_ACCESSED_AT);
    }
    /**
     * Get last accessed at.
     *
     * @return ?string
     */
    public function getLastAccessedAt(): ?string
    {
        return $this->getData(self::LAST_ACCESSED_AT);
    }
    /**
     * Get used at.
     *
     * @return ?string
     */
    public function getUsedAt(): ?string
    {
        return $this->getData(self::USED_AT);
    }
    /**
     * Get revoked at.
     *
     * @return ?string
     */
    public function getRevokedAt(): ?string
    {
        return $this->getData(self::REVOKED_AT);
    }

    /**
     * Set order id.
     *
     * @param int $orderId
     * @return void
     */
    public function setOrderId(int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }
    /**
     * Set token hash.
     *
     * @param string $hash
     * @return void
     */
    public function setTokenHash(string $hash): void
    {
        $this->setData(self::TOKEN_HASH, $hash);
    }
    /**
     * Set issued at.
     *
     * @param string $ts
     * @return void
     */
    public function setIssuedAt(string $ts): void
    {
        $this->setData(self::ISSUED_AT, $ts);
    }
    /**
     * Set expires at.
     *
     * @param string $ts
     * @return void
     */
    public function setExpiresAt(string $ts): void
    {
        $this->setData(self::EXPIRES_AT, $ts);
    }
    /**
     * Set first accessed at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setFirstAccessedAt(?string $ts): void
    {
        $this->setData(self::FIRST_ACCESSED_AT, $ts);
    }
    /**
     * Set last accessed at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setLastAccessedAt(?string $ts): void
    {
        $this->setData(self::LAST_ACCESSED_AT, $ts);
    }
    /**
     * Set used at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setUsedAt(?string $ts): void
    {
        $this->setData(self::USED_AT, $ts);
    }
    /**
     * Set revoked at.
     *
     * @param ?string $ts
     * @return void
     */
    public function setRevokedAt(?string $ts): void
    {
        $this->setData(self::REVOKED_AT, $ts);
    }
}
