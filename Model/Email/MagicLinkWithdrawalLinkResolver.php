<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model\Email;

use MageMe\EUWithdrawal\Api\Email\WithdrawalLinkResolverInterface;
use MageMe\EUWithdrawal\Api\Token\MagicLinkServiceInterface;
use MageMe\EUWithdrawal\Model\Email\LookupWithdrawalLinkResolver;
use MageMe\EUWithdrawal\Model\Frontend\RouteResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Pro-tier resolver — issues (or reuses) a magic-link token (admin-configurable
 * absolute lifetime, default 30 days) for the order and returns a one-click
 * `?t=TOKEN` URL.
 *
 * Bound via DI <preference> in this module's etc/di.xml; replaces the Free
 * default `LookupWithdrawalLinkResolver`. The observer + email-template
 * plumbing lives in the base module — this resolver only upgrades the URL behind the
 * same CTA.
 *
 * Gated by the `mageme_eu_withdrawal/magic_link/enabled` config flag (per
 * store-view). When disabled, delegates to `LookupWithdrawalLinkResolver`
 * so the CTA falls back to the lookup-form URL — same behaviour as a
 * module-only install. Useful for merchants who installed Pro but prefer the
 * lookup-form floor without uninstalling, or for staged rollouts.
 */
class MagicLinkWithdrawalLinkResolver implements WithdrawalLinkResolverInterface
{
    public const XML_PATH_ENABLED = 'mageme_eu_withdrawal/magic_link/enabled';

    /**
     * Constructor.
     *
     * @param MagicLinkServiceInterface $magicLinkService
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param LookupWithdrawalLinkResolver $fallbackResolver
     * @param RouteResolver $routeResolver
     */
    public function __construct(
        private readonly MagicLinkServiceInterface $magicLinkService,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LookupWithdrawalLinkResolver $fallbackResolver,
        private readonly RouteResolver $routeResolver,
    ) {
    }

    /**
     * Resolve for order.
     *
     * @param int $orderEntityId
     * @param ?int $storeId
     * @return string
     */
    public function resolveForOrder(int $orderEntityId, ?int $storeId = null): string
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
            return $this->fallbackResolver->resolveForOrder($orderEntityId, $storeId);
        }
        $token = $this->magicLinkService->issueOrReuseForOrder($orderEntityId);
        $base  = rtrim((string) $this->storeManager->getStore($storeId)->getBaseUrl(), '/');
        return $this->routeResolver->rewriteCanonical(
            $base . '/' . RouteResolver::CANONICAL_FRONT_NAME . '?t=' . $token,
            $storeId,
        );
    }
}
