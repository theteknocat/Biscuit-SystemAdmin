var LogViewer = {
	init: function() {
		// Hide all but first tab contents
		$('.debug-content:not(:first)').hide();
		// Select the first tab
		$('.debug-button:first').addClass('selected');
		$('#debug-console .debug-content-container:not(:first)').hide();
		$('#debug-error .debug-content-container:not(:first)').hide();
		$('#debug-db-queries .debug-content-container:not(:first)').hide();
		$('#debug-events .debug-content-container:not(:first)').hide();
		$('#debug-var-dump .debug-content-container:not(:first)').hide();

		$('#debug-console .debug-header:not(:first)').hide();
		$('#debug-error .debug-header:not(:first)').hide();
		$('#debug-db-queries .debug-header:not(:first)').hide();
		$('#debug-events .debug-header:not(:first)').hide();
		$('#debug-var-dump .debug-header:not(:first)').hide();

		$('.debug-button').click(function() {
			$('.debug-button').removeClass('selected');
			$(this).addClass('selected');
			$('.debug-content').hide();
			var section_name = this.id.substr(7);	// Everything after 'button-'
			$('#'+section_name).show();
			return false;
		});

		$('.debug-flip-button').click(function() {
			var my_id = this.id.substr(18);		// Everything after "debug-flip-button-"
			var id_bits = my_id.match(/([a-z\-]+)-([0-9]+)/);
			var log_type = id_bits[1];
			var page_num = id_bits[2];
			var header_id = '#debug-'+log_type+'-header-'+page_num;
			$(this).parent().parent().hide().next().hide();
			$(header_id).show().next().show();
			return false;
		});
	},
}

$(document).ready(function() {
	LogViewer.init();
});
