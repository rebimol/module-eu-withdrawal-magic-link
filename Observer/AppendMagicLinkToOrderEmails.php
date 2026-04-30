<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Observer;

use MageMe\EUWithdrawalMagicLink\Block\Email\WithdrawalLinkSnippet;
use MageMe\EUWithdrawalMagicLink\Model\Token\MagicLinkService;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AppendMagicLinkToOrderEmails implements ObserverInterface
{
    /**
     * Constructor.
     *
     * @param MagicLinkService $magicLinkService
     * @param StoreManagerInterface $storeManager
     * @param LayoutInterface $layout
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MagicLinkService $magicLinkService,
        private readonly StoreManagerInterface $storeManager,
        private readonly LayoutInterface $layout,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        // Magento_Sales is inconsistent: `OrderSender` ships `transport`
        // as a DataObject, but `ShipmentSender` / `InvoiceSender` ship it
        // as a raw array (snapshot of `$transportObject->getData()`).
        // Modifying that snapshot would not propagate back. The documented
        // stable argument is `transportObject` — always the live DataObject
        // that is later read via `templateContainer->setTemplateVars(
        // $transportObject->getData())`. Use it exclusively.
        $transport = $observer->getData('transportObject');
        if (!$transport instanceof DataObject) {
            return;
        }

        $order = $this->resolveOrderFromTransport($transport);
        if ($order === null) {
            return;
        }

        $orderEntityId = (int) $order->getEntityId();
        if ($orderEntityId <= 0) {
            return;
        }

        // Order-email observer bodies are customer-critical — a thrown exception
        // here would abort email delivery. Swallow + log so the email still ships.
        try {
            $token   = $this->magicLinkService->issueOrReuseForOrder($orderEntityId);
            $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/';
            $url     = $baseUrl . 'withdraw-contract?t=' . $token;

            // Magento's order/shipment senders pass the transport DataObject
            // through `email_*_set_template_vars_before` and then call
            // `templateContainer->setTemplateVars($transportObject->getData())`
            // — i.e. every key on the transport itself becomes a `{{var ...}}`
            // in the email template. Set our keys directly; do NOT nest them
            // inside a `template_vars` array (that would land as a single
            // unusable `{{var template_vars}}` blob).
            $transport->setData('withdrawal_link_url', $url);

            // Render the snippet block to HTML so the email template can drop
            // a single `{{var withdrawal_link_html|raw}}` instead of hand-
            // crafting table/button markup. Isolated try/catch: if HTML
            // rendering fails, the URL var still ships so the email is usable.
            try {
                $snippet = $this->layout->createBlock(
                    WithdrawalLinkSnippet::class,
                    '',
                    [
                        'data' => [
                            'template'            => 'MageMe_EUWithdrawalMagicLink::email/withdrawal_link_snippet.phtml',
                            'withdrawal_link_url' => $url,
                        ],
                    ],
                );
                $transport->setData('withdrawal_link_html', $snippet->toHtml());
            } catch (\Throwable $snippetError) {
                $this->logger->error(
                    'Failed to render withdrawal snippet HTML: ' . $snippetError->getMessage(),
                    ['exception' => $snippetError, 'order_entity_id' => $orderEntityId],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to append EU withdrawal magic link to order email: ' . $e->getMessage(),
                ['exception' => $e, 'order_entity_id' => $orderEntityId],
            );
        }
    }

    /**
     * Resolve order from transport.
     *
     * @param DataObject $transport
     * @return ?OrderInterface
     */
    private function resolveOrderFromTransport(DataObject $transport): ?OrderInterface
    {
        $order = $transport->getData('order');
        if ($order instanceof OrderInterface) {
            return $order;
        }
        $shipment = $transport->getData('shipment');
        if ($shipment instanceof ShipmentInterface) {
            $viaShipment = $shipment->getOrder();
            return $viaShipment instanceof OrderInterface ? $viaShipment : null;
        }
        $invoice = $transport->getData('invoice');
        if ($invoice instanceof InvoiceInterface) {
            $viaInvoice = $invoice->getOrder();
            return $viaInvoice instanceof OrderInterface ? $viaInvoice : null;
        }
        return null;
    }
}
