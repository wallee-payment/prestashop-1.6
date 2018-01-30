<div id="walleeTransactionInfo" class="panel">
	<div class="panel-heading">
		<i class="icon-rocket"></i>
		{l s="Wallee Transaction Information" mod="wallee_payment"}
	</div>
	<div class="wallee-transaction-data-column-container">
		<div class="wallee-transaction-column">
			<p>
				<strong>{l s="General Details" mod="wallee_payment"}</strong>
			</p>
			<dl class="well list-detail">
				<dt>{l s="Payment Method" mod="wallee_payment"}</dt>
				<dd>{$configurationName}
			{if !empty($methodImage)} 
			 	<br /><img
						src="{$methodImage|escape:'html'}"
						width="50" />
			{/if}
				</dd>
				<dt>{l s="Transaction State" mod="wallee_payment"}</dt>
				<dd>{$transactionState}</dd>
			{if !empty($failureReason)} 
            	<dt>{l s="Failure Reason" mod="wallee_payment"}</dt>
				<dd>{$failureReason}</dd>
			{/if}
        		<dt>{l s="Authorization Amount" mod="wallee_payment"}</dt>
				<dd>{displayPrice price=$authorizationAmount}</dd>
				<dt>{l s="Transaction" mod="wallee_payment"}</dt>
				<dd>
					<a href="{$transactionUrl|escape:'html'}" target="_blank">
						{l s="View in Wallee" mod="wallee_payment"}
					</a>
				</dd>
			</dl>
		</div>
		{if !empty($labelsByGroup)}
			{foreach from=$labelsByGroup item=group}
			<div class="wallee-transaction-column">
				<div class="wallee-payment-label-container" id="wallee-payment-label-container-{$group.id}">
					<p class="wallee-payment-label-group">
						<strong>
						{$group.translatedTitle}
						</strong>
					</p>
					<dl class="well list-detail">
						{foreach from=$group.labels item=label}
	                		<dt>{$label.translatedName}</dt>
							<dd>{$label.value}</dd>
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
					{l s="Wallee Completions" mod="wallee_payment"}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_completion_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Completion Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="wallee_payment"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$completions item=completion}
						<tr>
							<td>{$completion->getId()}</td>
							<td>{if ($completion->getCompletionId() != 0)}
									{$completion->getCompletionId()}
								{else}
									{l s="Not available" mod="wallee_payment"}
								{/if}	
							</td>
							<td>{$completion->getState()}</td>
							<td>{if !empty($completion->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$completion->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="wallee_payment"}
								{/if}
							</td>
							<td>
								{if ($completion->getCompletionId() != 0)}
									{assign var='completionUrl' value="{wallee_completion_url completion=$completion}"}
									<a href="{$completionUrl|escape:'html'}" target="_blank">
										{l s="View in Wallee" mod="wallee_payment"}
									</a>
								{else}
									{l s="Not available" mod="wallee_payment"}
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
					{l s="Wallee Voids" mod="wallee_payment"}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_void_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Void Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="wallee_payment"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$voids item=voidItem}
						<tr>
							<td>{$voidItem->getId()}</td>
							<td>{if ($voidItem->getVoidId() != 0)}
									{$voidItem->getVoidId()}
								{else}
									{l s="Not available" mod="wallee_payment"}
								{/if}		
							</td>
							<td>{$voidItem->getState()}</td>
							<td>{if !empty($voidItem->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$voidItem->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="wallee_payment"}
								{/if}
							</td>
							<td>
								{if ($voidItem->getVoidId() != 0)}
									{assign var='voidUrl' value="{wallee_void_url void=$voidItem}"}
									<a href="{$voidUrl|escape:'html'}" target="_blank">
										{l s="View in Wallee" mod="wallee_payment"}
									</a>
								{else}
									{l s="Not available" mod="wallee_payment"}
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
					{l s="Wallee Refunds" mod="wallee_payment"}
			</div>
			<div class="table-responsive">
				<table class="table" id="wallee_refund_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="wallee_payment"}</span>
							</th>
							
							<th>
								<span class="title_box ">{l s="External Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Refund Id" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Amount" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Type" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="wallee_payment"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="wallee_payment"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$refunds item=refund}
						<tr>
							<td>{$refund->getId()}</td>
							<td>{$refund->getExternalId()}</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{$refund->getRefundId()}
								{else}
									{l s="Not available" mod="wallee_payment"}
								{/if}	
							</td>
							<td>
								{assign var='refundAmount' value="{wallee_refund_amount refund=$refund}"}
								{displayPrice price=$refundAmount currency=$currency->id}
							</td>
							<td>
								{assign var='refundType' value="{wallee_refund_type refund=$refund}"}
								{$refundType}
							</td>
							<td>{$refund->getState()}</td>
							<td>{if !empty($refund->getFailureReason())}
									{assign var='failureReason' value="{wallee_translate text=$refund->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="wallee_payment"}
								{/if}
							</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{assign var='refundURl' value="{wallee_refund_url refund=$refund}"}
									<a href="{$refundURl|escape:'html'}" target="_blank">
										{l s="View in Wallee" mod="wallee_payment"}
									</a>
								{else}
									{l s="Not available" mod="wallee_payment"}
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