/* Paged, independent redirect saves: no large rule arrays are submitted with settings. */
(() => {
	'use strict';
	const editor = document.getElementById('staples-redirect-editor');
	if (!editor) return;
	const info = document.querySelector('[data-redirect-info]');
	const bubble = document.getElementById('staples-redirect-help');
	if (info && bubble) {
		const wrapper = info.parentElement;
		let hovered = false, focused = false, pinned = false, dismissed = false;
		const syncHelp = () => {
			const open = !dismissed && (hovered || focused || pinned);
			bubble.hidden = !open;
			info.setAttribute('aria-expanded', String(open));
		};
		wrapper.addEventListener('mouseenter', () => { hovered = true; dismissed = false; syncHelp(); });
		wrapper.addEventListener('mouseleave', () => { hovered = false; syncHelp(); });
		info.addEventListener('focus', () => { focused = true; dismissed = false; syncHelp(); });
		info.addEventListener('blur', () => { focused = false; pinned = false; syncHelp(); });
		info.addEventListener('click', () => { pinned = !pinned; dismissed = !pinned; syncHelp(); });
		document.addEventListener('keydown', event => {
			if (event.key === 'Escape') { pinned = false; dismissed = true; syncHelp(); }
		});
		document.addEventListener('click', event => {
			if (!wrapper.contains(event.target)) { pinned = false; dismissed = true; syncHelp(); }
		});
	}

	const text = window.siteStaplesRedirects;
	const featureToggle = document.getElementById('staples-redirects');
	const find = selector => editor.querySelector(selector);
	const rows = find('[data-redirect-rows]');
	const template = find('template');
	const fresh = find('[data-redirect-new] [data-redirect-row]');
	const newPanel = find('[data-redirect-new]');
	const openNew = find('[data-redirect-open]');
	const search = find('#staples-redirect-search');
	const clearSearch = find('[data-redirect-clear]');
	const message = find('[data-redirect-message]');
	const prev = Array.from(editor.querySelectorAll('[data-redirect-prev]'));
	const next = Array.from(editor.querySelectorAll('[data-redirect-next]'));
	const fields = row => Array.from(row.querySelectorAll('[data-redirect-field]'));
	const values = row => Object.fromEntries(fields(row).map(field => [field.dataset.redirectField, field.value]));
	const drafts = new Map();
	const editing = new Set();
	let state = { page: 1, pages: 1, total: 0, version: '' };
	let query = '';
	let busy = false;
	let loaded = false;
	const isDirty = () => drafts.size > 0 || Boolean(values(fresh).from || values(fresh).to);
	const fill = (row, rule) => fields(row).forEach(field => { field.value = String(rule[field.dataset.redirectField]); });
	const closeNew = () => {
		fill(fresh, { from: '', to: '', status: 302 });
		newPanel.hidden = true;
		openNew.setAttribute('aria-expanded', 'false');
	};
	const controls = () => {
		editor.querySelectorAll('input, select, button').forEach(control => { control.disabled = busy || !loaded; });
		prev.forEach(button => { button.disabled = busy || !loaded || state.page <= 1; });
		next.forEach(button => { button.disabled = busy || !loaded || state.page >= state.pages; });
		find('[data-redirect-reload]').disabled = busy;
		clearSearch.hidden = !query;
		fresh.querySelector('[data-redirect-save]').disabled = busy || !loaded || state.total >= 10000;
		openNew.disabled = busy || !loaded || state.total >= 10000;
		rows.querySelectorAll('[data-redirect-row]').forEach(row => {
			row.querySelector('[data-redirect-cancel]').disabled = busy;
			row.querySelector('[data-redirect-save]').disabled = busy || !drafts.has(row.dataset.original);
		});
	};
	const editMode = (row, open) => {
		row.querySelector('[data-redirect-read]').hidden = open;
		row.querySelector('[data-redirect-edit-fields]').hidden = !open;
		row.querySelector('[data-redirect-edit]').setAttribute('aria-expanded', String(open));
		if (open) editing.add(row.dataset.original); else editing.delete(row.dataset.original);
	};

	const summary = () => text.summary.replace('%1$d', state.matched).replace('%2$d', state.total);
	const renderPages = data => {
		editor.querySelectorAll('.site-staples-redirect-pagination').forEach(pagination => {
			pagination.hidden = data.pages <= 1;
		});
		const start = Math.max(1, Math.min(data.page - 2, data.pages - 4));
		const end = Math.min(data.pages, start + 4);
		editor.querySelectorAll('[data-redirect-pages]').forEach(pages => {
			pages.replaceChildren();
			const ellipsis = () => {
				const dots = document.createElement('span');
				dots.textContent = '…';
				dots.setAttribute('aria-hidden', 'true');
				pages.append(dots);
			};
			const pageButton = number => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'button';
				button.dataset.redirectPage = number;
				button.textContent = number;
				button.setAttribute('aria-label', text.page.replace('%1$d', number).replace('%2$d', data.pages));
				if (number === data.page) button.setAttribute('aria-current', 'page');
				pages.append(button);
			};
			if (start > 1) ellipsis();
			for (let number = start; number <= end; number++) pageButton(number);
			if (end < data.pages) {
				if (end + 1 < data.pages) ellipsis();
				pageButton(data.pages);
			}
		});
	};

	const render = data => {
		state = data;
		loaded = true;
		rows.replaceChildren();
		data.rules.forEach((rule, index) => {
			const fragment = template.content.cloneNode(true);
			const row = fragment.querySelector('[data-redirect-row]');
			row.dataset.original = rule.from;
			row.savedRule = rule;
			row.setAttribute('aria-label', rule.from);
			row.querySelector('[data-redirect-remove]').setAttribute('aria-label', text.remove.replace('%s', rule.from));
			row.querySelectorAll('[data-redirect-value]').forEach(value => {
				value.textContent = value.dataset.redirectValue === 'status' ? Array.from(fresh.querySelector('select').options).find(option => option.value === String(rule.status)).textContent : rule[value.dataset.redirectValue];
			});
			const panel = row.querySelector('[data-redirect-edit-fields]');
			panel.id = 'staples-redirect-row-' + index;
			row.querySelector('[data-redirect-edit]').setAttribute('aria-controls', panel.id);
			row.querySelector('[data-redirect-edit]').setAttribute('aria-label', text.edit.replace('%s', rule.from));
			fill(row, drafts.get(rule.from) || rule);
			editMode(row, editing.has(rule.from) || drafts.has(rule.from));
			rows.append(fragment);
		});
		find('[data-redirect-heading]').hidden = data.rules.length === 0;
		renderPages(data);
	};
	const request = async (operation, extra = {}, success = '') => {
		if (busy || (operation !== 'list' && !featureToggle.checked)) return;
		busy = true;
		editor.setAttribute('aria-busy', 'true');
		controls();
		message.textContent = text.working;
		let focusAfter = null;
		try {
			const body = new URLSearchParams({ action: 'site_staples_redirects', nonce: editor.dataset.nonce, operation, version: state.version, page: String(state.page), search: query, ...extra });
			const response = await fetch(editor.dataset.url, { method: 'POST', credentials: 'same-origin', body });
			const result = await response.json();
			if (!response.ok || !result.success) throw Object.assign(new Error(result.data?.message || text.failed), { friendly: true });
			if (operation === 'list') { drafts.clear(); editing.clear(); }
			if (operation === 'update' || operation === 'delete') { drafts.delete(extra.original); editing.delete(extra.original); }
			if (operation === 'add') closeNew();
			render(result.data);
			query = extra.search ?? query;
			if (extra.clearSearch) { search.value = ''; focusAfter = search; }
			message.textContent = [success, query ? summary() : '', state.total >= 10000 ? text.limit : '', result.data.matched === 0 ? (query ? text.noMatches : text.empty) : ''].filter(Boolean).join(' ');
			if (operation === 'list' && extra.pagination) {
				focusAfter = editor.querySelectorAll('[data-redirect-pages]')[Number(extra.pagination) - 1]?.querySelector('[aria-current="page"]');
			}
			if (operation !== 'list') {
				const row = Array.from(rows.querySelectorAll('[data-redirect-row]')).find(item => item.dataset.original === extra.from);
				focusAfter = operation === 'add' ? openNew : (row?.querySelector('[data-redirect-edit]') || openNew);
			}
		} catch (error) {
			message.textContent = error.friendly ? error.message : text.failed;
		} finally {
			busy = false;
			editor.setAttribute('aria-busy', 'false');
			controls();
			focusAfter?.focus();
		}
	};
	const navigate = extra => {
		if (drafts.size && !window.confirm(text.discard)) return;
		request('list', extra);
	};
	openNew.addEventListener('click', () => {
		newPanel.hidden = false;
		openNew.setAttribute('aria-expanded', 'true');
		fresh.querySelector('input').focus();
	});
	find('[data-redirect-close]').addEventListener('click', () => {
		closeNew();
		openNew.focus();
	});
	find('[data-redirect-search]').addEventListener('click', () => navigate({ search: search.value.trim(), page: '1' }));
	search.addEventListener('keydown', event => {
		if (event.key === 'Enter') { event.preventDefault(); navigate({ search: search.value.trim(), page: '1' }); }
	});
	clearSearch.addEventListener('click', () => navigate({ search: '', page: '1', clearSearch: '1' }));
	find('[data-redirect-reload]').addEventListener('click', () => navigate({ search: query }));
	prev.forEach((button, index) => button.addEventListener('click', () => navigate({ page: String(state.page - 1), pagination: String(index + 1) })));
	next.forEach((button, index) => button.addEventListener('click', () => navigate({ page: String(state.page + 1), pagination: String(index + 1) })));
	editor.querySelectorAll('[data-redirect-pages]').forEach((pages, index) => {
		pages.addEventListener('click', event => {
			const button = event.target.closest('[data-redirect-page]');
			if (button && !busy && Number(button.dataset.redirectPage) !== state.page) navigate({ page: button.dataset.redirectPage, pagination: String(index + 1) });
		});
	});
	editor.addEventListener('input', event => {
		const row = event.target.closest('[data-redirect-row]');
		if (row && row !== fresh) {
			const value = values(row);
			if (fields(row).every(field => field.value === String(row.savedRule[field.dataset.redirectField]))) drafts.delete(row.dataset.original);
			else drafts.set(row.dataset.original, value);
		}
		controls();
	});
	editor.addEventListener('click', event => {
		const button = event.target.closest('[data-redirect-save], [data-redirect-remove], [data-redirect-cancel], [data-redirect-edit]');
		if (!button || busy || !featureToggle.checked) return;
		const row = button.closest('[data-redirect-row]');
		if (button.hasAttribute('data-redirect-edit')) {
			editMode(row, true);
			fields(row)[0].focus();
			return;
		}
		if (button.hasAttribute('data-redirect-cancel')) {
			if (row === fresh) fill(row, { from: '', to: '', status: 302 });
			else { drafts.delete(row.dataset.original); fill(row, row.savedRule); editMode(row, false); }
			controls();
			(row === fresh ? row.querySelector('input') : row.querySelector('[data-redirect-edit]')).focus();
			return;
		}
		if (button.hasAttribute('data-redirect-remove')) {
			if (window.confirm(text.remove.replace('%s', row.dataset.original))) request('delete', { original: row.dataset.original }, text.deleted);
			return;
		}
		if (!fields(row).every(field => field.reportValidity())) return;
		request(row === fresh ? 'add' : 'update', { ...values(row), original: row.dataset.original || '' }, text.saved);
	});
	fresh.querySelector('[data-redirect-remove]').hidden = true;
	fresh.querySelector('[data-redirect-delete-separator]').hidden = true;
	window.addEventListener('beforeunload', event => {
		if (isDirty()) { event.preventDefault(); event.returnValue = ''; }
	});
	request('list');
})();
