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
 * Admin-only endpoint that issues a signed preview URL for an order's
 * success page. The signature is verified on the frontend by the
 * predispatch observer, so a leaked URL only exposes that one order.
 *
 * Because the controller is in the adminhtml area, Maho automatically
 * enforces admin authentication + ACL — no manual session check needed.
 */
class MageAustralia_CheckoutSuccess_Adminhtml_PreviewController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config/mageaustralia_checkoutsuccess';

    #[\Override]
    public function preDispatch(): static
    {
        // CSRF: require a valid form_key on the POSTed sign request, not just
        // the admin secret key in the URL. Belt-and-suspenders against any
        // cross-origin POST that happens to know the admin route.
        $this->_setForcedFormKeyActions(['url']);
        return parent::preDispatch();
    }

    #[Maho\Config\Route('/admin/mageaustralia_checkoutsuccess/preview/url', methods: ['POST'])]
    public function urlAction(): void
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');

        $incrementId = trim((string) $this->getRequest()->getPost('oid', ''));
        $storeCode = trim((string) $this->getRequest()->getPost('store', ''));
        if ($incrementId === '') {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => 'Missing order id']));
            return;
        }

        $store = $storeCode === ''
            ? Mage::app()->getDefaultStoreView()
            : Mage::app()->getStore($storeCode);

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order->getId()) {
            $this->getResponse()
                ->setHttpResponseCode(404)
                ->setBody(json_encode(['error' => 'Order not found: ' . $incrementId]));
            return;
        }

        /** @var MageAustralia_CheckoutSuccess_Helper_Data $helper */
        $helper = Mage::helper('mageaustralia_checkoutsuccess');
        $signature = $helper->signPreviewId($incrementId);

        $base = rtrim((string) $store->getBaseUrl(
            Mage_Core_Model_Store::URL_TYPE_LINK,
            $this->getRequest()->isSecure(),
        ), '/');

        $url = $base . '/checkout/onepage/success/'
            . '?previewObjectId=' . rawurlencode($incrementId)
            . '&previewSig=' . rawurlencode($signature)
            . '&___store=' . rawurlencode((string) $store->getCode());

        $this->getResponse()->setBody(json_encode(['url' => $url]));
    }
}
