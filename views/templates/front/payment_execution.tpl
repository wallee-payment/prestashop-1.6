{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='wallee'}">{l s='Checkout' mod='wallee'}</a><span class="navigation-pipe">{$navigationPipe}</span>{$name}
{/capture}

<h1 class="page-heading">
    {l s='Order summary' mod='wallee'}
</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $productNumber <= 0}
    <p class="alert alert-warning">
        {l s='Your shopping cart is empty.' mod='wallee'}
    </p>
{else}
	{if $showCart}
		{assign var='cartTemplate' value="{wallee_resolve_template template='cart_contents.tpl'}"}
		{include file="$cartTemplate"}
	{/if}
	
	<div id="wallee-error-messages"></div>
	
	<form action="{$form_target_url|escape:'html':'UTF-8'}" method="post" id="wallee-payment-form">
    	<input type="hidden" name="cartHash" value="{$cartHash}" />
    	<input type="hidden" name="methodId" value="{$methodId}" />
    	<h3 class="page-subheading">
                {$name}
        </h3>
        <div id="wallee-method-configuration" class="wallee-method-configuration" style="display: none;"
	data-method-id="{$methodId}" data-configuration-id="{$configurationId}"></div>
		<div id="wallee-method-container">
			<div class="wallee-loader"></div>		
		</div>
		
		{if $showTOS && $conditions && $cmsId}
	 		{if isset($overrideTOSDisplay) && $overrideTOSDisplay}
	        	{$overrideTOSDisplay}
			{else}
				<div class="box">
					<p class="checkbox">
						<input type="checkbox" name="cgv" id="cgv" value="1" {if $checkedTOS}checked="checked"{/if}/>
						<label for="cgv">{l s='I agree to the terms of service and will adhere to them unconditionally.'}</label>
						<a href="{$linkConditions|escape:'html':'UTF-8'}" class="iframe" rel="nofollow">{l s='(Read the Terms of Service)'}</a>
					</p>
				</div>
			{/if}
		{/if}
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" tabindex="-1">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='wallee'}
            </a>
            <button class="button btn btn-default button-medium" id="wallee-submit" disabled>
                <span>{l s='I confirm my order' mod='wallee'}<i class="icon-chevron-right right"></i></span>
            </button>
        </p>
    </form>
    <script type="text/javascript">$("a.iframe").fancybox({
		"type" : "iframe",
		"width":600,
		"height":600
	});</script>
	
	{if $showTOS && $conditions && cmsId}
		{addJsDefL name=wallee_msg_tos_error}{l s='You must agree to the terms of service before continuing.'  mod='wallee' js=1}{/addJsDefL}
	{/if}
	{addJsDefL name=wallee_msg_json_error}{l s='The server experienced an unexpected error, you may try again or try to use a different payment method.'  mod='wallee' js=1}{/addJsDefL}
{/if}