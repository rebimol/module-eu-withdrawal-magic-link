<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Test\Unit\Model\Token;

use MageMe\EUWithdrawalMagicLink\Api\Data\MagicLinkInterface;
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

    public function testIssueOrReuseRevokesExistingUsableRowAndIssuesFresh(): void
    {
        $staleRow = $this->createMock(MagicLink::class);
        $staleRow->expects(self::once())->method('setRevokedAt')->with(self::isType('string'));

        $newRow = $this->createMock(MagicLink::class);

        $collection = $this->makeIterableCollectionMock([$staleRow]);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);
        // Two saves: the stale row being revoked + the new row being persisted.
        $resource->expects(self::exactly(2))->method('save');

        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::once())
            ->method('dispatch')
            ->with('mageme_eu_withdrawal_audit_token_issued', self::isType('array'));

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        $plain = $service->issueOrReuseForOrder(777);

        self::assertNotEmpty($plain);
    }

    public function testIssueOrReuseRevokesMultipleStaleRows(): void
    {
        $stale = [];
        for ($i = 0; $i < 3; $i++) {
            $row = $this->createMock(MagicLink::class);
            $row->expects(self::once())->method('setRevokedAt')->with(self::isType('string'));
            $stale[] = $row;
        }

        $newRow = $this->createMock(MagicLink::class);

        $collection = $this->makeIterableCollectionMock($stale);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);
        // 3 revokes + 1 fresh issue.
        $resource->expects(self::exactly(4))->method('save');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNotEmpty($service->issueOrReuseForOrder(777));
    }

    public function testIssueOrReuseIgnoresRevokedRows(): void
    {
        // Only one usable row is returned — the DB filter excludes the revoked
        // one via addFieldToFilter('revoked_at', ['null' => true]). Assert that
        // exact filter clause is applied.
        $usableRow = $this->createMock(MagicLink::class);
        $usableRow->expects(self::once())->method('setRevokedAt')->with(self::isType('string'));

        $newRow = $this->createMock(MagicLink::class);

        $filterCalls = [];
        $collection = $this->makeIterableCollectionMock([$usableRow], $filterCalls);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        $service->issueOrReuseForOrder(777);

        // Verify the revoke filter chain: revoked_at IS NULL must be present.
        $revokedNullFilter = array_filter(
            $filterCalls,
            static fn ($call) => $call[0] === MagicLinkInterface::REVOKED_AT
                && is_array($call[1])
                && ($call[1]['null'] ?? null) === true,
        );
        self::assertNotEmpty($revokedNullFilter, 'revoked_at IS NULL filter must be applied');
    }

    public function testIssueOrReuseIgnoresUsedRows(): void
    {
        // Used rows are excluded by the DB filter — collection returns empty.
        $newRow = $this->createMock(MagicLink::class);

        $filterCalls = [];
        $collection = $this->makeIterableCollectionMock([], $filterCalls);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);
        // No revokes — only the single fresh issue.
        $resource->expects(self::once())->method('save');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNotEmpty($service->issueOrReuseForOrder(777));

        $usedNullFilter = array_filter(
            $filterCalls,
            static fn ($call) => $call[0] === MagicLinkInterface::USED_AT
                && is_array($call[1])
                && ($call[1]['null'] ?? null) === true,
        );
        self::assertNotEmpty($usedNullFilter, 'used_at IS NULL filter must be applied');
    }

    public function testIssueOrReuseIgnoresExpiredRows(): void
    {
        // Expired rows excluded via expires_at > NOW() — collection returns empty.
        $newRow = $this->createMock(MagicLink::class);

        $filterCalls = [];
        $collection = $this->makeIterableCollectionMock([], $filterCalls);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);
        $resource->expects(self::once())->method('save');

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        self::assertNotEmpty($service->issueOrReuseForOrder(777));

        $expiresGtFilter = array_filter(
            $filterCalls,
            static fn ($call) => $call[0] === MagicLinkInterface::EXPIRES_AT
                && is_array($call[1])
                && isset($call[1]['gt']),
        );
        self::assertNotEmpty($expiresGtFilter, 'expires_at > NOW() filter must be applied');
    }

    public function testIssueOrReuseAppliesGraceWindowFilterOnCreatedAt(): void
    {
        // The revoke filter must include `issued_at < NOW - REVOKE_GRACE_SECONDS`
        // so close-spaced re-sends (e.g. order + shipment email seconds apart)
        // keep the prior row usable and the customer's first-opened email works.
        $newRow = $this->createMock(MagicLink::class);

        $filterCalls = [];
        $collection = $this->makeIterableCollectionMock([], $filterCalls);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);

        $service = $this->makeService($modelFactory, $resource, $collectionFactory);

        $before = time();
        $service->issueOrReuseForOrder(777);
        $after = time();

        $graceFilter = array_values(array_filter(
            $filterCalls,
            static fn ($call) => $call[0] === MagicLinkInterface::ISSUED_AT
                && is_array($call[1])
                && isset($call[1]['lt']),
        ));
        self::assertNotEmpty($graceFilter, 'issued_at < NOW - grace filter must be applied');
        $cutoffTs = (new \DateTimeImmutable((string) $graceFilter[0][1]['lt'], new \DateTimeZone('UTC')))->getTimestamp();
        self::assertGreaterThanOrEqual($before - \MageMe\EUWithdrawalMagicLink\Model\Token\MagicLinkService::REVOKE_GRACE_SECONDS - 1, $cutoffTs);
        self::assertLessThanOrEqual($after - \MageMe\EUWithdrawalMagicLink\Model\Token\MagicLinkService::REVOKE_GRACE_SECONDS + 1, $cutoffTs);
    }

    public function testIssueOrReuseCallsIssueForOrderExactlyOnce(): void
    {
        // Even when multiple stale rows are revoked, only ONE token_issued
        // audit event fires (from the single fresh issueForOrder call).
        $stale = [];
        for ($i = 0; $i < 3; $i++) {
            $row = $this->createMock(MagicLink::class);
            $row->method('setRevokedAt');
            $stale[] = $row;
        }

        $newRow = $this->createMock(MagicLink::class);

        $collection = $this->makeIterableCollectionMock($stale);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $modelFactory = $this->createMock(MagicLinkFactory::class);
        $modelFactory->method('create')->willReturn($newRow);

        $resource = $this->createMock(MagicLinkResource::class);

        $dispatchedTopics = [];
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (string $topic) use (&$dispatchedTopics): void {
                $dispatchedTopics[] = $topic;
            });

        $service = $this->makeService($modelFactory, $resource, $collectionFactory, $eventManager);

        $service->issueOrReuseForOrder(777);

        self::assertSame(['mageme_eu_withdrawal_audit_token_issued'], $dispatchedTopics);
    }

    /**
     * Build a Collection mock that is iterable and records addFieldToFilter args.
     *
     * @param array<int, \PHPUnit\Framework\MockObject\MockObject> $rows
     * @param array<int, array{0: string, 1: mixed}>               $filterCalls
     */
    private function makeIterableCollectionMock(array $rows, array &$filterCalls = []): Collection
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'getIterator', 'getFirstItem'])
            ->getMock();
        $collection->method('addFieldToFilter')
            ->willReturnCallback(function (string $field, $cond) use (&$filterCalls, $collection) {
                $filterCalls[] = [$field, $cond];
                return $collection;
            });
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rows));
        $collection->method('getFirstItem')->willReturn(new \Magento\Framework\DataObject());
        return $collection;
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
