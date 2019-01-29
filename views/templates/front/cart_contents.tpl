{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
{assign var='total_discounts_num' value="{if $total_discounts != 0}1{else}0{/if}"}
{assign var='use_show_taxes' value="{if $use_taxes && $show_taxes}2{else}0{/if}"}
{assign var='total_wrapping_taxes_num' value="{if $total_wrapping != 0}1{else}0{/if}"}
{* eu-legal *}
{hook h="displayBeforeShoppingCartBlock"}
<div id="order-detail-content" class="table_block table-responsive">
    <table id="cart_summary" class="table table-bordered">
        <thead>
        <tr>
            <th class="cart_product first_item">{l s='Product' mod='wallee'}</th>
            <th class="cart_description item">{l s='Description' mod='wallee'}</th>
            {if $PS_STOCK_MANAGEMENT}
                <th class="cart_availability item text-center">{l s='Availability' mod='wallee'}</th>
            {/if}
            <th class="cart_unit item text-right">{l s='Unit price' mod='wallee'}</th>
            <th class="cart_quantity item text-center">{l s='Qty' mod='wallee'}</th>
            <th class="cart_total last_item text-right">{l s='Total' mod='wallee'}</th>
        </tr>
        </thead>
        <tfoot>
        {if $use_taxes}
            {if $priceDisplay}
                <tr class="cart_total_price">
                    <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total products (tax excl.)' mod='wallee'}{else}{l s='Total products' mod='wallee'}{/if}</td>
                    <td colspan="2" class="price" id="total_product">{displayPrice price=$total_products}</td>
                </tr>
            {else}
                <tr class="cart_total_price">
                    <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total products (tax incl.)' mod='wallee'}{else}{l s='Total products' mod='wallee'}{/if}</td>
                    <td colspan="2" class="price" id="total_product">{displayPrice price=$total_products_wt}</td>
                </tr>
            {/if}
        {else}
            <tr class="cart_total_price">
                <td colspan="4" class="text-right">{l s='Total products' mod='wallee'}</td>
                <td colspan="2" class="price" id="total_product">{displayPrice price=$total_products}</td>
            </tr>
        {/if}
        <tr class="cart_total_voucher" {if $total_wrapping == 0}style="display:none"{/if}>
            <td colspan="4" class="text-right">
                {if $use_taxes}
                    {if $priceDisplay}
                        {if $display_tax_label}{l s='Total gift wrapping (tax excl.):' mod='wallee'}{else}{l s='Total gift wrapping cost:' mod='wallee'}{/if}
                    {else}
                        {if $display_tax_label}{l s='Total gift wrapping (tax incl.)' mod='wallee'}{else}{l s='Total gift wrapping cost:' mod='wallee'}{/if}
                    {/if}
                {else}
                    {l s='Total gift wrapping cost:' mod='wallee'}
                {/if}
            </td>
            <td colspan="2" class="price-discount price" id="total_wrapping">
                {if $use_taxes}
                    {if $priceDisplay}
                        {displayPrice price=$total_wrapping_tax_exc}
                    {else}
                        {displayPrice price=$total_wrapping}
                    {/if}
                {else}
                    {displayPrice price=$total_wrapping_tax_exc}
                {/if}
            </td>
        </tr>
        {if $total_shipping_tax_exc <= 0 && (!isset($isVirtualCart) || !$isVirtualCart) && $free_ship}
            <tr class="cart_total_delivery">
                <td colspan="4" class="text-right">{l s='Total shipping' mod='wallee'}</td>
                <td colspan="2" class="price" id="total_shipping">{l s='Free Shipping!' mod='wallee'}</td>
            </tr>
        {else}
            {if $use_taxes && $total_shipping_tax_exc != $total_shipping}
                {if $priceDisplay}
                    <tr class="cart_total_delivery" {if $shippingCost <= 0} style="display:none"{/if}>
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total shipping (tax excl.)' mod='wallee'}{else}{l s='Total shipping' mod='wallee'}{/if}</td>
                        <td colspan="2" class="price" id="total_shipping">{displayPrice price=$shippingCostTaxExc}</td>
                    </tr>
                {else}
                    <tr class="cart_total_delivery"{if $shippingCost <= 0} style="display:none"{/if}>
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total shipping (tax incl.)' mod='wallee'}{else}{l s='Total shipping' mod='wallee'}{/if}</td>
                        <td colspan="2" class="price" id="total_shipping" >{displayPrice price=$shippingCost}</td>
                    </tr>
                {/if}
            {else}
                <tr class="cart_total_delivery"{if $shippingCost <= 0} style="display:none"{/if}>
                    <td colspan="4" class="text-right">{l s='Total shipping' mod='wallee'}</td>
                    <td colspan="2" class="price" id="total_shipping" >{displayPrice price=$shippingCostTaxExc}</td>
                </tr>
            {/if}
        {/if}
        <tr class="cart_total_voucher" {if $total_discounts == 0}style="display:none"{/if}>
            <td colspan="4" class="text-right">
                {if $use_taxes}
                    {if $priceDisplay}
                        {if $display_tax_label && $show_taxes}{l s='Total vouchers (tax excl.)' mod='wallee'}{else}{l s='Total vouchers' mod='wallee'}{/if}
                    {else}
                        {if $display_tax_label && $show_taxes}{l s='Total vouchers (tax incl.)' mod='wallee'}{else}{l s='Total vouchers' mod='wallee'}{/if}
                    {/if}
                {else}
                    {l s='Total vouchers' mod='wallee'}
                {/if}
            </td>
            <td colspan="2" class="price-discount price" id="total_discount">
                {if $use_taxes}
                    {if $priceDisplay}
                        {displayPrice price=$total_discounts_tax_exc*-1}
                    {else}
                        {displayPrice price=$total_discounts*-1}
                    {/if}
                {else}
                    {displayPrice price=$total_discounts_tax_exc*-1}
                {/if}
            </td>
        </tr>
        {if $use_taxes}
            {if $total_tax != 0 && $show_taxes}
                {if $priceDisplay != 0}
                    <tr class="cart_total_price">
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total (tax excl.)' mod='wallee'}{else}{l s='Total' mod='wallee'}{/if}</td>
                        <td colspan="2" class="price" id="total_price_without_tax">{displayPrice price=$total_price_without_tax}</td>
                    </tr>
                {/if}
                <tr class="cart_total_tax">
                    <td colspan="4" class="text-right">{l s='Tax' mod='wallee'}</td>
                    <td colspan="2" class="price" id="total_tax" >{displayPrice price=$total_tax}</td>
                </tr>
            {/if}
            <tr class="cart_total_price">
                <td colspan="4" class="total_price_container text-right"><span>{l s='Total' mod='wallee'}</span></td>
                <td colspan="2" class="price" id="total_price_container">
                    <span id="total_price" data-selenium-total-price="{$total_price}">{displayPrice price=$total_price}</span>
                </td>
            </tr>
        {else}
            <tr class="cart_total_price">
                <td colspan="4" class="text-right total_price_container">
                    <span>{l s='Total' mod='wallee'}</span>
                </td>
                <td colspan="2" class="price total_price_container" id="total_price_container">
                    <span id="total_price" data-selenium-total-price="{$total_price_without_tax}">{displayPrice price=$total_price_without_tax}</span>
                </td>
            </tr>
        {/if}
        </tfoot>

        <tbody>
        {foreach from=$products item=product name=productLoop}
            {assign var='productId' value=$product.id_product}
            {assign var='productAttributeId' value=$product.id_product_attribute}
            {assign var='quantityDisplayed' value=0}
            {assign var='cannotModify' value=1}
            {assign var='odd' value=$product@iteration%2}
            {assign var='noDeleteButton' value=1}

            {* Display the product line *}
            {include file="$tpl_dir./shopping-cart-product-line.tpl"}

            {* Then the customized datas ones*}
            {if isset($customizedDatas.$productId.$productAttributeId)}
                {foreach from=$customizedDatas.$productId.$productAttributeId[$product.id_address_delivery] key='id_customization' item='customization'}
                    <tr id="product_{$product.id_product}_{$product.id_product_attribute}_{$id_customization}" class="alternate_item cart_item">
                        <td colspan="4">
                            {foreach from=$customization.datas key='type' item='datas'}
                                {if $type == $CUSTOMIZE_FILE}
                                    <div class="customizationUploaded">
                                        <ul class="customizationUploaded">
                                            {foreach from=$datas item='picture'}
                                                <li>
                                                    <img src="{$pic_dir}{$picture.value}_small" alt="" class="customizationUploaded" />
                                                </li>
                                            {/foreach}
                                        </ul>
                                    </div>
                                {elseif $type == $CUSTOMIZE_TEXTFIELD}
                                    <ul class="typedText">
                                        {foreach from=$datas item='textField' name='typedText'}
                                            <li>
                                                {if $textField.name}
                                                    {l s='%s:' sprintf=$textField.name mod='wallee'}
                                                {else}
                                                    {l s='Text #%s:' sprintf=$smarty.foreach.typedText.index+1 mod='wallee'}
                                                {/if}
                                                {$textField.value}
                                            </li>
                                        {/foreach}
                                    </ul>
                                {/if}
                            {/foreach}
                        </td>
                        <td class="cart_quantity text-center">
                            {$customization.quantity}
                        </td>
                        <td class="cart_total"></td>
                    </tr>
                    {assign var='quantityDisplayed' value=$quantityDisplayed+$customization.quantity}
                {/foreach}
                {* If it exists also some uncustomized products *}
                {if $product.quantity-$quantityDisplayed > 0}{include file="$tpl_dir./shopping-cart-product-line.tpl"}{/if}
            {/if}
        {/foreach}
        {assign var='last_was_odd' value=$product@iteration%2}
        {foreach $gift_products as $product}
            {assign var='productId' value=$product.id_product}
            {assign var='productAttributeId' value=$product.id_product_attribute}
            {assign var='quantityDisplayed' value=0}
            {assign var='odd' value=($product@iteration+$last_was_odd)%2}
            {assign var='ignoreProductLast' value=isset($customizedDatas.$productId.$productAttributeId)}
            {assign var='cannotModify' value=1}
            {* Display the gift product line *}
            {include file="$tpl_dir./shopping-cart-product-line.tpl" productLast=$product@last productFirst=$product@first}
        {/foreach}
        </tbody>

        {if count($discounts)}
            <tbody>
            {foreach from=$discounts item=discount name=discountLoop}
                {if (float)$discount.value_real == 0}
                    {continue}
                {/if}
                <tr class="cart_discount {if $smarty.foreach.discountLoop.last}last_item{elseif $smarty.foreach.discountLoop.first}first_item{else}item{/if}" id="cart_discount_{$discount.id_discount}">
                    <td class="cart_discount_name" colspan="{if $PS_STOCK_MANAGEMENT}3{else}2{/if}">{$discount.name}</td>
                    <td class="cart_discount_price">
						<span class="price-discount">
							{if $discount.value_real > 0}
	                            {if !$priceDisplay}
	                                {displayPrice price=$discount.value_real*-1}
	                            {else}
	                                {displayPrice price=$discount.value_tax_exc*-1}
	                            {/if}
	                        {/if}
						</span>
                    </td>
                    <td class="cart_discount_delete">1</td>
                    <td class="cart_discount_price">
						<span class="price-discount">
							{if $discount.value_real > 0}
                                {if !$priceDisplay}
                                    {displayPrice price=$discount.value_real*-1}
                                {else}
                                    {displayPrice price=$discount.value_tax_exc*-1}
                                {/if}
                            {/if}
						</span>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        {/if}
    </table>
</div> <!-- end order-detail-content -->