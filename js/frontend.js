jQuery(function($) {
	var saveField = function(field) {
		var __this = $(this);
		var _data = __this.closest('p').data();

				if ( 1 !== _data['blockUI.isBlocked'] ) {
					__this.closest('p').block({
						message: "Saving",
						overlayCSS: {
							background: '#ccc',
							opacity: 0.6
						}
					});
				}
		var span = $("<span/>");
		var field_name = __this.attr('name');
		span.attr('id', 'wccf-message-' + field_name);
		var cart_item_key = field_name.split(":")[0];
		console.log(cart_item_key);
		var field_ident = field_name.split(":")[1];
		console.log(field_ident)
		
		$.post(wccfVars.ajaxurl, {'cart_item_key' : cart_item_key, 'field' : field_ident, 'value' : __this.val(), 'security' : wccfVars.security, 'action' : 'wccf_save_field'}, function(response) {
			console.log(response);
			span.text(response.message);
			__this.before(span);
			if (response.changed) {
				__this.val(response.val);
			}
			setTimeout(function() {
				span.fadeOut().remove();
			},1000);
		})
		.done(function() {
			if ( 1 === _data['blockUI.isBlocked'] ) {
					__this.closest('p').unblock();
				}
		});
	}
	$(".additional_fields input").change(saveField);
})