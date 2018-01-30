jQuery(function($) {
    
    function moveManualTasks(){
    	$("#wallee_notifications").find("li").each(function(key, element){
		$("#header_notifs_icon_wrapper").append(element);
    	});
    }
    moveManualTasks();
    
});