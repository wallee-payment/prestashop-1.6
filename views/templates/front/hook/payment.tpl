{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div class="row">
	<div class="col-xs-12">
		<div class="payment_module wallee-method">
			<div class="wallee {if empty($image)}no_logo{/if}" 
					{if !empty($image)} 
						style="background-image: url({$image|escape:'html'}); background-repeat: no-repeat; background-size: 64px; background-position:15px;"		
					{/if}
					onclick="document.getElementById('wallee-{$methodId|escape:'html':'UTF-8'}-link').click();" >
				<a class="wallee" id="wallee-{$methodId|escape:'html':'UTF-8'}-link" href="{$link|escape:'html'}" title="{$name}" >{$name}</a>
			{if !empty($description)}
				<span class="payment-method-description">{wallee_clean_html text=$description}</span>
			{/if}
			{if !empty($surchargeValues)}
				<span class="wallee-surcharge wallee-additional-amount"><span class="wallee-surcharge-text wallee-additional-amount-text">{l s='Minimum Sales Surcharge:' mod='wallee'}</span>
					<span class="wallee-surcharge-value wallee-additional-amount-value">
						{if $priceDisplay}
				          	{displayPrice price=$surchargeValues.surcharge_total} {if $display_tax_label}{l s='(tax excl.)' mod='wallee'}{/if}
				        {else}
				          	{displayPrice price=$surchargeValues.surcharge_total_wt} {if $display_tax_label}{l s='(tax incl.)' mod='wallee'}{/if}
				        {/if}
			       </span>
			   </span>
			{/if}
			{if !empty($feeValues)}
				<span class="wallee-payment-fee wallee-additional-amount"><span class="wallee-payment-fee-text wallee-additional-amount-text">{l s='Payment Fee:' mod='wallee'}</span>
					<span class="wallee-payment-fee-value wallee-additional-amount-value">
						{if $priceDisplay}
				          	{displayPrice price=$feeValues.fee_total} {if $display_tax_label}{l s='(tax excl.)' mod='wallee'}{/if}
				        {else}
				          	{displayPrice price=$feeValues.fee_total_wt} {if $display_tax_label}{l s='(tax incl.)' mod='wallee'}{/if}
				        {/if}
			       </span>
			   </span>
			{/if}
			</div>					
		</div>	
	</div>
</div>
