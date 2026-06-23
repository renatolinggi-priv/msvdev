/**
 * MSV UI Audit — Automatisiertes visuelles + technisches Audit
 *
 * Verwendet Playwright um jede Seite zu besuchen, Screenshots zu machen
 * und technische CSS/DOM-Daten zu extrahieren.
 *
 * Usage:
 *   node audit.js                          # Alle Seiten, Desktop + Mobile
 *   node audit.js --desktop-only           # Nur Desktop
 *   node audit.js --mobile-only            # Nur Mobile
 *   node audit.js --page jmrang            # Nur eine bestimmte Seite
 *   node audit.js --page jmrang,heimrang   # Mehrere bestimmte Seiten
 *   node audit.js --group portal           # Nur eine Gruppe
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const config = require('./config');

// --- CLI Args ---
const args = process.argv.slice(2);
const desktopOnly = args.includes('--desktop-only');
const mobileOnly = args.includes('--mobile-only');
const pageFilterIdx = args.indexOf('--page');
const pageFilter = pageFilterIdx !== -1 ? args[pageFilterIdx + 1]?.split(',') : null;
const groupFilterIdx = args.indexOf('--group');
const groupFilter = groupFilterIdx !== -1 ? args[groupFilterIdx + 1] : null;

// --- Output Dir ---
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const outputDir = path.join(__dirname, 'results', timestamp);
const screenshotDir = path.join(outputDir, 'screenshots');

fs.mkdirSync(path.join(screenshotDir, 'desktop'), { recursive: true });
fs.mkdirSync(path.join(screenshotDir, 'mobile'), { recursive: true });

// --- Collect all pages ---
function getAllPages() {
    const all = [];
    for (const [group, pages] of Object.entries(config.pages)) {
        if (groupFilter && group !== groupFilter) continue;
        for (const page of pages) {
            if (pageFilter && !pageFilter.includes(page.id)) continue;
            all.push({ ...page, group });
        }
    }
    return all;
}

// --- Extract CSS/DOM data from page ---
async function extractPageData(page) {
    return await page.evaluate(() => {
        const data = {
            title: document.title,
            consoleErrors: [],  // filled from outside
            bodyClasses: document.body.className,
            bodyBg: getComputedStyle(document.body).backgroundColor,
            cssVars: {},
            headings: [],
            buttons: [],
            containers: [],
            tables: [],
            modals: [],
            panels: [],
            zIndexes: [],
            importantCount: 0,
        };

        // CSS Variables from :root
        const rootStyle = getComputedStyle(document.documentElement);
        const varNames = [
            '--primary-color', '--secondary-color', '--accent-color',
            '--light-color', '--dark-color', '--nav-height',
            '--cup4-primary', '--cup4-accent'
        ];
        for (const v of varNames) {
            const val = rootStyle.getPropertyValue(v).trim();
            if (val) data.cssVars[v] = val;
        }

        // Headings
        document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(h => {
            const style = getComputedStyle(h);
            if (h.offsetHeight === 0) return; // hidden
            data.headings.push({
                tag: h.tagName,
                text: h.textContent.trim().substring(0, 80),
                classes: h.className,
                color: style.color,
                fontSize: style.fontSize,
                fontWeight: style.fontWeight,
            });
        });

        // Buttons (first 30)
        let btnCount = 0;
        document.querySelectorAll('.btn, button[class]').forEach(btn => {
            if (btnCount >= 30 || btn.offsetHeight === 0) return;
            const style = getComputedStyle(btn);
            data.buttons.push({
                text: btn.textContent.trim().substring(0, 40),
                classes: btn.className,
                bg: style.backgroundColor,
                color: style.color,
                border: style.border,
                borderRadius: style.borderRadius,
                padding: style.padding,
                fontSize: style.fontSize,
            });
            btnCount++;
        });

        // Container structure
        const wrappers = document.querySelectorAll(
            '.main-content-wrapper, .content-background, .content-wrapper, ' +
            '.table-wrapper, .portal-card, .ranking-card, .container-fluid'
        );
        wrappers.forEach(w => {
            const style = getComputedStyle(w);
            data.containers.push({
                tag: w.tagName,
                classes: w.className.substring(0, 100),
                bg: style.backgroundColor,
                padding: style.padding,
                borderRadius: style.borderRadius,
                maxWidth: style.maxWidth,
                boxShadow: style.boxShadow.substring(0, 60),
            });
        });

        // Tables
        document.querySelectorAll('table').forEach(t => {
            const style = getComputedStyle(t);
            const thead = t.querySelector('thead th');
            const theadStyle = thead ? getComputedStyle(thead) : null;
            data.tables.push({
                id: t.id || '(none)',
                classes: t.className.substring(0, 80),
                rows: t.querySelectorAll('tbody tr').length,
                headerBg: theadStyle?.backgroundColor || '',
                headerColor: theadStyle?.color || '',
                headerFontSize: theadStyle?.fontSize || '',
                borderCollapse: style.borderCollapse,
            });
        });

        // Modals & Panels
        document.querySelectorAll('.modal, .edit-panel, .anlass-panel, [class*="panel"]').forEach(m => {
            const style = getComputedStyle(m);
            data.panels.push({
                classes: m.className.substring(0, 100),
                display: style.display,
                position: style.position,
                zIndex: style.zIndex,
                width: style.width,
            });
        });

        // Z-Index scan (elements with z-index != auto)
        document.querySelectorAll('*').forEach(el => {
            const z = getComputedStyle(el).zIndex;
            if (z !== 'auto' && z !== '0') {
                data.zIndexes.push({
                    tag: el.tagName,
                    classes: (el.className || '').toString().substring(0, 60),
                    zIndex: z,
                    position: getComputedStyle(el).position,
                });
            }
        });

        // Deduplicate z-indexes by class+z
        const seen = new Set();
        data.zIndexes = data.zIndexes.filter(z => {
            const key = z.classes + '|' + z.zIndex;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });

        return data;
    });
}

// --- Main ---
(async () => {
    const pages = getAllPages();
    if (pages.length === 0) {
        console.log('Keine Seiten gefunden. Prüfe --page / --group Filter.');
        process.exit(1);
    }

    console.log(`\n🔍 MSV UI Audit`);
    console.log(`   ${pages.length} Seiten | ${desktopOnly ? 'Desktop' : mobileOnly ? 'Mobile' : 'Desktop + Mobile'}`);
    console.log(`   Output: ${outputDir}\n`);

    const browser = await chromium.launch({ headless: true });
    const results = {};
    let consoleLog = [];

    // --- Helper: process one page at one viewport ---
    async function processPage(pageConfig, viewportName) {
        const vp = config.viewports[viewportName];
        const context = await browser.newContext({
            viewport: vp,
            storageState: pageConfig.noAuth ? undefined : loginState,
        });
        const page = await context.newPage();

        // Collect console errors
        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' || msg.type() === 'warning') {
                errors.push({ type: msg.type(), text: msg.text().substring(0, 200) });
            }
        });
        page.on('pageerror', err => {
            errors.push({ type: 'exception', text: err.message.substring(0, 200) });
        });

        const base = pageConfig.group === 'portal' ? (config.portalBaseUrl || config.baseUrl) : config.baseUrl;
        const url = base + pageConfig.path;
        try {
            await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
        } catch (e) {
            // networkidle timeout is OK, page might have long-polling
            if (!e.message.includes('Timeout')) throw e;
        }

        // Extra wait for AJAX content
        await page.waitForTimeout(config.waitAfterLoad);

        // Jahr-Auswahl auf dataYear wechseln (falls vorhanden)
        const yearSwitched = await page.evaluate((targetYear) => {
            const selectors = ['#yearSelect', '#year', '#yearDropdown', 'select[name="year"]'];
            for (const sel of selectors) {
                const el = document.querySelector(sel);
                if (el && el.tagName === 'SELECT') {
                    const opt = el.querySelector(`option[value="${targetYear}"]`);
                    if (opt) {
                        el.value = targetYear;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        return sel;
                    }
                }
            }
            return null;
        }, config.dataYear);

        if (yearSwitched) {
            // Warten bis AJAX-Daten nachgeladen sind
            await page.waitForTimeout(config.waitAfterLoad + 500);
        }

        // Screenshot
        const filename = `${pageConfig.id}.png`;
        await page.screenshot({
            path: path.join(screenshotDir, viewportName, filename),
            fullPage: true,
        });

        // Extract data
        const data = await extractPageData(page);
        data.consoleErrors = errors;
        data.url = url;
        data.viewport = viewportName;

        await context.close();
        return data;
    }

    // --- Login first ---
    console.log('🔐 Login...');
    let loginState;
    try {
        const loginCtx = await browser.newContext({ viewport: config.viewports.desktop });
        const loginPage = await loginCtx.newPage();
        await loginPage.goto(config.baseUrl + '/login.php', { waitUntil: 'networkidle', timeout: 10000 });

        await loginPage.fill('#username', config.login.username);
        await loginPage.fill('#password', config.login.password);
        await loginPage.click('button[type="submit"]');
        await loginPage.waitForTimeout(2000);

        // Check if login was successful
        const currentUrl = loginPage.url();
        if (currentUrl.includes('login.php')) {
            console.error('❌ Login fehlgeschlagen! Prüfe Username/Passwort in config.js');
            await browser.close();
            process.exit(1);
        }

        loginState = await loginCtx.storageState();

        // Cookies für Portal-Domain duplizieren (Cross-Subdomain Session)
        if (config.portalBaseUrl && config.portalBaseUrl !== config.baseUrl) {
            const portalDomain = new URL(config.portalBaseUrl).hostname;
            const adminDomain = new URL(config.baseUrl).hostname;
            const extraCookies = loginState.cookies
                .filter(c => c.domain === adminDomain || c.domain === '.' + adminDomain.split('.').slice(-2).join('.'))
                .map(c => ({ ...c, domain: portalDomain }));
            loginState.cookies.push(...extraCookies);
        }

        console.log('✅ Login erfolgreich\n');
        await loginCtx.close();
    } catch (e) {
        console.error('❌ Login-Fehler:', e.message);
        console.error('   Prüfe ob die Applikation unter', config.baseUrl, 'erreichbar ist');
        await browser.close();
        process.exit(1);
    }

    // --- Process all pages ---
    let completed = 0;
    for (const pageConfig of pages) {
        completed++;
        const progress = `[${completed}/${pages.length}]`;

        try {
            const pageResult = { id: pageConfig.id, label: pageConfig.label, group: pageConfig.group };

            if (!mobileOnly) {
                process.stdout.write(`${progress} 🖥️  ${pageConfig.label}...`);
                pageResult.desktop = await processPage(pageConfig, 'desktop');
                process.stdout.write(' ✅');
            }

            if (!desktopOnly) {
                process.stdout.write(`  📱 Mobile...`);
                pageResult.mobile = await processPage(pageConfig, 'mobile');
                process.stdout.write(' ✅');
            }

            console.log();
            results[pageConfig.id] = pageResult;

        } catch (e) {
            console.log(` ❌ ${e.message.substring(0, 80)}`);
            results[pageConfig.id] = {
                id: pageConfig.id,
                label: pageConfig.label,
                group: pageConfig.group,
                error: e.message.substring(0, 200),
            };
        }
    }

    await browser.close();

    // --- Write results ---
    const reportPath = path.join(outputDir, 'report.json');
    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));

    // --- Generate summary ---
    const summary = generateSummary(results);
    const summaryPath = path.join(outputDir, 'SUMMARY.md');
    fs.writeFileSync(summaryPath, summary);

    console.log(`\n✅ Audit abgeschlossen!`);
    console.log(`   📁 Screenshots: ${screenshotDir}`);
    console.log(`   📊 Report:      ${reportPath}`);
    console.log(`   📝 Summary:     ${summaryPath}`);
})();

// --- Generate Markdown Summary ---
function generateSummary(results) {
    let md = `# MSV UI Audit Report\n`;
    md += `**Datum:** ${new Date().toLocaleDateString('de-CH')}\n\n`;

    // Console errors
    md += `## Console Errors & Warnings\n\n`;
    let hasErrors = false;
    for (const [id, r] of Object.entries(results)) {
        const errors = [
            ...(r.desktop?.consoleErrors || []),
            ...(r.mobile?.consoleErrors || []),
        ];
        if (errors.length > 0) {
            hasErrors = true;
            md += `### ${r.label} (\`${id}\`)\n`;
            errors.forEach(e => {
                md += `- **${e.type}:** ${e.text}\n`;
            });
            md += `\n`;
        }
    }
    if (!hasErrors) md += `Keine Console-Errors gefunden.\n\n`;

    // CSS Variables comparison
    md += `## CSS Variables pro Seite\n\n`;
    md += `| Seite | --primary-color | --secondary-color | --nav-height |\n`;
    md += `|-------|----------------|-------------------|-------------|\n`;
    for (const [id, r] of Object.entries(results)) {
        const vars = r.desktop?.cssVars || r.mobile?.cssVars || {};
        md += `| ${r.label} | ${vars['--primary-color'] || '-'} | ${vars['--secondary-color'] || '-'} | ${vars['--nav-height'] || '-'} |\n`;
    }
    md += `\n`;

    // Body backgrounds
    md += `## Body Backgrounds\n\n`;
    const bgMap = {};
    for (const [id, r] of Object.entries(results)) {
        const bg = r.desktop?.bodyBg || r.mobile?.bodyBg || '?';
        if (!bgMap[bg]) bgMap[bg] = [];
        bgMap[bg].push(r.label);
    }
    for (const [bg, pages] of Object.entries(bgMap)) {
        md += `- **\`${bg}\`**: ${pages.join(', ')}\n`;
    }
    md += `\n`;

    // Heading styles
    md += `## Heading-Styles\n\n`;
    md += `| Seite | Tag | Text | Farbe | Größe | Gewicht |\n`;
    md += `|-------|-----|------|-------|-------|---------|\n`;
    for (const [id, r] of Object.entries(results)) {
        const headings = r.desktop?.headings || [];
        if (headings.length > 0) {
            const h = headings[0]; // first heading
            md += `| ${r.label} | ${h.tag} | ${h.text.substring(0, 30)} | ${h.color} | ${h.fontSize} | ${h.fontWeight} |\n`;
        }
    }
    md += `\n`;

    // Button styles (unique patterns)
    md += `## Button-Patterns (Stichprobe)\n\n`;
    const btnPatterns = new Map();
    for (const [id, r] of Object.entries(results)) {
        const buttons = r.desktop?.buttons || [];
        buttons.slice(0, 5).forEach(b => {
            const key = `${b.borderRadius}|${b.padding}|${b.fontSize}`;
            if (!btnPatterns.has(key)) {
                btnPatterns.set(key, { ...b, pages: [] });
            }
            btnPatterns.get(key).pages.push(r.label);
        });
    }
    md += `| border-radius | padding | font-size | Seiten |\n`;
    md += `|--------------|---------|-----------|--------|\n`;
    for (const [, p] of btnPatterns) {
        const uniquePages = [...new Set(p.pages)];
        md += `| ${p.borderRadius} | ${p.padding} | ${p.fontSize} | ${uniquePages.slice(0, 3).join(', ')}${uniquePages.length > 3 ? '...' : ''} |\n`;
    }
    md += `\n`;

    // Z-Index overview
    md += `## Z-Index Übersicht\n\n`;
    const allZ = [];
    for (const [id, r] of Object.entries(results)) {
        (r.desktop?.zIndexes || []).forEach(z => {
            allZ.push({ ...z, page: r.label });
        });
    }
    const zMap = {};
    allZ.forEach(z => {
        const key = `${z.classes}|${z.zIndex}`;
        if (!zMap[key]) zMap[key] = { ...z, pages: new Set() };
        zMap[key].pages.add(z.page);
    });
    const sortedZ = Object.values(zMap).sort((a, b) => parseInt(b.zIndex) - parseInt(a.zIndex));
    md += `| z-index | Element | Klassen | Seiten |\n`;
    md += `|---------|---------|---------|--------|\n`;
    sortedZ.slice(0, 30).forEach(z => {
        md += `| ${z.zIndex} | ${z.tag} | ${z.classes.substring(0, 40)} | ${[...z.pages].slice(0, 2).join(', ')} |\n`;
    });
    md += `\n`;

    // Tables
    md += `## Tabellen\n\n`;
    md += `| Seite | Table-ID | Rows | Header-BG | Header-Farbe | border-collapse |\n`;
    md += `|-------|----------|------|-----------|-------------|----------------|\n`;
    for (const [id, r] of Object.entries(results)) {
        (r.desktop?.tables || []).forEach(t => {
            md += `| ${r.label} | ${t.id} | ${t.rows} | ${t.headerBg} | ${t.headerColor} | ${t.borderCollapse} |\n`;
        });
    }
    md += `\n`;

    // Failed pages
    const failed = Object.values(results).filter(r => r.error);
    if (failed.length > 0) {
        md += `## Fehlgeschlagene Seiten\n\n`;
        failed.forEach(r => {
            md += `- **${r.label}**: ${r.error}\n`;
        });
        md += `\n`;
    }

    md += `---\n*Generiert am ${new Date().toLocaleString('de-CH')}*\n`;
    return md;
}
