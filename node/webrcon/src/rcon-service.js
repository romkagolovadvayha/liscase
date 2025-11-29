require('dotenv').config();
const WebSocket = require('ws');
const express = require('express');
const bodyParser = require('body-parser');
const mysql = require('mysql2/promise');
const path = require('path');

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
const serversList = {}; // Храним информацию о серверах
const consoleListeners = new Map(); // Слушатели консольных сообщений
const systemCommandIds = new Set(); // Идентификаторы системных команд (не отображаются в консоли)

// Функции для получения данных сервера (определены глобально для использования в connectWebRcon)
let updateServerInfo = null; // Будет определена после инициализации (основная функция)
let updateServerFPS = null; // Будет определена после инициализации (для обратной совместимости)
let updateServerOnline = null; // Будет определена после инициализации (для обратной совместимости)

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

                // Получаем данные сразу после подключения
                setTimeout(() => {
                    if (updateServerInfo) updateServerInfo(tag);
                }, 2000); // Небольшая задержка для стабилизации соединения

                // Обработка всех сообщений от сервера для консоли
                // Создаем один обработчик для всех сообщений
                const messageHandler = (data) => {
                    try {
                        const msg = JSON.parse(data.toString());
                        // Пропускаем сообщения, которые уже обработаны (помечены как _processed)
                        if (msg._processed) {
                            return;
                        }
                        // Пропускаем ответы на системные команды (fps, serverinfo и т.д.)
                        const msgId = msg.Identifier || msg.identifier;
                        if (msgId && systemCommandIds.has(msgId)) {
                            return;
                        }
                        // Отправляем все сообщения слушателям консоли
                        // Type 0 или "Generic" - ответы на команды
                        // Type 2 - сообщения от сервера (логи)
                        if (consoleListeners.has(tag)) {
                            const listeners = consoleListeners.get(tag);
                            listeners.forEach(listener => {
                                try {
                                    listener({
                                        type: msg.Type || msg.type || 0,
                                        message: msg.Message || msg.message || '',
                                        timestamp: new Date().toISOString(),
                                        identifier: msgId
                                    });
                                } catch (err) {
                                    console.error(`[${tag}] Ошибка отправки сообщения слушателю:`, err.message);
                                }
                            });
                        }
                    } catch (e) {
                        // Не JSON сообщение, игнорируем
                    }
                };
                
                ws.on('message', messageHandler);
                
                ws.on('close', () => {
                    console.warn(`[${tag}] 🔌 Закрыто. Переподключение через 5с...`);
                    delete connections[tag];
                    consoleListeners.delete(tag);
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
function sendCommand(ws, command, timeout = 3000, isSystemCommand = false) {
    return new Promise((resolve, reject) => {
        const id = Math.floor(Math.random() * 1000000000);
        const payload = {
            Identifier: id,
            Message: command,
            Name: "WebRcon"
        };

        // Сохраняем идентификатор системной команды
        if (isSystemCommand) {
            systemCommandIds.add(id);
        }

        let resolved = false;

        const onMessage = data => {
            try {
                const msg = JSON.parse(data.toString());
                if ((msg.Type === 1 || msg.Type === "Generic") && msg.Identifier === id && typeof msg.Message === 'string') {
                    resolved = true;
                    ws.removeListener('message', onMessage);
                    // Удаляем идентификатор из списка системных команд после обработки
                    if (isSystemCommand) {
                        systemCommandIds.delete(id);
                    }
                    // Помечаем сообщение как обработанное, чтобы оно не отправлялось в консоль дважды
                    // через общий обработчик ws.on('message')
                    msg._processed = true;
                    resolve(msg.Message);
                }
            } catch (e) {}
        };

        ws.on('message', onMessage);

        ws.send(JSON.stringify(payload), err => {
            if (err) {
                ws.removeListener('message', onMessage);
                if (isSystemCommand) {
                    systemCommandIds.delete(id);
                }
                return reject(err);
            }
        });

        setTimeout(() => {
            if (!resolved) {
                ws.removeListener('message', onMessage);
                if (isSystemCommand) {
                    systemCommandIds.delete(id);
                }
                reject(new Error("RCON timeout (нет ответа от сервера)"));
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

// Основной запуск
// Основной запуск
(async () => {
    let db;
    try {
        db = await mysql.createConnection(dbConfig);
        console.log("✅ Подключение к БД");
    } catch (err) {
        console.error('❌ Ошибка подключения к БД:', err.message);
        process.exit(1); // можно также переподключаться с интервалом
    }

    const servers = await getServersFromDB(db);

    for (const server of servers) {
        try {
            const { tag, ip, rcon: port, rcon_password: password } = server;
            connectWebRcon(tag, ip, port, password);
        } catch (err) {
            console.error(`❌ Ошибка инициализации сервера "${server.tag || server.id}": ${err.message}`);
        }
    }

    // Сохраняем информацию о серверах
    for (const server of servers) {
        serversList[server.tag] = {
            id: server.id,
            name: server.name,
            tag: server.tag,
            ip: server.ip,
            port: server.port,
            rcon: server.rcon,
            status: server.status,
            players: server.players || 0, // Будет обновляться с сервера
            max: server.max || 0, // Будет обновляться с сервера
            fps: null // FPS будет обновляться периодически
        };
    }

    const app = express();
    app.use(bodyParser.json());
    app.use(express.static(path.join(__dirname, '..', 'public'))); // Статические файлы для веб-интерфейса

    // CORS для API
    app.use((req, res, next) => {
        res.header('Access-Control-Allow-Origin', '*');
        res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        res.header('Access-Control-Allow-Headers', 'Content-Type');
        if (req.method === 'OPTIONS') {
            return res.sendStatus(200);
        }
        next();
    });

    // Функция для получения данных сервера (FPS, онлайн и т.д.)
    updateServerInfo = async function(tag) {
        const ws = connections[tag];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return;
        }

        try {
            // Получаем FPS из команды fps (системная команда - не отображается в консоли)
            try {
                const fpsResult = await enqueueCommand(tag, () => sendCommand(ws, 'fps', 3000, true));
                // Парсим FPS из формата "240 FPS" или просто "240"
                const fpsMatch = fpsResult.match(/(\d+(?:\.\d+)?)\s*FPS/i) || fpsResult.match(/(\d+(?:\.\d+)?)/);
                if (fpsMatch && serversList[tag]) {
                    const fps = parseFloat(fpsMatch[1]);
                    if (!isNaN(fps) && fps > 0) {
                        serversList[tag].fps = fps;
                    }
                }
            } catch (fpsErr) {
                console.error(`[${tag}] Ошибка получения FPS:`, fpsErr.message);
            }

            // Получаем онлайн из команды serverinfo (системная команда - не отображается в консоли)
            try {
                const serverInfoResult = await enqueueCommand(tag, () => sendCommand(ws, 'serverinfo', 3000, true));
                
                // Парсим JSON ответ
                let serverInfo = null;
                try {
                    // Убираем возможные префиксы типа "[10:16:37]>" перед JSON
                    const jsonMatch = serverInfoResult.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        serverInfo = JSON.parse(jsonMatch[0]);
                    } else {
                        serverInfo = JSON.parse(serverInfoResult);
                    }
                } catch (parseErr) {
                    console.error(`[${tag}] Ошибка парсинга JSON serverinfo:`, parseErr.message);
                    return;
                }
                
                if (serverInfo && serversList[tag]) {
                    // Обновляем онлайн
                    if (serverInfo.Players !== undefined && serverInfo.Players !== null) {
                        const players = parseInt(serverInfo.Players);
                        if (!isNaN(players) && players >= 0) {
                            serversList[tag].players = players;
                        }
                    }
                    
                    // Обновляем максимальное количество игроков
                    if (serverInfo.MaxPlayers !== undefined && serverInfo.MaxPlayers !== null) {
                        const maxPlayers = parseInt(serverInfo.MaxPlayers);
                        if (!isNaN(maxPlayers) && maxPlayers > 0) {
                            serversList[tag].max = maxPlayers;
                        }
                    }
                }
            } catch (serverInfoErr) {
                console.error(`[${tag}] Ошибка получения serverinfo:`, serverInfoErr.message);
            }
            
            console.log(`[${tag}] Данные обновлены: FPS=${serversList[tag].fps || 'N/A'}, Игроки=${serversList[tag].players}/${serversList[tag].max}`);
        } catch (err) {
            console.error(`[${tag}] Ошибка получения данных сервера:`, err.message);
        }
    }

    // Функция для получения FPS сервера (для обратной совместимости)
    updateServerFPS = async function(tag) {
        await updateServerInfo(tag);
    }

    // Функция для получения онлайна сервера (для обратной совместимости)
    updateServerOnline = async function(tag) {
        await updateServerInfo(tag);
    }

    // Периодическое обновление данных для всех подключенных серверов (каждые 10 секунд)
    setInterval(() => {
        for (const tag in connections) {
            if (connections[tag]?.readyState === WebSocket.OPEN) {
                if (updateServerInfo) updateServerInfo(tag);
            }
        }
    }, 10000);

    // API: Получить список серверов со статусом соединений
    app.get('/api/servers', (req, res) => {
        const serversWithStatus = Object.values(serversList).map(server => {
            const serverData = {
                ...server,
                connected: connections[server.tag]?.readyState === WebSocket.OPEN,
                queueLength: queues[server.tag]?.length || 0
            };
            // Убеждаемся, что fps передается
            if (server.fps !== undefined) {
                serverData.fps = server.fps;
            }
            return serverData;
        });
        res.json({ success: true, servers: serversWithStatus });
    });

    // API: Получить статус соединений
    app.get('/api/status', (req, res) => {
        const status = {};
        for (const tag in connections) {
            status[tag] = {
                connected: connections[tag]?.readyState === WebSocket.OPEN,
                readyState: connections[tag]?.readyState,
                queueLength: queues[tag]?.length || 0
            };
        }
        res.json({ success: true, status });
    });

    // API: Получить историю команд
    app.get('/api/history', async (req, res) => {
        try {
            const limit = parseInt(req.query.limit) || 50;
            const server = req.query.server || null;
            
            console.log(`[API] Запрос истории: limit=${limit}, server=${server}`);
            
            let sql = 'SELECT * FROM `rcon_tasks` WHERE 1=1';
            const params = [];
            
            if (server) {
                sql += ' AND `server_tag` = ?';
                params.push(String(server));
            }
            
            // LIMIT должен быть числом, не параметром
            const limitValue = Math.max(1, Math.min(limit, 50));
            sql += ` ORDER BY \`created_at\` DESC LIMIT ${limitValue}`;
            
            console.log(`[API] SQL: ${sql}, params:`, params);
            
            // Выполняем запрос напрямую (без таймаута через Promise.race, так как это может вызвать проблемы)
            const [rows] = await db.execute(sql, params);
            
            console.log(`[API] История получена: ${rows.length} записей`);
            res.json({ success: true, history: rows });
        } catch (err) {
            console.error('Ошибка получения истории:', err.message);
            console.error('Stack:', err.stack);
            res.status(500).json({ success: false, error: err.message });
        }
    });

    // Функция парсинга списка плагинов из вывода команды o.plugins
    function parsePlugins(output) {
        const plugins = [];
        const pluginMap = new Map(); // Для избежания дубликатов
        
        if (!output) return plugins;
        
        // Разбиваем на строки и фильтруем пустые и разделители
        const lines = output.split('\n')
            .map(line => line.trim())
            .filter(line => line && 
                !line.startsWith('---') && 
                !line.toLowerCase().startsWith('loaded plugins:') &&
                !line.toLowerCase().startsWith('unloaded plugins:') &&
                !line.toLowerCase().startsWith('total plugins:') &&
                !line.match(/^[=\-]+$/) && // не разделитель из символов
                line.length > 0);
        
        for (const line of lines) {
            let plugin = null;
            
            // Сначала проверяем наличие слова Loaded или Unloaded в строке (независимо от позиции)
            // Важно: плагин загружен ТОЛЬКО если явно указано "Loaded"
            const statusMatch = line.match(/\b(Loaded|Unloaded)\b/i);
            const isLoaded = statusMatch ? statusMatch[1].toLowerCase() === 'loaded' : false;
            const isUnloaded = statusMatch ? statusMatch[1].toLowerCase() === 'unloaded' : false;
            
            // Формат 1: "01 "PluginName" (1.0.12) by Author (0.00s / 492 KB) - FileName.cs"
            // Это загруженный плагин (если есть номер, кавычки, версия в скобках и by Author)
            let match = line.match(/^\d+\s+"(.+?)"\s+\(([\d.]+(?:\.[\d.]+)*)\)\s+by\s+(.+?)\s+\(/i);
            if (match) {
                plugin = {
                    name: match[1].trim(),
                    version: match[2].trim(),
                    author: match[3].trim(),
                    loaded: true
                };
            } else {
                // Формат 1.1: "01 "PluginName" (1.0.12) by Author" (без времени/размера)
                match = line.match(/^\d+\s+"(.+?)"\s+\(([\d.]+(?:\.[\d.]+)*)\)\s+by\s+(.+?)(?:\s+\(|$)/i);
                if (match) {
                    plugin = {
                        name: match[1].trim(),
                        version: match[2].trim(),
                        author: match[3].trim(),
                        loaded: true
                    };
                } else {
                    // Формат 2: "31 BetterNpc - Unloaded" (отключенный плагин)
                    match = line.match(/^\d+\s+(.+?)\s+-\s+Unloaded\s*$/i);
                    if (match) {
                        plugin = {
                            name: match[1].trim(),
                            version: null,
                            author: null,
                            loaded: false
                        };
                    } else {
                        // Формат 3: "PluginName - Unloaded" (без номера)
                        match = line.match(/^(.+?)\s+-\s+Unloaded\s*$/i);
                        if (match) {
                            plugin = {
                                name: match[1].trim(),
                                version: null,
                                author: null,
                                loaded: false
                            };
                        } else {
                            // Формат 4: Старая логика для других форматов
                            // Пытаемся извлечь имя плагина, версию и автора
                            // Убираем "Unloaded" если есть
                            let cleanLine = line.replace(/\s+-\s+Unloaded\s*$/i, '').trim();
                            
                            // Пытаемся найти формат с версией в скобках: "PluginName" (1.0.0) by Author
                            match = cleanLine.match(/"(.+?)"\s+\(([\d.]+(?:\.[\d.]+)*)\)(?:\s+by\s+(.+?))?/i);
                            if (match) {
                                plugin = {
                                    name: match[1].trim(),
                                    version: match[2].trim(),
                                    author: match[3] ? match[3].trim() : null,
                                    loaded: !isUnloaded
                                };
                            } else {
                                // Убираем номер в начале, если есть
                                cleanLine = cleanLine.replace(/^\d+\s+/, '').trim();
                                
                                // Пытаемся найти версию и автора в другом формате
                                match = cleanLine.match(/^(.+?)\s+v([\d.]+(?:\.[\d.]+)*)(?:\s+by\s+(.+?))?$/i);
                                if (match) {
                                    plugin = {
                                        name: match[1].trim(),
                                        version: match[2].trim(),
                                        author: match[3] ? match[3].trim() : null,
                                        loaded: !isUnloaded
                                    };
                                } else if (cleanLine.length > 0 && 
                                    !cleanLine.match(/^\d+$/) && 
                                    !cleanLine.toLowerCase().includes('plugin') && 
                                    !cleanLine.match(/^[=\-]+$/)) {
                                    // Просто имя плагина
                                    plugin = {
                                        name: cleanLine,
                                        version: null,
                                        author: null,
                                        loaded: !isUnloaded
                                    };
                                }
                            }
                        }
                    }
                }
            }
            
            // Добавляем плагин, если он найден и еще не добавлен
            if (plugin && plugin.name) {
                const key = plugin.name.toLowerCase();
                // Если плагин уже есть, обновляем статус
                if (pluginMap.has(key)) {
                    const existing = pluginMap.get(key);
                    // Обновляем статус (если новый статус явно указан как Unloaded, то отключаем)
                    if (isUnloaded) {
                        existing.loaded = false;
                    } else if (!existing.loaded) {
                        // Если плагин был помечен как unloaded, но теперь найден без "Unloaded", значит он загружен
                        existing.loaded = true;
                    }
                    // Обновляем версию и автора, если они есть
                    if (plugin.version) {
                        existing.version = plugin.version;
                    }
                    if (plugin.author) {
                        existing.author = plugin.author;
                    }
                } else {
                    pluginMap.set(key, plugin);
                    plugins.push(plugin);
                }
            }
        }
        
        // Сортируем по имени для удобства
        plugins.sort((a, b) => a.name.localeCompare(b.name));
        
        return plugins;
    }

    // API: Получить список плагинов сервера
    app.get('/api/plugins', async (req, res) => {
        const { server } = req.query;
        
        if (!server) {
            return res.status(400).json({ success: false, error: 'Параметр server обязателен' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            // Получаем список всех плагинов (загруженных и отключенных)
            const pluginsResult = await enqueueCommand(server, () => sendCommand(ws, 'o.plugins'));
            
            // Логируем сырой вывод для отладки
            if (pluginsResult && pluginsResult.length > 0) {
                console.log(`[${server}] Сырой вывод o.plugins:\n${pluginsResult}`);
            }
            
            const plugins = parsePlugins(pluginsResult);
            console.log(`[${server}] Найдено плагинов: ${plugins.length}`);
            // Логируем статусы плагинов для отладки
            plugins.forEach(p => {
                console.log(`[${server}] Плагин: ${p.name}, Загружен: ${p.loaded}`);
            });
            
            // Пытаемся получить список всех установленных плагинов через o.loaded
            // Это покажет все плагины, которые были загружены хотя бы раз
            try {
                const loadedResult = await enqueueCommand(server, () => sendCommand(ws, 'o.loaded'));
                const loadedPlugins = parsePlugins(loadedResult);
                
                // Объединяем списки, убирая дубликаты
                const pluginMap = new Map();
                
                // Сначала добавляем все из o.plugins
                plugins.forEach(plugin => {
                    pluginMap.set(plugin.name.toLowerCase(), plugin);
                });
                
                // Затем добавляем из o.loaded те, которых нет
                loadedPlugins.forEach(plugin => {
                    const key = plugin.name.toLowerCase();
                    if (!pluginMap.has(key)) {
                        pluginMap.set(key, plugin);
                    } else {
                        // Обновляем статус, если плагин загружен
                        const existing = pluginMap.get(key);
                        if (plugin.loaded) {
                            existing.loaded = true;
                        }
                    }
                });
                
                // Преобразуем обратно в массив и сортируем
                const allPlugins = Array.from(pluginMap.values()).sort((a, b) => 
                    a.name.localeCompare(b.name)
                );
                
                console.log(`[${server}] Всего плагинов после объединения: ${allPlugins.length}`);
                res.json({ success: true, plugins: allPlugins });
            } catch (loadedErr) {
                // Если o.loaded не работает, используем только o.plugins
                console.warn(`[${server}] o.loaded не доступен, используем только o.plugins:`, loadedErr.message);
                res.json({ success: true, plugins });
            }
        } catch (err) {
            console.error(`[${server}] Ошибка получения плагинов:`, err.message);
            res.json({ success: false, error: err.message });
        }
    });

    // API: Управление плагином (unload/reload/load)
    app.post('/api/plugins/:action', async (req, res) => {
        const { server, plugin } = req.body;
        const { action } = req.params;
        
        if (!server || !plugin) {
            return res.status(400).json({ success: false, error: 'Поля server и plugin обязательны' });
        }

        if (!['unload', 'reload', 'load'].includes(action)) {
            return res.status(400).json({ success: false, error: 'Недопустимое действие. Используйте: unload, reload, load' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            const command = `o.${action} ${plugin}`;
            const result = await enqueueCommand(server, () => sendCommand(ws, command));
            
            // Сохраняем в историю
            try {
                // Ограничиваем длину результата для базы данных и приводим к строке
                let resultStr = '';
                if (result !== null && result !== undefined) {
                    resultStr = typeof result === 'string' ? result : JSON.stringify(result);
                    if (resultStr.length > 65535) {
                        resultStr = resultStr.substring(0, 65535);
                    }
                }
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, resultStr, 1]
                );
            } catch (err) {
                console.error('Ошибка сохранения в историю:', err.message);
                console.error('Параметры:', { server, command, resultType: typeof result });
            }
            
            res.json({ success: true, result });
        } catch (err) {
            // Сохраняем ошибку в историю
            try {
                let errorMessage = '';
                if (err && err.message) {
                    errorMessage = String(err.message);
                } else if (err) {
                    errorMessage = String(err);
                }
                if (errorMessage.length > 65535) {
                    errorMessage = errorMessage.substring(0, 65535);
                }
                const errorCommand = `o.${action} ${plugin}`;
                const serverStr = String(server || '');
                const commandStr = String(errorCommand || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, errorMessage, 0]
                );
            } catch (dbErr) {
                console.error('Ошибка сохранения в историю:', dbErr.message);
            }
            
            res.json({ success: false, error: err.message });
        }
    });

    // Функция парсинга списка админов из вывода команды ownerlist
    function parseAdmins(output) {
        const admins = [];
        if (!output) return admins;
        
        const lines = output.split('\n');
        let inPlayersSection = false;
        let playersLine = '';
        
        for (const line of lines) {
            const trimmedLine = line.trim();
            
            // Определяем начало секции с игроками
            if (trimmedLine.toLowerCase().includes("group 'admin' players:") || 
                trimmedLine.toLowerCase().includes("group \"admin\" players:")) {
                inPlayersSection = true;
                // Извлекаем строку после "Group 'admin' players:"
                const colonIndex = trimmedLine.indexOf(':');
                if (colonIndex !== -1) {
                    playersLine = trimmedLine.substring(colonIndex + 1).trim();
                }
                continue;
            }
            
            // Определяем конец секции с игроками (начало секции с правами)
            if (trimmedLine.toLowerCase().includes("group 'admin' permissions:") ||
                trimmedLine.toLowerCase().includes("group \"admin\" permissions:")) {
                // Парсим накопленную строку с игроками перед выходом из секции
                if (playersLine) {
                    parsePlayersLine(playersLine, admins);
                    playersLine = '';
                }
                inPlayersSection = false;
                continue;
            }
            
            // Пропускаем пустые строки и служебные строки
            if (!trimmedLine || 
                trimmedLine.match(/^[=\-]+$/) ||
                trimmedLine.toLowerCase().includes('no permissions') ||
                trimmedLine.toLowerCase().includes('no players')) {
                continue;
            }
            
            // Парсим только в секции игроков
            if (inPlayersSection) {
                // Если строка не пустая, добавляем её к накопленной строке
                // (на случай, если список админов разбит на несколько строк)
                if (trimmedLine) {
                    if (playersLine) {
                        playersLine += ', ' + trimmedLine;
                    } else {
                        playersLine = trimmedLine;
                    }
                }
            }
        }
        
        // Если осталась накопленная строка, парсим её
        if (playersLine && inPlayersSection) {
            parsePlayersLine(playersLine, admins);
        }
        
        return admins;
    }
    
    // Вспомогательная функция для парсинга строки с игроками
    function parsePlayersLine(playersLine, admins) {
        if (!playersLine) return;
        
        // Разбиваем строку по запятым
        // Формат: "76561198037069011 (Arty), 76561199615706587 (daaqq), ..."
        const players = playersLine.split(',').map(p => p.trim()).filter(p => p);
        
        for (const player of players) {
            // Формат: "76561199615706587 (daaqq)" или "76561199615706587"
            // Ищем Steam ID (17 цифр) и опционально имя в скобках
            const match = player.match(/^(\d{17})(?:\s+\((.+?)\))?/);
            if (match) {
                const steamId = match[1];
                const name = match[2] ? match[2].trim() : null;
                
                // Проверяем, что это не дубликат
                if (!admins.find(a => a.steamId === steamId)) {
                    admins.push({
                        steamId: steamId,
                        name: name
                    });
                }
            }
        }
    }

    // API: Получить список админов сервера
    app.get('/api/admins', async (req, res) => {
        const { server } = req.query;
        
        if (!server) {
            return res.status(400).json({ success: false, error: 'Параметр server обязателен' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            // Используем команду oxide.show group admin для получения списка админов
            // Увеличиваем таймаут до 10 секунд, так как команда может выполняться долго
            const result = await enqueueCommand(server, () => sendCommand(ws, 'oxide.show group admin', 10000, true));
            console.log(`[${server}] oxide.show group admin результат:`, result);
            const admins = parseAdmins(result);
            
            res.json({ success: true, admins });
        } catch (err) {
            console.error(`[${server}] Ошибка получения списка админов:`, err.message);
            res.json({ success: false, error: err.message });
        }
    });

    // API: Добавить админа
    app.post('/api/admins/add', async (req, res) => {
        const { server, steamId } = req.body;
        
        if (!server || !steamId) {
            return res.status(400).json({ success: false, error: 'Поля server и steamId обязательны' });
        }

        // Проверка формата Steam ID
        if (!/^\d{17}$/.test(steamId)) {
            return res.status(400).json({ success: false, error: 'Неверный формат Steam ID. Должно быть 17 цифр' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            const command = `ownerid ${steamId}`;
            const result = await enqueueCommand(server, () => sendCommand(ws, command));
            
            // Сохраняем в историю
            try {
                let resultStr = '';
                if (result !== null && result !== undefined) {
                    resultStr = typeof result === 'string' ? result : JSON.stringify(result);
                    if (resultStr.length > 65535) {
                        resultStr = resultStr.substring(0, 65535);
                    }
                }
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, resultStr, 1]
                );
            } catch (err) {
                console.error('Ошибка сохранения в историю:', err.message);
            }
            
            res.json({ success: true, result });
        } catch (err) {
            // Сохраняем ошибку в историю
            try {
                let errorMessage = '';
                if (err && err.message) {
                    errorMessage = String(err.message);
                } else if (err) {
                    errorMessage = String(err);
                }
                if (errorMessage.length > 65535) {
                    errorMessage = errorMessage.substring(0, 65535);
                }
                const command = `ownerid ${steamId}`;
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, errorMessage, 0]
                );
            } catch (dbErr) {
                console.error('Ошибка сохранения в историю:', dbErr.message);
            }
            
            res.json({ success: false, error: err.message });
        }
    });

    // API: Удалить админа
    app.post('/api/admins/remove', async (req, res) => {
        const { server, steamId } = req.body;
        
        if (!server || !steamId) {
            return res.status(400).json({ success: false, error: 'Поля server и steamId обязательны' });
        }

        // Проверка формата Steam ID
        if (!/^\d{17}$/.test(steamId)) {
            return res.status(400).json({ success: false, error: 'Неверный формат Steam ID. Должно быть 17 цифр' });
        }

        const ws = connections[server];
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            return res.status(400).json({ success: false, error: 'Нет активного соединения с сервером' });
        }

        try {
            const command = `removeowner ${steamId}`;
            const result = await enqueueCommand(server, () => sendCommand(ws, command));
            
            // Сохраняем в историю
            try {
                let resultStr = '';
                if (result !== null && result !== undefined) {
                    resultStr = typeof result === 'string' ? result : JSON.stringify(result);
                    if (resultStr.length > 65535) {
                        resultStr = resultStr.substring(0, 65535);
                    }
                }
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, resultStr, 1]
                );
            } catch (err) {
                console.error('Ошибка сохранения в историю:', err.message);
            }
            
            res.json({ success: true, result });
        } catch (err) {
            // Сохраняем ошибку в историю
            try {
                let errorMessage = '';
                if (err && err.message) {
                    errorMessage = String(err.message);
                } else if (err) {
                    errorMessage = String(err);
                }
                if (errorMessage.length > 65535) {
                    errorMessage = errorMessage.substring(0, 65535);
                }
                const command = `removeowner ${steamId}`;
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, errorMessage, 0]
                );
            } catch (dbErr) {
                console.error('Ошибка сохранения в историю:', dbErr.message);
            }
            
            res.json({ success: false, error: err.message });
        }
    });

    // API: Отправить команду
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
            // Отправляем команду в консольные слушатели (только один раз)
            if (consoleListeners.has(server)) {
                const listeners = consoleListeners.get(server);
                listeners.forEach(listener => {
                    try {
                        listener({
                            type: 'command',
                            message: command,
                            timestamp: new Date().toISOString()
                        });
                    } catch (err) {
                        console.error(`[${server}] Ошибка отправки команды в консоль:`, err.message);
                    }
                });
            }
            
            // Выполняем команду - результат автоматически попадет в консоль через ws.on('message')
            const result = await enqueueCommand(server, () => sendCommand(ws, command));
            
            // Сохраняем в историю
            try {
                // Ограничиваем длину результата для базы данных и приводим к строке
                const truncatedResult = result && typeof result === 'string' && result.length > 65535 ? result.substring(0, 65535) : (result || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [String(server || ''), String(command || ''), String(truncatedResult), 1]
                );
            } catch (err) {
                console.error('Ошибка сохранения в историю:', err.message);
            }
            
            return res.json({ success: true, result });
        } catch (err) {
            // Отправляем ошибку в консольные слушатели
            if (consoleListeners.has(server)) {
                const listeners = consoleListeners.get(server);
                listeners.forEach(listener => {
                    try {
                        listener({
                            type: 'error',
                            message: `Ошибка: ${err.message}`,
                            timestamp: new Date().toISOString()
                        });
                    } catch (listenerErr) {
                        console.error(`[${server}] Ошибка отправки ошибки в консоль:`, listenerErr.message);
                    }
                });
            }
            
            // Сохраняем ошибку в историю
            try {
                let errorMessage = '';
                if (err && err.message) {
                    errorMessage = String(err.message);
                } else if (err) {
                    errorMessage = String(err);
                }
                if (errorMessage.length > 65535) {
                    errorMessage = errorMessage.substring(0, 65535);
                }
                const serverStr = String(server || '');
                const commandStr = String(command || '');
                await db.execute(
                    'INSERT INTO `rcon_tasks` (`server_tag`, `command`, `result`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())',
                    [serverStr, commandStr, errorMessage, 0]
                );
            } catch (dbErr) {
                console.error('Ошибка сохранения в историю:', dbErr.message);
            }
            
            return res.json({ success: false, error: err.message });
        }
    });

    // API: Подписка на консольные сообщения (Server-Sent Events)
    app.get('/api/console/:server', (req, res) => {
        const { server } = req.params;
        
        // Настройка Server-Sent Events
        res.setHeader('Content-Type', 'text/event-stream');
        res.setHeader('Cache-Control', 'no-cache');
        res.setHeader('Connection', 'keep-alive');
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('X-Accel-Buffering', 'no'); // Отключаем буферизацию в nginx

        if (!server) {
            res.write(`data: ${JSON.stringify({ type: 'error', message: 'Сервер не указан' })}\n\n`);
            res.end();
            return;
        }

        if (!connections[server] || connections[server].readyState !== WebSocket.OPEN) {
            res.write(`data: ${JSON.stringify({ type: 'error', message: 'Сервер не подключен' })}\n\n`);
            res.end();
            return;
        }

        // Отправляем начальное сообщение о подключении сразу
        try {
            res.write(`data: ${JSON.stringify({ type: 'info', message: 'Подключено к консоли сервера' })}\n\n`);
        } catch (err) {
            console.error(`[${server}] Ошибка отправки начального сообщения:`, err.message);
            res.end();
            return;
        }

        // Добавляем слушателя
        if (!consoleListeners.has(server)) {
            consoleListeners.set(server, []);
        }

        const listener = (message) => {
            try {
                if (!res.destroyed && res.writable) {
                    res.write(`data: ${JSON.stringify(message)}\n\n`);
                }
            } catch (err) {
                // Клиент отключился
                const listeners = consoleListeners.get(server);
                if (listeners) {
                    const index = listeners.indexOf(listener);
                    if (index > -1) {
                        listeners.splice(index, 1);
                    }
                }
            }
        };

        consoleListeners.get(server).push(listener);

        // Периодическая отправка ping для поддержания соединения
        const pingInterval = setInterval(() => {
            try {
                if (!res.destroyed && res.writable) {
                    res.write(`: ping\n\n`);
                } else {
                    clearInterval(pingInterval);
                }
            } catch (err) {
                clearInterval(pingInterval);
            }
        }, 30000); // каждые 30 секунд

        // Обработка отключения клиента
        req.on('close', () => {
            clearInterval(pingInterval);
            const listeners = consoleListeners.get(server);
            if (listeners) {
                const index = listeners.indexOf(listener);
                if (index > -1) {
                    listeners.splice(index, 1);
                }
            }
            if (!res.destroyed) {
                res.end();
            }
        });
    });

    // Главная страница веб-интерфейса
    app.get('/', (req, res) => {
        res.sendFile(path.join(__dirname, '..', 'public', 'index.html'));
    });

    const PORT = process.env.PORT || 3010;
    app.listen(PORT, () => {
        console.log(`🚀 RCON API работает: http://localhost:${PORT}`);
        console.log(`📊 Веб-интерфейс: http://localhost:${PORT}/`);
    });
})();
