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
 * Handles the "Create account for next time" form on the success page.
 *
 * Constraints (all must hold for registration to proceed):
 *   - Form key is valid (CSRF guard).
 *   - Not already logged in.
 *   - There IS a recently-placed order in checkout/session
 *     (the page is gated to that flow).
 *   - The order's customer_email isn't already a registered account.
 *
 * On success: create + log in customer, redirect to the order view page so
 * they land somewhere coherent.
 * On failure: redirect back to the success page with an error flash.
 */
class MageAustralia_CheckoutSuccess_QuickController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/mageaustralia_checkoutsuccess/quick/register', methods: ['POST'])]
    public function registerAction(): void
    {
        $request = $this->getRequest();
        $checkoutSession = Mage::getSingleton('checkout/session');
        $customerSession = Mage::getSingleton('customer/session');

        // CSRF + form key
        if (!$this->_validateFormKey()) {
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('Invalid form key. Please try again.'));
            $this->_redirectToSuccess();
            return;
        }

        if ($customerSession->isLoggedIn()) {
            $this->_redirectToSuccess();
            return;
        }

        $orderId = (int) $checkoutSession->getLastOrderId();
        if ($orderId === 0) {
            $this->_redirectToSuccess();
            return;
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $email = trim((string) $order->getCustomerEmail());
        if ($email === '') {
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('Order is missing a customer email; cannot register.'));
            $this->_redirectToSuccess();
            return;
        }

        $password = (string) $request->getPost('password', '');
        $confirmation = (string) $request->getPost('confirmation', '');

        if ($password === '' || $password !== $confirmation) {
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('Passwords did not match.'));
            $this->_redirectToSuccess();
            return;
        }
        if (strlen($password) < 8) {
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('Password must be at least 8 characters.'));
            $this->_redirectToSuccess();
            return;
        }

        $websiteId = (int) Mage::app()->getStore()->getWebsiteId();

        // Reject if email is already a registered customer (defence in depth —
        // the success-page block already hides the CTA in that case).
        $existing = Mage::getModel('customer/customer')->setWebsiteId($websiteId)->loadByEmail($email);
        if ($existing->getId()) {
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('An account already exists for this email — please log in instead.'));
            $this->_redirectToSuccess();
            return;
        }

        try {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel('customer/customer');
            $customer->setWebsiteId($websiteId)
                ->setStoreId((int) Mage::app()->getStore()->getId())
                ->setEmail($email)
                ->setFirstname((string) $order->getCustomerFirstname())
                ->setLastname((string) $order->getCustomerLastname())
                ->setPassword($password)
                ->setData('is_active', 1);

            $customer->save();

            // Link any matching guest orders to the new customer so they
            // appear in My Orders without further intervention.
            Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_email', $email)
                ->addFieldToFilter('customer_id', ['null' => true])
                ->walk(function (Mage_Sales_Model_Order $o) use ($customer): void {
                    $o->setCustomerId((int) $customer->getId())
                        ->setCustomerIsGuest(0)
                        ->setCustomerGroupId((int) $customer->getGroupId())
                        ->save();
                });

            $customerSession->setCustomerAsLoggedIn($customer);
            $customerSession->addSuccess(Mage::helper('mageaustralia_checkoutsuccess')->__('Your account has been created.'));

            $this->_redirect('sales/order/view', ['order_id' => $orderId]);
            return;
        } catch (\Throwable $e) {
            // Log the real reason for support; surface only a generic message
            // to avoid leaking internal exception text to customers.
            Mage::logException($e);
            $checkoutSession->addError(Mage::helper('mageaustralia_checkoutsuccess')->__('Could not create your account. Please try again or contact support.'));
            $this->_redirectToSuccess();
            return;
        }
    }

    protected function _redirectToSuccess(): void
    {
        $this->_redirect('checkout/onepage/success');
    }
}
