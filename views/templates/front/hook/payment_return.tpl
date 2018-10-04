{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<h3>{l s='Your order on %s is complete.' sprintf=$shop_name mod='wallee'}</h3>
<div class="wallee_return">
	<br />{l s='Amount' mod='wallee'}: <span class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span>
	<br />{l s='Order Reference' mod='wallee'}: <span class="reference"><strong>{$reference|escape:'html':'UTF-8'}</strong></span>
	<br /><br />{l s='An email has been sent with this information.' mod='wallee'}
	<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='wallee'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='wallee'}</a>
</div>
