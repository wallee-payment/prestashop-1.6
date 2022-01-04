/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {
    
    function moveWalleeManualTasks()
    {
        $("#wallee_notifications").find("li").each(function (key, element) {
            $("#header_notifs_icon_wrapper").append(element);
        });
    }
    moveWalleeManualTasks();
    
});