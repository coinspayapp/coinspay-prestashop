// The CoinsPay settings screen: copy buttons, "Check connection", "Refresh" of an invoice.
// Plain jQuery: the back office of every PrestaShop version from 1.6 to 9 has it.
(function ($) {
	$(function () {
		var root = $('#coinspay-settings');
		if (!root.length) {
			return;
		}

		root.on('click', '.coinspay-copy', function () {
			var button = $(this);
			var input = $('#' + button.data('target'));
			input.focus().select();
			try {
				document.execCommand('copy');
			} catch (e) {}
			var label = button.text();
			button.text(root.data('copied'));
			setTimeout(function () {
				button.text(label);
			}, 1500);
		});

		$('#coinspay-check').on('click', function () {
			var out = $('#coinspay-check-result').removeClass('text-danger text-success').text(root.data('checking'));
			$.ajax({
				url: root.data('check-url'),
				type: 'post',
				dataType: 'json',
				data: {api_url: $('#coinspay-api-url').val(), api_key: $('#coinspay-api-key').val()}
			}).done(function (json) {
				out.empty().addClass(json.ok ? 'text-success' : 'text-danger');
				$.each(json.lines || [], function (i, line) {
					out.append($('<div>').text(line));
				});
			}).fail(function () {
				out.addClass('text-danger').text(root.data('check-failed'));
			});
		});

		root.on('click', '.coinspay-refresh', function () {
			var button = $(this);
			var row = button.closest('tr');
			var result = row.find('.coinspay-refresh-result').removeClass('text-danger text-success').text('');
			button.prop('disabled', true);
			$.ajax({
				url: root.data('refresh-url'),
				type: 'post',
				dataType: 'json',
				data: {invoice_id: button.data('invoice')}
			}).done(function (json) {
				result.addClass(json.ok ? 'text-success' : 'text-danger').text(json.message || '');
			}).fail(function () {
				result.addClass('text-danger').text(root.data('refresh-failed'));
			}).always(function () {
				button.prop('disabled', false);
			});
		});
	});
})(jQuery);
