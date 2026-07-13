<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Model\Token;

use MageMe\EUWithdrawalMagicLink\Model\Config\MagicLinkConfig;
use MageMe\EUWithdrawalMagicLink\Model\MagicLink;
use MageMe\EUWithdrawalMagicLink\Model\MagicLinkFactory;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink as MagicLinkResource;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink\Collection;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink\CollectionFactory;
use MageMe\EUWithdrawalMagicLink\Model\Token\MagicLinkService;
use Magento\Framework\Event\ManagerInterface;
use PHPUnit\Framework\TestCase;

class MagicLinkServiceTest extends TestCase
{
    public function testIssueForOrderReturnsPlainTokenAndPersistsHash(): void
    {
        $model = $this->createMock(MagicLink::class);
        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($model);

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($model);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn(new \Magento\Framework\DataObject());
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $model->expects(self::once())->method('setOrderId')->with(555);
        $model->expects(self::once())->method('setTokenHash')->with(self::isType('string'));
        $model->expects(self::once())->method('setIssuedAt');
        $model->expects(self::once())->method('setExpiresAt');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        $plain = $service->issueForOrder(555);

        self::assertNotEmpty($plain);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $plain, 'URL-safe base64 only');
        self::assertGreaterThanOrEqual(40, strlen($plain));
    }

    public function testResolveOrderReturnsNullForUnknownToken(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $emptyRow = $this->createMock(MagicLink::class);
        $emptyRow->method('getTokenId')->willReturn(null);
        $collection->method('getFirstItem')->willReturn($emptyRow);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNull($service->resolveOrder('bogus-token'));
    }

    public function testResolveOrderReturnsOrderIdAndUpdatesAccessTimestamps(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify('+60 minutes')->format('Y-m-d H:i:s');

        $row = $this->createMock(MagicLink::class);
        $row->method('getTokenId')->willReturn(42);
        $row->method('getOrderId')->willReturn(999);
        $row->method('getRevokedAt')->willReturn(null);
        $row->method('getUsedAt')->willReturn(null);
        $row->method('getFirstAccessedAt')->willReturn(null);
        $row->method('getExpiresAt')->willReturn($expiresAt);
        $row->expects(self::once())->method('setFirstAccessedAt')->with(self::isType('string'));
        $row->expects(self::once())->method('setLastAccessedAt')->with(self::isType('string'));

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($row);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($row);

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertSame(999, $service->resolveOrder('plain-token'));
    }

    public function testResolveOrderReturnsNullWhenRevoked(): void
    {
        $row = $this->createMock(MagicLink::class);
        $row->method('getTokenId')->willReturn(42);
        $row->method('getRevokedAt')->willReturn('2026-04-20 10:00:00');
        $row->expects(self::never())->method('setLastAccessedAt');

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($row);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::never())->method('save');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNull($service->resolveOrder('plain-token'));
    }

    public function testResolveOrderReturnsNullWhenAbsoluteTtlExceeded(): void
    {
        $tzUtc = new \DateTimeZone('UTC');
        $expiresAt = (new \DateTimeImmutable('now', $tzUtc))
            ->modify('-1 hour')
            ->format('Y-m-d H:i:s');

        $row = $this->createMock(MagicLink::class);
        $row->method('getTokenId')->willReturn(42);
        $row->method('getOrderId')->willReturn(999);
        $row->method('getRevokedAt')->willReturn(null);
        $row->method('getUsedAt')->willReturn(null);
        $row->method('getFirstAccessedAt')->willReturn(null);
        $row->method('getExpiresAt')->willReturn($expiresAt);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($row);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::never())->method('save');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNull($service->resolveOrder('plain-token'));
    }

    public function testIssueForOrderDispatchesTokenIssuedAuditEvent(): void
    {
        $model = $this->createMock(MagicLink::class);
        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($model);

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($model);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn(new \Magento\Framework\DataObject());
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $captured = null;
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (string $topic, array $payload) use (&$captured): void {
                $captured = ['topic' => $topic, 'payload' => $payload];
            });

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        $plain = $service->issueForOrder(555);

        self::assertNotNull($captured);
        self::assertSame('mageme_eu_withdrawal_audit_token_issued', $captured['topic']);
        self::assertSame(555, $captured['payload']['order_id']);
        self::assertSame(3 * 86400, $captured['payload']['ttl_seconds']);
        self::assertIsString($captured['payload']['token']);
        self::assertNotEmpty($captured['payload']['token']);
        self::assertSame($plain, $captured['payload']['token']);
    }

    public function testResolveOrderDispatchesTokenUsedAuditEventOnSuccess(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify('+60 minutes')->format('Y-m-d H:i:s');

        $row = $this->createMock(MagicLink::class);
        $row->method('getTokenId')->willReturn(42);
        $row->method('getOrderId')->willReturn(999);
        $row->method('getRevokedAt')->willReturn(null);
        $row->method('getUsedAt')->willReturn(null);
        $row->method('getFirstAccessedAt')->willReturn(null);
        $row->method('getExpiresAt')->willReturn($expiresAt);
        $row->expects(self::once())->method('setFirstAccessedAt')->with(self::isType('string'));
        $row->expects(self::once())->method('setLastAccessedAt')->with(self::isType('string'));

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($row);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($row);

        $captured = null;
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (string $topic, array $payload) use (&$captured): void {
                $captured = ['topic' => $topic, 'payload' => $payload];
            });

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        self::assertSame(999, $service->resolveOrder('plain-token'));

        self::assertNotNull($captured);
        self::assertSame('mageme_eu_withdrawal_audit_token_used', $captured['topic']);
        self::assertSame(999, $captured['payload']['order_id']);
        self::assertSame('plain-token', $captured['payload']['token']);
        self::assertIsString($captured['payload']['used_at']);
        self::assertNotEmpty($captured['payload']['used_at']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $captured['payload']['used_at'],
        );
    }

    public function testResolveOrderDoesNotDispatchTokenUsedOnRepeatAccess(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify('+60 minutes')->format('Y-m-d H:i:s');

        $row = $this->createMock(MagicLink::class);
        $row->method('getTokenId')->willReturn(42);
        $row->method('getOrderId')->willReturn(999);
        $row->method('getRevokedAt')->willReturn(null);
        $row->method('getUsedAt')->willReturn(null);
        $row->method('getFirstAccessedAt')->willReturn('2026-01-01 00:00:00');
        $row->method('getExpiresAt')->willReturn($expiresAt);
        $row->expects(self::never())->method('setFirstAccessedAt');
        $row->expects(self::once())->method('setLastAccessedAt')->with(self::isType('string'));

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($row);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($row);

        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::never())->method('dispatch');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        self::assertSame(999, $service->resolveOrder('plain-token'));
    }

    public function testResolveOrderDoesNotDispatchWhenTokenInvalid(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $emptyRow = $this->createMock(MagicLink::class);
        $emptyRow->method('getTokenId')->willReturn(null);
        $collection->method('getFirstItem')->willReturn($emptyRow);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $resource = $this->createMock(MagicLinkResource::class);

        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::never())->method('dispatch');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        self::assertNull($service->resolveOrder('bogus-token'));
    }

    public function testIssueOrReuseDoesNotRevokePriorTokens(): void
    {
        // Issuing (or re-issuing) a link never revokes a prior token: earlier
        // links stay valid until their own expiry. A token is invalidated only
        // by its absolute TTL or an explicit admin revoke().
        $newRow = $this->createMock(MagicLink::class);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        // No prior rows are queried and none are revoked - only the fresh issue.
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())->method('create');

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save');

        $dispatched = [];
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (string $topic) use (&$dispatched): void {
                $dispatched[] = $topic;
            });

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        self::assertNotEmpty($service->issueOrReuseForOrder(777));
        self::assertSame(['mageme_eu_withdrawal_audit_token_issued'], $dispatched);
    }

    public function testIssueForOrderReadsLifetimeForOrderStoreScope(): void
    {
        $model = $this->createMock(MagicLink::class);
        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($model);

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save')->with($model);

        $config = $this->createMock(MagicLinkConfig::class);
        $config->expects(self::once())->method('getLifetimeDays')->with(7)->willReturn(10);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn(new \Magento\Framework\DataObject());
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $captured = null;
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->method('dispatch')->willReturnCallback(
            function (string $topic, array $payload) use (&$captured): void {
                $captured = $payload;
            }
        );

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager, $config);
        $service->issueForOrder(555, 7);

        self::assertSame(10 * 86400, $captured['ttl_seconds']);
    }

    private function makeService(
        MagicLinkFactory $modelFactory,
        MagicLinkResource $resource,
        CollectionFactory $collectionFactory,
        ?ManagerInterface $eventManager = null,
        ?MagicLinkConfig $config = null,
    ): MagicLinkService {
        if ($config === null) {
            $config = $this->createMock(MagicLinkConfig::class);
            $config->method('getLifetimeDays')->willReturn(3);
        }
        return new MagicLinkService(
            $modelFactory,
            $resource,
            $collectionFactory,
            $eventManager ?? $this->createMock(ManagerInterface::class),
            $config,
        );
    }
}
