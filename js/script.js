jQuery(function($) {
	$(".wccf_delete_field").click(function(e) {
		e.preventDefault();
		$(this).closest('tr').fadeOut('slow', function() {
			$(this).remove();
		})
	})
})