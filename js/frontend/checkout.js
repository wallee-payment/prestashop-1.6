/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function($) {

    var wallee_checkout = {
	    
	handlerCounter: 0, 
	iframe_handler: null,
	configuration_id: null,
	height: null,

	init : function() {
		this.handlerCounter = 0;
	    var configuration = $("#wallee-method-configuration");
	    this.configuration_id = configuration.data("configuration-id");
	    this.register_method();
	    this.register_button_handler();
	},

	register_method : function() {
	    var self = this;
	    if (typeof window.IframeCheckoutHandler === 'undefined') {
	    	if(this.handlerCounter > 20){
	    		$('.wallee-loader').remove();
	    		this.enable_pay_button();	    		
	    		return;
	    	}
	    	this.handlerCounter++;
			setTimeout(function() {
				self.register_method();
			}, 100);
			return;
	    }
	    
	    if (this.iframe_handler != null) {
	    	return;
	    }	 
	    this.iframe_handler = window.IframeCheckoutHandler(this.configuration_id);
	    this.iframe_handler.setValidationCallback(function(validation_result) {
			self.process_validation(validation_result);
	    	});
	    this.iframe_handler.setInitializeCallback(function(){
			$('#wallee-iframe-possible').remove();
			$('.wallee-loader').remove();
			self.enable_pay_button();
	    });
	    this.iframe_handler.create("wallee-method-container");
	},
	
	register_button_handler : function(){
	    $("#wallee-submit").off("click.wallee").on("click.wallee", {
			self : this
	    	}, this.handler_submit);
	},
	
	handler_submit : function(event) {
	    var self = event.data.self;
	    self.disable_pay_button();
	    
	    var tosInput = $("#cgv");
	    if(tosInput.size() > 0){
			if(!tosInput.is(':checked')){
			    self.remove_existing_errors();
			    self.show_new_errors(wallee_msg_tos_error);
			    self.enable_pay_button();
			    return false;
			}
	    }
	    if(self.iframe_handler == null){
	    	self.process_validation({success: true});
	    	return false;
	    }
	    self.iframe_handler.validate();
	    return false;
	},

	
	process_validation : function(validation_result) {
	    var self = this;
	    if (validation_result.success) {
			var form = $("#wallee-payment-form");		
			$.ajax({
				type:		'POST',
				dataType: 	"json",
				url: 		form.attr("action"),
				data: 		form.serialize(),
				success: 	function(response, textStatus, jqXHR) {
					if ( response.result == 'success' ) {
					    	self.iframe_handler.submit();
					    	return;
					}
					else if(response.result =='redirect'){
						location.replace(response.redirect+"&paymentMethodConfigurationId="+self.configuration_id);
						return;
					}
					else if ( response.result == 'failure' ) {
					    if(response.reload == 'true' ){
							location.reload();
							self.enable_pay_button();
							return;
					    }
					    else if(response.redirect) {
					    	location.replace(response.redirect);
					    	return;
					    }
					}
					self.remove_existing_errors();
					self.show_new_errors(wallee_msg_json_error);
					self.enable_pay_button();
				},
				error: 		function(jqXHR, textStatus, errorThrown){
				    self.remove_existing_errors();
				    self.show_new_errors(wallee_msg_json_error);
				    self.enable_pay_button();
				},
			});
	    }
	    else {
			if (validation_result.errors) {
			    this.remove_existing_errors();
			    this.show_new_errors(this.format_error_messages(validation_result.errors));
			}
		
			this.enable_pay_button();
	    }
	},
	
	disable_pay_button : function(){
	    $("#wallee-submit").prop('disabled', true);
	},
	
	enable_pay_button : function(){
	    $("#wallee-submit").prop('disabled', false);
	},
	
	remove_existing_errors : function(){
	    $("#wallee-error-messages").empty();
	    $("#wallee-error-messages").removeClass("alert alert-danger");
	},
	
	show_new_errors : function(message){
	    $("#wallee-error-messages").addClass("alert alert-danger");
	    $("#wallee-error-messages").append("<div>"+message+"</div>");
	    $('html, body').animate({
	    	scrollTop : ($("#wallee-error-messages").offset().top - 20)
		}, 1000);
    
	},

	format_error_messages : function(messages) {
	    var formatted_message;
	    if (typeof messages == 'object') {
		formatted_message = messages.join("\n");
	    } else if (messages.constructor === Array) {
		formatted_message = messages.join("\n").stripTags().toString();
	    } else {
		formatted_message = messages
	    }
	    return formatted_message;
	}
    }
    wallee_checkout.init();
    
});