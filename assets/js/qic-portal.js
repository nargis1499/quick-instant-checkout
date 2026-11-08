/**
 * Quick Instant Checkout — Order Portal (multistep modal checkout).
 * Steps: 1 Package -> 2 Contact -> 3 Payment (crypto protocol or gateway) -> 4 Verify (TXID + proof) -> 5 Success.
 */
(function () {
	'use strict';

	var state = {
		cat: 0, tier: 0, price: 0, per: 0, accounts: 0, catName: '',
		protocol: null, gateway: null, file: null, nonce: '', maxStep: 1,
		finished: false, isCustom: false, customQty: 0
	};

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function fmt(n) { return Number(n).toFixed(2); }
	function money(n) { return qicPortalData.currencySymbol + fmt(n); }
	function portal() { return $('[data-qic-portal]'); }
	function panel(n) { return $('.qic-panel[data-panel="' + n + '"]', portal()); }
	function val(id) { var el = $('#' + id); return el ? el.value.trim() : ''; }

	function cryptoTotal() { return state.price; }
	function currentTotal() { return state.protocol ? cryptoTotal() : state.price; }

	/* ---------- step 1: selection ---------- */

	function selectCat(catId) {
		state.cat = parseInt(catId, 10);
		var catBtn = $('.qic-cat[data-cat="' + state.cat + '"]', portal());
		$$('.qic-cat', portal()).forEach(function (c) { c.classList.remove('is-selected'); });
		if (catBtn) {
			catBtn.classList.add('is-selected');
			state.catName = (catBtn.querySelector('strong') || {}).textContent || '';
		}
		$$('.qic-tier-tiles', portal()).forEach(function (g) {
			g.hidden = parseInt(g.getAttribute('data-cat'), 10) !== state.cat;
		});
		var nameEl = $('.qic-tier-cat-name', portal());
		if (nameEl) { nameEl.textContent = state.catName; }

		var group = $('.qic-tier-tiles[data-cat="' + state.cat + '"]', portal());
		if (!group) { return; }
		var current = $('.qic-tile[data-tier="' + state.tier + '"]', group);
		var pick = current || $('.qic-tile[data-default="1"]:not(.is-disabled)', group) || $('.qic-tile:not(.is-disabled)', group);
		if (pick) { selectTile(pick); }
	}

	function selectTile(tile) {
		if (tile.classList.contains('is-disabled')) { return; }
		$$('.qic-tile', portal()).forEach(function (t) { t.classList.remove('is-selected'); });
		tile.classList.add('is-selected');
		state.tier = parseInt(tile.getAttribute('data-tier'), 10);
		state.price = parseFloat(tile.getAttribute('data-price'));
		state.per = parseFloat(tile.getAttribute('data-per'));
		state.accounts = parseInt(tile.getAttribute('data-accounts'), 10);
		state.isCustom = false;
		state.customQty = state.accounts;

		var cqInput = $('.qic-cq-input', portal());
		if (cqInput) {
			cqInput.value = state.accounts > 0 ? state.accounts : 1;
		}
		var cqBox = $('.qic-custom-qty-box', portal());
		if (cqBox) {
			cqBox.classList.remove('is-custom-active');
		}

		updateTotals();
	}

	function setCustomQty(qty) {
		qty = Math.max(1, parseInt(qty, 10) || 1);
		state.isCustom = true;
		state.customQty = qty;
		state.accounts = qty;

		// Unselect all tier tiles when custom quantity is used
		$$('.qic-tile', portal()).forEach(function (t) { t.classList.remove('is-selected'); });

		var rate = state.per > 0 ? state.per : (state.price > 0 ? state.price : 0);
		state.price = Math.round(qty * rate * 100) / 100;

		var cqInput = $('.qic-cq-input', portal());
		if (cqInput && parseInt(cqInput.value, 10) !== qty) {
			cqInput.value = qty;
		}
		var cqBox = $('.qic-custom-qty-box', portal());
		if (cqBox) {
			cqBox.classList.add('is-custom-active');
		}

		updateTotals();
	}

	function updateTotals() {
		var pkg = $('.qic-ps-package', portal());
		var unit = $('.qic-ps-unit', portal());
		var total = $('.qic-ps-total', portal());
		var payTotal = $('.qic-pay-total-val', portal());
		if (pkg) {
			var accText = state.accounts + ' ' + (state.accounts === 1 ? qicPortalData.accountWord : qicPortalData.accountWord + 's');
			pkg.textContent = state.catName + ' (' + accText + ')' + (state.isCustom ? ' [Custom]' : '');
		}
		if (unit) { unit.textContent = 'Estimated Unit Cost: ' + money(state.per) + '/' + qicPortalData.accountWord; }
		if (total) { total.textContent = money(currentTotal()) + ' ' + qicPortalData.currencyCode; }
		if (payTotal) { payTotal.textContent = money(currentTotal()) + ' ' + qicPortalData.currencyCode; }
		var depAmount = $('.qic-dep-amount-val', portal());
		if (depAmount && state.protocol) { depAmount.textContent = money(currentTotal()) + ' ' + qicPortalData.currencyCode + ' equivalent'; }
	}

	/* ---------- step 3: protocol / gateway ---------- */

	function selectProto(btn) {
		$$('.qic-proto', portal()).forEach(function (p) { p.classList.remove('is-selected'); });
		btn.classList.add('is-selected');
		state.protocol = {
			code: btn.getAttribute('data-code'),
			name: btn.getAttribute('data-name'),
			address: btn.getAttribute('data-address'),
			badge: btn.getAttribute('data-badge')
		};
		state.gateway = null;
		$$('input[name="qic_gateway"]', portal()).forEach(function (r) { r.checked = false; });
		$$('.qic-gw', portal()).forEach(function (g) { g.classList.remove('is-selected'); });

		var dep = $('.qic-deposit', portal());
		if (dep) {
			dep.hidden = false;
			$('.qic-dep-title', dep).textContent = state.protocol.name + ' (' + state.protocol.code + ')';
			var badge = $('.qic-dep-badge', dep);
			badge.hidden = !state.protocol.badge;
			badge.textContent = state.protocol.badge;
			$('.qic-addr-code', dep).textContent = state.protocol.code;
			$('.qic-addr', dep).value = state.protocol.address;
			$('.qic-dep-code2', dep).textContent = state.protocol.code;
			var qr = $('.qic-qr', dep);
			if (state.protocol.address) {
				qr.src = qicPortalData.qrTemplate.replace('{address}', encodeURIComponent(state.protocol.address));
				qr.parentNode.hidden = false;
			} else {
				qr.parentNode.hidden = true;
			}
		}
		var cont = $('.qic-step3-continue', portal());
		if (cont) { cont.innerHTML = qicPortalData.i18n.sentPayment + ' &rarr; ' + qicPortalData.i18n.uploadHash; }
		updateTotals();
	}

	function selectGateway(gwLabel) {
		state.gateway = gwLabel.querySelector('input').value;
		state.protocol = null;
		$$('.qic-proto', portal()).forEach(function (p) { p.classList.remove('is-selected'); });
		var dep = $('.qic-deposit', portal());
		if (dep) { dep.hidden = true; }
		var cont = $('.qic-step3-continue', portal());
		if (cont) { cont.innerHTML = qicPortalData.i18n.continueVerify + ' &rarr;'; }
		updateTotals();
	}

	/* ---------- steps navigation ---------- */

	function portalError(msg) {
		var current = $('.qic-panel:not([hidden])', portal());
		var box = $('.qic-portal-error', current) || $('.qic-portal-error', panel(4));
		if (box) { box.textContent = msg; box.hidden = false; }
	}

	function clearErrors() {
		$$('.qic-portal-error', portal()).forEach(function (b) { b.hidden = true; });
		$$('.qic-field.is-invalid', portal()).forEach(function (f) { f.classList.remove('is-invalid'); });
	}

	function validateStep(n) {
		if (n === 2) {
			var okName = val('qic-f-name').length >= 2;
			var okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('qic-f-email'));
			var fName = $('#qic-f-name'); var fEmail = $('#qic-f-email');
			if (fName) { fName.closest('.qic-field').classList.toggle('is-invalid', !okName); }
			if (fEmail) { fEmail.closest('.qic-field').classList.toggle('is-invalid', !okEmail); }
			if (!okName) { portalError(qicPortalData.i18n.requiredName); return false; }
			if (!okEmail) { portalError(qicPortalData.i18n.invalidEmail); return false; }
			return true;
		}
		if (n === 3) {
			if (!state.protocol && !state.gateway) { portalError(qicPortalData.i18n.selectGateway); return false; }
			return true;
		}
		return true;
	}

	function goto(n) {
		n = parseInt(n, 10);
		$$('.qic-panel', portal()).forEach(function (p) { p.hidden = parseInt(p.getAttribute('data-panel'), 10) !== n; });
		$$('.qic-step', portal()).forEach(function (s) {
			var sn = parseInt(s.getAttribute('data-step'), 10);
			s.classList.toggle('is-active', sn === n);
			s.classList.toggle('is-done', sn < n);
		});
		state.maxStep = Math.max(state.maxStep, n);
		clearErrors();
		if (n === 4) { buildReview(); }
		$('.qic-portal-body', portal()).scrollTop = 0;
	}

	function buildReview() {
		var isCrypto = !!state.protocol;
		$$('.qic-crypto-only', portal()).forEach(function (el) { el.hidden = !isCrypto; });

		var total = state.price;

		set('.qic-rv-package', state.accounts + 'x ' + state.catName + (state.isCustom ? ' (Custom)' : ''));
		set('.qic-rv-gateway', isCrypto
			? state.protocol.name + ' (' + state.protocol.code + ')'
			: gatewayTitle());
		set('.qic-rv-email', val('qic-f-email'));
		set('.qic-rv-total', money(total) + ' ' + qicPortalData.currencyCode);

		var place = $('.qic-place-order', portal());
		if (place) {
			place.classList.toggle('qic-btn-success', isCrypto);
			place.innerHTML = (isCrypto
				? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="vertical-align:-3px;margin-right:8px"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="m8.5 12.5 2.5 2.5 4.5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' + qicPortalData.i18n.submitOrder
				: qicPortalData.i18n.placeOrder + ' • ' + money(total));
		}

		function set(sel, v) { var el = $(sel, portal()); if (el) { el.textContent = v; } }
	}

	function gatewayTitle() {
		var gw = $('input[name="qic_gateway"]:checked', portal());
		if (!gw) { return ''; }
		var strong = gw.closest('.qic-gw').querySelector('strong');
		return strong ? strong.textContent : gw.value;
	}

	/* ---------- open / close ---------- */

	function open(catId, tierId) {
		var p = portal();
		if (!p) { return; }
		p.hidden = false;
		document.body.classList.add('qic-portal-open');
		var loading = $('.qic-loading', p);
		if (loading) { loading.hidden = true; }
		state.maxStep = 1;
		state.tier = parseInt(tierId, 10) || 0;
		selectCat(catId || ($('.qic-cat', p) || {}).getAttribute('data-cat'));
		if (state.tier) {
			var tile = $('.qic-tile[data-tier="' + state.tier + '"]', p);
			if (tile) { selectTile(tile); }
		}
		goto(1);
		if (!state.nonce) { fetchNonce(function () {}); }
	}

	function close() {
		var p = portal();
		if (!p) { return; }
		p.hidden = true;
		document.body.classList.remove('qic-portal-open');
		// After a successful order, wipe everything so the next purchase starts fresh.
		if (state.finished) { resetAll(); }
	}

	/** Clear all fields + payment selection + step state. */
	function resetAll() {
		var p = portal();
		if (!p) { return; }
		['qic-f-name', 'qic-f-email', 'qic-f-telegram', 'qic-f-whatsapp', 'qic-f-notes', 'qic-f-txid'].forEach(function (id) {
			var el = $('#' + id);
			if (el) { el.value = ''; }
		});
		var country = $('#qic-f-country');
		if (country) { country.value = 'US'; }
		var terms = $('#qic-f-terms');
		if (terms) { terms.checked = false; }
		var fileInput = $('#qic-f-proof');
		if (fileInput) { fileInput.value = ''; }
		state.file = null;
		var dz = $('#qic-dropzone');
		if (dz) {
			var prev = $('.qic-dz-preview', dz);
			if (prev) { prev.hidden = true; prev.src = ''; }
			var b = $('b', dz);
			if (b) { b.textContent = b.getAttribute('data-initial') || qicPortalData.i18n.uploadCta; }
		}
		state.protocol = null;
		state.gateway = null;
		state.isCustom = false;
		state.customQty = 0;
		var cqBox = $('.qic-custom-qty-box', p);
		if (cqBox) { cqBox.classList.remove('is-custom-active'); }
		$$('.qic-proto', p).forEach(function (x) { x.classList.remove('is-selected'); });
		var dep = $('.qic-deposit', p);
		if (dep) { dep.hidden = true; }
		$$('input[name="qic_gateway"]', p).forEach(function (r) { r.checked = false; });
		$$('.qic-gw', p).forEach(function (g) { g.classList.remove('is-selected'); });
		var cont = $('.qic-step3-continue', p);
		if (cont) { cont.innerHTML = qicPortalData.i18n.continueVerify + ' &rarr;'; }
		var place = $('.qic-place-order', p);
		if (place) {
			place.disabled = false;
			place.classList.remove('qic-btn-success');
			place.textContent = qicPortalData.i18n.placeOrder;
		}
		var loading = $('.qic-loading', p);
		if (loading) { loading.hidden = true; }
		state.finished = false;
		state.maxStep = 1;
		clearErrors();
	}

	function fetchNonce(cb) {
		fetch(qicPortalData.nonceUrl, { method: 'POST', credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.success) { state.nonce = j.data.nonce; cb(); } })
			.catch(function () { cb(); });
	}

	/* ---------- step 4: submit ---------- */

	function checkFile(f) {
		if (!f) { return ''; }
		if (['image/png', 'image/jpeg', 'image/gif'].indexOf(f.type) === -1) { return qicPortalData.i18n.fileBadType; }
		if (f.size > 5 * 1024 * 1024) { return qicPortalData.i18n.fileTooBig; }
		return '';
	}

	function placeOrder() {
		var btn = $('.qic-place-order', portal());
		var terms = $('#qic-f-terms');
		if (terms && !terms.checked) { portalError(qicPortalData.i18n.acceptTerms); return; }

		var isCrypto = !!state.protocol;
		if (isCrypto && val('qic-f-txid').length < 8) { portalError(qicPortalData.i18n.invalidTxid); return; }
		var fileErr = checkFile(state.file);
		if (fileErr) { portalError(fileErr); return; }

		function send() {
			btn.disabled = true;
			var loading = $('.qic-loading', portal());
			if (loading) { loading.hidden = false; }
			var body = new FormData();
			body.append('token', state.nonce);
			body.append('variation_id', state.tier);
			body.append('is_custom', state.isCustom ? '1' : '0');
			body.append('custom_qty', state.accounts);
			body.append('custom_price', state.price);
			body.append('full_name', val('qic-f-name'));
			body.append('email', val('qic-f-email'));
			body.append('telegram', val('qic-f-telegram'));
			body.append('whatsapp', val('qic-f-whatsapp'));
			body.append('country', val('qic-f-country'));
			body.append('notes', val('qic-f-notes'));
			if (isCrypto) {
				body.append('protocol', state.protocol.code);
				body.append('txid', val('qic-f-txid'));
				if (state.file) { body.append('proof', state.file, state.file.name); }
			} else {
				body.append('gateway', state.gateway);
			}

			fetch(qicPortalData.placeUrl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (text) {
					var json;
					try { json = JSON.parse(text); } catch (e) {
						console.error('[QIC] Non-JSON place-order response:', text.slice(0, 500));
						throw new Error(qicPortalData.i18n.genericError);
					}
					if (json && json.success && json.data && json.data.redirect) {
						window.location = json.data.redirect;
						return;
					}
					if (json && json.success && json.data && json.data.congrats) {
						showSuccess(json.data);
						return;
					}
					console.error('[QIC] Place order rejected:', json);
					throw new Error((json && json.data && json.data.message) || qicPortalData.i18n.genericError);
				})
				.catch(function (err) {
					if (loading) { loading.hidden = true; }
					btn.disabled = false;
					portalError(err && err.message ? err.message : qicPortalData.i18n.genericError);
				});
		}

		if (!state.nonce) { fetchNonce(send); } else { send(); }
	}

	function showSuccess(data) {
		state.finished = true;
		var loading = $('.qic-loading', portal());
		if (loading) { loading.hidden = true; }
		var orderLine = $('.qic-success-order', portal());
		if (orderLine) {
			orderLine.innerHTML = qicPortalData.i18n.orderReceived.replace('{number}', '<b>#' + data.order_number + '</b>');
		}
		var text = $('.qic-success-text', portal());
		if (text) {
			text.textContent = qicPortalData.i18n.verifyNote.replace('{email}', data.email);
		}
		$$('.qic-step', portal()).forEach(function (s) { s.classList.remove('is-active'); s.classList.add('is-done'); });
		$$('.qic-panel', portal()).forEach(function (p) { p.hidden = parseInt(p.getAttribute('data-panel'), 10) !== 5; });
		$('.qic-portal-body', portal()).scrollTop = 0;
	}

	/* ---------- wiring ---------- */

	document.addEventListener('click', function (e) {
		var p = portal();
		if (!p) { return; }

		if (e.target.closest('[data-qic-close]')) { close(); return; }

		var cat = e.target.closest('.qic-cat');
		if (cat && p.contains(cat)) { selectCat(cat.getAttribute('data-cat')); return; }

		var tile = e.target.closest('.qic-tile');
		if (tile && p.contains(tile)) { selectTile(tile); return; }

		if (e.target.closest('.qic-copy-btn')) {
			var addr = $('.qic-addr', p);
			if (addr && addr.value) {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(addr.value);
				} else {
					addr.focus(); addr.select();
					try { document.execCommand('copy'); } catch (err) {}
				}
			}
			return;
		}

		if (e.target.closest('#qic-dropzone')) { $('#qic-f-proof').click(); return; }

		var proto = e.target.closest('.qic-proto');
		if (proto && p.contains(proto)) { selectProto(proto); return; }

		var stepBtn = e.target.closest('.qic-step');
		if (stepBtn && p.contains(stepBtn)) {
			if (state.finished) { return; } // no going back from the congrats screen
			var sn = parseInt(stepBtn.getAttribute('data-step'), 10);
			if (sn <= state.maxStep) { goto(sn); }
			return;
		}

		var go = e.target.closest('[data-goto]');
		if (go && p.contains(go)) {
			var targetStep = parseInt(go.getAttribute('data-goto'), 10);
			var currentPanel = $('.qic-panel:not([hidden])', p);
			var current = currentPanel ? parseInt(currentPanel.getAttribute('data-panel'), 10) : 1;
			if (targetStep > current && !validateStep(current)) { return; }
			goto(targetStep);
			return;
		}

		if (e.target.closest('.qic-place-order')) { placeOrder(); return; }

		if (e.target.closest('.qic-cq-plus')) {
			var curPlus = state.isCustom ? state.customQty : (state.accounts || 1);
			setCustomQty(curPlus + 1);
			return;
		}

		if (e.target.closest('.qic-cq-minus')) {
			var curMinus = state.isCustom ? state.customQty : (state.accounts || 1);
			setCustomQty(Math.max(1, curMinus - 1));
			return;
		}

		var gw = e.target.closest('.qic-gw');
		if (gw && p.contains(gw)) {
			$$('.qic-gw', p).forEach(function (g) { g.classList.remove('is-selected'); });
			gw.classList.add('is-selected');
			var input = gw.querySelector('input');
			if (input && !e.target.closest('input')) { input.checked = true; }
			selectGateway(gw);
			return;
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target.classList.contains('qic-cq-input')) {
			var parsed = parseInt(e.target.value, 10);
			if (!isNaN(parsed) && parsed >= 1) {
				setCustomQty(parsed);
			}
		}
	});

	document.addEventListener('change', function (e) {
		if (e.target.classList.contains('qic-cq-input')) {
			var parsed = parseInt(e.target.value, 10);
			setCustomQty(Math.max(1, isNaN(parsed) ? 1 : parsed));
			return;
		}
		if (e.target.id === 'qic-f-proof') {
			var f = e.target.files && e.target.files[0];
			var err = checkFile(f);
			if (err) { portalError(err); e.target.value = ''; state.file = null; return; }
			state.file = f || null;
			var dz = $('#qic-dropzone');
			var preview = $('.qic-dz-preview', dz);
			if (f) {
				preview.src = URL.createObjectURL(f);
				preview.hidden = false;
				$('b', dz).textContent = f.name;
			}
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !state.finished) { close(); }
	});

	// Remember the dropzone label so it can be restored after a reset.
	document.addEventListener('DOMContentLoaded', function () {
		var dzLabel = $('#qic-dropzone b');
		if (dzLabel) { dzLabel.setAttribute('data-initial', dzLabel.textContent); }
	});

	window.QICPortal = { open: open, close: close };
})();
