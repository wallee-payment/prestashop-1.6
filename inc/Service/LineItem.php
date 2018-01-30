<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This service provides methods to handle manual tasks.
 */
class Wallee_Service_LineItem extends Wallee_Service_Abstract
{

    /**
     * Returns the line items from the given cart
     *
     * @param Cart $cart
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function getItemsFromCart(Cart $cart)
    {
        $currencyCode = Wallee_Helper::convertCurrencyIdToCode($cart->id_currency);
        $items = array();
        $summary = $cart->getSummaryDetails();
        foreach ($summary['products'] as $productItem) {
            $item = new \Wallee\Sdk\Model\LineItemCreate();
            $item->setAmountIncludingTax($this->roundAmount(floatval($productItem['total_wt']), $currencyCode));
            $item->setName($productItem['name']);
            $item->setQuantity($productItem['quantity']);
            $item->setShippingRequired($productItem['is_virtual'] != '1');
            if (! empty($productItem['reference'])) {
                $item->setSku($productItem['reference']);
            }
            if ($productItem['rate'] > 0) {
                $tax = new \Wallee\Sdk\Model\TaxCreate();
                $tax->setTitle($productItem['tax_name']);
                $tax->setRate(round($productItem['rate'],8));
                $item->setTaxes(array(
                    $tax
                ));
            }
            $item->setType(\Wallee\Sdk\Model\LineItemType::PRODUCT);
            if ($productItem['id_product'] == Configuration::get(Wallee_Payment::CK_FEE_ITEM)) {
                $item->setType(\Wallee\Sdk\Model\LineItemType::FEE);
                $item->setShippingRequired(false);
            }
            
            $item->setUniqueId(
                'cart-' . $cart->id . '-item-' . $productItem['id_product'] . '-' .
                     $productItem['id_product_attribute']);
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add shipping costs
        $shippingCosts = floatval($summary['total_shipping']);
        $shippingCostExcl = floatval($summary['total_shipping_tax_exc']);
        if ($shippingCosts > 0) {
            $item = new \Wallee\Sdk\Model\LineItemCreate();
            $item->setAmountIncludingTax($this->roundAmount($shippingCosts, $currencyCode));
            $name = Wallee_Helper::translatePS('Shipping');
            $taxRate = 0;
            $taxName = Wallee_Helper::translatePS('Tax');
            if ($shippingCostExcl > 0) {
                $taxRate = ($shippingCosts - $shippingCostExcl) / $shippingCostExcl * 100;
            }
            if (isset($summary['carrier']) && $summary['carrier'] instanceof Carrier) {
                $name = $summary['carrier']->name;
                if (isset($summary['delivery']) && $summary['delivery'] instanceof Address) {
                    $taxCalculator = $summary['carrier']->getTaxCalculator($summary['delivery']);
                    $taxRate = $taxCalculator->getTotalRate();
                    $taxName = $taxCalculator->getTaxesName();
                }
            }
            $item->setName($name);
            $item->setQuantity(1);
            $item->setShippingRequired(false);
            $item->setSku('shipping');
            if ($taxRate > 0) {
                $tax = new \Wallee\Sdk\Model\TaxCreate();
                $tax->setTitle($taxName);
                $tax->setRate(round($taxRate, 8));
                $item->setTaxes(array(
                    $tax
                ));
            }
            $item->setType(\Wallee\Sdk\Model\LineItemType::SHIPPING);
            $item->setUniqueId('cart-' . $cart->id . '-shipping');
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add wrapping costs
        $wrappingCosts = floatval($summary['total_wrapping']);
        $wrappingCostExcl = floatval($summary['total_wrapping_tax_exc']);
        if ($wrappingCosts > 0) {
            $item = new \Wallee\Sdk\Model\LineItemCreate();
            $item->setAmountIncludingTax($this->roundAmount($wrappingCosts, $currencyCode));
            $taxRate = 0;
            $taxName = Wallee_Helper::translatePS('Tax');
            if ($wrappingCostExcl > 0) {
                $taxRate = ($wrappingCosts - $wrappingCostExcl) / $wrappingCostExcl * 100;
            }
            $item->setName(Wallee_Helper::translatePS('Wrapping Fee'));
            $item->setQuantity(1);
            $item->setShippingRequired(false);
            $item->setSku('wrapping');
            if ($taxRate > 0) {
                $tax = new \Wallee\Sdk\Model\TaxCreate();
                $tax->setTitle($taxName);
                $tax->setRate(round($taxRate, 8));
                $item->setTaxes(array(
                    $tax
                ));
            }
            $item->setType(\Wallee\Sdk\Model\LineItemType::FEE);
            $item->setUniqueId('cart-' . $cart->id . '-wrapping');
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add discounts
        if (count($summary['discounts']) > 0) {
            foreach ($summary['discounts'] as $discount) {
                $discountCosts = floatval($discount['value_real']);
                $discountCostExcl = floatval($discount['value_tax_exc']);
                $item = new \Wallee\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($discountCosts * - 1, $currencyCode));
                $taxRate = 0;
                $taxName = Wallee_Helper::translatePS('Tax');
                if ($discountCostExcl > 0) {
                    $taxRate = ($discountCosts - $discountCostExcl) / $discountCostExcl * 100;
                }
                $item->setName($discount['description']);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku('discount-' . $discount['id_cart_rule']);
                if ($taxRate > 0) {
                    $tax = new \Wallee\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(array(
                        $tax
                    ));
                }
                $item->setType(\Wallee\Sdk\Model\LineItemType::DISCOUNT);
                $item->setUniqueId('cart-' . $cart->id . '-discount-' . $discount['id_cart_rule']);
                $items[] = $this->cleanLineItem($item);
            }
        }
        
        $cleaned = Wallee_Helper::cleanupLineItems($items, $cart->getOrderTotal(true, Cart::BOTH),
            $currencyCode);
        return $cleaned;
    }

    /**
     * Returns the line items from the given cart
     *
     * @param Order[] $orders
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public function getItemsFromOrders(array $orders)
    {
        $items = $this->getItemsFromOrdersInner($orders);
        $orderTotal = 0;
        foreach ($orders as $order) {
            $orderTotal += floatval($order->total_products_wt) + floatval($order->total_shipping) -
                 floatval($order->total_discounts) + floatval($order->total_wrapping);
        }        
        $cleaned = Wallee_Helper::cleanupLineItems($items, $orderTotal,
            Wallee_Helper::convertCurrencyIdToCode($order->id_currency));
        return $cleaned;
    }

    protected function getItemsFromOrdersInner(array $orders)
    {
        $items = array();
        
        foreach ($orders as $order) {
            $currencyCode = Wallee_Helper::convertCurrencyIdToCode($order->id_currency);
            foreach ($order->getProducts() as $orderItem) {
                $uniqueId = 'order-' . $order->id . '-item-' . $orderItem['product_id'] . '-' .               $orderItem['product_attribute_id'];
                
                 $itemCosts = floatval($orderItem['total_wt']);
                 if(isset($orderItem['total_customization_wt'])){
                     $itemCosts = floatval($orderItem['total_customization_wt']);
                 }
                $sku = $orderItem['reference'];
                if (empty($sku)) {
                    $sku = $orderItem['product_name'];
                }                
                $item = new \Wallee\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($itemCosts, $currencyCode));
                $item->setName($orderItem['product_name']);
                $item->setQuantity($orderItem['product_quantity']);
                $item->setShippingRequired($orderItem['is_virtual'] == '1');
                $item->setSku($sku);
                $taxCalculator = $orderItem['tax_calculator'];
                if ($taxCalculator->getTotalRate() > 0) {
                    $tax = new \Wallee\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxCalculator->getTaxesName());
                    $tax->setRate(round($taxCalculator->getTotalRate(), 8));
                    $item->setTaxes(
                        array(
                            $tax
                        ));
                }
                $item->setType(\Wallee\Sdk\Model\LineItemType::PRODUCT);
                if ($orderItem['product_id'] == Configuration::get(Wallee_Payment::CK_FEE_ITEM)) {
                    $item->setType(\Wallee\Sdk\Model\LineItemType::FEE);
                    $item->setShippingRequired(false);
                }
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
            
            // Add shipping costs
            $shippingCosts = floatval($order->total_shipping);
            $shippingCostExcl = floatval($order->total_shipping_tax_excl);
            if ($shippingCosts > 0) {
                $uniqueId = 'order-' . $order->id . '-shipping';
                $taxRate = 0;
                $taxName = Wallee_Helper::translatePS('Tax');
                if ($shippingCostExcl > 0) {
                    $taxRate = ($shippingCosts - $shippingCostExcl) / $shippingCostExcl * 100;
                }
                $carrier = new Carrier($order->id_carrier);
                $address = new Address($order->id_address_delivery);
                if ($carrier->id && $address->id) {
                    $taxCalculator = $carrier->getTaxCalculator($address);
                    $taxRate = $taxCalculator->getTotalRate();
                    $taxName = $taxCalculator->getTaxesName();
                }
                $item = new \Wallee\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($shippingCosts, $currencyCode));
                $name = Wallee_Helper::translatePS('Shipping');
                
                $item->setName($name);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku('shipping');
                if ($taxRate > 0) {
                    $tax = new \Wallee\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(
                        array(
                            $tax
                        ));
                }
                $item->setType(\Wallee\Sdk\Model\LineItemType::SHIPPING);
                $item->setShippingRequired(false);
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
            
            // Add wrapping costs
            $wrappingCosts = floatval($order->total_wrapping);
            $wrappingCostExcl = floatval($order->total_wrapping_tax_excl);
            if ($wrappingCosts > 0) {
                $uniqueId = 'order-' . $order->id . '-wrapping';
                $taxRate = 0;
                $taxName = Wallee_Helper::translatePS('Tax');
                if ($wrappingCostExcl > 0) {
                    $taxRate = ($wrappingCosts - $wrappingCostExcl) / $wrappingCostExcl * 100;
                }
                $item = new \Wallee\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($wrappingCosts, $currencyCode));
                $item->setName(Wallee_Helper::translatePS('Wrapping Fee'));
                $item->setQuantity(1);
                $item->setSku('wrapping');
                if ($taxRate > 0) {
                    $tax = new \Wallee\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(
                        array(
                            $tax
                        ));
                }
                $item->setType(\Wallee\Sdk\Model\LineItemType::FEE);
                $item->setShippingRequired(false);
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
            
            foreach ($order->getCartRules() as $cartRule) {
                $uniqueId = 'order-' . $order->id . '-discount-' . $cartRule['id_cart_rule'];
                $ruleValue = floatval($cartRule['value']);
                $ruleValueExcl = floatval($cartRule['value_tax_excl']);
                $item = new \Wallee\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($ruleValue * - 1, $currencyCode));
                $taxRate = 0;
                $taxName = Wallee_Helper::translatePS('Tax');
                if ($ruleValueExcl > 0) {
                    $taxRate = ($ruleValue - $ruleValueExcl) / $ruleValueExcl * 100;
                }
                $item->setName($cartRule['name']);
                $item->setQuantity(1);
                $item->setSku('discount-' . $cartRule['id_cart_rule']);
                if ($taxRate > 0) {
                    $tax = new \Wallee\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(
                        array(
                            $tax
                        ));
                }
                $item->setType(\Wallee\Sdk\Model\LineItemType::DISCOUNT);
                $item->setShippingRequired(false);
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
        }
        return $items;
    }

    /**
     * Cleans the given line item for it to meet the API's requirements.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate $lineItem
     * @return \Wallee\Sdk\Model\LineItemCreate
     */
    protected function cleanLineItem(\Wallee\Sdk\Model\LineItemCreate $lineItem)
    {
        $lineItem->setSku($this->fixLength($lineItem->getSku(), 200));
        $lineItem->setName($this->fixLength($lineItem->getName(), 40));
        return $lineItem;
    }
}