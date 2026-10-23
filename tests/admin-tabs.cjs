/* Behavior checks with DOM stand-ins; these do not replace a browser check. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync(require('node:path').join(__dirname, '../assets/admin.js'), 'utf8');
function fixture({ hash = '', stored = '', search = '', storageBlocked = false } = {}) {
	const elements = new Map();
	let focused;
	const handlers = new Map();
	const timers = [];
	const element = (id) => {
		const attrs = new Map();
		const listeners = {};
		const node = { id, hidden: false, tabIndex: 0, listeners,
			setAttribute: (key, value) => attrs.set(key, String(value)),
			getAttribute: key => attrs.get(key),
			addEventListener: (type, handler) => { listeners[type] = handler; },
			focus: () => { focused = node; },
			closest: () => node, querySelector: () => null, querySelectorAll: () => [],
		};
		elements.set(id, node);
		return node;
	};
	const panels = ['content', 'media', 'site', 'admin', 'plugin'].map(key => element('staples-group-' + key));
	const tabs = panels.map((panel, index) => Object.assign(element('tab-' + index), { hash: '#' + panel.id, parentElement: element('li-' + index) }));
	const list = element('list');
	const navigation = Object.assign(element('navigation'), { querySelectorAll: () => tabs, querySelector: () => list });
	navigation.setAttribute('aria-label', 'Feature groups');
	const root = { querySelector: () => navigation, querySelectorAll: selector => selector === '[data-feature-options]' ? [options] : [badge], addEventListener: (type, handler) => handlers.set(type, handler) };
	const form = Object.assign(element('site-staples-settings'), { closest: () => root });
	const save = element('site-staples-save');
	const discard = element('site-staples-discard');
	const status = element('site-staples-save-status');
	status.setAttribute('data-clean', 'No unsaved changes'); status.setAttribute('data-dirty', 'You have unsaved changes');
	const options = { disabled: false, closest: () => card };
	const toggle = { checked: false, name: 'module[enabled]', form, closest: () => card };
	const badge = element('badge');
	badge.setAttribute('data-on', 'Enabled'); badge.setAttribute('data-off', 'Disabled');
	const card = { querySelector: () => toggle };
	badge.closest = () => card;
	const count = element('count'); count.setAttribute('data-template', '%1$d of %2$d enabled');
	panels[0].querySelector = () => count; panels[0].querySelectorAll = () => [toggle];
	const limit = { form, name: 'module[limit]', type: 'number', value: '10', disabled: false };
	const choice = { form, name: 'module[types][]', type: 'checkbox', checked: true, value: 'post', disabled: false };
	options.elements = [limit, choice];
	let data = [['setting', 'saved']];
	const location = { hash, pathname: '/wp-admin/options-general.php', search };
	const window = { location, addEventListener: (type, handler) => handlers.set(type, handler),
		sessionStorage: { getItem: () => { if (storageBlocked) throw Error(); return stored; }, setItem: (_, value) => { if (storageBlocked) throw Error(); stored = value; } },
		history: Object.fromEntries(['pushState', 'replaceState'].map(method => [method, (_, __, value) => { location.hash = value; }])),
		setTimeout: handler => timers.push(handler),
	};
	class FixtureFormData {
		constructor() {
			this.values = [...data, ...(toggle.checked ? [['module[enabled]', '1']] : [])];
			if (!options.disabled) this.values.push([limit.name, limit.value], ...(choice.checked ? [[choice.name, choice.value]] : []));
			form.listeners.formdata({ formData: this });
		}
		append(name, value) { this.values.push([name, value]); }
		entries() { return this.values.values(); }
	}
	vm.runInNewContext(script, { document: { getElementById: id => elements.get(id) || null }, window, URLSearchParams, FormData: FixtureFormData });
	const event = key => ({ key, preventDefault() {} });
	return { tabs, panels, form, handlers, timers, location, save, discard, status, badge, count, options, limit, choice, payload: () => Array.from(new FixtureFormData().entries()), focused: () => focused,
		enable: checked => { toggle.checked = checked; handlers.get('change')({ type: 'change', target: toggle }); },
		reset: () => { form.listeners.reset(); data = [['setting', 'saved']]; toggle.checked = false; limit.value = '10'; choice.checked = true; timers.splice(0).forEach(handler => handler()); },
		click: index => tabs[index].listeners.click(event()),
		key: (index, key) => tabs[index].listeners.keydown(event(key)),
		edit: value => { data = [['setting', value]]; handlers.get('input')({ target: { form, name: 'setting' } }); },
		invalid: index => handlers.get('invalid')({ target: { form, closest: () => panels[index] } }),
	};
}
const selected = state => state.tabs.findIndex(tab => tab.getAttribute('aria-selected') === 'true');
let state = fixture();
assert.equal(selected(state), 0);
assert.equal(state.save.disabled, true); assert.equal(state.discard.disabled, true);
assert.equal(state.status.textContent, 'No unsaved changes');
assert.equal(state.options.disabled, true);
assert.ok(state.payload().some(([name,value]) => name === 'module[types][]' && value === 'post'));
assert.ok(state.payload().some(([name,value]) => name === 'module[limit]' && value === '10'));
state.enable(true);
assert.equal(state.badge.textContent, 'Enabled'); assert.equal(state.count.textContent, '1 of 1 enabled');
assert.equal(state.options.disabled, false); assert.equal(state.save.disabled, false);
state.limit.value = '19'; state.enable(false);
assert.equal(state.options.disabled, true);
assert.ok(state.payload().some(([name,value]) => name === 'module[limit]' && value === '19'), 'switching off preserves in-session edits');
state.choice.checked = false;
assert.ok(!state.payload().some(([name]) => name === 'module[types][]'), 'unchecked disabled boxes remain unchecked in the payload');
state.edit('edited'); state.reset();
assert.equal(state.badge.textContent, 'Disabled'); assert.equal(state.count.textContent, '0 of 1 enabled');
assert.equal(state.save.disabled, true); assert.equal(state.status.getAttribute('data-dirty-state'), 'false');
assert.equal(state.panels.filter(panel => !panel.hidden).length, 1);
state.click(2);
assert.equal(selected(state), 2);
assert.equal(state.focused(), state.tabs[2]);
assert.equal(state.tabs.filter(tab => tab.tabIndex === 0).length, 1);
state.key(2, 'ArrowDown'); assert.equal(selected(state), 3);
state.key(3, 'End'); assert.equal(selected(state), 4);
state.key(4, 'ArrowDown'); assert.equal(selected(state), 0);
state.key(0, 'ArrowUp'); assert.equal(selected(state), 4);
state.key(4, 'Home'); assert.equal(selected(state), 0);
state.edit('edited'); state.click(1); assert.equal(state.status.getAttribute('data-dirty-state'), 'true');
state.edit('saved'); assert.equal(state.status.getAttribute('data-dirty-state'), 'false');
state.invalid(0);
state.invalid(2); assert.equal(selected(state), 0, 'later invalid fields cannot hide the first one');
state.timers.forEach(handler => handler()); state.invalid(2); assert.equal(selected(state), 2);
state.location.hash = '#staples-group-admin'; state.handlers.get('popstate')(); assert.equal(selected(state), 3);
state = fixture({ hash: '#staples-group-media' }); assert.equal(selected(state), 1);
state = fixture({ stored: 'staples-group-plugin' }); assert.equal(selected(state), 4);
state = fixture({ search: '?staples_notice=revision_batch', stored: 'staples-group-plugin' }); assert.equal(selected(state), 0);
state = fixture({ search: '?staples_notice=imported' }); assert.equal(selected(state), 4);
state = fixture({ storageBlocked: true }); state.click(2); assert.equal(selected(state), 2);
console.log('PASS: tab selection, roving focus, vertical arrow/Home/End navigation, retained edits, reversible unsaved feedback, validation in hidden groups, history, restored selection, action returns, unavailable storage, native switch feedback, group counts, discard, inactive controls and disabled-field value preservation (DOM stand-ins).');
