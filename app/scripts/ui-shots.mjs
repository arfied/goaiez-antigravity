import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

(async () => {
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
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password'); // Assume standard password
    await page.click('button[type="submit"]');
    // We wait for navigation but don't hardcode /home as it might be something else, or we do hardcode it.
    await page.waitForLoadState('networkidle');
    
    // Screens to capture
    const screens = [
        { name: 'home', path: '/home' },
        { name: 'account-settings', path: '/user/profile' }, // default laravel fortify/jetstream is usually /user/profile or similar. Let's use what's asked.
        { name: 'memberships', path: '/memberships' },
        { name: 'website-builder', path: '/builder' } // just using paths requested
    ];

    for (const screen of screens) {
        // override paths according to standard or what's asked
        let screenPath = screen.path;
        if (screen.name === 'account-settings') screenPath = '/account'; // as requested
        
        await page.goto(`${baseUrl}${screenPath}`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(outputDir, `${screen.name}.png`), fullPage: true });
    }
    
    await browser.close();
})();
