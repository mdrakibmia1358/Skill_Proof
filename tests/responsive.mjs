// Invoked by responsive.py only: every page, account and DB is disposable.
import assert from 'node:assert/strict';
import { readFile, mkdir } from 'node:fs/promises';
import { chromium, expect } from '@playwright/test';

const fixture = JSON.parse(await readFile(process.argv[2], 'utf8'));
assert.match(fixture.base, /^http:\/\/127\.0\.0\.1:\d+$/);
const widths = [320, 375, 390, 430, 768, 1024, 1440];
const publicPages = ['/home.php', '/index.php', '/skills.php', '/recruiter.php',
  '/login.html', '/register.html', '/skills.html', '/recruiter.html',
  '/auth_system/login.php', '/auth_system/register.php', '/dashboard-app/index.html'];
const scenarios = [
  ...publicPages.map(path => ({ persona: 'anonymous', path })),
  ...['/auth_system/dashboard.php', '/auth_system/profile.php', '/home.php', '/skills.php', '/recruiter.php']
    .map(path => ({ persona: 'developer', path })),
  ...['/auth_system/dashboard.php', '/auth_system/profile.php'].map(path => ({ persona: 'empty', path })),
  ...['/auth_system/recruiter_dashboard.php', '/auth_system/profile.php', '/home.php']
    .map(path => ({ persona: 'recruiter', path })),
];
await mkdir(fixture.screenshots, { recursive: true });
const browser = await chromium.launch({ executablePath: fixture.chrome, headless: true });
let checks = 0;
const failures = [];
const contexts = new Map();
const authenticated = new Set();

async function checkLayout(page, label) {
  const issue = await page.evaluate(() => {
    const viewport = document.documentElement.clientWidth;
    const scrollWidth = Math.max(document.documentElement.scrollWidth, document.body.scrollWidth);
    const problems = [];
    if (scrollWidth > viewport + 1) problems.push(`document ${scrollWidth}px exceeds viewport ${viewport}px`);
    // Intentional tables may scroll inside their own region; all other text must fit the page.
    const isInScroller = element => {
      for (let node = element; node && node !== document.body; node = node.parentElement) {
        if (['auto', 'scroll'].includes(getComputedStyle(node).overflowX) && node.scrollWidth > node.clientWidth + 1) return true;
      }
      return false;
    };
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const node = walker.currentNode;
      if (!node.textContent.trim() || /^(SCRIPT|STYLE|OPTION|TEXTAREA)$/.test(node.parentElement.tagName)) continue;
      if (isInScroller(node.parentElement)) continue;
      const range = document.createRange();
      range.selectNodeContents(node);
      for (const rect of range.getClientRects()) {
        if (rect.width && (rect.left < -1 || rect.right > viewport + 1)) {
          problems.push(`text outside viewport: ${node.textContent.trim().slice(0, 90)}`);
          break;
        }
      }
      if (problems.length >= 6) break;
    }
    return problems;
  });
  assert.deepEqual(issue, [], `${label}: ${issue.join('; ')}`);
}

async function checkMenu(page, width, label) {
  const button = page.locator('#menuButton');
  if (!(await button.count())) return;
  const breakpoint = Number(await button.getAttribute('data-menu-breakpoint') || 768);
  const menu = page.locator('#mobileMenu');
  assert.equal(await button.isVisible(), width < breakpoint, `${label}: menu breakpoint`);
  if (width >= breakpoint) {
    assert.equal(await menu.isVisible(), false, `${label}: desktop mobile menu hidden`);
    return;
  }
  assert.equal(await button.getAttribute('aria-expanded'), 'false', `${label}: menu starts closed`);
  await button.click();
  assert.equal(await menu.isVisible(), true, `${label}: menu opens`);
  assert.equal(await button.getAttribute('aria-expanded'), 'true');
  await checkLayout(page, `${label} menu open`);
  const links = menu.locator('a');
  assert.ok(await links.count(), `${label}: menu contains navigation`);
  await links.first().click({ trial: true });
  await page.keyboard.press('Escape');
  assert.equal(await menu.isVisible(), false, `${label}: Escape closes menu`);
  assert.equal(await button.getAttribute('aria-expanded'), 'false');
  await button.click();
  await button.click();
  assert.equal(await menu.isVisible(), false, `${label}: second click closes menu`);
  await button.click();
  await page.setViewportSize({ width: Math.max(1440, breakpoint), height: 900 });
  await page.waitForFunction(() => document.getElementById('menuButton').getAttribute('aria-expanded') === 'false');
  await page.setViewportSize({ width, height: 900 });
  assert.equal(await menu.isVisible(), false, `${label}: resize resets mobile menu`);
  const anchor = menu.locator('a[href^="#"]').first();
  if (await anchor.count()) {
    const hash = await anchor.getAttribute('href');
    await button.click();
    await anchor.click();
    assert.equal(new URL(page.url()).hash, hash, `${label}: section link navigates`);
    assert.equal(await menu.isVisible(), false, `${label}: link click closes menu`);
    if (label.includes('/home.php') || label.includes('/index.php')) {
      const headingClear = await page.evaluate(hash => {
        const section = document.querySelector(hash);
        return section.querySelector('h2').getBoundingClientRect().top >= document.querySelector('header').getBoundingClientRect().bottom;
      }, hash);
      assert.ok(headingClear, `${label}: section heading clears sticky navigation`);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
  }
}

async function checkCSS(page, label) {
  assert.equal(await page.locator('script[src*="tailwindcss.com"]').count(), 0, `${label}: runtime Tailwind removed`);
  const styles = await page.locator('link[rel="stylesheet"]').evaluateAll(links => links.map(link => ({
    href: link.href, rules: link.sheet ? link.sheet.cssRules.length : 0,
  })));
  assert.ok(styles.some(style => style.href.startsWith(locationOrigin()) && style.rules > 10), `${label}: local CSS is parsed`);
  if (!label.includes('/login.html')) {
    assert.ok(styles.some(style => new URL(style.href).pathname === '/assets/css/tailwind.css' && style.rules > 100), `${label}: production Tailwind CSS is parsed`);
  }
}

function locationOrigin() { return fixture.base + '/'; }

async function checkStatuses(page) {
  const colors = {
    'Verified': 'rgb(220, 252, 231)', 'Needs Evidence': 'rgb(254, 249, 195)',
    'Low Evidence': 'rgb(254, 243, 199)', 'Not Detected': 'rgb(241, 245, 249)',
    'Not Connected': 'rgb(241, 245, 249)',
  };
  for (const [status, color] of Object.entries(colors)) {
    const badge = page.locator('td span').filter({ hasText: new RegExp(`^\\s*${status}\\s*$`) }).first();
    assert.equal(await badge.evaluate(node => getComputedStyle(node).backgroundColor), color, `${status} dynamic badge CSS`);
  }
}

try {
  for (const persona of ['anonymous', 'developer', 'empty', 'recruiter']) {
    const context = await browser.newContext({ viewport: { width: 390, height: 900 }, serviceWorkers: 'block' });
    context.setDefaultTimeout(5000);
    // No third-party request can leave the test browser, including the runtime Tailwind CDN.
    await context.route('**/*', route => new URL(route.request().url()).origin === fixture.base ? route.continue() : route.abort());
    contexts.set(persona, context);
  }
  for (const scenario of scenarios) {
    const context = contexts.get(scenario.persona);
    if (fixture.accounts[scenario.persona] && !authenticated.has(scenario.persona)) {
      // Sign in immediately before this persona's checks so the normal 5-minute idle timeout remains intact.
      const login = await context.request.get(fixture.base + '/auth_system/login.php');
      const token = (await login.text()).match(/name="csrf_token" value="([^"]+)"/)[1];
      const response = await context.request.post(fixture.base + '/auth_system/login.php', {
        form: { csrf_token: token, email: fixture.accounts[scenario.persona], password: fixture.password }, maxRedirects: 0,
      });
      assert.ok([302, 303].includes(response.status()), `${scenario.persona}: synthetic login succeeds`);
      authenticated.add(scenario.persona);
    }
    const page = await context.newPage();
    const errors = [];
    const cssResponses = [];
    const cdnRequests = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
      if (/cdn\.tailwindcss\.com/.test(request.url())) cdnRequests.push(request.url());
    });
    page.on('response', response => {
      if (response.request().resourceType() === 'stylesheet') {
        cssResponses.push({ url: response.url(), status: response.status(), type: response.headers()['content-type'] });
      }
    });
    for (const width of widths) {
      const label = `${scenario.persona} ${scenario.path} @${width}`;
      try {
        cssResponses.length = 0;
        await page.setViewportSize({ width, height: 900 });
        const response = await page.goto(fixture.base + scenario.path, { waitUntil: 'load' });
        assert.equal(response.status(), 200, `${label}: HTTP success`);
        assert.equal(new URL(page.url()).pathname, scenario.path, `${label}: correct authenticated page`);
        await checkCSS(page, label);
        assert.ok(cssResponses.length > 0, `${label}: CSS was requested`);
        for (const css of cssResponses) {
          assert.ok(css.url.startsWith(fixture.base + '/'), `${label}: stylesheet is local`);
          assert.equal(css.status, 200, `${label}: local CSS HTTP 200`);
          assert.match(css.type, /^text\/css\b/, `${label}: correct stylesheet MIME type`);
        }
        assert.deepEqual(cdnRequests, [], `${label}: no Tailwind CDN request attempted`);
        await checkLayout(page, label);
        if (width < 640) {
          const smallFields = await page.locator('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea')
            .evaluateAll(fields => fields.filter(field => field.getClientRects().length && parseFloat(getComputedStyle(field).fontSize) < 16).length);
          assert.equal(smallFields, 0, `${label}: phone form text avoids automatic input zoom`);
        }
        await checkMenu(page, width, label);
        if (scenario.persona === 'developer' && scenario.path === '/auth_system/dashboard.php') await checkStatuses(page);
        const localErrors = await page.locator('a[href*="localhost"], form[action*="localhost"]').count();
        assert.equal(localErrors, 0, `${label}: deployment-safe links and forms`);
        assert.deepEqual(errors, [], `${label}: no JavaScript errors`);
        if ([320, 390, 1440].includes(width) && ['/home.php', '/auth_system/dashboard.php', '/auth_system/profile.php', '/auth_system/recruiter_dashboard.php'].includes(scenario.path)) {
          const name = `${scenario.persona}-${scenario.path.replaceAll('/', '-').replace('.php', '')}-${width}.png`;
          await page.screenshot({ path: `${fixture.screenshots}/${name}`, fullPage: true });
          await page.screenshot({ path: `${fixture.screenshots}/viewport-${name}` });
          if (scenario.path === '/auth_system/profile.php' && width < 640) {
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.screenshot({ path: `${fixture.screenshots}/bottom-${name}` });
          }
        }
        checks++;
      } catch (error) {
        failures.push(`${label}: ${error.message}`);
        console.error(`FAIL ${failures.at(-1)}`);
        await page.screenshot({ path: `${fixture.screenshots}/failure-${failures.length}.png`, fullPage: true }).catch(() => {});
      }
    }
    await page.close();
    console.log(`Checked ${scenario.persona} ${scenario.path} at ${widths.join(', ')}px`);
  }

  // Exercise navigation and controls in real pages at both phone and desktop sizes.
  for (const width of [320, 1440]) {
    const page = await contexts.get('anonymous').newPage();
    await page.setViewportSize({ width, height: 900 });
    await page.goto(fixture.base + '/home.php');
    if (await page.locator('#menuButton').isVisible()) await page.locator('#menuButton').click();
    await page.locator('a:visible').filter({ hasText: /^Login$/ }).first().click();
    await page.waitForLoadState('load');
    assert.equal(new URL(page.url()).pathname, '/auth_system/login.php', 'Home Login navigation');
    await page.locator('button[type="submit"]').click();
    assert.equal(await page.locator('input:invalid').count() > 0, true, 'Empty login form validates');
    await page.goto(fixture.base + '/dashboard-app/index.html');
    await page.locator('#user-password').fill('StrongPassword123!');
    await expect(page.locator('#strength-meter')).toHaveCSS('background-color', 'rgb(34, 197, 94)');
    await page.locator('#show-password').check();
    assert.equal(await page.locator('#user-password').getAttribute('type'), 'text', 'Show password control');
    await page.locator('#user-email').fill('synthetic@example.test');
    await page.locator('button[type="submit"]').click();
    await checkLayout(page, `demo form success @${width}`);
    await page.close();

    const recruiter = await contexts.get('recruiter').newPage();
    await recruiter.setViewportSize({ width, height: 900 });
    await recruiter.goto(fixture.base + '/auth_system/recruiter_dashboard.php');
    await recruiter.locator('[name="skill"]').fill('PHP');
    await recruiter.locator('button[type="submit"]').click();
    await recruiter.waitForLoadState('load');
    assert.equal(new URL(recruiter.url()).searchParams.get('skill'), 'PHP', 'Recruiter filter submits');
    await checkCSS(recruiter, `recruiter filtered @${width}`);
    await checkLayout(recruiter, `recruiter filtered @${width}`);
    await recruiter.close();
    checks++;
  }
  if (failures.length) throw new Error(`${failures.length} responsive failures:\n${failures.join('\n')}`);
  console.log(`PASS ${checks} responsive viewport and interaction checks; external network blocked; local CSS parsed.`);
  console.log(`Screenshots: ${fixture.screenshots}`);
} finally {
  await browser.close();
}
