const { app, BrowserWindow, protocol, net } = require('electron');
const path = require('path');
const url = require('url');

protocol.registerSchemesAsPrivileged([
    {
        scheme: 'app',
        privileges: {
            standard: true,
            secure: true,
            supportFetchAPI: true,
            corsEnabled: true
        }
    }
]);

function createWindow() {
    const win = new BrowserWindow({
        fullscreen: true,
        autoHideMenuBar: true,
        icon: path.join(__dirname, 'icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    win.loadURL('app://localhost/index.html');

    win.webContents.on('before-input-event', (event, input) => {
        if (input.key === 'F11') {
            win.setFullScreen(!win.isFullScreen());
            event.preventDefault();
        } else if (input.key === 'Escape' && win.isFullScreen()) {
            win.setFullScreen(false);
            event.preventDefault();
        }
    });
}

app.whenReady().then(() => {
    protocol.handle('app', (request) => {
        const parsed = new URL(request.url);
        const decodedPath = decodeURIComponent(parsed.pathname);
        const relativePath = decodedPath.startsWith('/') ? decodedPath.slice(1) : decodedPath;
        const filePath = path.join(__dirname, 'app', relativePath);
        return net.fetch(url.pathToFileURL(filePath).toString());
    });

    createWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
