<h3>{l s='Your order on %s is complete.' sprintf=$shop_name mod='wallee'}</h3>
<div class="wallee_return">
	<br />{l s='Amount' mod='wallee'}: <span class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span>
	<br />{l s='Order Reference' mod='wallee'}: <span class="reference"><strong>{$reference|escape:'html':'UTF-8'}</strong></span>
	<br /><br />{l s='An email has been sent with this information.' mod='wallee'}
	<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='wallee'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='wallee'}</a>
</div>
