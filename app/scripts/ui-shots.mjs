import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { execSync, spawn } from 'child_process';
import http from 'http';
import net from 'net';

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
        const page = await browser.newPage();
        
        const outputDir = path.resolve('storage/app/ui-review');
        if (fs.existsSync(outputDir)) {
            fs.rmSync(outputDir, { recursive: true, force: true });
        }
        fs.mkdirSync(outputDir, { recursive: true });
        
        // Unauthenticated screens
        await page.goto(`${baseUrl}/login`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: path.join(outputDir, 'login.png'), fullPage: true });

        // Login
        await page.fill('#password-email', 'owner2@business.com');
        await page.fill('#password', 'password');
        await page.click('form:has(#password) button[type="submit"]');
        await page.waitForLoadState('networkidle');
        
        // Assert login succeeded
        if (page.url().endsWith('/login')) {
            const html = await page.content();
            fs.writeFileSync(path.join(outputDir, 'login-FAILED.html'), html);
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
    } finally {
        console.log("Stopping server...");
        serverProcess.kill();
        if (fs.existsSync('server.pid')) {
            fs.unlinkSync('server.pid');
        }
    }
})();
