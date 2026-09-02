import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

(async () => {
    // Seed the database
    console.log("Seeding database with UiReviewSeeder...");
    execSync('php artisan db:seed --class=UiReviewSeeder', { stdio: 'inherit' });

    const port = process.env.PORT || 58817;
    const baseUrl = `http://127.0.0.1:${port}`;
    const browser = await chromium.launch();
    const page = await browser.newPage();
    
    const outputDir = path.resolve('storage/app/ui-review');
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }
    
    // Unauthenticated screens
    await page.goto(`${baseUrl}/login`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: path.join(outputDir, 'login.png'), fullPage: true });

    // Login
    await page.fill('input[name="email"]', 'owner@business.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Assert login succeeded
    if (page.url().endsWith('/login')) {
        await page.screenshot({ path: path.join(outputDir, 'login-FAILED.png'), fullPage: true });
        console.error("Login failed. URL is still /login");
        await browser.close();
        process.exit(1);
    }
    
    // Screens to capture
    const screens = [
        { name: 'home', path: '/home' },
        { name: 'account-settings', path: '/account' },
        { name: 'memberships', path: '/memberships' },
        { name: 'website-builder', path: '/advanced/website-builder' }
    ];

    for (const screen of screens) {
        await page.goto(`${baseUrl}${screen.path}`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
    }
    
    await browser.close();
})();
