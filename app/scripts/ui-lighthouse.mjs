import fs from 'fs';
import path from 'path';
import { spawn, execSync } from 'child_process';
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

        const outputDir = path.resolve('storage/app/ui-review/lighthouse');
        if (fs.existsSync(outputDir)) {
            fs.rmSync(outputDir, { recursive: true, force: true });
        }
        fs.mkdirSync(outputDir, { recursive: true });

        const pages = [
            { name: 'home', path: '/' },
            { name: 'pricing', path: '/pricing' },
            { name: 'compare', path: '/compare' },
            { name: 'login', path: '/login' },
            { name: 'review-business-2', path: '/f/review-business-2' }
        ];

        const presets = ['mobile', 'desktop'];
        const summaryLines = [];

        for (const page of pages) {
            for (const preset of presets) {
                const url = `${baseUrl}${page.path}`;
                const outFile = path.join(outputDir, `${page.name}-${preset}.json`);
                console.log(`Running lighthouse for ${page.name} (${preset})`);
                
                const presetFlag = preset === 'desktop' ? '--preset=desktop' : '';
                // The lighthouse command
                const cmd = `npx lighthouse "${url}" --chrome-flags="--headless --no-sandbox" --only-categories=performance,accessibility,best-practices,seo --output=json --output-path="${outFile}" ${presetFlag}`;
                try {
                    execSync(cmd, { 
                        stdio: 'pipe',
                        env: { ...process.env, CHROME_PATH: '/home/goaiez/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome' }
                    });
                } catch (e) {
                    console.log(`Lighthouse exited with non-zero for ${page.name} (${preset})`);
                }
                
                if (fs.existsSync(outFile)) {
                    const data = JSON.parse(fs.readFileSync(outFile, 'utf8'));
                    const cats = data.categories || {};
                    const scores = {
                        perf: Math.round((cats.performance?.score || 0) * 100),
                        a11y: Math.round((cats.accessibility?.score || 0) * 100),
                        bp: Math.round((cats['best-practices']?.score || 0) * 100),
                        seo: Math.round((cats.seo?.score || 0) * 100),
                    };
                    
                    const audits = data.audits || {};
                    // Get failing audits (score < 1 or score !== 1, maybe score !== 1 and scoreDisplayMode === 'binary' or 'numeric')
                    // We only want failed audits, sorted by weight?
                    // Actually, lighthouse doesn't export "weight" directly on the audit, weights are in the categories.
                    let failedAudits = [];
                    for (const catKey of ['performance', 'accessibility', 'best-practices', 'seo']) {
                        const cat = cats[catKey];
                        if (!cat || !cat.auditRefs) continue;
                        for (const ref of cat.auditRefs) {
                            const audit = audits[ref.id];
                            if (audit && (audit.score !== null && audit.score < 1) && ref.weight > 0) {
                                failedAudits.push({
                                    id: ref.id,
                                    title: audit.title,
                                    weight: ref.weight,
                                    score: audit.score,
                                    category: catKey
                                });
                            }
                        }
                    }
                    
                    // sort by weight desc
                    failedAudits.sort((a, b) => b.weight - a.weight);
                    
                    // top 3
                    const top3 = failedAudits.slice(0, 3).map(a => `${a.id}`).join(', ') || 'none';
                    
                    summaryLines.push(`${page.name}-${preset}: perf ${scores.perf}, a11y ${scores.a11y}, bp ${scores.bp}, seo ${scores.seo} | top fails: ${top3}`);
                }
            }
        }
        
        fs.writeFileSync(path.join(outputDir, 'SUMMARY.txt'), summaryLines.join('\n') + '\n');
        console.log("Done.");

    } finally {
        console.log("Stopping server...");
        serverProcess.kill();
        if (fs.existsSync('server.pid')) {
            fs.unlinkSync('server.pid');
        }
    }
})();
