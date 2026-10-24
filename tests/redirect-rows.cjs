/* Actual browser interaction tests with an isolated, simulated AJAX transport. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const wpRoot = process.env.WP_ROOT || path.resolve(root, '../../..');
const html = execFileSync('php', [path.join(__dirname, 'render-admin.php'), '--enabled', '--maintenance-pages'], { encoding: 'utf8' })
	.replace(/<link[^>]+file:[^>]+>/g, '').replace(/<script[^>]+file:[^>]+><\/script>/g, '');
(async () => {
	const browser = await chromium.launch({ headless: true });
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		let rules = Array.from({ length: 75 }, (_, index) => ({ from: `/old-${index}`, to: `/new-${index}`, status: 302 }));
		let version = 1;
		let failNext = false;
		let saves = 0;
		await page.route('https://example.test/**', async route => {
			if (!route.request().url().includes('admin-ajax.php')) return route.fulfill({ contentType: 'text/html', body: html });
			const input = Object.fromEntries(new URLSearchParams(route.request().postData()));
			if (failNext) { failNext = false; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ success: false, data: { message: 'Temporary save failure. Your entered paths have been kept.' } }) }); }
			if (input.operation !== 'list') {
				if (input.version !== String(version)) return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ success: false, data: { message: 'Redirects changed in another tab. Reload rules before saving.' } }) });
				const index = rules.findIndex(rule => rule.from === input.original);
				if (input.operation === 'delete') rules.splice(index, 1);
				else {
					const rule = { from: input.from, to: input.to, status: Number(input.status) };
					if (input.operation === 'add') rules.push(rule); else rules[index] = rule;
				}
				version++; saves++;
			}
			const matches = rules.filter(rule => (rule.from + ' ' + rule.to).includes(input.search));
			const pages = Math.max(1, Math.ceil(matches.length / 25));
			const current = Math.max(1, Math.min(pages, Number(input.page)));
			await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { rules: matches.slice((current - 1) * 25, current * 25), total: rules.length, matched: matches.length, pages, page: current, version: String(version) } }) });
		});
		await page.goto('https://example.test/settings');
		for (const name of ['common', 'forms', 'buttons']) await page.addStyleTag({ content: fs.readFileSync(path.join(wpRoot, name === 'buttons' ? 'wp-includes/css' : 'wp-admin/css', name + '.css'), 'utf8') });
		await page.addStyleTag({ content: fs.readFileSync(path.join(root, 'assets/admin.css'), 'utf8') });
		await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/redirects.js'), 'utf8') });
		await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'assets/admin.js'), 'utf8') });
		await page.locator('#staples-tab-site').click();
		const maintenancePage = page.locator('#staples-maintenance-page');
		assert.equal(await maintenancePage.inputValue(), '321');
		assert.equal(await maintenancePage.locator('option[value="322"], option[value="323"], option[value="324"]').count(), 0);
		const clearMaintenance = page.locator('#staples-maintenance-page-clear');
		assert.equal(await clearMaintenance.isVisible(), true);
		await clearMaintenance.click();
		assert.equal(await maintenancePage.inputValue(), '0');
		assert.equal(await clearMaintenance.isVisible(), false);
		assert.equal(await page.locator('#site-staples-save').isEnabled(), true);
		assert.equal(await page.evaluate(() => new FormData(document.getElementById('site-staples-settings')).get('site_staples_settings[maintenance][page_id]')), '0');
		await page.locator('#site-staples-discard').click();
		await clearMaintenance.waitFor({ state: 'visible' });
		assert.equal(await maintenancePage.inputValue(), '321');
		assert.equal(await clearMaintenance.isVisible(), true);
		await maintenancePage.selectOption('321');
		await page.locator('#staples-maintenance').uncheck();
		assert.equal(await maintenancePage.isDisabled(), true);
		assert.equal(await page.evaluate(() => new FormData(document.getElementById('site-staples-settings')).get('site_staples_settings[maintenance][page_id]')), '321');
		await page.locator('#staples-maintenance').check();
		assert.equal(await maintenancePage.isDisabled(), false);

		const info = page.locator('[data-redirect-info]');
		const bubble = page.locator('#staples-redirect-help');
		assert.equal(await bubble.isVisible(), false);
		await info.hover();
		assert.equal(await bubble.isVisible(), true);
		await bubble.hover();
		assert.equal(await bubble.isVisible(), true, 'help remains open while its contents are hovered');
		await page.keyboard.press('Escape');
		assert.equal(await bubble.isVisible(), false);
		await page.mouse.move(0, 0);
		await info.focus();
		assert.equal(await bubble.isVisible(), true);
		await page.keyboard.press('Escape');
		assert.equal(await bubble.isVisible(), false);
		await info.click();
		assert.equal(await bubble.isVisible(), true);
		await page.locator('#staples-heading-site').click();
		assert.equal(await bubble.isVisible(), false);
		const editor = page.locator('#staples-redirect-editor');
		const rows = editor.locator('[data-redirect-rows] [data-redirect-row]');
		const message = editor.locator('[data-redirect-message]');
		const fresh = editor.locator('[data-redirect-new]');
		const settled = () => page.waitForFunction(() => document.getElementById('staples-redirect-editor').getAttribute('aria-busy') === 'false');
		await settled();
		for (const selector of ['#staples-redirect-search', '[data-redirect-search]', '[data-redirect-reload]', '[data-redirect-open]']) {
			assert.equal(await editor.locator(selector).evaluate(el => el.getBoundingClientRect().height), 44, 'redirect toolbar control height: ' + selector);
		}
		assert.equal(await editor.locator('[data-redirect-search]').getAttribute('aria-label'), 'Search');
		assert.equal(await editor.locator('[data-redirect-search] svg').count(), 1);
		assert.equal(await rows.count(), 25);
		assert.equal(await editor.locator('[data-redirect-clear]').isVisible(), false);
		assert.equal(await editor.locator('.site-staples-redirect-pagination:visible').count(), 2);
		const editRow = async row => {
			if (!(await row.locator('[data-redirect-edit-fields]').isVisible())) {
				await row.hover(); await row.locator('[data-redirect-edit]').click();
			}
		};
		assert.equal(await rows.first().locator('[data-redirect-edit-fields]').isVisible(), false);
		assert.equal(await rows.first().locator('[data-redirect-value="from"]').textContent(), '/old-0');
		assert.equal(await rows.first().locator('[data-redirect-value="to"]').textContent(), '/new-0');
		assert.match(await rows.first().locator('[data-redirect-value="status"]').textContent(), /302/);
		await page.mouse.move(0, 0);
		assert.equal(await rows.first().locator('.site-staples-redirect-row-links').evaluate(el => getComputedStyle(el).opacity), '0');
		await rows.first().hover();
		assert.equal(await rows.first().locator('.site-staples-redirect-row-links').evaluate(el => getComputedStyle(el).opacity), '1');
		await page.mouse.move(0, 0);
		await rows.first().locator('[data-redirect-edit]').focus();
		assert.equal(await rows.first().locator('.site-staples-redirect-row-links').evaluate(el => getComputedStyle(el).opacity), '1');
		await page.keyboard.press('Enter');
		assert.equal(await rows.first().locator('[data-redirect-edit-fields]').isVisible(), true);
		await rows.first().locator('[data-redirect-cancel]').click();
		assert.equal(await rows.first().locator('[data-redirect-edit-fields]').isVisible(), false);

		// Disabling the feature locks every editor control without dropping data or drafts.
		await editRow(rows.first());
		await rows.first().locator('[data-redirect-field="to"]').fill('/draft-preserved-while-off');
		await page.locator('#staples-redirects').uncheck();
		for (const selector of ['[data-redirect-open]', '#staples-redirect-search', '[data-redirect-search]', '[data-redirect-reload]', '[data-redirect-next]', '[data-redirect-page]']) {
			assert.equal(await editor.locator(selector).first().isDisabled(), true, 'disabled redirect control: ' + selector);
		}
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').isDisabled(), true);
		assert.equal(await rows.first().locator('[data-redirect-save]').isDisabled(), true);
		assert.equal(await rows.first().locator('[data-redirect-remove]').isDisabled(), true);
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').inputValue(), '/draft-preserved-while-off');
		assert.equal(await page.evaluate(() => new FormData(document.getElementById('site-staples-settings')).get('site_staples_settings[redirects][managed]')), '1');
		assert.equal(rules[0].to, '/new-0');
		await page.locator('#staples-redirects').check();
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').isDisabled(), false);
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').inputValue(), '/draft-preserved-while-off');
		await rows.first().locator('[data-redirect-cancel]').click();
		const topPages = editor.locator('[data-redirect-pages]').first();
		assert.deepEqual(await topPages.locator('button').allTextContents(), ['1', '2', '3']);
		assert.equal(await topPages.locator('[aria-current="page"]').textContent(), '1');
		// Exercise more than five pages, direct page selection and final-page boundaries.
		rules.push(...Array.from({ length: 225 }, (_, index) => ({ from: `/old-${index + 75}`, to: `/new-${index + 75}`, status: 302 })));
		version++;
		await editor.locator('[data-redirect-reload]').click(); await settled();
		assert.deepEqual(await topPages.locator('button').allTextContents(), ['1', '2', '3', '4', '5', '12']);
		assert.match(await topPages.textContent(), /…12/);
		await topPages.locator('[data-redirect-page="12"]').click(); await settled();
		assert.deepEqual(await topPages.locator('button').allTextContents(), ['8', '9', '10', '11', '12']);
		assert.equal(await editor.locator('[data-redirect-next]').first().isDisabled(), true);
		assert.equal(await topPages.locator('[aria-current="page"]').textContent(), '12');
		assert.equal(await topPages.locator('[aria-current="page"]').evaluate(el => el === document.activeElement), true);
		await editor.locator('[data-redirect-prev]').first().click(); await settled();
		assert.equal(await topPages.locator('[aria-current="page"]').textContent(), '11');
		assert.equal(await editor.locator('[data-redirect-pages]').last().locator('[aria-current="page"]').textContent(), '11');
		await topPages.locator('[data-redirect-page="8"]').click(); await settled();
		assert.deepEqual(await topPages.locator('button').allTextContents(), ['6', '7', '8', '9', '10', '12']);
		rules = rules.slice(0, 75); version++;
		await editor.locator('[data-redirect-search]').click(); await settled();
		assert.equal(await editor.locator('[data-redirect-prev]').first().isDisabled(), true);
		await editor.locator('[data-redirect-next]').first().click(); await settled();
		assert.equal(await rows.first().locator('input').first().inputValue(), '/old-25');
		await editor.locator('#staples-redirect-search').fill('new-74');
		await editor.locator('#staples-redirect-search').press('Enter'); await settled();
		assert.equal(await rows.count(), 1);
		assert.equal(await editor.locator('.site-staples-redirect-pagination:visible').count(), 0);
		assert.match(await message.textContent(), /1 matching redirects of 75/);
		assert.equal(await editor.locator('[data-redirect-clear]').isVisible(), true);
		await editor.locator('#staples-redirect-search').fill('no-result');
		await editor.locator('[data-redirect-search]').click(); await settled();
		assert.equal(await rows.count(), 0);
		assert.equal(await editor.locator('.site-staples-redirect-pagination:visible').count(), 0);
		assert.match(await message.textContent(), /No matching/);
		failNext = true;
		await editor.locator('[data-redirect-clear]').click(); await settled();
		assert.equal(await editor.locator('[data-redirect-clear]').isVisible(), true);
		assert.equal(await editor.locator('#staples-redirect-search').inputValue(), 'no-result');
		await editor.locator('[data-redirect-clear]').click(); await settled();
		assert.equal(await editor.locator('#staples-redirect-search').inputValue(), '');
		assert.equal(await editor.locator('[data-redirect-clear]').isVisible(), false);
		assert.equal(await rows.count(), 25);
		assert.equal(await rows.first().locator('[data-redirect-value="from"]').textContent(), '/old-0');
		assert.equal(await editor.locator('[data-redirect-pages]').first().locator('[aria-current="page"]').textContent(), '1');
		assert.equal(await message.textContent(), '');
		assert.equal(await editor.locator('#staples-redirect-search').evaluate(el => el === document.activeElement), true);
		// Multiple drafts survive saving a different row, including unsubmitted main settings.
		await page.locator('#staples-maintenance-title').fill('Unsubmitted settings');
		await editRow(rows.nth(0)); await editRow(rows.nth(1));
		await rows.nth(0).locator('[data-redirect-field="to"]').fill('/changed-first');
		await rows.nth(1).locator('[data-redirect-field="to"]').fill('/unsaved-second');
		await rows.nth(0).locator('[data-redirect-save]').click(); await settled();
		assert.equal(rules[0].to, '/changed-first');
		assert.equal(await rows.first().locator('[data-redirect-edit-fields]').isVisible(), false);
		assert.equal(await rows.first().locator('[data-redirect-value="to"]').textContent(), '/changed-first');
		assert.equal(await rows.nth(1).locator('[data-redirect-field="to"]').inputValue(), '/unsaved-second');
		assert.equal(await page.locator('#staples-maintenance-title').inputValue(), 'Unsubmitted settings');
		await rows.nth(1).locator('[data-redirect-cancel]').click();
		assert.equal(await rows.nth(1).locator('[data-redirect-field="to"]').inputValue(), '/new-1');
		assert.equal(await rows.nth(1).locator('[data-redirect-save]').isDisabled(), true);
		// Stale responses and server failures retain typed paths and do not claim success.
		await editRow(rows.first());
		await rows.first().locator('[data-redirect-field="to"]').fill('/kept-on-conflict'); version++;
		await rows.first().locator('[data-redirect-save]').click(); await settled();
		assert.match(await message.textContent(), /another tab/);
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').inputValue(), '/kept-on-conflict');
		await rows.first().locator('[data-redirect-cancel]').click();
		await editor.locator('[data-redirect-reload]').click(); await settled();
		await editRow(rows.first());
		await rows.first().locator('[data-redirect-field="to"]').fill('/kept-on-failure'); failNext = true;
		await rows.first().locator('[data-redirect-save]').click(); await settled();
		assert.match(await message.textContent(), /Temporary save failure/);
		assert.equal(await rows.first().locator('[data-redirect-field="to"]').inputValue(), '/kept-on-failure');
		await rows.first().locator('[data-redirect-cancel]').click();
		// The add form starts collapsed; keyboard activation and closing manage focus and drafts.
		const openNew = editor.locator('[data-redirect-open]');
		assert.equal(await fresh.isVisible(), false);
		await openNew.focus(); await page.keyboard.press('Enter');
		assert.equal(await fresh.isVisible(), true);
		assert.equal(await openNew.getAttribute('aria-expanded'), 'true');
		assert.equal(await fresh.locator('[data-redirect-field="from"]').evaluate(element => element === document.activeElement), true);
		await fresh.locator('[data-redirect-field="from"]').fill('/discarded-draft');
		await fresh.locator('[data-redirect-close]').click();
		assert.equal(await fresh.isVisible(), false);
		assert.equal(await openNew.getAttribute('aria-expanded'), 'false');
		assert.equal(await openNew.evaluate(element => element === document.activeElement), true);
		await openNew.click();
		assert.equal(await fresh.locator('[data-redirect-field="from"]').inputValue(), '');
		// Incomplete rows fail native validation and never send a mutation request.
		await fresh.locator('[data-redirect-field="from"]').fill('/new-source');
		const previous = saves;
		await fresh.locator('[data-redirect-save]').click();
		assert.equal(saves, previous);
		await fresh.locator('[data-redirect-field="to"]').fill('/new-destination');
		await fresh.locator('[data-redirect-save]').click(); await settled();
		assert.equal(rules.length, 76);
		assert.equal(await fresh.locator('[data-redirect-field="from"]').inputValue(), '');
		assert.equal(await fresh.isVisible(), false);
		assert.equal(await openNew.evaluate(element => element === document.activeElement), true);
		// Cancellation and confirmed deletion both behave predictably.
		await editRow(rows.first());
		page.once('dialog', dialog => dialog.dismiss());
		await rows.first().locator('[data-redirect-remove]').click();
		assert.equal(rules.length, 76);
		page.once('dialog', dialog => dialog.accept());
		await rows.first().locator('[data-redirect-remove]').click(); await settled();
		assert.equal(rules.length, 75);
		assert.match(await message.textContent(), /Redirect deleted/);
		// The editor's fields are excluded from the main Settings API payload.
		assert.equal(await page.evaluate(() => Array.from(new FormData(document.getElementById('site-staples-settings')).keys()).some(key => /rules_rows|\[from\]|\[to\]/.test(key))), false);
		await page.setViewportSize({ width: 390, height: 844 });
		assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'mobile page has no horizontal overflow');
		await page.screenshot({ path: '/tmp/site-staples-scalable-mobile.png', fullPage: true });
		await page.setViewportSize({ width: 1440, height: 1000 });
		await page.screenshot({ path: '/tmp/site-staples-scalable-desktop.png', fullPage: true });
		assert.deepEqual(errors, []);
		console.log('PASS browser: read-only rows, hover/keyboard Edit actions, save/discard transitions, paging, search, empty states, independent saves, multiple drafts, conflict/failure recovery, native validation, focus, deletion, bounded settings payload and mobile overflow. AJAX transport simulated.');
	} finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
