#!/usr/bin/env node

const Path = require('path');

// Поддержка аргументов командной строки: node app.js [port] [tracksPath]
const args = process.argv.slice(2);
if (args.length >= 1) {
    process.env.PORT = args[0]; // Порт
}
if (args.length >= 2) {
    const tracksPath = Path.resolve(__dirname, args[1]);
    console.log(`📁 Changing directory to: ${tracksPath}`);
    try {
        process.chdir(tracksPath);
        console.log(`✅ Working directory: ${process.cwd()}`);
    } catch (err) {
        console.error(`❌ Error changing directory: ${err.message}`);
        process.exit(1);
    }
}

require('./config');
const Hapi = require('@hapi/hapi');
const StaticFilePlugin = require('@hapi/inert');
const Routes = require('./routes');
const Engine = require('./engine');

void async function startApp() {

    try {
        const server = Hapi.server({
            port: process.env.PORT || 8080,
            host: process.env.HOST || 'localhost',
            compression: false, // Отключаем сжатие для потокового аудио
            routes: { 
                files: { relativeTo: Path.join(__dirname, 'public') },
                cors: {
                    origin: ['https://prostoj.store', 'https://moscow77.store', 'http://localhost', 'http://127.0.0.1'],
                    credentials: false
                },
                timeout: {
                    server: false, // Без таймаута сервера для потоков
                    socket: false  // Без таймаута сокета для потоков
                }
            }
        });
        await server.register(StaticFilePlugin);
        await server.register(Routes);

        Engine.start();
        await server.start();
        console.log(`🎵 Radio Server running at: ${server.info.uri}`);
        console.log(`📂 Tracks directory: ${process.cwd()}`);
    }
    catch (err) {
        console.log(`Server errored with: ${err}`);
        console.error(err.stack);
        process.exit(1);
    }
}();
