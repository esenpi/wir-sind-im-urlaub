/**
 * Wir sind im Urlaub – Frontend-Verhalten.
 *
 * - Blendet den Hinweis clientseitig aus, sobald der Endzeitpunkt erreicht ist
 *   (wichtig für Seiten aus dem Page-Cache).
 * - Merkt sich "Geschlossen"-Entscheidungen des Besuchers (localStorage),
 *   gebunden an den konfigurierten Zeitraum (data-hash).
 * - Steuert die Popup-Frequenz (immer / pro Besuch / pro Tag).
 * - Aktualisiert den Countdown live.
 */
(function () {
	'use strict';

	var STORAGE_PREFIX = 'wsiu:';

	function storageGet(store, key) {
		try { return store.getItem(STORAGE_PREFIX + key); } catch (e) { return null; }
	}

	function storageSet(store, key, value) {
		try { store.setItem(STORAGE_PREFIX + key, value); } catch (e) { /* Privatmodus o. ä. */ }
	}

	function todayKey() {
		var d = new Date();
		return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
	}

	/** Differenz in ganzen Kalendertagen (Mitternacht zu Mitternacht). */
	function dayDiff(fromDate, toTimestampSec) {
		var from = new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate());
		var target = new Date(toTimestampSec * 1000);
		var to = new Date(target.getFullYear(), target.getMonth(), target.getDate());
		return Math.round((to - from) / 86400000);
	}

	function countdownText(root) {
		var now = new Date();
		var phase = root.getAttribute('data-phase');
		var start = parseInt(root.getAttribute('data-start'), 10);
		var end = parseInt(root.getAttribute('data-end'), 10);

		if (phase === 'before' && start) {
			var untilStart = dayDiff(now, start);
			if (untilStart <= 0) { return 'Der Urlaub beginnt heute'; }
			if (untilStart === 1) { return 'Der Urlaub beginnt morgen'; }
			return 'Der Urlaub beginnt in ' + untilStart + ' Tagen';
		}
		if (end) {
			var untilEnd = dayDiff(now, end) + 1; // Rückkehr am Tag nach dem letzten Urlaubstag
			if (untilEnd <= 1) { return 'Ab morgen wieder da'; }
			return 'Wieder da in ' + untilEnd + ' Tagen';
		}
		return '';
	}

	function isExpired(root) {
		var end = parseInt(root.getAttribute('data-end'), 10);
		return end && (Date.now() / 1000) > end;
	}

	function setBodyOffset(root, active) {
		var pos = root.getAttribute('data-banner-pos') || 'top';
		var prop = pos === 'bottom' ? 'paddingBottom' : 'paddingTop';
		var dataKey = pos === 'bottom' ? 'wsiuOrigPadBottom' : 'wsiuOrigPadTop';
		var body = document.body;

		if (active) {
			if (body.dataset[dataKey] === undefined) {
				body.dataset[dataKey] = window.getComputedStyle(body)[prop];
			}
			var base = parseFloat(body.dataset[dataKey]) || 0;
			body.style[prop] = (base + root.offsetHeight) + 'px';
		} else if (body.dataset[dataKey] !== undefined) {
			body.style[prop] = body.dataset[dataKey];
		}
	}

	function removeRoot(root) {
		if (root.getAttribute('data-mode') === 'banner') { setBodyOffset(root, false); }
		if (root.getAttribute('data-mode') === 'popup') { document.body.classList.remove('wsiu-lock'); }
		root.parentNode && root.parentNode.removeChild(root);
	}

	function init(root) {
		var mode = root.getAttribute('data-mode');
		var hash = root.getAttribute('data-hash');
		var isPreview = root.getAttribute('data-preview') === '1';

		if (isExpired(root)) { removeRoot(root); return; }

		// Frühere Entscheidungen respektieren (nicht im Vorschau-Modus).
		if (!isPreview) {
			if ((mode === 'banner' || mode === 'card') && storageGet(localStorage, 'dismissed:' + hash)) {
				removeRoot(root);
				return;
			}
			if (mode === 'popup') {
				var freq = root.getAttribute('data-frequency');
				if (freq === 'session' && storageGet(sessionStorage, 'popup:' + hash)) { removeRoot(root); return; }
				if (freq === 'day' && storageGet(localStorage, 'popup:' + hash) === todayKey()) { removeRoot(root); return; }
			}
		}

		// Anzeigen.
		root.classList.add('wsiu-show');

		if (mode === 'banner') {
			setBodyOffset(root, true);
			window.addEventListener('resize', function () {
				if (document.body.contains(root)) { setBodyOffset(root, true); }
			});
		}

		if (mode === 'popup') {
			document.body.classList.add('wsiu-lock');
			if (!isPreview) {
				var frequency = root.getAttribute('data-frequency');
				if (frequency === 'session') { storageSet(sessionStorage, 'popup:' + hash, '1'); }
				if (frequency === 'day') { storageSet(localStorage, 'popup:' + hash, todayKey()); }
			}

			var firstButton = root.querySelector('.wsiu-dismiss, .wsiu-btn');
			if (firstButton) { firstButton.focus({ preventScroll: true }); }

			// Klick auf das Overlay (außerhalb des Fensters) schließt.
			root.addEventListener('click', function (e) {
				if (e.target === root) { removeRoot(root); }
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && document.body.contains(root)) { removeRoot(root); }
			});
		}

		// Schließen-Buttons.
		root.querySelectorAll('[data-wsiu-dismiss]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!isPreview && (mode === 'banner' || mode === 'card')) {
					storageSet(localStorage, 'dismissed:' + hash, '1');
				}
				removeRoot(root);
			});
		});

		// Countdown initial setzen und mitlaufen lassen.
		var countdownEl = root.querySelector('[data-wsiu-countdown]');
		function tick() {
			if (!document.body.contains(root)) { return; }
			if (isExpired(root)) { removeRoot(root); return; }
			if (countdownEl) {
				var text = countdownText(root);
				if (text) { countdownEl.textContent = text; }
			}
		}
		tick();
		setInterval(tick, 60000);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) { tick(); }
		});
	}

	function boot() {
		document.querySelectorAll('[data-wsiu-root]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
