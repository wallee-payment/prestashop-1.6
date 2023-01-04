{*
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div class="tab-pane" id="wallee_documents">
<h4 class="visible-print">wallee {l s='Documents' mod='wallee'} <span class="badge">({$walleeDocumentsCount|escape:'html':'UTF-8'})</span></h4>

	<div class="table-responsive">
		<table class="table" id="wallee_documents_table">
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
								{l s='There is no document availabe yet.' mod='wallee'}
							</div>
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>

</div>
