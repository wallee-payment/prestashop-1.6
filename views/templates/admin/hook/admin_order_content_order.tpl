<div class="tab-pane" id="wallee_documents">
<h4 class="visible-print">{l s='Wallee Documents' mod='wallee_payment'} <span class="badge">({$walleeDocuments|@count})</span></h4>

	<div class="table-responsive">
		<table class="table" id="walle_documents_table">
			<tbody>
				{foreach from=$walleeDocuments item=document}
					<tr>
						<td><a class="_blank" href="{$document.url|escape:'html':'UTF-8'}"><i class="icon-{$document.icon} wallee-document"></i><span>{$document.name}<pan></a>
						</td>
					</tr>
				{foreachelse}
					<tr>
						<td colspan="1" class="list-empty">
							<div class="list-empty-msg">
								<i class="icon-warning-sign list-empty-icon"></i>
								{l s='There is no document availabe yet.' mod='wallee_payment'}
							</div>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>

</div>
