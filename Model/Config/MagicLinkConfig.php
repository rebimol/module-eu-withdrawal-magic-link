<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reader for the Magic Link admin group
 * (`Stores → Configuration → MageMe Extensions → EU Withdrawal → Magic Link (Pro)`).
 *
 * Keeps `MagicLinkService` free of `ScopeConfigInterface` plumbing and
 * provides a single fallback default so a missing/zero/negative stored
 * value still yields a usable lifetime instead of a token that expires
 * the instant it is issued.
 */
class MagicLinkConfig
{
    public const XML_LIFETIME_DAYS = 'mageme_eu_withdrawal/magic_link/lifetime_days';
    public const DEFAULT_LIFETIME_DAYS = 30;

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Absolute token lifetime in days for newly issued tokens.
     * Falls back to {@see DEFAULT_LIFETIME_DAYS} when the stored value is
     * empty, zero, or negative — this can only happen if an admin saved an
     * invalid value through a bypass of the `validate-digits` JS check.
     *
     * @param ?int $storeId
     * @return int
     */
    public function getLifetimeDays(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_LIFETIME_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId,
        );
        return $value > 0 ? $value : self::DEFAULT_LIFETIME_DAYS;
    }
}
