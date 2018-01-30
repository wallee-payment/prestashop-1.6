<div id="wallee_notifications" style="display:none";>
	<li id="wallee_manual_notifs" class="dropdown" data-type="wallee_manual_messages">	
		<a href="javascript:void(0);" class="dropdown-toggle notifs" data-toggle="dropdown">
			<i class="icon-bullhorn"></i>
				<span id="wallee_manual_messages_notif_number_wrapper" class="notifs_badge">
					<span id="wallee_manual_messages_notif_value">{$manualTotal}</span>
				</span>
		</a>
		<div class="dropdown-menu notifs_dropdown">
			<section id="wallee_manual_messages_notif_number_wrapper" class="notifs_panel">
				<div class="notifs_panel_header">
					<h3>Manual Tasks</h3>
				</div>
				<div id="list_wallee_manual_messages_notif" class="list_notif">
					<a href="{$manualUrl|escape:'html':'UTF-8'}" target="_blank">
						<p>{if $manualTotal > 1}
							{l s='There are %s manual tasks that need your attention.' sprintf=$manualTotal mod='wallee_payment'}
						{else}
							{l s='There is a manual task that needs your attention.' mod='wallee_payment'}
						{/if}
						</p>
					</a>
					
				</div>
			</section>
		</div>
	</li>
</div>