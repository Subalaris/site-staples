/* Row editing enhances native controls; settings and shared validation stay server-side. */
(() => {
	'use strict';
	const editor = document.getElementById('staples-redirect-editor');
	const form = document.getElementById('site-staples-settings');
	if (!editor || !form) return;
	const rows = editor.querySelector('[data-redirect-rows]');
	const template = editor.querySelector('template');
	const add = editor.querySelector('[data-redirect-add]');
	const initial = rows.cloneNode(true);
	const maximum = Number(editor.dataset.maxRows);
	let nextIndex = rows.children.length;
	const refresh = () => {
		rows.querySelectorAll('[data-redirect-row]').forEach(row => {
			row.querySelector('[data-redirect-remove]').hidden = false;
			const from = row.querySelector('[data-redirect-field="from"]');
			const to = row.querySelector('[data-redirect-field="to"]');
			from.required = to.required = Boolean(from.value.trim() || to.value.trim());
		});
		add.disabled = rows.children.length >= maximum;
	};
	const changed = () => { refresh(); form.dispatchEvent(new Event('input', { bubbles: true })); };
	add.hidden = false;
	add.addEventListener('click', () => {
		if (rows.children.length >= maximum) return;
		const fragment = template.content.cloneNode(true);
		fragment.querySelectorAll('[name]').forEach(control => { control.name = control.name.replace('__INDEX__', nextIndex); });
		nextIndex++;
		rows.append(fragment);
		changed();
		rows.lastElementChild.querySelector('input').focus();
	});
	rows.addEventListener('click', event => {
		const remove = event.target.closest('[data-redirect-remove]');
		if (!remove) return;
		const row = remove.closest('[data-redirect-row]');
		const next = row.nextElementSibling || row.previousElementSibling;
		row.remove();
		changed();
		if (next) next.querySelector('input').focus(); else add.focus();
	});
	rows.addEventListener('input', refresh);
	form.addEventListener('reset', () => window.setTimeout(() => {
		rows.replaceChildren(...Array.from(initial.cloneNode(true).children));
		nextIndex = rows.children.length;
		refresh();
	}, 0));
	refresh();
})();
