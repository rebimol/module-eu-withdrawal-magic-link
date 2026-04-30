<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Block\Email;

use MageMe\EUWithdrawalMagicLink\Block\Email\WithdrawalLinkSnippet;
use MageMe\EUWithdrawal\Model\Frontend\FooterLinkLabelResolver;
use Magento\Framework\View\Element\Template\Context as BlockContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WithdrawalLinkSnippetTest extends TestCase
{
    private FooterLinkLabelResolver&MockObject $resolver;
    private WithdrawalLinkSnippet $block;

    protected function setUp(): void
    {
        $context = $this->createMock(BlockContext::class);
        $this->resolver = $this->createMock(FooterLinkLabelResolver::class);
        $this->block = new WithdrawalLinkSnippet($context, $this->resolver);
    }

    public function testGetButtonLabelDelegatesToResolver(): void
    {
        $this->resolver
            ->expects(self::once())
            ->method('step1Label')
            ->with(null)
            ->willReturn('Withdraw from contract here');

        self::assertSame('Withdraw from contract here', $this->block->getButtonLabel());
    }

    public function testGetButtonLabelUsesLocaleDataWhenSet(): void
    {
        $this->block->setData('locale', 'de_DE');
        $this->resolver
            ->expects(self::once())
            ->method('step1Label')
            ->with('de_DE')
            ->willReturn('Vertrag hier widerrufen');

        self::assertSame('Vertrag hier widerrufen', $this->block->getButtonLabel());
    }

    public function testGetWithdrawalLinkUrlReadsTemplateVar(): void
    {
        $url = 'https://store.example/withdraw-contract?t=abcdef';
        $this->block->setData('withdrawal_link_url', $url);

        self::assertSame($url, $this->block->getWithdrawalLinkUrl());
    }

    public function testGetWithdrawalLinkUrlReturnsNullWhenMissing(): void
    {
        self::assertNull($this->block->getWithdrawalLinkUrl());
    }

    public function testGetWithdrawalLinkUrlReturnsNullWhenEmptyString(): void
    {
        $this->block->setData('withdrawal_link_url', '');

        self::assertNull($this->block->getWithdrawalLinkUrl());
    }
}
