{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div id="walleeTransactionInfo" class="card">
	<div class="card-header">
		<i class="icon-rocket"></i>
		wallee {l s='Transaction Information' mod='wallee'}
	</div>
	<div class="card-body">
	<div class="wallee-transaction-data-column-container">
		<div class="wallee-transaction-column">
			<p>
				<strong>{l s='General Details' mod='wallee'}</strong>
			</p>
			<dl class="well list-detail">
				<dt>{l s='Payment Method' mod='wallee'}</dt>
				<dd>{$configurationName|escape:'html':'UTF-8'}
			{if !empty($methodImage)} 
			 	<br /><img
						src="{$methodImage|escape:'html'}"
						width="50" />
			{/if}
				</dd>
				<dt>{l s='Transaction State' mod='wallee'}</dt>
				<dd>{$transactionState|escape:'html':'UTF-8'}</dd>
			{if !empty($failureReason)} 
            	<dt>{l s='Failure Reason' mod='wallee'}</dt>
				<dd>{$failureReason|escape:'html':'UTF-8'}</dd>
			{/if}
        		<dt>{l s='Authorization Amount' mod='wallee'}</dt>
				<dd>{displayPrice price=$authorizationAmount}</dd>
				<dt>{l s='Transaction' mod='wallee'}</dt>
				<dd>
					<a href="{$transactionUrl|escape:'html'}" target="_blank">
						{l s='View' mod='wallee'}
					</a>
				</dd>
			</dl>
		</div>
		{if !empty($labelsByGroup)}
			{foreach from=$labelsByGroup item=group}
			<div class="wallee-transaction-column">
				<div class="wallee-payment-label-container" id="wallee-payment-label-container-{$group.id|escape:'html':'UTF-8'}">
					<p class="wallee-payment-label-group">
						<strong>
						{$group.translatedTitle|escape:'html':'UTF-8'}
						</strong>
					</p>
					<dl class="well list-detail">
						{foreach from=$group.labels item=label}
	                		<dt>{$label.translatedName|escape:'html':'UTF-8'}</dt>
							<dd>{$label.value|escape:'html':'UTF-8'}</dd>
						{/foreach}
					</dl>
				</div>
			</div>
			{/foreach}
		{/if}
	</div>
	{if !empty($completions)}
		<div class="wallee-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-check"></i>
					wallee {l s='Completions' mod='wallee'}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_completion_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Completion Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='wallee'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$completions item=completion}
						<tr>
							<td>{$completion->getId()|escape:'html':'UTF-8'}</td>
							<td>{if ($completion->getCompletionId() != 0)}
									{$completion->getCompletionId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='wallee'}
								{/if}	
							</td>
							<td>{$completion->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($completion->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$completion->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='wallee'}
								{/if}
							</td>
							<td>
								{if ($completion->getCompletionId() != 0)}
									{assign var='completionUrl' value="{wallee_completion_url completion=$completion}"}
									<a href="{$completionUrl|escape:'html'}" target="_blank">
										{l s='View' mod='wallee'}
									</a>
								{else}
									{l s='Not available' mod='wallee'}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($void)}
		<div class="wallee-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-remove"></i>
					wallee {l s='Voids' mod='wallee'}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_void_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Void Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='wallee'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$voids item=voidItem}
						<tr>
							<td>{$voidItem->getId()|escape:'html':'UTF-8'}</td>
							<td>{if ($voidItem->getVoidId() != 0)}
									{$voidItem->getVoidId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='wallee'}
								{/if}		
							</td>
							<td>{$voidItem->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($voidItem->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$voidItem->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='wallee'}
								{/if}
							</td>
							<td>
								{if ($voidItem->getVoidId() != 0)}
									{assign var='voidUrl' value="{wallee_void_url void=$voidItem}"}
									<a href="{$voidUrl|escape:'html'}" target="_blank">
										{l s='View' mod='wallee'}
									</a>
								{else}
									{l s='Not available' mod='wallee'}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($refunds)}
		<div class="wallee-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-exchange"></i>
					wallee {l s='Refunds' mod='wallee'}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_refund_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s='Job Id' mod='wallee'}</span>
							</th>
							
							<th>
								<span class="title_box ">{l s='External Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Refund Id' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Amount' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Type' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Status' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Error Message' mod='wallee'}</span>
							</th>
							<th>
								<span class="title_box ">{l s='Links' mod='wallee'}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$refunds item=refund}
						<tr>
							<td>{$refund->getId()|escape:'html':'UTF-8'}</td>
							<td>{$refund->getExternalId()|escape:'html':'UTF-8'}</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{$refund->getRefundId()|escape:'html':'UTF-8'}
								{else}
									{l s='Not available' mod='wallee'}
								{/if}	
							</td>
							<td>
								{assign var='refundAmount' value="{wallee_refund_amount refund=$refund}"}
								{displayPrice price=$refundAmount currency=$currency->id}
							</td>
							<td>
								{assign var='refundType' value="{wallee_refund_type refund=$refund}"}
								{$refundType|escape:'html':'UTF-8'}
							</td>
							<td>{$refund->getState()|escape:'html':'UTF-8'}</td>
							<td>{if !empty($refund->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$refund->getFailureReason()}"}
									{$failureReason|escape:'html':'UTF-8'}
								{else}
									{l s='(None)' mod='wallee'}
								{/if}
							</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{assign var='refundURl' value="{wallee_refund_url refund=$refund}"}
									<a href="{$refundURl|escape:'html'}" target="_blank">
										{l s='View' mod='wallee'}
									</a>
								{else}
									{l s='Not available' mod='wallee'}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
	</div>	

</div>