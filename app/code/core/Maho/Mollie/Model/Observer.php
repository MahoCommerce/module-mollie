<?php

/**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Mollie_Model_Observer
{
    /**
     * Copy the Mollie payment fee from the quote address onto the order.
     *
     * Listens on sales_model_service_quote_submit_before. The fee was put on
     * the shipping (or billing, for virtual quotes) address by the totals
     * collector; carry it across to the order so invoices/creditmemos and the
     * order grid can show it.
     */
    public function copyPaymentFeeToOrder(Varien_Event_Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var Mage_Sales_Model_Order|null $order */
        $order = $event->getData('order');
        /** @var Mage_Sales_Model_Quote|null $quote */
        $quote = $event->getData('quote');

        if (!$order instanceof Mage_Sales_Model_Order || !$quote instanceof Mage_Sales_Model_Quote) {
            return;
        }

        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        if (!$address instanceof Mage_Sales_Model_Quote_Address) {
            return;
        }

        $fee     = (float) $address->getPaymentFeeAmount();
        $baseFee = (float) $address->getBasePaymentFeeAmount();

        if ($fee <= 0 && $baseFee <= 0) {
            return;
        }

        $order->setPaymentFeeAmount($fee);
        $order->setBasePaymentFeeAmount($baseFee);
    }
}
