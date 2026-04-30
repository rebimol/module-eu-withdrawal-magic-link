<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class EventsXmlBindingsTest extends TestCase
{
    private const EVENTS_XML = __DIR__ . '/../../../etc/events.xml';
    private const OBSERVER_FQN = 'MageMe\\EUWithdrawalMagicLink\\Observer\\AppendMagicLinkToOrderEmails';

    private \SimpleXMLElement $xml;

    protected function setUp(): void
    {
        $xml = simplexml_load_file(self::EVENTS_XML);
        if ($xml === false) {
            $this->fail('Could not load events.xml');
        }
        $this->xml = $xml;
    }

    public function testOrderEmailBindingPresent(): void
    {
        $observers = $this->findObserversForEvent('email_order_set_template_vars_before');
        $this->assertCount(1, $observers);
        $this->assertSame(self::OBSERVER_FQN, (string) $observers[0]['instance']);
    }

    public function testShipmentEmailBindingPresent(): void
    {
        $observers = $this->findObserversForEvent('email_shipment_set_template_vars_before');
        $this->assertCount(1, $observers);
        $this->assertSame(self::OBSERVER_FQN, (string) $observers[0]['instance']);
    }

    public function testInvoiceEmailBindingAbsent(): void
    {
        // Decision #9: invoice email is a payment receipt, not a delivery notice.
        $observers = $this->findObserversForEvent('email_invoice_set_template_vars_before');
        $this->assertCount(0, $observers, 'invoice_email must NOT bind AppendMagicLinkToOrderEmails (decision #9)');
    }

    public function testAppendMagicLinkBoundExactlyTwice(): void
    {
        $count = 0;
        foreach ($this->xml->xpath('//observer') as $observer) {
            if ((string) $observer['instance'] === self::OBSERVER_FQN) {
                $count++;
            }
        }
        $this->assertSame(2, $count, 'AppendMagicLinkToOrderEmails must be bound exactly twice (order + shipment)');
    }

    /** @return array<int, \SimpleXMLElement> */
    private function findObserversForEvent(string $eventName): array
    {
        $results = $this->xml->xpath(sprintf('event[@name="%s"]/observer', $eventName));
        return $results === false ? [] : $results;
    }
}
