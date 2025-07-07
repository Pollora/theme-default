import { defineConfig } from "vite";
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';

// Détection de l'environnement
const isDocker = process.env.IS_DOCKER || process.env.DOCKER_ENV || process.env.DDEV_PRIMARY_URL;
const port = 5173;
const publicDirectory = "../../public";
const themeName = path.basename(__dirname);

const getBaseUrl = () => {
    return process.env.APP_URL || process.env.DDEV_PRIMARY_URL || 'http://localhost';
};

const isHttps = getBaseUrl().startsWith('https');

const getDevServerConfig = () => {
    const commonConfig = {
        server: {
            port,
            strictPort: true,
            cors: {
                origin: '*',
                methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                credentials: true
            },
        }
    };

    if (isDocker) {
        return {
            server: {
                ...commonConfig.server,
                host: '0.0.0.0',
                origin: `${getBaseUrl()}:${port}`,
                hmr: {
                    protocol: isHttps ? 'wss' : 'ws',
                    host: new URL(getBaseUrl()).hostname,
                }
            },
        };
    }

    return {
        server: {
            ...commonConfig.server,
            https: isHttps,
            host: isHttps ? new URL(getBaseUrl()).hostname : 'localhost',
            hmr: {
                protocol: isHttps ? 'wss' : 'ws',
                host: new URL(getBaseUrl()).hostname
            }
        },
    };
};

const getThemeConfig = () => ({
    base: "/build/theme/" + themeName,
    input: ["./resources/assets/app.js"],
    publicDirectory,
    hotFile: path.join(publicDirectory, `${themeName}.hot`),
    buildDirectory: path.join("build", "theme", themeName),
    refresh: [
        ...refreshPaths,
        'themes/'+themeName+'/resources/views/**',
    ],
});

export default defineConfig({
    base: "/build/theme/" + themeName,
    build: {
        emptyOutDir: false,
    },
    plugins: [
        tailwindcss(),
        laravel(getThemeConfig()),
        {
            name: "blade",
            handleHotUpdate({ file, server }) {
                if (file.endsWith(".blade.php")) {
                    server.ws.send({
                        type: "full-reload",
                        path: "*",
                    });
                }
            },
        },
    ],
    ...getDevServerConfig()
});
