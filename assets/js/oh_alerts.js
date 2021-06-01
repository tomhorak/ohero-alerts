jQuery(document).ready( function($) {

	//WP uses localization as a bit of a hack to create a js object that contains php values
	//The object that was created: alerts_ajax_script

	// alert is present, if logged out users can see the alert check if has been marked read in cookie
	if ($("#notification-area").length && alerts_ajax_script.logged_in === 'no') {
		let alert_id = $('#notification-area #remove-alert').attr('rel');
		if(!$.cookie('alert-' + alert_id)) {
			$('#notification-area').show();
		}
	}

	//click handler
	$(".remove-alert").click( function() {

		let alert_id = $(this).attr('rel');
		let a_user_id =  $(this).attr('data');

		//Use cookies, for not logged in users
		if(alerts_ajax_script.logged_in === 'no') {
			// store a cookie so alert is not shown again
			$.cookie('alert-' + alert_id, 'yes', { expires: 1 });
			$('#notification-area').fadeOut();
		}

		let data = {
			// action: 'oh_mark_alert_as_read',
			action: alerts_ajax_script.action,
			'_ajax_nonce': alerts_ajax_script.nonce,
			'a_user_id': a_user_id,
			alert_read: alert_id
		};

		//let's fire the ajax call now
		//This should hit the OheroAlertsAdmin class static method: oh_mark_alert_as_read
		//TODO: Add some error catching
		jQuery.ajax({
			url: alerts_ajax_script.ajaxurl,
			data:{
				action: alerts_ajax_script.action,
				'_ajax_nonce': alerts_ajax_script.nonce,
				'a_user_id': a_user_id,
				alert_read: alert_id
			},
			dataType: 'JSON',
			method: 'POST',
			success:function(data){
				if(data.success) {
					//dismiss the alert
					$('#notification-area').fadeOut();
				}
				else {
					// error of some sort
					$('#notification-area').html('Sorry, an unknown error has occurred.');
				}
			}
		}); //--> End Ajax

		//prevent default click behavior
		return false;

	});//--> END remove-alert Click Handler

}); //--> END DOC READY