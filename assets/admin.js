(function () {
	'use strict';

	function getConfig() {
		return window.WPPostDateRefresh || {};
	}

	function setMessage(box, message, type) {
		var messageEl = box.querySelector('.quick-date-refresh-message');

		if (!messageEl) {
			return;
		}

		messageEl.textContent = message;
		messageEl.style.marginTop = '8px';
		messageEl.style.color = type === 'success' ? '#008a20' : '#b32d2e';
	}

	function updateVisibleDate(displayDate) {
		var classicTimestamp = document.querySelector('#timestamp b');

		if (classicTimestamp && displayDate) {
			classicTimestamp.textContent = displayDate;
		}
	}

	function parseResponse(response) {
		return response.json().catch(function () {
			return {
				success: false,
				data: {
					message: getConfig().i18n && getConfig().i18n.error
						? getConfig().i18n.error
						: 'Unable to update the date. Please try again.',
				},
			};
		});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.quick-date-refresh-button');

		if (!button) {
			return;
		}

		var config = getConfig();
		var box = button.closest('.quick-date-refresh-box');

		if (!box || !config.ajaxUrl || !config.action) {
			return;
		}

		var hoursInput = box.querySelector('#quick-date-refresh-hours');
		var nonceInput = box.querySelector('input[name="quick_date_refresh_nonce"]') ||
			document.querySelector('input[name="quick_date_refresh_nonce"]');
		var postId = button.getAttribute('data-post-id');
		var originalText = button.textContent;
		var formData = new window.FormData();

		formData.append('action', config.action);
		formData.append('post_id', postId || '');
		formData.append('hours_offset', hoursInput ? hoursInput.value : '0');
		formData.append('nonce', nonceInput ? nonceInput.value : '');

		button.disabled = true;
		button.textContent = config.i18n && config.i18n.updating ? config.i18n.updating : 'Updating date...';
		setMessage(box, '', 'success');

		window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(parseResponse)
			.then(function (result) {
				var data = result && result.data ? result.data : {};
				var message = data.message || (
					config.i18n && config.i18n.error
						? config.i18n.error
						: 'Unable to update the date. Please try again.'
				);

				if (result && result.success) {
					setMessage(box, message, 'success');
					updateVisibleDate(data.displayDate);
					return;
				}

				setMessage(box, message, 'error');
			})
			.catch(function () {
				var message = config.i18n && config.i18n.error
					? config.i18n.error
					: 'Unable to update the date. Please try again.';

				setMessage(box, message, 'error');
			})
			.finally(function () {
				button.disabled = false;
				button.textContent = originalText;
			});
	});
}());
