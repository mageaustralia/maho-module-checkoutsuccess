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
 * Inline preview action on the system-config screen. Admin enters an order
 * increment_id, clicks "Start Preview", JS POSTs to the admin endpoint to
 * fetch a signed preview URL, then opens it in a new tab.
 *
 * Why a new tab and not an iframe: admin and storefront may live on
 * different subdomains, in which case X-Frame-Options: SAMEORIGIN blocks
 * the iframe. A new tab sidesteps that entirely and works regardless of
 * how subdomains are configured.
 *
 * The frontend success page's predispatch observer (Model/Observer.php)
 * verifies the signature and only then primes checkout/session with the
 * historical order's IDs. Without the signature, the URL is harmless.
 */
class MageAustralia_CheckoutSuccess_Block_Adminhtml_System_Config_Form_Field_FrontendPreview
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element): string
    {
        $store = $this->_resolveStore();
        $signUrl = $this->_buildAdminSignUrl();
        $lastIncrement = $this->_getLastOrderIncrement($store);
        $storeCode = (string) $store->getCode();

        $html = sprintf(
            '<tr id="row_%s">'
            . '<td class="label">%s</td>'
            . '<td class="value">'
            .   '<p>'
            .     '<input id="cs-preview-order-number" type="text" class="input-text" value="%s">'
            .     ' <button type="button" id="cs-preview-start" class="scalable go" style="margin-left: 10px;">'
            .       '<span><span><span>%s</span></span></span>'
            .     '</button>'
            .   '</p>'
            .   '<p class="note">%s</p>'
            .   '<p id="cs-preview-error" style="color:#c00;display:none;margin-top:6px;"></p>'
            . '</td>'
            . '</tr>'
            . '<script>%s</script>',
            $this->escapeHtml($element->getHtmlId()),
            $this->escapeHtml((string) $element->getLabel()),
            $this->escapeHtml((string) $lastIncrement),
            $this->escapeHtml($this->__('Open Preview in New Tab')),
            $this->escapeHtml((string) $element->getComment()),
            $this->_inlineJs($signUrl, $storeCode),
        );

        return $html;
    }

    protected function _resolveStore(): Mage_Core_Model_Store
    {
        $storeCode = (string) $this->getRequest()->getParam('store', '');
        $websiteCode = (string) $this->getRequest()->getParam('website', '');

        if ($storeCode !== '') {
            return Mage::app()->getStore($storeCode);
        }
        if ($websiteCode !== '') {
            return Mage::app()->getWebsite($websiteCode)->getDefaultStore();
        }
        return Mage::app()->getDefaultStoreView();
    }

    /**
     * Build an absolute URL for our admin signing endpoint. The legacy
     * adminhtml URL helper produces `frontName/routerCode_controller/action`
     * (joined with underscores) which doesn't match our Symfony attribute
     * route (`frontName/mageaustralia_checkoutsuccess/preview/url`). Easiest
     * fix: generate via the Symfony route name + prepend the base URL.
     *
     * CSRF protection comes from the form_key POSTed by the JS — the URL
     * itself doesn't need the legacy `/key/<secret>/` admin segment.
     */
    protected function _buildAdminSignUrl(): string
    {
        $adminFrontName = (string) Mage::getConfig()->getNode(
            'admin/routers/adminhtml/args/frontName',
        ) ?: 'admin';

        $context = new \Symfony\Component\Routing\RequestContext();
        $path = \Maho\Routing\RouteCollectionBuilder::createGenerator($context)
            ->generate('mageaustralia.checkoutsuccess.adminhtml.preview.url', [
                '_adminFrontName' => $adminFrontName,
            ]);

        $base = rtrim((string) Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/');
        return $base . $path;
    }

    protected function _getLastOrderIncrement(Mage_Core_Model_Store $store): string
    {
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addAttributeToFilter('store_id', (int) $store->getId())
            ->setOrder('entity_id', 'DESC')
            ->setPageSize(1);
        $first = $collection->getFirstItem();
        return $first && $first->getId() ? (string) $first->getIncrementId() : '';
    }

    protected function _inlineJs(string $signUrl, string $storeCode): string
    {
        $signUrlJson = json_encode($signUrl, JSON_UNESCAPED_SLASHES);
        $storeJson = json_encode($storeCode, JSON_UNESCAPED_SLASHES);
        $formKey = json_encode((string) Mage::getSingleton('core/session')->getFormKey());

        return <<<JS
(function () {
    const input  = document.getElementById('cs-preview-order-number');
    const button = document.getElementById('cs-preview-start');
    const errBox = document.getElementById('cs-preview-error');
    if (!input || !button) { return; }

    const signUrl  = {$signUrlJson};
    const storeKey = {$storeJson};
    const formKey  = {$formKey};

    function showError(msg) {
        errBox.textContent = msg;
        errBox.style.display = 'block';
    }

    function clearError() {
        errBox.textContent = '';
        errBox.style.display = 'none';
    }

    async function loadPreview() {
        clearError();
        const oid = (input.value || '').trim();
        if (!oid) {
            showError('Enter an order number first.');
            return;
        }

        // Open the tab synchronously inside the user-gesture handler — if we
        // wait for the fetch() to resolve first, browsers treat window.open()
        // as a popup and block it. We point the placeholder tab at about:blank
        // and update its location once the signed URL comes back.
        const tab = window.open('about:blank', '_blank');
        if (!tab) {
            showError('Popup blocked. Allow popups on this page to use the preview, or sign the URL manually.');
            return;
        }

        try {
            const body = new FormData();
            body.append('oid', oid);
            body.append('store', storeKey);
            body.append('form_key', formKey);
            // mahoFetch is Maho's wrapped fetch — same-origin creds + JSON
            // parsing + ajaxExpired handling are built in.
            const data = await mahoFetch(signUrl, {
                method: 'POST',
                body: body,
                loaderArea: false,
            });
            if (data.error) {
                tab.close();
                showError(data.error);
                return;
            }
            tab.location.href = data.url;
        } catch (e) {
            tab.close();
            showError('Failed to sign preview URL: ' + (e && e.message ? e.message : String(e)));
        }
    }

    button.addEventListener('click', loadPreview);
    input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); loadPreview(); }
    });
})();
JS;
    }
}
