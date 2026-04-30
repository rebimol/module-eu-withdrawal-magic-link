<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MagicLink extends AbstractDb
{
    /**
     * Construct.
     */
    protected function _construct()
    {
        $this->_init('mm_eu_withdrawal_magic_link', 'token_id');
    }
}
