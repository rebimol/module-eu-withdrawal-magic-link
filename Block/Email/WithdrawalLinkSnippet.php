<?php
declare(strict_types=1);

namespace MageMe\EUWithdrawalMagicLink\Block\Email;

use MageMe\EUWithdrawal\Model\Frontend\FooterLinkLabelResolver;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class WithdrawalLinkSnippet extends Template
{
    /**
     * Constructor.
     *
     * @param Context $context
     * @param FooterLinkLabelResolver $labelResolver
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly FooterLinkLabelResolver $labelResolver,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get button label.
     *
     * @return string
     */
    public function getButtonLabel(): string
    {
        $locale = $this->getData('locale');
        return $this->labelResolver->step1Label(is_string($locale) && $locale !== '' ? $locale : null);
    }

    /**
     * Get withdrawal link url.
     *
     * @return ?string
     */
    public function getWithdrawalLinkUrl(): ?string
    {
        $url = $this->getData('withdrawal_link_url');
        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Get heading.
     *
     * @return Phrase
     */
    public function getHeading(): Phrase
    {
        return __('Right of withdrawal');
    }

    /**
     * Get reminder text.
     *
     * Phrasing follows Art. 9(2)(b) CRD: for sales of goods the 14-day
     * window starts when the consumer acquires physical possession. The
     * "from the day you receive" phrasing avoids the common merchant
     * mistake of measuring from order placement.
     *
     * @return Phrase
     */
    public function getReminderText(): Phrase
    {
        return __(
            'You have 14 days from the day you receive your order to withdraw without giving any reason — full refund to your original payment method.',
        );
    }

    /**
     * Get exclusions note.
     *
     * Surfaces the Art. 16 CRD presets so the customer is not promised an
     * unconditional right; the next-step UI shows exactly which items are
     * eligible (per-item exclusion reason from `EligibilityEngine`).
     *
     * @return Phrase
     */
    public function getExclusionsNote(): Phrase
    {
        return __(
            'Some items may be excluded by law (perishable goods, custom-made items, sealed hygiene or audio/video products). We\'ll show you exactly what\'s eligible on the next page.',
        );
    }

    /**
     * Get helper text.
     *
     * @return Phrase
     */
    public function getHelperText(): Phrase
    {
        return __('Direct link — no login or account password required.');
    }
}
