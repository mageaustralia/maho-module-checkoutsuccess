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
 * Per-slot block picker. Renders a list of checkboxes (one per available
 * block) with drag-handles. Selected blocks appear first, in saved order;
 * unselected blocks appear below, in registration order.
 *
 * The actual form value is a hidden input holding comma-separated block
 * codes. A small vanilla-JS controller (sortable.js) keeps the hidden
 * input in sync as the admin checks/unchecks/reorders.
 */
class MageAustralia_CheckoutSuccess_Block_Adminhtml_System_Config_Form_Field_SortableBlocks extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[Override]
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element): string
    {
        /** @var MageAustralia_CheckoutSuccess_Model_Source_AvailableBlocks $source */
        $source = Mage::getSingleton('mageaustralia_checkoutsuccess/source_availableBlocks');
        $available = $source->toOptionArray();

        $selectedCsv = (string) $element->getValue();
        $selected = $selectedCsv === ''
            ? []
            : array_values(array_filter(array_map(trim(...), explode(',', $selectedCsv))));

        // Index available by code for ordered lookup.
        $byCode = [];
        foreach ($available as $opt) {
            $byCode[$opt['value']] = $opt;
        }

        // Selected first (in saved order), then unselected (in registration order).
        $ordered = [];
        $selectedSet = array_flip($selected);
        foreach ($selected as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code] + ['checked' => true];
            }
        }
        foreach ($available as $opt) {
            if (!isset($selectedSet[$opt['value']])) {
                $ordered[] = $opt + ['checked' => false];
            }
        }

        $listId = 'csl-' . $element->getHtmlId();
        $items = '';
        foreach ($ordered as $opt) {
            $checked = $opt['checked'] ? ' checked' : '';
            $items .= sprintf(
                '<li draggable="true" data-code="%s">'
                . '<span class="csl-handle" aria-hidden="true">&#x2630;</span>'
                . '<label><input type="checkbox" value="%s"%s> %s</label>'
                . '</li>',
                $this->escapeHtml($opt['value']),
                $this->escapeHtml($opt['value']),
                $checked,
                $this->escapeHtml($opt['label']),
            );
        }

        return sprintf(
            '<ul id="%s" class="csl-sortable">%s</ul>'
            . '<input type="hidden" id="%s" name="%s" value="%s">'
            . '<script>'
            . 'document.addEventListener("DOMContentLoaded", function () {'
            . ' if (window.MageAustraliaCheckoutSuccessSortable) {'
            . '   MageAustraliaCheckoutSuccessSortable.init(%s, %s);'
            . ' }'
            . '});'
            . '</script>',
            $this->escapeHtml($listId),
            $items,
            $this->escapeHtml($element->getHtmlId()),
            $this->escapeHtml($element->getName()),
            $this->escapeHtml($selectedCsv),
            json_encode($listId, JSON_UNESCAPED_SLASHES),
            json_encode($element->getHtmlId(), JSON_UNESCAPED_SLASHES),
        );
    }
}
