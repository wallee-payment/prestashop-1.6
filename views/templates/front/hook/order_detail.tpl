<div id="wallee_documents" style="display:none">
{if !empty($walleeInvoice)}
	<p class="wallee-document">
		<i class="icon-file-text-o"></i>
		<a target="_blank" href="{$walleeInvoice|escape:'html':'UTF-8'}">{l s='Download your wallee invoice as a PDF file.'}</a>
	</p>
{/if}
{if !empty($walleePackingSlip)}
	<p class="wallee-document">
		<i class="icon-truck"></i>
		<a target="_blank" href="{$walleePackingSlip|escape:'html':'UTF-8'}">{l s='Download your wallee packing slip as a PDF file.'}</a>
	</p>
{/if}
</div>
<script type="text/javascript">

jQuery(function($) {    
    $('#wallee_documents').find('p.wallee-document').each(function(key, element){
	
		$(".info-order.box").append(element);
    });
});

</script>