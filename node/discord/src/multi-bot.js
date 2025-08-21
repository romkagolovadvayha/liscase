// node multi-bots-all.js <dbHost> <dbUser> <dbPass> <dbName>
// пример: node multi-bots-all.js 127.0.0.1 root pass prostoj

const { Client, GatewayIntentBits } = require("discord.js");
const mysql = require("mysql");

const [,, DB_HOST, DB_USER, DB_PASS, DB_NAME] = process.argv;
if (!DB_HOST || !DB_USER || !DB_PASS || !DB_NAME) {
    console.error("Usage: node multi-bots-all.js <dbHost> <dbUser> <dbPass> <dbName>");
    process.exit(1);
}

const pool = mysql.createPool({
    host: DB_HOST,
    user: DB_USER,
    password: DB_PASS,
    database: DB_NAME,
    connectionLimit: 10,
    charset: "utf8mb4"
});

const PRESENCE_INTERVAL_MS = 30_000;
const bots = new Map(); // server_id -> { client, timer }

function q(sql, params = []) {
    return new Promise((resolve, reject) => {
        pool.query(sql, params, (err, rows) => (err ? reject(err) : resolve(rows)));
    });
}

async function fetchServers() {
    // Берём все сервера, у которых задан токен
    return q(
        `SELECT id, tag, players, joined, \`max\`, \`status\`, discord_token
     FROM \`servers\`
     WHERE discord_token IS NOT NULL AND discord_token <> ''`
    );
}

function computePresenceRow(row) {
    let name = `Текущий онлайн: ${Number(row.players) + Number(row.joined)}/${row.max}`;
    let state = "online";
    if (row.status == 0) { name = "Выключен"; state = "idle"; }
    else if (row.status == 2) { name = "Скоро открытие сервера"; state = "idle"; }
    // status == 3 — ничего не ставим (как в исходнике)
    return { name, state, skip: row.status == 3 };
}

async function updatePresence(serverId, client) {
    try {
        const [row] = await q(
            "SELECT players, joined, `max`, `status` FROM `servers` WHERE `id` = ? LIMIT 1",
            [serverId]
        );
        if (!row) return;

        const { name, state, skip } = computePresenceRow(row);
        if (skip) return;

        await client.user.setPresence({
            activities: [{ name, type: 4 }], // 4 = Custom Status (как у вас)
            status: state
        });
    } catch (e) {
        console.error(`[${serverId}] presence update error:`, e.message);
    }
}

function startBotForServer(serverRow) {
    if (bots.has(serverRow.id)) return; // уже запущен

    const client = new Client({ intents: [GatewayIntentBits.Guilds] });

    client.once("ready", () => {
        console.log(`[${serverRow.tag || serverRow.id}] logged in as ${client.user.tag}`);

        // первый апдейт сразу
        updatePresence(serverRow.id, client);

        // периодические апдейты с небольшим джиттером
        const jitter = Math.floor(Math.random() * 5000);
        const timer = setInterval(
            () => updatePresence(serverRow.id, client),
            PRESENCE_INTERVAL_MS + jitter
        );

        bots.set(serverRow.id, { client, timer });
    });

    client.on("error", (e) => console.error(`[${serverRow.tag || serverRow.id}] client error:`, e));
    client.on("shardError", (e) => console.error(`[${serverRow.tag || serverRow.id}] shard error:`, e));

    client.login(serverRow.discord_token).catch(err => {
        console.error(`[${serverRow.tag || serverRow.id}] login failed:`, err.message);
    });
}

(async () => {
    try {
        const servers = await fetchServers();

        if (!servers.length) {
            console.log("Нет серверов с discord_token.");
            process.exit(0);
        }

        // не запускаем два клиента с одним токеном
        const seenTokens = new Set();
        for (const s of servers) {
            if (seenTokens.has(s.discord_token)) {
                console.warn(`[${s.tag || s.id}] пропущен: дублирующийся discord_token`);
                continue;
            }
            seenTokens.add(s.discord_token);
            startBotForServer(s);
        }
    } catch (e) {
        console.error("Startup error:", e);
        process.exit(1);
    }
})();

// Аккуратное завершение
function shutdown() {
    console.log("Shutting down...");
    for (const { client, timer } of bots.values()) {
        clearInterval(timer);
        client.destroy();
    }
    pool.end(() => process.exit(0));
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
process.on("unhandledRejection", (r) => console.error("UnhandledRejection:", r));
