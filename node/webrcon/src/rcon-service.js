require('dotenv').config();
const WebSocket = require('ws');
const express = require('express');
const bodyParser = require('body-parser');
const mysql = require('mysql2/promise');

// Чтение конфигурации БД из .env
const dbConfig = {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME
};

const connections = {}; // tag => WebSocket
const queues = {}; // tag => очередь команд

// Получение серверов из базы
async function getServersFromDB(db) {
    const [rows] = await db.execute(
        "SELECT * FROM `servers` WHERE `rcon_password` IS NOT NULL AND `status` <> 3"
    );
    return rows;
}

// Установка WebSocket соединения
function connectWebRcon(tag, ip, port, password) {
    const connect = () => {
        const ws = new WebSocket(`ws://${ip}:${port}/${password}`);
        let isConnected = false;

        ws.on('open', () => {
            console.log(`[${tag}] ✅ Подключено`);
            connections[tag] = ws;
            isConnected = true;

            ws.on('message', data => {
                // Проброс сообщений в текущую очередь
                // (обрабатываются в sendCommand)
            });

            ws.on('close', () => {
                console.warn(`[${tag}] 🔌 Соединение закрыто, попытка переподключения через 5 секунд...`);
                delete connections[tag];
                setTimeout(connect, 5000);
            });

            ws.on('error', err => {
                console.error(`[${tag}] ❌ Ошибка: ${err.message}`);
                // Не удаляем соединение здесь, оно удаляется в on('close')
            });
        });

        ws.on('error', err => {
            if (!isConnected) {
                console.warn(`[${tag}] ❌ Сервер недоступен, повтор через 5 секунд...`);
                setTimeout(connect, 5000);
            }
        });
    };

    try {
        connect();
    } catch (ex) {
        console.log(`error: ${ex.message}`);
    }
}

// Отправка команды в RCON
function sendCommand(ws, command, timeout = 3000) {
    return new Promise((resolve, reject) => {
        const id = Math.floor(Math.random() * 1000000000); // уникальный ID
        const payload = {
            Identifier: id,
            Message: command,
            Name: "WebRcon"
        };

        let resolved = false;

        function onMessage(data) {
            try {
                const msg = JSON.parse(data.toString());
                // Сравниваем именно по Identifier
                if ((msg.Type === 1 || msg.Type === "Generic") && msg.Identifier === id && typeof msg.Message === 'string') {
                    resolved = true;
                    ws.removeListener('message', onMessage);
                    resolve(msg.Message);
                }
            } catch (e) {}
        }

        ws.on('message', onMessage);

        ws.send(JSON.stringify(payload), err => {
            if (err) {
                ws.removeListener('message', onMessage);
                return reject(err);
            }
        });

        setTimeout(() => {
            if (!resolved) {
                ws.removeListener('message', onMessage);
                reject(new Error("RCON timeout (нет ответа от сервера)"));
            }
        }, timeout);
    });
}

// Добавление задачи в очередь
function enqueueCommand(tag, commandFn) {
    if (!queues[tag]) {
        queues[tag] = [];
    }

    return new Promise((resolve, reject) => {
        queues[tag].push({ commandFn, resolve, reject });
        if (queues[tag].length === 1) {
            processQueue(tag);
        }
    });
}

// Выполнение очереди
async function processQueue(tag) {
    const task = queues[tag][0];
    if (!task) return;

    try {
        const result = await task.commandFn();
        task.resolve(result);
    } catch (err) {
        task.reject(err);
    } finally {
        queues[tag].shift();
        if (queues[tag].length > 0) {
            processQueue(tag);
        }
    }
}

// Основной запуск
(async () => {
    const db = await mysql.createConnection(dbConfig);
    console.log("✅ Подключение к БД");

    const servers = await getServersFromDB(db);

    for (const server of servers) {
        const { tag, ip, rcon: port, rcon_password: password } = server;
        connectWebRcon(tag, ip, port, password);
    }

    const app = express();
    app.use(bodyParser.json());

    // HTTP POST /send
    app.post('/send', async (req, res) => {
        const { server, command } = req.body;

        if (!server || !command) {
            return res.status(400).json({ success: false, error: 'Поля server и command обязательны' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            const result = await enqueueCommand(server, () => sendCommand(ws, command));
            return res.json({ success: true, result });
        } catch (err) {
            return res.json({ success: false, error: err.message });
        }
    });

    const PORT = 3010;
    app.listen(PORT, () => {
        console.log(`🚀 RCON API работает: http://localhost:${PORT}/send`);
    });
})();
