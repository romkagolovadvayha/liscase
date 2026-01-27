require('dotenv').config();
const WebSocket = require('ws');
const express = require('express');
const bodyParser = require('body-parser');
const mysql = require('mysql2/promise');

// Глобальный хендлинг ошибок
process.on('unhandledRejection', err => {
    console.error('🚨 UnhandledRejection:', err);
});
process.on('uncaughtException', err => {
    console.error('🚨 UncaughtException:', err);
});

const dbConfig = {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME
};

const connections = {};
const queues = {};

// Получение серверов из базы
async function getServersFromDB(db) {
    try {
        const [rows] = await db.execute(
            "SELECT * FROM `servers` WHERE `rcon_password` IS NOT NULL AND `status` <> 3"
        );
        return rows;
    } catch (err) {
        console.error("Ошибка при получении серверов из БД:", err.message);
        return [];
    }
}

// Установка WebSocket соединения
function connectWebRcon(tag, ip, port, password) {
    const connect = () => {
        try {
            const ws = new WebSocket(`ws://${ip}:${port}/${password}`);
            let isConnected = false;

            ws.on('open', () => {
                console.log(`[${tag}] ✅ Подключено`);
                connections[tag] = ws;
                isConnected = true;

                ws.on('message', data => {});
                ws.on('close', () => {
                    console.warn(`[${tag}] 🔌 Закрыто. Переподключение через 5с...`);
                    delete connections[tag];
                    setTimeout(connect, 5000);
                });
            });

            ws.on('error', err => {
                console.error(`[${tag}] ❌ Ошибка соединения: ${err.message}`);
                if (!isConnected) {
                    setTimeout(connect, 5000);
                }
            });
        } catch (err) {
            console.error(`[${tag}] ❌ Фатальная ошибка при подключении: ${err.message}`);
            setTimeout(connect, 5000);
        }
    };
    connect();
}

// Отправка команды
function sendCommand(ws, command, timeout = 3000) {
    return new Promise((resolve, reject) => {
        // Проверяем состояние WebSocket перед отправкой
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return reject(new Error('WebSocket соединение не открыто'));
        }

        const id = Math.floor(Math.random() * 1000000000);
        const payload = {
            Identifier: id,
            Message: command,
            Name: "WebRcon"
        };

        let resolved = false;

        const onMessage = data => {
            try {
                const msg = JSON.parse(data.toString());
                // Более гибкая проверка идентификатора (может быть строкой или числом)
                const msgId = msg.Identifier;
                const isMatch = msgId == id || String(msgId) === String(id);
                
                // Проверяем тип сообщения и идентификатор
                if ((msg.Type === 1 || msg.Type === "Generic" || msg.Type === 0) && isMatch) {
                    resolved = true;
                    ws.removeListener('message', onMessage);
                    const result = typeof msg.Message === 'string' ? msg.Message : JSON.stringify(msg.Message || '');
                    resolve(result);
                }
            } catch (e) {
                // Игнорируем ошибки парсинга других сообщений
            }
        };

        ws.on('message', onMessage);

        // Проверяем состояние перед отправкой
        if (ws.readyState !== WebSocket.OPEN) {
            resolved = true;
            ws.removeListener('message', onMessage);
            return reject(new Error('WebSocket соединение закрыто перед отправкой'));
        }

        try {
            ws.send(JSON.stringify(payload), err => {
                if (err) {
                    if (!resolved) {
                        resolved = true;
                        ws.removeListener('message', onMessage);
                        reject(err);
                    }
                }
            });
        } catch (err) {
            if (!resolved) {
                resolved = true;
                ws.removeListener('message', onMessage);
                reject(err);
            }
        }

        setTimeout(() => {
            if (!resolved) {
                resolved = true;
                ws.removeListener('message', onMessage);
                reject(new Error(`RCON timeout (нет ответа от сервера за ${timeout}ms)`));
            }
        }, timeout);
    });
}

// Очередь
function enqueueCommand(tag, commandFn) {
    if (!queues[tag]) queues[tag] = [];

    return new Promise((resolve, reject) => {
        queues[tag].push({ commandFn, resolve, reject });
        if (queues[tag].length === 1) {
            processQueue(tag);
        }
    });
}

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
        if (queues[tag].length > 0) processQueue(tag);
    }
}

// Создаем пул соединений
let dbPool = null;

function getDbPool() {
    if (!dbPool) {
        dbPool = mysql.createPool({
            ...dbConfig,
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0,
            enableKeepAlive: true,
            keepAliveInitialDelay: 0,
        });
    }
    return dbPool;
}

// Основной запуск
// Основной запуск
(async () => {
    const pool = getDbPool();
    console.log("✅ Пул соединений к БД создан");

    let servers;
    try {
        const connection = await pool.getConnection();
        try {
            servers = await getServersFromDB(connection);
        } finally {
            connection.release();
        }
    } catch (err) {
        console.error('❌ Ошибка подключения к БД:', err.message);
        process.exit(1);
    }

    for (const server of servers) {
        try {
            const { tag, ip, rcon: port, rcon_password: password } = server;
            connectWebRcon(tag, ip, port, password);
        } catch (err) {
            console.error(`❌ Ошибка инициализации сервера "${server.tag || server.id}": ${err.message}`);
        }
    }

    const app = express();
    app.use(bodyParser.json());

    app.post('/send', async (req, res) => {
        const { server, command } = req.body;

        if (!server || !command) {
            return res.status(400).json({ success: false, error: 'Поля server и command обязательны' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        // Таймаут для всего запроса (10 секунд)
        const requestTimeout = setTimeout(() => {
            if (!res.headersSent) {
                res.status(500).json({ success: false, error: 'Таймаут запроса (10 секунд)' });
            }
        }, 10000);

        let responseSent = false;
        const sendResponse = (data) => {
            if (!responseSent) {
                responseSent = true;
                clearTimeout(requestTimeout);
                if (!res.headersSent) {
                    res.json(data);
                }
            }
        };

        try {
            const result = await enqueueCommand(server, () => sendCommand(ws, command, 8000));
            sendResponse({ success: true, result });
        } catch (err) {
            sendResponse({ success: false, error: err.message });
        }
    });

    const PORT = 3010;
    app.listen(PORT, () => {
        console.log(`🚀 RCON API работает: http://localhost:${PORT}/send`);
    });
})();
