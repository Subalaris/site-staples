/* Exercise the production row controller with DOM stand-ins, without dependencies. */
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/redirects.js'), 'utf8');
let focused;
const timers = [];
const row = (index, from = '', to = '') => {
	const controls = ['from', 'to', 'status'].map((field, offset) => ({
		name: `settings[redirects][rules_rows][${index}][${field}]`, value: [from, to, '302'][offset],
		focus() { focused = this; },
	}));
	const remove = { hidden: true, closest: () => item };
	const item = {
		controls, removeControl: remove,
		querySelector(selector) { return selector.includes('remove') ? remove : selector.includes('to"') ? controls[1] : controls[0]; },
		querySelectorAll: () => controls,
		remove() { const index = rows.children.indexOf(item); rows.children.splice(index, 1); },
		get nextElementSibling() { return rows.children[rows.children.indexOf(item) + 1]; },
		get previousElementSibling() { return rows.children[rows.children.indexOf(item) - 1]; },
	};
	return item;
};
const cloneRows = children => {
	const copy = children.map(item => row(item.controls[0].name.match(/\[([^\]]+)\]\[from\]/)[1], item.controls[0].value, item.controls[1].value));
	return { children: copy, cloneNode: () => cloneRows(copy) };
};
const rows = {
	children: [row(0, '/old', '/new'), row(1)], listeners: {},
	cloneNode() { return cloneRows(this.children); },
	querySelectorAll() { return this.children; },
	append(fragment) { this.children.push(fragment); },
	replaceChildren(...children) { this.children = children; },
	addEventListener(type, callback) { this.listeners[type] = callback; },
	get lastElementChild() { return this.children.at(-1); },
};
const add = { hidden: true, listeners: {}, addEventListener(type, callback) { this.listeners[type] = callback; }, focus() { focused = this; } };
const template = { content: { cloneNode: () => row('__INDEX__') } };
const editor = { dataset: { maxRows: '3' }, querySelector: selector => selector === '[data-redirect-rows]' ? rows : selector === 'template' ? template : add };
const form = { listeners: {}, changed: 0, addEventListener(type, callback) { this.listeners[type] = callback; }, dispatchEvent() { this.changed++; } };
vm.runInNewContext(script, { document: { getElementById: id => id === 'staples-redirect-editor' ? editor : form }, window: { setTimeout: callback => timers.push(callback) }, Event: class {} });
assert.equal(add.hidden, false);
assert.equal(rows.children[0].controls[0].required, true);
assert.equal(rows.children[1].controls[0].required, false);
add.listeners.click();
assert.equal(rows.children.length, 3);
assert.equal(add.disabled, true);
assert.equal(focused, rows.children[2].controls[0]);
assert.match(rows.children[2].controls[0].name, /\[2\]\[from\]/);
add.listeners.click(); assert.equal(rows.children.length, 3);
rows.listeners.click({ target: { closest: () => rows.children[0].removeControl } });
assert.equal(rows.children.length, 2);
assert.equal(add.disabled, false);
add.listeners.click();
assert.match(rows.children[2].controls[0].name, /\[3\]\[from\]/, 'removed indices are not reused');
rows.children[0].controls[0].value = '/partial'; rows.listeners.input();
assert.equal(rows.children[0].controls[1].required, true);
while (rows.children.length) rows.listeners.click({ target: { closest: () => rows.children[0].removeControl } });
assert.equal(focused, add);
assert.ok(form.changed > 0);
form.listeners.reset(); timers.forEach(callback => callback());
assert.equal(rows.children.length, 2);
assert.equal(rows.children[0].controls[0].value, '/old');
assert.equal(rows.children[0].controls[1].value, '/new');
assert.equal(rows.children[1].controls[0].value, '');
assert.equal(add.disabled, false);
console.log('PASS: redirect add/remove, field names, focus, row limit, partial-row validation, change notification and discard restoration (DOM stand-ins).');
