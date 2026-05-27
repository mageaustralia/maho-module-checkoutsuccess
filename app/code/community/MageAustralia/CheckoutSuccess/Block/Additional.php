<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CheckoutSuccess
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Renders the four mockupsets CMS blocks in order. Each slot is optional;
 * slots set to 0 / blank are skipped. Used by the template to stack
 * arbitrary CMS-managed content (typically promo banners or post-purchase
 * cross-sell) below the order summary.
 */
class MageAustralia_CheckoutSuccess_Block_Additional extends MageAustralia_CheckoutSuccess_Block_Template
{
    /**
     * @return list<string> HTML fragments, one per configured CMS block.
     */
    public function getRenderedBlocks(): array
    {
        $out = [];
        for ($i = 1; $i <= 4; $i++) {
            $id = $this->getSuccessHelper()->getMockupSetBlockId($i);
            if ($id <= 0) {
                continue;
            }
            $block = $this->getLayout()->createBlock('cms/block')->setBlockId($id);
            $html = (string) $block->toHtml();
            if ($html !== '') {
                $out[] = $html;
            }
        }
        return $out;
    }

    #[\Override]
    protected function _toHtml()
    {
        if (!$this->isEnabled() || empty($this->getRenderedBlocks())) {
            return '';
        }
        return parent::_toHtml();
    }
}
