{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
{$name}
{if !empty($description)}
			<span class="payment-method-description">{$description}</span>
{/if}

{if !empty($feeValues)}
	<span class="wallee-payment-fee"><span class="wallee-payment-fee-text">{l s='Additional Fee:' mod='wallee'}</span>
		<span class="wallee-payment-fee-value">
			{if $priceDisplay}
	          	{displayPrice price=$feeValues.fee_total} {if $display_tax_label}{l s='(tax excl.)' mod='wallee'}{/if}
	        {else}
	          	{displayPrice price=$feeValues.fee_total_wt} {if $display_tax_label}{l s='(tax incl.)' mod='wallee'}{/if}
	        {/if}
       </span>
   </span>
{/if}