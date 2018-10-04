{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div class="row">
	<div class="col-xs-12">
			<p class="payment_module wallee-method">
				<a class="wallee {if empty($image)}no_logo{/if}" href="{$link|escape:'html'}" title="{$name}" 
					{if !empty($image)} 
						style="background: url({$image|escape:'html'}) no-repeat #fbfbfb; background-size: 64px; background-position:15px;"
					{/if}
				>
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
				</a>
			</p>	
	</div>
</div>

