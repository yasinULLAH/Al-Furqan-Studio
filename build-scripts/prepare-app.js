const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

const ROOT_DIR = path.resolve(__dirname, '..');

const LIBS = [
    { url: 'https://cdn.jsdelivr.net/npm/vis-network@latest/dist/vis-network.min.js', file: 'vis-network.min.js' },
    { url: 'https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js', file: 'diff_match_patch.js' },
    { url: 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js', file: 'html2pdf.bundle.min.js' },
    { url: 'https://cdn.jsdelivr.net/npm/gun@0.2020.1240/gun.js', file: 'gun.js' },
    { url: 'https://unpkg.com/mqtt@5.3.5/dist/mqtt.min.js', file: 'mqtt.min.js' },
    { url: 'https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js', file: 'peerjs.min.js' },
    { url: 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', file: 'qrcode.min.js' },
    { url: 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js', file: 'html5-qrcode.min.js' },
    { url: 'https://cdn.jsdelivr.net/npm/docx@9.5.0/dist/index.iife.min.js', file: 'docx.iife.min.js' },
    { url: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', file: 'html2canvas.min.js' },
    { url: 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', file: 'jszip.min.js' }
];

const DATA_FILES = [
    'data new.AM',
    'dataENG.AM',
    'dataBNG.AM',
    'dataPS.AM',
    'data5 new.AM',
    'word2.AM',
    'info.csv',
    'manifest.json',
    'favicon.png',
    'icon-192.png',
    'icon-512.png'
];

const FONTS = [
    { src: path.join(ROOT_DIR, 'app_backup', 'db.onlinewebfonts.com', 't', 'f0034555c887b9dbfb67c6fd228d8f33.woff2'), name: 'f0034555c887b9dbfb67c6fd228d8f33.woff2' },
    { src: path.join(ROOT_DIR, 'app_backup', 'db.onlinewebfonts.com', 't', 'f0034555c887b9dbfb67c6fd228d8f33.woff'), name: 'f0034555c887b9dbfb67c6fd228d8f33.woff' },
    { src: path.join(ROOT_DIR, 'app_backup', 'db.onlinewebfonts.com', 't', 'f0034555c887b9dbfb67c6fd228d8f33.ttf'), name: 'f0034555c887b9dbfb67c6fd228d8f33.ttf' }
];

function download(url, dest) {
    return new Promise((resolve, reject) => {
        const client = url.startsWith('https') ? https : http;
        client.get(url, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (!redirectUrl.startsWith('http')) {
                    const u = new URL(url);
                    redirectUrl = new URL(redirectUrl, u.origin).toString();
                }
                return download(redirectUrl, dest).then(resolve).catch(reject);
            }
            if (res.statusCode !== 200) {
                return reject(new Error(`Failed to download ${url}: HTTP ${res.statusCode}`));
            }
            const fileStream = fs.createWriteStream(dest);
            res.pipe(fileStream);
            fileStream.on('finish', () => {
                fileStream.close(resolve);
            });
            fileStream.on('error', reject);
        }).on('error', reject);
    });
}

async function prepare() {
    const targetArg = process.argv[2] || 'all';

    const cacheLibsDir = path.join(ROOT_DIR, '.cache_libs');
    fs.mkdirSync(cacheLibsDir, { recursive: true });

    for (const item of LIBS) {
        const cachedFile = path.join(cacheLibsDir, item.file);
        if (!fs.existsSync(cachedFile) || fs.statSync(cachedFile).size === 0) {
            console.log(`Downloading ${item.file}...`);
            await download(item.url, cachedFile);
        }
    }

    let htmlContent = fs.readFileSync(path.join(ROOT_DIR, 'index.html'), 'utf8');

    for (const item of LIBS) {
        htmlContent = htmlContent.split(item.url).join(`libs/${item.file}`);
    }

    htmlContent = htmlContent.split('https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.woff2').join('fonts/f0034555c887b9dbfb67c6fd228d8f33.woff2');
    htmlContent = htmlContent.split('https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.woff').join('fonts/f0034555c887b9dbfb67c6fd228d8f33.woff');
    htmlContent = htmlContent.split('https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.ttf').join('fonts/f0034555c887b9dbfb67c6fd228d8f33.ttf');

    const destinations = [];
    if (targetArg === 'all' || targetArg === 'android') {
        destinations.push(path.join(ROOT_DIR, 'android', 'app', 'src', 'main', 'assets'));
    }
    if (targetArg === 'all' || targetArg === 'electron') {
        destinations.push(path.join(ROOT_DIR, 'electron', 'app'));
    }

    for (const destDir of destinations) {
        fs.mkdirSync(destDir, { recursive: true });
        const libsDir = path.join(destDir, 'libs');
        const fontsDir = path.join(destDir, 'fonts');
        fs.mkdirSync(libsDir, { recursive: true });
        fs.mkdirSync(fontsDir, { recursive: true });

        fs.writeFileSync(path.join(destDir, 'index.html'), htmlContent, 'utf8');

        for (const item of LIBS) {
            fs.copyFileSync(path.join(cacheLibsDir, item.file), path.join(libsDir, item.file));
        }

        for (const f of FONTS) {
            if (fs.existsSync(f.src)) {
                fs.copyFileSync(f.src, path.join(fontsDir, f.name));
            }
        }

        for (const fileName of DATA_FILES) {
            const srcPath = path.join(ROOT_DIR, fileName);
            if (fs.existsSync(srcPath)) {
                fs.copyFileSync(srcPath, path.join(destDir, fileName));
            }
        }
    }

    const androidDrawableDir = path.join(ROOT_DIR, 'android', 'app', 'src', 'main', 'res', 'drawable');
    fs.mkdirSync(androidDrawableDir, { recursive: true });
    if (fs.existsSync(path.join(ROOT_DIR, 'icon-192.png'))) {
        fs.copyFileSync(path.join(ROOT_DIR, 'icon-192.png'), path.join(androidDrawableDir, 'ic_launcher.png'));
    }

    const electronDir = path.join(ROOT_DIR, 'electron');
    fs.mkdirSync(electronDir, { recursive: true });
    if (fs.existsSync(path.join(ROOT_DIR, 'icon-512.png'))) {
        fs.copyFileSync(path.join(ROOT_DIR, 'icon-512.png'), path.join(electronDir, 'icon.png'));
    }

    console.log('Preparation completed successfully.');
}

prepare().catch((err) => {
    console.error(err);
    process.exit(1);
});
