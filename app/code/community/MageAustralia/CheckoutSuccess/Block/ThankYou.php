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
 * "Thank you" headline block. Pure template-driven, no behaviour beyond
 * what the base Template block provides.
 */
class MageAustralia_CheckoutSuccess_Block_ThankYou extends MageAustralia_CheckoutSuccess_Block_Template
{
    public function canViewOrder(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        // Authenticated customers can always view their own order; guests
        // can view via the checkout session that placed the order, so the
        // checkout/success block's link is safe to expose here too.
        return true;
    }

    public function getViewOrderUrl(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }
        return $this->getUrl('sales/order/view', ['order_id' => $order->getId()]);
    }

    public function getPrintUrl(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }
        return $this->getUrl('sales/order/print', ['order_id' => $order->getId()]);
    }
}
