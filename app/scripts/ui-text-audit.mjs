import fs from 'fs';
import path from 'path';

const reviewDir = 'storage/app/ui-review';
const outputFile = 'storage/app/ui-review/TEXT-AUDIT.txt';

function processFiles() {
    let outputLines = [];
    if (!fs.existsSync(reviewDir)) return;
    const files = fs.readdirSync(reviewDir).filter(f => f.endsWith('.html'));
    
    // Patterns
    const regexDotted = /(?<![a-zA-Z0-9_.-])([a-z_]+(?:\.[a-z_]+)+)(?![a-zA-Z0-9_.-])/g;
    const regexWords = /\b(Lorem|ipsum|TODO|FIXME|lorem)\b/g;
    const regexLaravel = /\bLaravel\b/g;
    const regexStandalone = /(?:^|\s)(null|undefined|NaN|\[\]|\{\})(?=\s|$)/g;
    const regexTest = /\b(Test Customer|Test User)\b/g;
    const regexTrans = /(__\(|trans\()/g;

    for (const file of files) {
        const filePath = path.join(reviewDir, file);
        const html = fs.readFileSync(filePath, 'utf8');
        
        let stripped = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, m => m.replace(/[^\n]/g, ' '));
        stripped = stripped.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, m => m.replace(/[^\n]/g, ' '));
        stripped = stripped.replace(/<[^>]+>/g, m => m.replace(/[^\n]/g, ' '));
        
        const lines = stripped.split('\n');
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (!line.trim()) continue;
            
            let matches = [];
            
            let m;
            while ((m = regexDotted.exec(line)) !== null) {
                const ctx = line.substring(Math.max(0, m.index - 10), Math.min(line.length, m.index + m[0].length + 10));
                if (!ctx.includes('@') && !ctx.includes('/') && !/\d/.test(m[0]) && !ctx.includes('.js') && !ctx.includes('.css')) {
                    matches.push(m[0]);
                }
            }
            while ((m = regexWords.exec(line)) !== null) matches.push(m[1]);
            if (!file.includes('tech-stack')) {
                while ((m = regexLaravel.exec(line)) !== null) matches.push(m[0]);
            }
            while ((m = regexStandalone.exec(line)) !== null) matches.push(m[1]);
            while ((m = regexTest.exec(line)) !== null) matches.push(m[1]);
            while ((m = regexTrans.exec(line)) !== null) matches.push(m[1]);
            
            if (matches.length > 0) {
                for (const matchStr of matches) {
                    const matchIdx = line.indexOf(matchStr);
                    const start = Math.max(0, matchIdx - 30);
                    const end = Math.min(line.length, matchIdx + matchStr.length + 30);
                    let context = line.substring(start, end).replace(/\s+/g, ' ').trim();
                    outputLines.push(`${file} · ${i + 1} · ${matchStr} · ${context}`);
                }
            }
        }
    }
    fs.writeFileSync(outputFile, outputLines.join('\n') + '\n');
    console.log(`Wrote ${outputLines.length} issues to ${outputFile}`);
}

processFiles();
