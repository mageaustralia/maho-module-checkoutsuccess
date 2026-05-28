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
 * Renders the bottom_html config value with variable substitution. Typically
 * used to drop GA conversion / Meta pixel / generic tracking snippets onto
 * the success page without editing a template. Output is rendered raw -
 * substitution only swaps {{token}} placeholders, it does not escape the
 * surrounding markup.
 */
class MageAustralia_CheckoutSuccess_Block_Html_Bottom extends MageAustralia_CheckoutSuccess_Block_Template
{
    #[Override]
    protected function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        }
        return $this->getSuccessHelper()->renderBottomHtml($this->getOrder());
    }
}
