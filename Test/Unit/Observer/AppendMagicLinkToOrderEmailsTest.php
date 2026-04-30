<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Observer;

use MageMe\EUWithdrawalMagicLink\Block\Email\WithdrawalLinkSnippet;
use MageMe\EUWithdrawalMagicLink\Model\Token\MagicLinkService;
use MageMe\EUWithdrawalMagicLink\Observer\AppendMagicLinkToOrderEmails;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AppendMagicLinkToOrderEmailsTest extends TestCase
{
    /**
     * Default LayoutInterface stub: returns a snippet mock whose toHtml()
     * yields stable HTML. Tests that care about snippet rendering pass a
     * custom layout mock instead.
     */
    private function makeLayoutStub(string $html = '<a href="#">stub</a>'): LayoutInterface
    {
        $snippet = $this->createMock(WithdrawalLinkSnippet::class);
        $snippet->method('toHtml')->willReturn($html);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->willReturn($snippet);
        return $layout;
    }

    public function testAppendsWithdrawalLinkUrlVariable(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(555);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')->with(555)->willReturn('plain-tok-abc');

        $transport = new DataObject([
            'order' => $order,
            'template_vars' => [],
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertArrayHasKey('withdrawal_link_url', $vars);
        self::assertSame(
            'https://example.test/withdraw-contract?t=plain-tok-abc',
            $vars['withdrawal_link_url'],
        );
    }

    public function testNoOpWhenOrderMissing(): void
    {
        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::never())->method('issueOrReuseForOrder');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = new DataObject(['template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        self::assertArrayNotHasKey('withdrawal_link_url', $transport->getData());
    }

    public function testPreservesExistingTemplateVarsOnMerge(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(777);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')->with(777)->willReturn('tok-xyz');

        $transport = new DataObject([
            'order' => $order,
            'customer_name' => 'Alice',
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame('Alice', $vars['customer_name']);
        self::assertSame($order, $vars['order']);
        self::assertSame(
            'https://example.test/withdraw-contract?t=tok-xyz',
            $vars['withdrawal_link_url'],
        );
    }

    public function testLogsAndSwallowsWhenIssueForOrderThrows(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(888);

        $storeManager = $this->createMock(StoreManagerInterface::class);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')
            ->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $transport = new DataObject([
            'order' => $order,
            'customer_name' => 'Bob',
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer); // must not throw

        $vars = $transport->getData();
        self::assertArrayNotHasKey('withdrawal_link_url', $vars);
        self::assertArrayNotHasKey('withdrawal_link_html', $vars);
        self::assertSame('Bob', $vars['customer_name']);
    }

    public function testExecuteResolvesOrderFromShipmentTransport(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(777);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn($order);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::once())
            ->method('issueOrReuseForOrder')
            ->with(777)
            ->willReturn('ship-tok');

        $transport = new DataObject([
            'shipment' => $shipment,
            'template_vars' => [],
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame(
            'https://example.test/withdraw-contract?t=ship-tok',
            $vars['withdrawal_link_url'],
        );
    }

    public function testExecuteResolvesOrderFromInvoiceTransport(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(888);

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn($order);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::once())
            ->method('issueOrReuseForOrder')
            ->with(888)
            ->willReturn('inv-tok');

        $transport = new DataObject([
            'invoice' => $invoice,
            'template_vars' => [],
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame(
            'https://example.test/withdraw-contract?t=inv-tok',
            $vars['withdrawal_link_url'],
        );
    }

    public function testExecuteReturnsEarlyWhenNoRecognisedKeys(): void
    {
        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::never())->method('issueOrReuseForOrder');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = new DataObject([
            'something_else' => 'value',
            'customer_name' => 'Eve',
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertArrayNotHasKey('withdrawal_link_url', $vars);
        self::assertSame('Eve', $vars['customer_name']);
    }

    public function testExecuteUsesIssueOrReuseNotIssueForOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(999);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::once())
            ->method('issueOrReuseForOrder')
            ->with(999)
            ->willReturn('reuse-tok');
        $magicLinks->expects(self::never())->method('issueForOrder');

        $transport = new DataObject([
            'order' => $order,
            'template_vars' => [],
        ]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame(
            'https://example.test/withdraw-contract?t=reuse-tok',
            $vars['withdrawal_link_url'],
        );
    }

    public function testExecuteReturnsEarlyWhenShipmentGetOrderIsNull(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn(null);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::never())->method('issueOrReuseForOrder');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = new DataObject(['shipment' => $shipment, 'template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        self::assertArrayNotHasKey('withdrawal_link_url', $transport->getData());
    }

    public function testExecuteReturnsEarlyWhenInvoiceGetOrderIsNull(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getOrder')->willReturn(null);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->expects(self::never())->method('issueOrReuseForOrder');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $transport = new DataObject(['invoice' => $invoice, 'template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $this->makeLayoutStub(), $logger);
        $obs->execute($observer);

        self::assertArrayNotHasKey('withdrawal_link_url', $transport->getData());
    }

    public function testExecuteSetsWithdrawalLinkHtmlInTemplateVars(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(111);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')->with(111)->willReturn('html-tok');

        $expectedHtml = '<a href="https://example.test/withdraw-contract?t=html-tok">Withdraw</a>';
        $snippet = $this->createMock(WithdrawalLinkSnippet::class);
        $snippet->method('toHtml')->willReturn($expectedHtml);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())
            ->method('createBlock')
            ->with(
                WithdrawalLinkSnippet::class,
                '',
                self::callback(static function (array $args): bool {
                    return isset($args['data']['template'], $args['data']['withdrawal_link_url'])
                        && $args['data']['template'] === 'MageMe_EUWithdrawal::email/withdrawal_link_snippet.phtml'
                        && $args['data']['withdrawal_link_url']
                            === 'https://example.test/withdraw-contract?t=html-tok';
                }),
            )
            ->willReturn($snippet);

        $transport = new DataObject(['order' => $order, 'template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $logger = $this->createMock(LoggerInterface::class);
        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $layout, $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame($expectedHtml, $vars['withdrawal_link_html']);
        self::assertSame(
            'https://example.test/withdraw-contract?t=html-tok',
            $vars['withdrawal_link_url'],
        );
    }

    public function testExecuteSwallowsSnippetRenderingExceptionButKeepsUrl(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(222);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')->with(222)->willReturn('fail-tok');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')
            ->willThrowException(new \RuntimeException('layout boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Failed to render withdrawal snippet HTML'));

        $transport = new DataObject(['order' => $order, 'template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $layout, $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertSame(
            'https://example.test/withdraw-contract?t=fail-tok',
            $vars['withdrawal_link_url'],
        );
        self::assertArrayNotHasKey('withdrawal_link_html', $vars);
    }

    public function testExecuteDoesNotSetLinkHtmlWhenTokenAcquisitionFails(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(333);

        $storeManager = $this->createMock(StoreManagerInterface::class);

        $magicLinks = $this->createMock(MagicLinkService::class);
        $magicLinks->method('issueOrReuseForOrder')
            ->willThrowException(new \RuntimeException('token fail'));

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::never())->method('createBlock');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $transport = new DataObject(['order' => $order, 'template_vars' => []]);
        $observer = new Observer(['transportObject' => $transport]);

        $obs = new AppendMagicLinkToOrderEmails($magicLinks, $storeManager, $layout, $logger);
        $obs->execute($observer);

        $vars = $transport->getData();
        self::assertArrayNotHasKey('withdrawal_link_url', $vars);
        self::assertArrayNotHasKey('withdrawal_link_html', $vars);
    }
}
