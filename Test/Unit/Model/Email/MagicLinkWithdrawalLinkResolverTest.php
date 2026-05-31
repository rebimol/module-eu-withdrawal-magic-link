<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Model\Email;

use MageMe\EUWithdrawal\Api\Token\MagicLinkServiceInterface;
use MageMe\EUWithdrawal\Model\Email\LookupWithdrawalLinkResolver;
use MageMe\EUWithdrawal\Model\Frontend\RouteResolver;
use MageMe\EUWithdrawalMagicLink\Model\Email\MagicLinkWithdrawalLinkResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class MagicLinkWithdrawalLinkResolverTest extends TestCase
{
    public function testResolveForOrderProducesTokenisedUrlWhenEnabled(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.com/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLink = $this->createMock(MagicLinkServiceInterface::class);
        $magicLink->expects(self::once())
            ->method('issueOrReuseForOrder')
            ->with(42)
            ->willReturn('abc123');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')
            ->with(MagicLinkWithdrawalLinkResolver::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $fallback = $this->createMock(LookupWithdrawalLinkResolver::class);
        $fallback->expects(self::never())->method('resolveForOrder');

        $routeResolver = $this->createMock(RouteResolver::class);
        $routeResolver->method('rewriteCanonical')->willReturnArgument(0);

        $resolver = new MagicLinkWithdrawalLinkResolver($magicLink, $storeManager, $scopeConfig, $fallback, $routeResolver);

        self::assertSame('https://example.com/withdraw-contract?t=abc123', $resolver->resolveForOrder(42));
    }

    public function testResolveForOrderTrimsTrailingSlashOnBaseUrl(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/de/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLink = $this->createMock(MagicLinkServiceInterface::class);
        $magicLink->method('issueOrReuseForOrder')->willReturn('TOK');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $fallback = $this->createMock(LookupWithdrawalLinkResolver::class);

        $routeResolver = $this->createMock(RouteResolver::class);
        $routeResolver->method('rewriteCanonical')->willReturnArgument(0);

        $resolver = new MagicLinkWithdrawalLinkResolver($magicLink, $storeManager, $scopeConfig, $fallback, $routeResolver);

        self::assertSame('https://shop.example.com/de/withdraw-contract?t=TOK', $resolver->resolveForOrder(7));
    }

    public function testResolveForOrderDelegatesToFallbackWhenDisabled(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects(self::never())->method('getStore');

        $magicLink = $this->createMock(MagicLinkServiceInterface::class);
        $magicLink->expects(self::never())->method('issueOrReuseForOrder');

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')
            ->with(MagicLinkWithdrawalLinkResolver::XML_PATH_ENABLED)
            ->willReturn(false);

        $fallback = $this->createMock(LookupWithdrawalLinkResolver::class);
        $fallback->expects(self::once())
            ->method('resolveForOrder')
            ->with(99)
            ->willReturn('https://example.com/withdraw-contract/');

        $routeResolver = $this->createMock(RouteResolver::class);
        $routeResolver->method('rewriteCanonical')->willReturnArgument(0);

        $resolver = new MagicLinkWithdrawalLinkResolver($magicLink, $storeManager, $scopeConfig, $fallback, $routeResolver);

        self::assertSame('https://example.com/withdraw-contract/', $resolver->resolveForOrder(99));
    }
}
