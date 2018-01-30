

{if (isset($showAuthorizedActions) && $showAuthorizedActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-default wallee-management-btn"  id="wallee_void">
			<i class="icon-remove"></i>
			{l s='Void' mod='wallee_payment'}
		</a>
		<a class="btn btn-default wallee-management-btn"  id="wallee_completion">
			<i class="icon-check"></i>
			{l s='Completion' mod='wallee_payment'}
		</a>	
	</div>
	
	{addJsDefL name=wallee_void_title}{l s='Are you sure?' mod='wallee_payment' js=1}{/addJsDefL}
	{addJsDefL name=wallee_void_btn_confirm_txt}{l s='Void Order'  mod='wallee_payment' js=1}{/addJsDefL}
	{addJsDefL name=wallee_void_btn_deny_txt}{l s='No' mod='wallee_payment' js=1}{/addJsDefL}
	<div id="wallee_void_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also void the following orders:' mod='wallee_payment' js=1}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='wallee_payment' js=1}
						</a>
					</li>
				{/foreach}
			</ul>
			{l s='If you only want to void this order, we recommend to remove all products from this order.' mod='wallee_payment' js=1}
		{else}
			{l s='This action cannot be undone.' mod='wallee_payment' js=1}
		{/if}
	</div>
	
	{addJsDefL name=wallee_completion_title}{l s='Are you sure?' mod='wallee_payment' js=1}{/addJsDefL}
	{addJsDefL name=wallee_completion_btn_confirm_txt}{l s='Complete Order'  mod='wallee_payment' js=1}{/addJsDefL}
	{addJsDefL name=wallee_completion_btn_deny_txt}{l s='No' mod='wallee_payment' js=1}{/addJsDefL}
	<div id="wallee_completion_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also complete the following orders:' mod='wallee_payment'}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
								{l s='Order %d' sprintf=$other mod='wallee_payment'}
						</a>
					</li>
				{/foreach}
			</ul>
		{else}
			{l s='This finalizes the order, it no longer can be changed.' mod='wallee_payment'}			
		{/if}		
	</div>
{/if}
  
{if (isset($showUpdateActions) && $showUpdateActions)}
<div style="display:none;" class="hidden-print">
	<a class="btn btn-default wallee-management-btn" id="wallee_update">
		<i class="icon-refresh"></i>
		{l s='Update' mod='wallee_payment'}
	</a>
</div>
{/if}


{addJsDefL name=wallee_msg_general_error}{l s='The server experienced an unexpected error, please try again.'  mod='wallee_payment' js=1}{/addJsDefL}
{addJsDefL name=wallee_msg_general_title_succes}{l s='Success'  mod='wallee_payment' js=1}{/addJsDefL}
{addJsDefL name=wallee_msg_general_title_error}{l s='Error'  mod='wallee_payment' js=1}{/addJsDefL}
{addJsDefL name=wallee_btn_info_confirm_txt}{l s='OK'  mod='wallee_payment' js=1}{/addJsDefL}

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
<p id="wallee_refund_online_text_total">{l s='This refund is sent to wallee and money is transfered back to the customer.' mod='wallee_payment'}</p>
<p id="wallee_refund_offline_text_total" style="display:none;">{l s='This refund is sent to wallee, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] mod='wallee_payment'}</p>
<p id="wallee_refund_no_text_total" style="display:none;">{l s='This refund is [1]not[/1] sent to wallee.' tags=['<b>'] mod='wallee_payment'}</p>
<p id="wallee_refund_offline_span_total" class="checkbox" style="display: none;">
	<label for="wallee_refund_offline_cb_total">
		<input type="checkbox" id="wallee_refund_offline_cb_total" name="wallee_offline">
		{l s='Send as offline refund to wallee.' mod='wallee_payment'}
	</label>
</p>

<p id="wallee_refund_online_text_partial">{l s='This refund is sent to wallee and money is transfered back to the customer.' mod='wallee_payment'}</p>
<p id="wallee_refund_offline_text_partial" style="display:none;">{l s='This refund is sent to wallee, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] mod='wallee_payment'}</p>
<p id="wallee_refund_no_text_partial" style="display:none;">{l s='This refund is [1]not[/1] sent to wallee.' tags=['<b>'] mod='wallee_payment'}</p>
<p id="wallee_refund_offline_span_partial" class="checkbox" style="display: none;">
	<label for="wallee_refund_offline_cb_partial">
		<input type="checkbox" id="wallee_refund_offline_cb_partial" name="wallee_offline">
		{l s='Send as offline refund to wallee.' mod='wallee_payment'}
	</label>
</p>
</div>
{/if}

{if isset($completionPending)}
<div style="display:none;" class="hidden-print" id="wallee_completion_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Completion in Process' mod='wallee_payment'}
	</span>
</div>
{/if}

{if isset($voidPending)}
<div style="display:none;" class="hidden-print" id="wallee_void_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Void in Process' mod='wallee_payment'}
	</span>

</div>
{/if}

{if isset($refundPending)}
<div style="display:none;" class="hidden-print" id="wallee_refund_pending">
	<span class="span label label-inactive wallee-management-info">
		<i class="icon-refresh"></i>
		{l s='Refund in Process' mod='wallee_payment'}
	</span>
</div>
{/if}


<script type="text/javascript">
{if isset($voidUrl)}
	var walleeVoidUrl = "{$voidUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($voidUrl)}
	var walleeCompletionUrl = "{$completionUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($updateUrl)}
	var walleeUpdateUrl = "{$updateUrl|escape:'javascript':'UTF-8'}";
{/if}

</script>