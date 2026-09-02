import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import fs from 'fs';
import path from 'path';
import { execSync, spawn } from 'child_process';
import http from 'http';
import net from 'net';


const summaryLines = [];
let onlyRegex = null;
const onlyArg = process.argv.find(arg => arg.startsWith('--only='));
if (onlyArg) {
    onlyRegex = new RegExp(onlyArg.split('=')[1]);
}

function shouldCapture(name) {
    if (!onlyRegex) return true;
    return onlyRegex.test(name);
}

async function runAxe(page, name, outputDir) {
    const axeDir = path.join(outputDir, 'axe');
    if (!fs.existsSync(axeDir)) {
        fs.mkdirSync(axeDir, { recursive: true });
    }
    const results = await new AxeBuilder({ page }).analyze();
    const violations = results.violations.map(v => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodes: v.nodes.slice(0, 3).map(n => ({
            target: n.target,
            html: n.html
        }))
    }));
    fs.writeFileSync(path.join(axeDir, `${name}.json`), JSON.stringify(violations, null, 2));

    const counts = { critical: 0, serious: 0, moderate: 0, minor: 0 };
    violations.forEach(v => {
        if (counts[v.impact] !== undefined) {
            counts[v.impact]++;
        }
    });
    const summaryLine = `${name}  critical ${counts.critical}  serious ${counts.serious}  moderate ${counts.moderate}  minor ${counts.minor}`;
    summaryLines.push(summaryLine);
}

function getFreePort() {
    return new Promise((resolve, reject) => {
        const server = net.createServer();
        server.unref();
        server.on('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const port = server.address().port;
            server.close(() => {
                resolve(port);
            });
        });
    });
}

function waitForServer(url) {
    return new Promise((resolve) => {
        const check = () => {
            http.get(url, (res) => {
                if (res.statusCode === 200 || res.statusCode === 302 || res.statusCode === 404) {
                    resolve();
                } else {
                    setTimeout(check, 100);
                }
            }).on('error', () => {
                setTimeout(check, 100);
            });
        };
        check();
    });
}

(async () => {
    // Seed the database
    console.log("Seeding database with UiReviewSeeder...");
    execSync('php artisan db:seed --class=UiReviewSeeder', { stdio: 'inherit' });

    const port = process.env.PORT || await getFreePort();
    const baseUrl = `http://127.0.0.1:${port}`;
    
    console.log(`Starting server on ${baseUrl}...`);
    const serverProcess = spawn('php', ['artisan', 'serve', `--port=${port}`], {
        stdio: 'ignore'
    });

    try {
        await waitForServer(`${baseUrl}/login`);
        console.log("Server is ready.");

        const browser = await chromium.launch();
        const context = await browser.newContext();
        const page = await context.newPage();
        
        const outputDir = path.resolve('storage/app/ui-review');
        if (fs.existsSync(outputDir)) {
            fs.rmSync(outputDir, { recursive: true, force: true });
        }
        fs.mkdirSync(outputDir, { recursive: true });
        
        // Unauthenticated screens
        await page.setViewportSize({ width: 1280, height: 720 });


        if (shouldCapture('error-404')) {
            await page.goto(`${baseUrl}/this-does-not-exist`);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, 'error-404.png'), fullPage: true });
            await runAxe(page, 'error-404', outputDir);
        }

        if (shouldCapture('error-419')) {
        const r = await page.request.post(baseUrl + '/login', { form: { email: 'x' }, headers: { 'X-Requested-With': '' } });
        if (r.status() !== 419) {
            console.error(`419 capture failed, status was ${r.status()}`);
            // Let the test print to REPORT.md if needed, but for now we set it to what it is.
        }
        await page.setContent(await r.text());
        await page.screenshot({ path: path.join(outputDir, 'error-419.png'), fullPage: true });
        await runAxe(page, 'error-419', outputDir);
        }

        await page.goto(`${baseUrl}/`);
        await page.waitForLoadState('networkidle');
        if (shouldCapture('home')) {
            await page.screenshot({ path: path.join(outputDir, 'home.png'), fullPage: true });
            await runAxe(page, 'home', outputDir);
        }

        const footerLinks = await page.$$eval('footer a', anchors => anchors.map(a => ({ text: a.textContent.trim(), href: a.href })));
        const targetTexts = ['Pricing', 'What it does', 'Compare', 'Guarantee', 'Questions', 'Customers', 'Affiliates', 'Agencies'];
        const publicUrls = targetTexts.map(text => {
            const link = footerLinks.find(l => l.text === text);
            return link ? link.href : null;
        }).filter(Boolean);
        
        const startFreeLink = await page.$$eval('a', anchors => {
            const link = anchors.find(a => a.textContent.trim() === 'Start free');
            return link ? link.href : null;
        });
        if (startFreeLink) publicUrls.push(startFreeLink);
        
        const publicScreens = publicUrls.map(url => {
            const urlObj = new URL(url);
            let slug = urlObj.pathname.replace(/^\/+|\/+$/g, '').replace(/\//g, '-');
            if (!slug) slug = 'home';
            return { name: `public-${slug}`, url };
        });

        for (const screen of publicScreens) {
            if (!shouldCapture(screen.name)) continue;
            await page.goto(screen.url);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
            await runAxe(page, screen.name, outputDir);
        }

        
        const customerScreens = [
            { name: 'customer-feedback', path: '/f/review-business-2' },
            { name: 'customer-thanks', path: '/f/review-business-2/thanks' },
            { name: 'customer-to-google', path: '/f/review-business-2/to/google' },
            { name: 'customer-review-hub', path: '/r/review-business-2' },
            { name: 'customer-unsubscribe', path: '/mail/unsubscribe/eyJpdiI6Ikp4ZWNFMnF6TVl2UENGTmVoMVIrVFE9PSIsInZhbHVlIjoicGlrb01FUEZNVSsxRGhYakNITHZOS0I2SDlGUGtybDZvQ1dzS2dzYXJGRDRPaENoR2dkaG5ZQTJvQ0J1YzR4Z05nQzk4YTljS2wxdjJEeGUrdERYRE9UUkljcWw1bVM1L0N1SmpCaW9hMGM9IiwibWFjIjoiZDFiZjRmNzAxMzVlZjQyNTA0NDM5ZTZjNGFiZTcxODk5YjU3MDhiZDA4NDQ4ZTRjYjQ0YmRjMmUyOTlmMTA2YSIsInRhZyI6IiJ9' },
            { name: 'customer-legal-terms', path: '/legal/terms' },
            { name: 'customer-sms-terms', path: '/sms-terms' },
            { name: 'customer-privacy', path: '/privacy' }
        ];

        for (const screen of customerScreens) {
            if (!shouldCapture(screen.name)) continue;
            await page.goto(`${baseUrl}${screen.path}`);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
            await runAxe(page, screen.name, outputDir);
        }

        await page.goto(`${baseUrl}/login`);
        await page.waitForLoadState('networkidle');
        if (shouldCapture('login')) {
            await page.screenshot({ path: path.join(outputDir, 'login.png'), fullPage: true });
            await runAxe(page, 'login', outputDir);
        }

        // Login
        await page.fill('#password-email', 'owner2@business.com');
        await page.fill('#password', 'password');
        await page.click('form:has(#password) button[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Assert login succeeded
        if (page.url().endsWith('/login')) {
            const html = await page.content();
            fs.writeFileSync(path.join(outputDir, 'login-FAILED.html'), html);
            if (shouldCapture('login-FAILED')) {
                await page.screenshot({ path: path.join(outputDir, 'login-FAILED.png'), fullPage: true });
                await runAxe(page, 'login-FAILED', outputDir);
            }
            console.error("Login failed. URL is still /login");
            await browser.close();
            process.exit(1);
        }
        
        // Screens to capture
        const screens = [
            { name: 'account-home', path: '/home' },
            { name: 'account-settings', path: '/account' },
            { name: 'memberships', path: '/memberships' },
            { name: 'website-builder', path: '/advanced/website-builder' },
            { name: 'account-inbox', path: '/account/inbox' },
            { name: 'account-customers', path: '/account/customers' },
            { name: 'account-messages', path: '/account/messages' },
            { name: 'account-plan', path: '/account/plan' },
            { name: 'account-support', path: '/account/support' },
            { name: 'account-connections', path: '/account/connections' },
            { name: 'advanced-home', path: '/advanced' },
            { name: 'advanced-citations', path: '/advanced/citations' },
            { name: 'advanced-visibility', path: '/advanced/visibility' },
            { name: 'advanced-broadcasts', path: '/advanced/broadcasts' },
            { name: 'advanced-posts', path: '/advanced/posts' },
            { name: 'advanced-competitors', path: '/advanced/competitors' },
            { name: 'advanced-reports', path: '/advanced/reports' },
            { name: 'advanced-voice', path: '/advanced/voice' },
            { name: 'advanced-integrations', path: '/advanced/integrations' },
            { name: 'advanced-settings', path: '/advanced/settings' }
        ];
        for (const screen of screens) {
            if (!shouldCapture(screen.name)) continue;
            await page.goto(`${baseUrl}${screen.path}`);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
            await runAxe(page, screen.name, outputDir);
        }

        if (shouldCapture('error-404-signed-in')) {
            await page.goto(`${baseUrl}/this-does-not-exist`);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, 'error-404-signed-in.png'), fullPage: true });
            await runAxe(page, 'error-404-signed-in', outputDir);
        }

        if (shouldCapture('error-403')) {
            await page.goto(`${baseUrl}/admin/settings`);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: path.join(outputDir, 'error-403.png'), fullPage: true });
            await runAxe(page, 'error-403', outputDir);
        }

        await browser.close();

        // Mobile pass
        const mobileBrowser = await chromium.launch();
        const mobileContext = await mobileBrowser.newContext();
        const mobilePage = await mobileContext.newPage();
        await mobilePage.setViewportSize({ width: 390, height: 844 });

        if (shouldCapture('home@390')) {
            await mobilePage.goto(`${baseUrl}/`);
            await mobilePage.waitForLoadState('networkidle');
            await mobilePage.screenshot({ path: path.join(outputDir, 'home@390.png'), fullPage: true });
            await runAxe(mobilePage, 'home@390', outputDir);
        }

        for (const screen of publicScreens) {
            if (!shouldCapture(`${screen.name}@390`)) continue;
            await mobilePage.goto(screen.url);
            await mobilePage.waitForLoadState('networkidle');
            await mobilePage.screenshot({ path: path.join(outputDir, `${screen.name}@390.png`), fullPage: true });
            await runAxe(mobilePage, `${screen.name}@390`, outputDir);
        }

        
        for (const screen of customerScreens) {
            if (!shouldCapture(`${screen.name}@390`)) continue;
            await mobilePage.goto(`${baseUrl}${screen.path}`);
            await mobilePage.waitForLoadState('networkidle');
            await mobilePage.screenshot({ path: path.join(outputDir, `${screen.name}@390.png`), fullPage: true });
            await runAxe(mobilePage, `${screen.name}@390`, outputDir);
        }

        await mobilePage.goto(`${baseUrl}/login`);
        await mobilePage.waitForLoadState('networkidle');
        if (shouldCapture('login@390')) {
            await mobilePage.screenshot({ path: path.join(outputDir, 'login@390.png'), fullPage: true });
            await runAxe(mobilePage, 'login@390', outputDir);
        }

        await mobilePage.fill('#password-email', 'owner2@business.com');
        await mobilePage.fill('#password', 'password');
        await mobilePage.click('form:has(#password) button[type="submit"]');
        await mobilePage.waitForLoadState('networkidle');

        const mobileScreens = [
            { name: 'account-home', path: '/home' },
            { name: 'account-settings', path: '/account' },
            { name: 'memberships', path: '/memberships' },
            { name: 'account-inbox', path: '/account/inbox' }
        ];

        for (const screen of mobileScreens) {
            if (!shouldCapture(`${screen.name}@390`)) continue;
            await mobilePage.goto(`${baseUrl}${screen.path}`);
            await mobilePage.waitForLoadState('networkidle');
            await mobilePage.screenshot({ path: path.join(outputDir, `${screen.name}@390.png`), fullPage: true });
            await runAxe(mobilePage, `${screen.name}@390`, outputDir);
        }

        await mobileBrowser.close();
        
        const setupBrowser = await chromium.launch();
        const setupContext = await setupBrowser.newContext();
        const setupPage = await setupContext.newPage();
        
        await setupPage.goto(`${baseUrl}/login`);
        await setupPage.waitForLoadState('networkidle');
        await setupPage.fill('#password-email', 'setup@business.com');
        await setupPage.fill('#password', 'password');
        await setupPage.click('form:has(#password) button[type="submit"]');
        await setupPage.waitForLoadState('networkidle');

        const setupScreens = [
            { name: 'setup-index', path: '/setup' },
            { name: 'setup-welcome', path: '/setup/welcome' },
            { name: 'setup-find-business', path: '/setup/find-business' },
            { name: 'setup-how-customers-reach', path: '/setup/how-customers-reach' },
            { name: 'setup-review-rules', path: '/setup/review-rules' },
            { name: 'setup-done', path: '/setup/done' }
        ];

        for (const screen of setupScreens) {
            if (!shouldCapture(screen.name)) continue;
            await setupPage.goto(`${baseUrl}${screen.path}`);
            await setupPage.waitForLoadState('networkidle');
            await setupPage.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
            await runAxe(setupPage, screen.name, outputDir);
        }
        
        const setupMobileContext = await setupBrowser.newContext({ viewport: { width: 390, height: 844 } });
        const setupMobilePage = await setupMobileContext.newPage();
        
        await setupMobilePage.goto(`${baseUrl}/login`);
        await setupMobilePage.waitForLoadState('networkidle');
        await setupMobilePage.fill('#password-email', 'setup@business.com');
        await setupMobilePage.fill('#password', 'password');
        await setupMobilePage.click('form:has(#password) button[type="submit"]');
        await setupMobilePage.waitForLoadState('networkidle');

        for (const screen of setupScreens) {
            if (!shouldCapture(`${screen.name}@390`)) continue;
            await setupMobilePage.goto(`${baseUrl}${screen.path}`);
            await setupMobilePage.waitForLoadState('networkidle');
            await setupMobilePage.screenshot({ path: path.join(outputDir, `${screen.name}@390.png`), fullPage: true });
            await runAxe(setupMobilePage, `${screen.name}@390`, outputDir);
        }
        await setupBrowser.close();

        
        const locationId = execSync('php artisan tinker --execute="echo App\\\\Models\\\\Location::withoutGlobalScopes()->first()->id;"').toString().trim();
        
        const staffBrowser = await chromium.launch();
        const staffContext = await staffBrowser.newContext();
        const staffPage = await staffContext.newPage();
        
        await staffPage.goto(`${baseUrl}/login`);
        await staffPage.waitForLoadState('networkidle');
        await staffPage.fill('#password-email', 'staff@business.com');
        await staffPage.fill('#password', 'password');
        await Promise.all([staffPage.waitForNavigation(), staffPage.click('form:has(#password) button[type="submit"]')]);
        await staffPage.waitForLoadState('networkidle');
        console.log('Desktop URL after password:', staffPage.url());
        if (staffPage.url().includes('two-factor-challenge')) {
            await staffPage.fill('#recovery_code', '12345-67890');
            await Promise.all([staffPage.waitForNavigation(), staffPage.click('form:has(#recovery_code) button[type="submit"]')]);
            await staffPage.waitForLoadState('networkidle');
            console.log('Desktop URL after 2FA:', staffPage.url());
        }

        const staffScreens = [
            { name: 'staff-settings', path: '/admin/settings' },
            { name: 'staff-credentials', path: '/admin/credentials' },
            { name: 'staff-audit-account', path: '/admin/audit/account' },
            { name: 'staff-audit-staff', path: '/admin/audit/staff' },
            { name: 'staff-internal-users', path: '/admin/internal-users' },
            { name: 'staff-legal', path: '/admin/legal' },
            { name: 'staff-phi-tenants', path: '/admin/phi-tenants' },
            { name: 'staff-tenant-locations', path: '/admin/tenant-locations' },
            { name: 'staff-terms-acceptances', path: '/admin/terms-acceptances' },
            { name: 'staff-location-reviews', path: `/admin/locations/${locationId}/reviews` }
        ];

        for (const screen of staffScreens) {
            if (!shouldCapture(screen.name)) continue;
            await staffPage.goto(`${baseUrl}${screen.path}`);
            await staffPage.waitForLoadState('networkidle');
            await staffPage.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
            await runAxe(staffPage, screen.name, outputDir);
        }
        
        const staffMobileContext = await staffBrowser.newContext({ viewport: { width: 390, height: 844 } });
        const staffMobilePage = await staffMobileContext.newPage();
        
        await staffMobilePage.goto(`${baseUrl}/login`);
        await staffMobilePage.waitForLoadState('networkidle');
        await staffMobilePage.fill('#password-email', 'staff@business.com');
        await staffMobilePage.fill('#password', 'password');
        await Promise.all([staffMobilePage.waitForNavigation(), staffMobilePage.click('form:has(#password) button[type="submit"]')]);
        await staffMobilePage.waitForLoadState('networkidle');
        if (staffMobilePage.url().includes('two-factor-challenge')) {
            await staffMobilePage.fill('#recovery_code', '09876-54321');
            await Promise.all([staffMobilePage.waitForNavigation(), staffMobilePage.click('form:has(#recovery_code) button[type="submit"]')]);
            await staffMobilePage.waitForLoadState('networkidle');
        }

        const urlAfterLoginMobile = staffMobilePage.url();
        if (urlAfterLoginMobile.endsWith('/login') || urlAfterLoginMobile.includes('two-factor-challenge')) {
            const html = await staffMobilePage.content();
            fs.writeFileSync(path.join(outputDir, 'staff-login-FAILED@390.html'), html);
            if (shouldCapture('staff-login-FAILED@390')) {
                await staffMobilePage.screenshot({ path: path.join(outputDir, 'staff-login-FAILED@390.png'), fullPage: true });
                await runAxe(staffMobilePage, 'staff-login-FAILED@390', outputDir);
            }
            console.error("Mobile staff login failed. URL is " + urlAfterLoginMobile);
            await staffBrowser.close();
            process.exit(1);
        }

        for (const screen of staffScreens) {
            if (!shouldCapture(`${screen.name}@390`)) continue;
            await staffMobilePage.goto(`${baseUrl}${screen.path}`);
            await staffMobilePage.waitForLoadState('networkidle');
            await staffMobilePage.screenshot({ path: path.join(outputDir, `${screen.name}@390.png`), fullPage: true });
            await runAxe(staffMobilePage, `${screen.name}@390`, outputDir);
        }
        await staffBrowser.close();

        fs.writeFileSync(path.join(outputDir, 'axe', 'SUMMARY.txt'), summaryLines.join('\n') + '\n');

    } finally {
        console.log("Stopping server...");
        serverProcess.kill();
        if (fs.existsSync('server.pid')) {
            fs.unlinkSync('server.pid');
        }
    }
})();
