/**
 * Wir sind im Urlaub – Admin-Verhalten.
 *
 * - Lädt Schulferien-Vorschläge per AJAX und trägt sie per Klick ein.
 * - Blendet Felder passend zur gewählten Anzeigeform ein/aus.
 * - Rendert eine Live-Vorschau (nutzt dieselben CSS-Klassen wie das
 *   Frontend; Markup-Struktur gespiegelt aus class-wsiu-frontend.php).
 */
(function () {
	'use strict';

	var form = document.getElementById('wsiu-form');
	if (!form) { return; }

	var stage = document.getElementById('wsiu-preview-stage');
	var i18n = (window.wsiuAdmin && wsiuAdmin.i18n) || {};

	/* ----------------------------------------------------------------------
	 * Hilfsfunktionen
	 * ------------------------------------------------------------------- */

	function value(name) {
		var els = form.querySelectorAll('[name="wsiu_settings[' + name + ']"]');
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.type === 'radio' || el.type === 'checkbox') {
				if (el.type === 'checkbox' && el.checked) { return el.value; }
				if (el.type === 'radio' && el.checked) { return el.value; }
			} else if (el.type !== 'hidden') {
				return el.value;
			}
		}
		return '';
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str == null ? '' : String(str)));
		return div.innerHTML;
	}

	function formatDate(iso, opts) {
		if (!iso) { return '…'; }
		var parts = iso.split('-');
		var d = new Date(+parts[0], parts[1] - 1, +parts[2]);
		return d.toLocaleDateString('de-DE', opts || { day: 'numeric', month: 'long', year: 'numeric' });
	}

	function addDays(iso, days) {
		if (!iso) { return ''; }
		var parts = iso.split('-');
		var d = new Date(+parts[0], parts[1] - 1, +parts[2] + days);
		var m = String(d.getMonth() + 1).padStart(2, '0');
		var day = String(d.getDate()).padStart(2, '0');
		return d.getFullYear() + '-' + m + '-' + day;
	}

	function dayDiffFromToday(iso) {
		if (!iso) { return null; }
		var now = new Date();
		var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
		var parts = iso.split('-');
		var target = new Date(+parts[0], parts[1] - 1, +parts[2]);
		return Math.round((target - today) / 86400000);
	}

	/* ----------------------------------------------------------------------
	 * Felder passend zur Anzeigeform ein-/ausblenden
	 * ------------------------------------------------------------------- */

	function updateVisibility() {
		var mode = value('display_mode');
		document.querySelectorAll('[data-wsiu-show-for]').forEach(function (el) {
			var modes = el.getAttribute('data-wsiu-show-for').split(' ');
			el.hidden = modes.indexOf(mode) === -1;
		});
		var customColors = document.getElementById('wsiu-custom-colors');
		if (customColors) { customColors.hidden = value('theme') !== 'custom'; }
	}

	/* ----------------------------------------------------------------------
	 * Live-Vorschau
	 * ------------------------------------------------------------------- */

	function countdownPreviewText() {
		var end = value('end_date');
		var start = value('start_date');
		var startDiff = dayDiffFromToday(start);
		var endDiff = dayDiffFromToday(end);

		// Läuft die Vorankündigung? Dann Countdown bis zum Start zeigen.
		if (startDiff !== null && startDiff > 0) {
			if (startDiff === 1) { return 'Der Urlaub beginnt morgen'; }
			return 'Der Urlaub beginnt in ' + startDiff + ' Tagen';
		}
		if (endDiff !== null) {
			var back = endDiff + 1;
			if (back <= 1) { return 'Ab morgen wieder da'; }
			return 'Wieder da in ' + back + ' Tagen';
		}
		return 'Wieder da in … Tagen';
	}

	function replacePlaceholders(text) {
		return String(text || '')
			.split('{start}').join(formatDate(value('start_date')))
			.split('{ende}').join(formatDate(value('end_date')))
			.split('{wieder_da}').join(formatDate(addDays(value('end_date'), 1)));
	}

	function currentPhase() {
		var startDiff = dayDiffFromToday(value('start_date'));
		return startDiff !== null && startDiff > 0 ? 'before' : 'during';
	}

	function renderPreview() {
		if (!stage) { return; }

		var mode = value('display_mode');
		var theme = value('theme');
		var icon = value('icon');
		var phase = currentPhase();

		var headline = replacePlaceholders(value(phase === 'before' ? 'headline_before' : 'headline')) || '…';
		var message = escapeHtml(replacePlaceholders(value(phase === 'before' ? 'message_before' : 'message'))).replace(/\n/g, '<br>');
		var showCountdown = !!value('show_countdown');
		var dismissible = !!value('dismissible');

		var style = '';
		if (theme === 'custom') {
			style = ' style="--wsiu-a:' + escapeHtml(value('color_start')) +
				';--wsiu-b:' + escapeHtml(value('color_end')) +
				';--wsiu-text:' + escapeHtml(value('color_text')) + ';"';
		}

		var iconHtml = icon ? '<span class="wsiu-icon" aria-hidden="true">' + escapeHtml(icon) + '</span>' : '';
		var chipHtml = showCountdown ? '<span class="wsiu-chip">' + escapeHtml(countdownPreviewText()) + '</span>' : '';
		var dismissHtml = '<button type="button" class="wsiu-dismiss" tabindex="-1" aria-hidden="true">&#10005;</button>';

		var html = '';

		if (mode === 'popup') {
			var datesHtml =
				'<div class="wsiu-dates">' +
				'<span class="wsiu-date-pill">' + escapeHtml(formatDate(value('start_date'), { day: 'numeric', month: 'short', year: 'numeric' })) + '</span>' +
				'<span class="wsiu-dates-arrow" aria-hidden="true">&#8594;</span>' +
				'<span class="wsiu-date-pill">' + escapeHtml(formatDate(value('end_date'), { day: 'numeric', month: 'short', year: 'numeric' })) + '</span>' +
				'</div>';
			html =
				'<div class="wsiu-root wsiu-overlay wsiu-theme-' + escapeHtml(theme) + '"' + style + '>' +
				'<div class="wsiu-popup">' + dismissHtml +
				'<div class="wsiu-popup-icon" aria-hidden="true">' + escapeHtml(icon || '🌴') + '</div>' +
				'<h2 class="wsiu-headline">' + escapeHtml(headline) + '</h2>' +
				'<p class="wsiu-message">' + message + '</p>' +
				datesHtml + chipHtml +
				'<button type="button" class="wsiu-btn" tabindex="-1">' + escapeHtml(value('button_text') || 'Alles klar') + '</button>' +
				'</div></div>';
		} else if (mode === 'card') {
			html =
				'<div class="wsiu-root wsiu-card wsiu-card--' + escapeHtml(value('card_position')) + ' wsiu-theme-' + escapeHtml(theme) + '"' + style + '>' +
				(dismissible ? dismissHtml : '') +
				'<div class="wsiu-card-head">' + iconHtml +
				'<strong class="wsiu-headline">' + escapeHtml(headline) + '</strong></div>' +
				'<p class="wsiu-message">' + message + '</p>' + chipHtml +
				'</div>';
		} else {
			html =
				'<div class="wsiu-root wsiu-banner wsiu-banner--' + escapeHtml(value('banner_position')) + ' wsiu-theme-' + escapeHtml(theme) + '"' + style + '>' +
				'<div class="wsiu-banner-inner">' + iconHtml +
				'<div class="wsiu-banner-text">' +
				'<strong class="wsiu-headline">' + escapeHtml(headline) + '</strong>' +
				'<span class="wsiu-message">' + message + '</span>' +
				'</div>' + chipHtml + (dismissible ? dismissHtml : '') +
				'</div></div>';
		}

		stage.innerHTML = html;
	}

	/* ----------------------------------------------------------------------
	 * Schulferien
	 * ------------------------------------------------------------------- */

	var ferienList = document.getElementById('wsiu-ferien-list');

	function ferienStatus(text, kind) {
		if (!ferienList) { return; }
		ferienList.innerHTML = '<span class="wsiu-ferien-status' + (kind ? ' wsiu-ferien-status--' + kind : '') + '">' + escapeHtml(text) + '</span>';
	}

	function renderFerien(holidays) {
		if (!ferienList) { return; }
		if (!holidays.length) {
			ferienStatus(i18n.noResults || 'Keine kommenden Ferien gefunden.');
			return;
		}
		ferienList.innerHTML = '';
		holidays.forEach(function (h) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'wsiu-ferien-chip';
			btn.innerHTML =
				'<strong>' + escapeHtml(h.name) + '</strong>' +
				'<span>' + escapeHtml(formatDate(h.start, { day: '2-digit', month: '2-digit', year: 'numeric' })) +
				' – ' + escapeHtml(formatDate(h.end, { day: '2-digit', month: '2-digit', year: 'numeric' })) + '</span>';
			btn.addEventListener('click', function () {
				var startField = document.getElementById('wsiu-start-date');
				var endField = document.getElementById('wsiu-end-date');
				if (startField) { startField.value = h.start; }
				if (endField) { endField.value = h.end; }
				ferienList.querySelectorAll('.wsiu-ferien-chip').forEach(function (c) { c.classList.remove('is-selected'); });
				btn.classList.add('is-selected');
				renderPreview();

				var note = document.createElement('span');
				note.className = 'wsiu-ferien-status wsiu-ferien-status--ok';
				note.textContent = i18n.applied || 'Zeitraum übernommen – bitte speichern.';
				var old = ferienList.querySelector('.wsiu-ferien-status');
				if (old) { old.remove(); }
				ferienList.appendChild(note);
			});
			ferienList.appendChild(btn);
		});
	}

	function loadFerien(refresh) {
		if (!window.wsiuAdmin || !ferienList) { return; }
		ferienStatus(i18n.loading || 'Ferien werden geladen …');

		var body = new FormData();
		body.append('action', 'wsiu_school_holidays');
		body.append('nonce', wsiuAdmin.nonce);
		body.append('land', value('bundesland'));
		if (refresh) { body.append('refresh', '1'); }

		fetch(wsiuAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json && json.success) {
					renderFerien(json.data.holidays || []);
				} else {
					ferienStatus((json && json.data && json.data.message) || i18n.error || 'Fehler beim Laden.', 'error');
				}
			})
			.catch(function () {
				ferienStatus(i18n.error || 'Fehler beim Laden.', 'error');
			});
	}

	/* ----------------------------------------------------------------------
	 * Verdrahtung
	 * ------------------------------------------------------------------- */

	var bundeslandSelect = document.getElementById('wsiu-bundesland');
	if (bundeslandSelect) {
		bundeslandSelect.addEventListener('change', function () { loadFerien(false); });
	}

	var reloadBtn = document.getElementById('wsiu-ferien-reload');
	if (reloadBtn) {
		reloadBtn.addEventListener('click', function () { loadFerien(true); });
	}

	var debounce;
	form.addEventListener('input', function () {
		clearTimeout(debounce);
		debounce = setTimeout(function () {
			updateVisibility();
			renderPreview();
		}, 120);
	});
	form.addEventListener('change', function () {
		updateVisibility();
		renderPreview();
	});

	updateVisibility();
	renderPreview();
	loadFerien(false);
})();
