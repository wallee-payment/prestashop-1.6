{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
{if (isset($showAuthorizedActions) && $showAuthorizedActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-action wallee-management-btn"  id="wallee_void">
			<i class="icon-remove"></i>
			{l s='Void' mod='wallee'}
		</a>
		<a class="btn btn-action wallee-management-btn"  id="wallee_completion">
			<i class="icon-check"></i>
			{l s='Completion' mod='wallee'}
		</a>	
	</div>

	<script type="text/javascript">
		var wallee_void_title = "{l s='Are you sure?' mod='wallee' js=1}";
		var wallee_void_btn_confirm_txt = "{l s='Void Order' mod='wallee' js=1}";
		var wallee_void_btn_deny_txt = "{l s='No' mod='wallee' js=1}";

		var wallee_completion_title = "{l s='Are you sure?' mod='wallee' js=1}";
		var wallee_completion_btn_confirm_txt = "{l s='Complete Order'  mod='wallee' js=1}";
		var wallee_completion_btn_deny_txt = "{l s='No' mod='wallee' js=1}";

		var wallee_msg_general_error = "{l s='The server experienced an unexpected error, please try again.'  mod='wallee' js=1}";
		var wallee_msg_general_title_succes = "{l s='Success'  mod='wallee' js=1}";
		var wallee_msg_general_title_error = "{l s='Error'  mod='wallee' js=1}";
		var wallee_btn_info_confirm_txt = "{l s='OK'  mod='wallee' js=1}";
	</script>
	
	<div id="wallee_void_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also void the following orders:' mod='wallee' js=1}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='wallee' js=1}
						</a>
					</li>
				{/foreach}
			</ul>
			{l s='If you only want to void this order, we recommend to remove all products from this order.' mod='wallee' js=1}
		{else}
			{l s='This action cannot be undone.' mod='wallee' js=1}
		{/if}
	</div>
	
	<div id="wallee_completion_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also complete the following orders:' mod='wallee'}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
								{l s='Order %d' sprintf=$other mod='wallee'}
						</a>
					</li>
				{/foreach}
			</ul>
		{else}
			{l s='This finalizes the order, it no longer can be changed.' mod='wallee'}			
		{/if}		
	</div>
{/if}
  
{if (isset($showUpdateActions) && $showUpdateActions)}
<div style="display:none;" class="hidden-print">
	<a class="btn btn-default wallee-management-btn" id="wallee_update">
		<i class="icon-refresh"></i>
		{l s='Update' mod='wallee'}
	</a>
</div>
{/if}


{if isset($isWalleeTransaction)}
<div style="display:none;" class="hidden-print" id="wallee_is_transaction"></div>
{/if}

{if isset($editButtons)}
<div style="display:none;" class="hidden-print" id="wallee_remove_edit"></div>
{/if}

{if isset($cancelButtons)}
<div style="display:none;" class="hidden-print" id="wallee_remove_cancel"></div>
{/if}

{if isset($refundChanges)}
<div style="display:none;" class="hidden-print" id="wallee_changes_refund">
<p id="wallee_refund_online_text_total">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_offline_text_total" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_no_text_total" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_offline_span_total" class="checkbox" style="display: none;">
	<label for="wallee_refund_offline_cb_total">
		<input type="checkbox" id="wallee_refund_offline_cb_total" name="wallee_offline">
		{l s='Send as offline refund to %s.' sprintf='wallee' mod='wallee'}
	</label>
</p>

<p id="wallee_refund_online_text_partial">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_offline_text_partial" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_no_text_partial" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='wallee' mod='wallee'}</p>
<p id="wallee_refund_offline_span_partial" class="checkbox" style="display: none;">
	<label for="wallee_refund_offline_cb_partial">
		<input type="checkbox" id="wallee_refund_offline_cb_partial" name="wallee_offline">
		{l s='Send as offline refund to %s.' sprintf='wallee' mod='wallee'}
	</label>
</p>
</div>
{/if}

{if isset($completionPending)}
<div style="display:none;" class="hidden-print" id="wallee_completion_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Completion in Process' mod='wallee'}
	</span>
</div>
{/if}

{if isset($voidPending)}
<div style="display:none;" class="hidden-print" id="wallee_void_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Void in Process' mod='wallee'}
	</span>

</div>
{/if}

{if isset($refundPending)}
<div style="display:none;" class="hidden-print" id="wallee_refund_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Refund in Process' mod='wallee'}
	</span>
</div>
{/if}


<script type="text/javascript">
	var isVersionGTE177 = true;
{if isset($voidUrl)}
	var walleeVoidUrl = "{$voidUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($completionUrl)}
	var walleeCompletionUrl = "{$completionUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($updateUrl)}
	var walleeUpdateUrl = "{$updateUrl|escape:'javascript':'UTF-8'}";
{/if}

</script>