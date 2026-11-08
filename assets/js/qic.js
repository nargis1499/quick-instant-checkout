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
			total.textContent = qicData.currencySymbol + fmt(price) + '\u00A0' + qicData.currencyCode;
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

	function getProductIdFromTarget(target) {
		if (!target || typeof target.closest !== 'function') { return null; }

		var el = target.closest(
			'[qc_cart_product_id], [data-qc-cart-product-id], [data-qc_cart_product_id], ' +
			'[data-qc-product-id], [data-qc_product_id], ' +
			'[class*="qc_cart_product_id"], [class*="qc-cart-product-id"], ' +
			'[id*="qc_cart_product_id"], [id*="qc-cart-product-id"], ' +
			'[href*="qc_cart_product_id"], [href*="qc-cart-product-id"]'
		);

		if (!el) {
			var clickable = target.closest('button, a, [role="button"], .btn, .button, input[type="button"], input[type="submit"]');
			if (clickable && clickable.attributes) {
				for (var i = 0; i < clickable.attributes.length; i++) {
					var attrName = clickable.attributes[i].name.toLowerCase();
					if (attrName.indexOf('qc_cart_product_id') !== -1 || attrName.indexOf('qc-cart-product-id') !== -1) {
						var val = parseInt(clickable.attributes[i].value, 10);
						if (!isNaN(val) && val > 0) {
							return { el: clickable, id: val };
						}
						var match = attrName.match(/\d+/);
						if (match) {
							return { el: clickable, id: parseInt(match[0], 10) };
						}
					}
				}
			}
			return null;
		}

		var attrs = [
			'qc_cart_product_id',
			'data-qc-cart-product-id',
			'data-qc_cart_product_id',
			'data-qc-product-id',
			'data-qc_product_id',
			'data-product-id',
			'data-product_id'
		];
		for (var j = 0; j < attrs.length; j++) {
			var a = el.getAttribute(attrs[j]);
			if (a) {
				var n = parseInt(a, 10);
				if (!isNaN(n) && n > 0) {
					return { el: el, id: n };
				}
			}
		}

		var cls = el.className || '';
		if (typeof cls === 'string' && (cls.indexOf('qc_cart_product_id') !== -1 || cls.indexOf('qc-cart-product-id') !== -1)) {
			var mCls = cls.match(/qc[_-]cart[_-]product[_-]id[_-]?(\d+)/i) || cls.match(/\d+/);
			if (mCls) {
				return { el: el, id: parseInt(mCls[1] || mCls[0], 10) };
			}
		}

		var idStr = el.id || '';
		if (idStr.indexOf('qc_cart_product_id') !== -1 || idStr.indexOf('qc-cart-product-id') !== -1) {
			var mId = idStr.match(/qc[_-]cart[_-]product[_-]id[_-]?(\d+)/i) || idStr.match(/\d+/);
			if (mId) {
				return { el: el, id: parseInt(mId[1] || mId[0], 10) };
			}
		}

		var href = el.getAttribute('href') || '';
		if (href.indexOf('qc_cart_product_id') !== -1 || href.indexOf('qc-cart-product-id') !== -1) {
			var mHref = href.match(/qc[_-]cart[_-]product[_-]id[=_-]?(\d+)/i) || href.match(/\d+/);
			if (mHref) {
				return { el: el, id: parseInt(mHref[1] || mHref[0], 10) };
			}
		}

		return null;
	}

	// CTA or universal product button opens the multistep Order Portal.
	document.addEventListener('click', function (e) {
		var matched = getProductIdFromTarget(e.target);
		if (matched && matched.id > 0) {
			e.preventDefault();
			if (window.QICPortal) {
				window.QICPortal.open(matched.id, 0);
			}
			return;
		}

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
