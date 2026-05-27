<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'mageaustralia', 'checkoutsuccess');

it('can instantiate the helper', function () {
    $helper = Mage::helper('mageaustralia_checkoutsuccess');
    expect($helper)->toBeInstanceOf(MageAustralia_CheckoutSuccess_Helper_Data::class);
});

it('exposes the canonical SLOTS list', function () {
    expect(MageAustralia_CheckoutSuccess_Helper_Data::SLOTS)
        ->toBe(['top', 'middleleft', 'middleright', 'bottom']);
});

it('parses slot config CSV into an ordered block-code list', function () {
    Mage::getConfig()->saveConfig(
        'mageaustralia_checkoutsuccess/mockup/slot_middleright',
        'checkoutsuccess.quick.register,sales.order.view,sales.order.info',
        'default', 0,
    );
    Mage::app()->getStore()->resetConfig();

    $helper = Mage::helper('mageaustralia_checkoutsuccess');
    expect($helper->getSlotBlocks('middleright'))
        ->toBe(['checkoutsuccess.quick.register', 'sales.order.view', 'sales.order.info']);
});

it('returns empty list for unknown slot names', function () {
    $helper = Mage::helper('mageaustralia_checkoutsuccess');
    expect($helper->getSlotBlocks('nonsense'))->toBe([]);
});

it('trims whitespace and drops empty entries in slot config', function () {
    Mage::getConfig()->saveConfig(
        'mageaustralia_checkoutsuccess/mockup/slot_top',
        '  alpha  , , beta  ,',
        'default', 0,
    );
    Mage::app()->getStore()->resetConfig();

    $helper = Mage::helper('mageaustralia_checkoutsuccess');
    expect($helper->getSlotBlocks('top'))->toBe(['alpha', 'beta']);
});

it('substitutes order variables in bottom_html', function () {
    Mage::getConfig()->saveConfig(
        'mageaustralia_checkoutsuccess/general/bottom_html',
        'order={{orderIncrementId}}; amount={{orderAmount}}; email={{customerEmail}}',
        'default', 0,
    );
    Mage::app()->getStore()->resetConfig();

    $order = new Mage_Sales_Model_Order();
    $order->setData('entity_id', 999);
    $order->setData('increment_id', 'TEST-001');
    $order->setData('grand_total', 42.50);
    $order->setData('customer_email', 'buyer@example.com');

    $rendered = Mage::helper('mageaustralia_checkoutsuccess')->renderBottomHtml($order);
    expect($rendered)->toBe('order=TEST-001; amount=42.5; email=buyer@example.com');
});

it('strips unresolved tokens when no order is provided', function () {
    Mage::getConfig()->saveConfig(
        'mageaustralia_checkoutsuccess/general/bottom_html',
        'order={{orderIncrementId}} static',
        'default', 0,
    );
    Mage::app()->getStore()->resetConfig();

    $rendered = Mage::helper('mageaustralia_checkoutsuccess')->renderBottomHtml(null);
    expect($rendered)->toBe('order= static');
});

it('returns empty string when bottom_html is not configured', function () {
    Mage::getConfig()->saveConfig('mageaustralia_checkoutsuccess/general/bottom_html', '', 'default', 0);
    Mage::app()->getStore()->resetConfig();

    $rendered = Mage::helper('mageaustralia_checkoutsuccess')->renderBottomHtml(null);
    expect($rendered)->toBe('');
});

it('exposes 6 available block codes that match registered layout blocks', function () {
    $source = Mage::getSingleton('mageaustralia_checkoutsuccess/source_availableBlocks');
    $options = $source->toOptionArray();
    expect($options)->toHaveCount(6);

    $codes = array_column($options, 'value');
    expect($codes)->toContain('checkoutsuccess.thank.you');
    expect($codes)->toContain('checkoutsuccess.quick.register');
    expect($codes)->toContain('sales.order.view');
    expect($codes)->toContain('sales.order.info');
    expect($codes)->toContain('checkoutsuccess.additional');
    expect($codes)->toContain('sales.recurring.profile.schedule');
});

it('cms block source always includes a None option as the first entry', function () {
    $source = Mage::getSingleton('mageaustralia_checkoutsuccess/source_cmsBlock');
    $options = $source->toOptionArray();
    expect($options[0]['value'])->toBe(0);
    expect($options[0]['label'])->toContain('None');
});

it('block classes resolve via the layout factory', function () {
    $layout = Mage::app()->getLayout();
    expect($layout->createBlock('mageaustralia_checkoutsuccess/template'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Template::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/thankYou'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_ThankYou::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/quick_register'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Quick_Register::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/sales_order_view'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Sales_Order_View::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/sales_order_info'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Sales_Order_Info::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/additional'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Additional::class);
    expect($layout->createBlock('mageaustralia_checkoutsuccess/html_bottom'))
        ->toBeInstanceOf(MageAustralia_CheckoutSuccess_Block_Html_Bottom::class);
});

it('thank-you block returns empty html when module is disabled', function () {
    Mage::getConfig()->saveConfig('mageaustralia_checkoutsuccess/general/enabled', '0', 'default', 0);
    Mage::app()->getStore()->resetConfig();

    $block = Mage::app()->getLayout()->createBlock('mageaustralia_checkoutsuccess/thankYou');
    expect($block->toHtml())->toBe('');
});
