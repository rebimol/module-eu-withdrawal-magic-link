<?php
/**
 * Copyright © MageMe. All rights reserved.
 * See LICENSE for license terms, or https://mageme.com/license.
 */
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink;

use MageMe\EUWithdrawalMagicLink\Model\MagicLink;
use MageMe\EUWithdrawalMagicLink\Model\ResourceModel\MagicLink as MagicLinkResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Construct.
     */
    protected function _construct()
    {
        $this->_init(MagicLink::class, MagicLinkResource::class);
    }
}
