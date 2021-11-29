/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2021 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

    function getOrderIdFromUrl(string) {
        let urlSegment = string.split('wallee')[1];
        return urlSegment.split('/')[1]
    }

    function initialiseDocumentButtons() {
        if ($('.grid-download-wallee-invoice-row-link').length) {
            $('.grid-download-packing-slip-row-link').click(function(e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(wallee_admin_token + "&action=walleePackingSlip&id_order=" + id_order, "_blank");
            });
        
            $('.grid-download-wallee-invoice-row-link').click(function(e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(wallee_admin_token + "&action=walleeInvoice&id_order=" + id_order, "_blank");
            });
        
            $('.grid-download-wallee-invoice-row-link').each(function() {
                let $this = $(this);
                let $row = $this.closest('tr');
                let isWPayment = "0";
                let $paymentStatusCol = $row.find('.column-osname');
                let isWPaymentCol = $row.find('.column-is_w_payment').html();
                if (isWPaymentCol) {
                    isWPayment = isWPaymentCol.trim();
                }
                let paymentStatusText = $paymentStatusCol.find('.btn').text();
                if (!paymentStatusText.includes("Payment accepted") || isWPayment.includes("0")) {
                    $row.find('.grid-download-wallee-invoice-row-link').hide();
                    $row.find('.grid-download-packing-slip-row-link').hide();
                }
            });
        }
    }

    function hideIsWPaymentColumn() {
        $('th').each(function() {
            let $this = $(this);
            if ($this.html().includes("is_w_payment")) {
                $('table tr').find('td:eq(' + $this.index() + '),th:eq(' + $this.index() + ')').remove();
                return false;
            }
        });
    }

    function isVersionGTE177()
    {
        if (_PS_VERSION_ === undefined) {
            return false;
        } else {
            return compareVersions(_PS_VERSION_, "1.7.7");
        }
    }

    function compareVersions (currentVersion, minVersion)
    {
        currentVersion = currentVersion.split('.');
        minVersion = minVersion.split('.');
        // we only care about the 3rd digit of the version as 1.8 will be a whole different kettle of fish
        if (typeof currentVersion[2] === 'undefined') {
            return false;
        }
        return (currentVersion[2] >= minVersion[2]) ? true : false;
    }
    
    function moveWalleeDocuments()
    {
        var documentsTab = $('#wallee_documents_tab');
        if (isVersionGTE177()) {
            documentsTab.children('a').addClass('nav-link');
        } else {
            var parentElement = documentsTab.parent();
            documentsTab.detach().appendTo(parentElement);
        }
    }
    
    function moveWalleeActionsAndInfo()
    {
        var managementBtn = $('a.wallee-management-btn');
        var managementInfo = $('span.wallee-management-info');
        var orderActions = $('div.order-actions');
        var panel = $('div.panel');
        
        managementBtn.each(function (key, element) {
            $(element).detach();
            if (isVersionGTE177()) {
                orderActions.find('.order-navigation').before(element);
            } else {
                panel.find('div.well.hidden-print').find('i.icon-print').closest('div.well').append(element);
            }
        });
        managementInfo.each(function (key, element) {
            $(element).detach();
            if (isVersionGTE177()) {
                orderActions.find('.order-navigation').before(element);
            } else {
                panel.find('div.well.hidden-print').find('i.icon-print').closest('div.well').append(element);
            }
        });
        //to get the styling of prestashop we have to add this
        managementBtn.after("&nbsp;\n");
        managementInfo.after("&nbsp;\n");
    }
    
    function registerWalleeActions()
    {
        $('#wallee_update').off('click.wallee').on(
            'click.wallee',
            updateWallee
        );
        $('#wallee_void').off('click.wallee').on(
            'click.wallee',
            showWalleeVoid
        );
        $("#wallee_completion").off('click.wallee').on(
            'click.wallee',
            showWalleeCompletion
        );
        $('#wallee_completion_submit').off('click.wallee').on(
            'click.wallee',
            executeWalleeCompletion
        );
    }
    
    function showWalleeInformationSuccess(msg)
    {
        showWalleeInformation(msg, wallee_msg_general_title_succes, wallee_btn_info_confirm_txt, 'dark_green', function () {
            window.location.replace(window.location.href);});
    }
    
    function showWalleeInformationFailures(msg)
    {
        showWalleeInformation(msg, wallee_msg_general_title_error, wallee_btn_info_confirm_txt, 'dark_red', function () {
            window.location.replace(window.location.href);});
    }
    
    function showWalleeInformation(msg, title, btnText, theme, callback)
    {
        $.jAlert({
            'type': 'modal',
            'title': title,
            'content': msg,
            'theme': theme,
            'replaceOtherAlerts': true,
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': btnText,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': callback
            }
            ],
            'onClose': callback
        });
    }
    
    function updateWallee()
    {
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    walleeUpdateUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    location.reload();
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWalleeInformation(response.message, msg_wallee_confirm_txt);
                    }
                    return;
                }
                showWalleeInformation(wallee_msg_general_error, msg_wallee_confirm_txt);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWalleeInformation(wallee_msg_general_error, msg_wallee_confirm_txt);
            }
        });
    }
    
        
    function showWalleeVoid(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': wallee_void_title,
            'content': $('#wallee_void_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': wallee_void_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': wallee_void_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick':  executeWalleeVoid

            }
            ],
            'theme':'blue'
        });
        return false;
    }

    function executeWalleeVoid()
    {
        showWalleeSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    walleeVoidUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showWalleeInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWalleeInformationFailures(response.message);
                        return;
                    }
                }
                showWalleeInformationFailures(wallee_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWalleeInformationFailures(wallee_msg_general_error);
            }
        });
        return false;
    }
    
    
    function showWalleeSpinner()
    {
        $.jAlert({
            'type': 'modal',
            'title': false,
            'content': '<div class="wallee-loader"></div>',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'class': 'unnoticeable',
            'replaceOtherAlerts': true
        });
    
    }
    
    function showWalleeCompletion(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': wallee_completion_title,
            'content': $('#wallee_completion_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': wallee_completion_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': wallee_completion_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': executeWalleeCompletion
            }
            ],
            'theme':'blue'
        });

        return false;
    }
    
    
    function executeWalleeCompletion()
    {
        showWalleeSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    walleeCompletionUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showWalleeInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWalleeInformationFailures(response.message);
                        return;
                    }
                }
                showWalleeInformationFailures(wallee_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWalleeInformationFailures(wallee_msg_general_error);
            }
        });
        return false;
    }
    
    function walleeTotalRefundChanges()
    {
        var generateDiscount =  $('.standard_refund_fields').find('#generateDiscount').attr("checked") === 'checked';
        var sendOffline = $('#wallee_refund_offline_cb_total').attr("checked") === 'checked';
        walleeRefundChanges('total', generateDiscount, sendOffline);
    }
    
    function walleePartialRefundChanges()
    {
    
        var generateDiscount = $('.partial_refund_fields').find('#generateDiscountRefund').attr("checked") === 'checked';
        var sendOffline = $('#wallee_refund_offline_cb_partial').attr("checked")  === 'checked';
        walleeRefundChanges('partial', generateDiscount, sendOffline);
    }
    
    function walleeRefundChanges(type, generateDiscount, sendOffline)
    {
        if (generateDiscount) {
            $('#wallee_refund_online_text_'+type).css('display','none');
            $('#wallee_refund_offline_span_'+type).css('display','block');
            if (sendOffline) {
                $('#wallee_refund_offline_text_'+type).css('display','block');
                $('#wallee_refund_no_text_'+type).css('display','none');
            } else {
                $('#wallee_refund_no_text_'+type).css('display','block');
                $('#wallee_refund_offline_text_'+type).css('display','none');
            }
        } else {
            $('#wallee_refund_online_text_'+type).css('display','block');
            $('#wallee_refund_no_text_'+type).css('display','none');
            $('#wallee_refund_offline_text_'+type).css('display','none');
            $('#wallee_refund_offline_span_'+type).css('display','none');
            $('#wallee_refund_offline_cb_'+type).attr('checked', false);
        }
    }
    
    function handleWalleeLayoutChanges()
    {
        var addVoucher = $('#add_voucher');
        var addProduct = $('#add_product');
        var editProductChangeLink = $('.edit_product_change_link');
        var descOrderStandardRefund = $('#desc-order-standard_refund');
        var standardRefundFields = $('.standard_refund_fields');
        var partialRefundFields = $('.partial_refund_fields');
        var descOrderPartialRefund = $('#desc-order-partial_refund');

        if ($('#wallee_is_transaction').length > 0) {
            addVoucher.remove();
        }
        if ($('#wallee_remove_edit').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            $('.panel-vouchers').find('i.icon-minus-sign').closest('a').remove();
        }
        if ($('#wallee_remove_cancel').length > 0) {
            descOrderStandardRefund.remove();
        }
        if ($('#wallee_changes_refund').length > 0) {
            $('#refund_total_3').closest('div').remove();
            standardRefundFields.find('div.form-group').after($('#wallee_refund_online_text_total'));
            standardRefundFields.find('div.form-group').after($('#wallee_refund_offline_text_total'));
            standardRefundFields.find('div.form-group').after($('#wallee_refund_no_text_total'));
            standardRefundFields.find('#spanShippingBack').after($('#wallee_refund_offline_span_total'));
            standardRefundFields.find('#generateDiscount').off('click.wallee').on('click.wallee', walleeTotalRefundChanges);
            $('#wallee_refund_offline_cb_total').on('click.wallee', walleeTotalRefundChanges);
        
            $('#refund_3').closest('div').remove();
            partialRefundFields.find('button').before($('#wallee_refund_online_text_partial'));
            partialRefundFields.find('button').before($('#wallee_refund_offline_text_partial'));
            partialRefundFields.find('button').before($('#wallee_refund_no_text_partial'));
            partialRefundFields.find('#generateDiscountRefund').closest('p').after($('#wallee_refund_offline_span_partial'));
            partialRefundFields.find('#generateDiscountRefund').off('click.wallee').on('click.wallee', walleePartialRefundChanges);
            $('#wallee_refund_offline_cb_partial').on('click.wallee', walleePartialRefundChanges);
        }
        if ($('#wallee_completion_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#wallee_void_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#wallee_refund_pending').length > 0) {
            descOrderStandardRefund.remove();
            descOrderPartialRefund.remove();
        }
        moveWalleeDocuments();
        moveWalleeActionsAndInfo();
    }
    
    function init()
    {
        handleWalleeLayoutChanges();
        registerWalleeActions();
        initialiseDocumentButtons();
        hideIsWPaymentColumn();
    }
    
    init();
});
