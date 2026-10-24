/* Native form controls remain usable without this enhancement. */
(() => {
	'use strict';
	const form = document.getElementById('site-staples-settings');
	if (!form) return;

	const root = form.closest('.site-staples-admin');
	const navigation = root.querySelector('.site-staples-navigation');
	const tabs = Array.from(navigation.querySelectorAll('a'));
	const panels = tabs.map(tab => document.getElementById(tab.hash.slice(1)));
	const storageKey = 'site-staples-group:' + window.location.pathname;
	const list = navigation.querySelector('ul');
	list.setAttribute('role', 'tablist');
	list.setAttribute('aria-label', navigation.getAttribute('aria-label'));
	list.setAttribute('aria-orientation', 'vertical');
	tabs.forEach((tab, index) => {
		tab.parentElement.setAttribute('role', 'presentation');
		tab.setAttribute('role', 'tab');
		tab.setAttribute('aria-controls', panels[index].id);
		panels[index].setAttribute('role', 'tabpanel');
		panels[index].setAttribute('aria-labelledby', tab.id);
		panels[index].tabIndex = 0;
	});

	const activate = (tab, focus = false, historyMode = '') => {
		const selected = tabs.indexOf(tab);
		if (selected < 0) return;
		tabs.forEach((item, index) => {
			item.setAttribute('aria-selected', String(index === selected));
			item.tabIndex = index === selected ? 0 : -1;
			panels[index].hidden = index !== selected;
		});
		try { window.sessionStorage.setItem(storageKey, panels[selected].id); } catch (_) { /* Storage may be unavailable. */ }
		if (historyMode) {
			try { window.history[historyMode + 'State'](null, '', tab.hash); } catch (_) { /* Tab switching still works. */ }
		}
		if (focus) tab.focus();
	};
	const fromHash = () => {
		let id;
		try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (_) { return null; }
		const target = document.getElementById(id);
		return target ? target.closest('.site-staples-group') : null;
	};
	const restore = (initial = false) => {
		let panel = fromHash();
		if (!panel && initial) {
			const code = new URLSearchParams(window.location.search).get('staples_notice');
			if (code === 'revision_preview' || code === 'revision_batch') panel = document.getElementById('staples-group-content');
			if (code === 'import_preview' || code === 'imported') panel = document.getElementById('staples-group-plugin');
		}
		if (!panel) {
			try { panel = document.getElementById(window.sessionStorage.getItem(storageKey)); } catch (_) { /* Use the first group. */ }
		}
		activate(tabs[Math.max(0, panels.indexOf(panel))]);
	};
	tabs.forEach((tab, index) => {
		tab.addEventListener('click', event => {
			event.preventDefault();
			activate(tab, true, 'push');
		});
		tab.addEventListener('keydown', event => {
			let next;
			if (event.key === 'ArrowDown') next = (index + 1) % tabs.length;
			if (event.key === 'ArrowUp') next = (index - 1 + tabs.length) % tabs.length;
			if (event.key === 'Home') next = 0;
			if (event.key === 'End') next = tabs.length - 1;
			if (event.key === ' ' || event.key === 'Enter') next = index;
			if (next === undefined) return;
			event.preventDefault();
			activate(tabs[next], true, 'replace');
		});
	});
	window.addEventListener('hashchange', () => restore());
	window.addEventListener('popstate', () => restore());
	restore(true);
	if (!window.location.hash) activate(tabs.find(tab => tab.getAttribute('aria-selected') === 'true'), false, 'replace');

	const optionSets = Array.from(root.querySelectorAll('[data-feature-options]'));
	const syncOptions = () => {
		optionSets.forEach(options => {
			const toggle = options.closest('.site-staples-card').querySelector('.site-staples-switch');
			options.disabled = !toggle.checked;
		});
	};
	// Disabled fields are omitted by HTML forms. Retain their current values in
	// snapshots and native POSTs, including edits made before switching a feature off.
	form.addEventListener('formdata', event => {
		optionSets.filter(options => options.disabled).forEach(options => {
			Array.from(options.elements).forEach(control => {
				if (control.form !== form || !control.name || control.disabled) return;
				if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
				event.formData.append(control.name, control.value);
			});
		});
	});
	syncOptions();
	const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()));
	const saved = snapshot();
	const save = document.getElementById('site-staples-save');
	const discard = document.getElementById('site-staples-discard');
	const status = document.getElementById('site-staples-save-status');
	const maintenancePage = document.getElementById('staples-maintenance-page');
	const clearMaintenancePage = document.getElementById('staples-maintenance-page-clear');
	if (maintenancePage && clearMaintenancePage) {
		clearMaintenancePage.addEventListener('click', () => {
			maintenancePage.value = '0';
			maintenancePage.dispatchEvent(new Event('change', { bubbles: true }));
			maintenancePage.focus();
		});
	}

	const refresh = () => {
		syncOptions();
		if (maintenancePage && clearMaintenancePage) clearMaintenancePage.hidden = maintenancePage.value === '0';
		const dirty = snapshot() !== saved;
		if (save) save.disabled = !dirty;
		if (discard) { discard.hidden = false; discard.disabled = !dirty; }
		if (status) {
			status.textContent = status.getAttribute(dirty ? 'data-dirty' : 'data-clean');
			status.setAttribute('data-dirty-state', String(dirty));
		}
		root.querySelectorAll('[data-feature-state]').forEach(badge => {
			const toggle = badge.closest('.site-staples-card').querySelector('.site-staples-switch');
			badge.textContent = badge.getAttribute(toggle.checked ? 'data-on' : 'data-off');
			badge.setAttribute('data-enabled', String(toggle.checked));
		});
		panels.forEach(panel => {
			const count = panel.querySelector('[data-enabled-count]');
			if (!count) return;
			const toggles = Array.from(panel.querySelectorAll('.site-staples-switch'));
			count.textContent = count.getAttribute('data-template').replace('%1$d', toggles.filter(toggle => toggle.checked).length).replace('%2$d', toggles.length);
		});
	};
	const update = (event) => {
		if (event.target !== form && event.target.form !== form) return;
		refresh();
	};
	form.addEventListener('reset', () => window.setTimeout(refresh, 0));
	refresh();
	root.addEventListener('input', update);
	root.addEventListener('change', update);
	// Reveal the first invalid field's group; subsequent invalid fields must not hide it.
	let revealingInvalid = false;
	root.addEventListener('invalid', (event) => {
		if (event.target.form !== form) return;
		const panel = event.target.closest('.site-staples-group');
		if (panel && !revealingInvalid) {
			activate(tabs[panels.indexOf(panel)], false, 'replace');
			revealingInvalid = true;
			window.setTimeout(() => { revealingInvalid = false; }, 0);
		}
	}, true);
})();
