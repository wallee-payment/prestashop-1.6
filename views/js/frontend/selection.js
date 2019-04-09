/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function($) {
	
	var targetNodes         = $("#HOOK_PAYMENT");
	var MutationObserver    = window.MutationObserver || window.WebKitMutationObserver;
	var myObserver          = new MutationObserver (mutationHandler);
	var obsConfig           = { childList: true };

	//--- Add a target node to the observer. Can only add one node at a time.
	targetNodes.each ( function () {
	    myObserver.observe (this, obsConfig);
	} );

	function mutationHandler (mutationRecords) {
	    console.info ("mutationHandler:");

	    mutationRecords.forEach ( function (mutation) {
	    	$('#HOOK_PAYMENT div.wallee-method a:not(.wallee)').off('click.wallee').on('click.wallee', function(event){event.stopPropagation()});
	    });
	}	
	$('#HOOK_PAYMENT div.wallee-method a:not(.wallee)').off('click.wallee').on('click.wallee', function(event){event.stopPropagation()});
});