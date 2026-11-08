/**
 * Quick Instant Checkout — widget interactions.
 * Selection -> live summary/total/CTA updates; CTA -> opens the Order Portal modal.
 */
(function () {
	'use strict';

	function fmt(num) {
		return Number(num).toFixed(2);
	}

	function widgetOf(el) {
		return el.closest('.qic-widget');
	}

	/** Re-render summary + CTA from the currently checked tier. */
	function refresh(widget) {
		var input = widget.querySelector('.qic-tier-radio:checked');
		if (!input) { return; }

		var price = parseFloat(input.getAttribute('data-price'));
		var accounts = parseInt(input.getAttribute('data-accounts'), 10);

		widget.querySelectorAll('.qic-tier').forEach(function (row) {
			row.classList.remove('is-selected');
		});
		input.closest('.qic-tier').classList.add('is-selected');

		var tierLine = widget.querySelector('.qic-summary-tier');
		if (tierLine) {
			tierLine.textContent = accounts + ' x ' + (widget.getAttribute('data-product-name') || '');
		}

		var total = widget.querySelector('.qic-summary-total');
		if (total) {
			total.textContent = qicData.currencySymbol + fmt(price) + ' ' + qicData.currencyCode;
		}

		widget.querySelectorAll('.qic-cta').forEach(function (cta) {
			var tpl = cta.getAttribute('data-template') || 'Proceed to Payment ({price})';
			var label = cta.querySelector('.qic-cta-label');
			if (label) {
				label.textContent = tpl.replace('{price}', qicData.currencySymbol + ' ' + fmt(price));
			}
		});
	}

	document.addEventListener('change', function (e) {
		if (e.target.classList.contains('qic-tier-radio')) {
			refresh(widgetOf(e.target));
		}
	});

	// CTA opens the multistep Order Portal, preselected to this product + tier.
	document.addEventListener('click', function (e) {
		var button = e.target.closest('.qic-cta');
		if (!button || !window.qicData || !window.QICPortal) { return; }
		var widget = widgetOf(button);
		if (!widget) { return; }
		var input = widget.querySelector('.qic-tier-radio:checked');
		window.QICPortal.open(widget.getAttribute('data-product-id'), input ? input.value : 0);
	});

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.qic-widget').forEach(refresh);
	});
})();
