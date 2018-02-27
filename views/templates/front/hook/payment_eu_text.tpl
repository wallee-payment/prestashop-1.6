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