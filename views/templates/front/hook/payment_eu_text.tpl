{name}
{if !empty($description)}
			<span class="payment-method-description">{$description}</span>
{/if}

{if !empty($feeValues)}
	<span class="wallee-payment-fee"><span class="wallee-payment-fee-text">{l s='Additional Fee:' mod='wallee_payment'}</span>
		<span class="wallee-payment-fee-value">
			{if $priceDisplay}
	          	{displayPrice price=$feeValues.wallee_fee_total} {if $display_tax_label}{l s='(tax excl.)' mod='wallee_payment'}{/if}
	        {else}
	          	{displayPrice price=$feeValues.wallee_fee_total_wt} {if $display_tax_label}{l s='(tax incl.)' mod='wallee_payment'}{/if}
	        {/if}
       </span>
   </span>
{/if}