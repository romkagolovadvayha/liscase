const { Client, GatewayIntentBits } = require("discord.js");
const mysql = require("mysql");

const [host, user, password, database, tag] = process.argv.slice(2);

const client = new Client({
    intents: [GatewayIntentBits.Guilds]
});

const con = mysql.createConnection({
    host,
    user,
    password,
    database
});

con.connect(function(err) {
    if (err) throw err;
    console.log(`[${tag}] Connected to MySQL`);

    const sql = `SELECT * FROM \`servers\` WHERE \`tag\` = ${con.escape(tag)}`;
    con.query(sql, function (err, result) {
        if (err) throw err;
        if (!result.length) {
            console.log(`[${tag}] No server data found.`);
            return process.exit(1);
        }

        const token = result[0].discord_token;
        client.login(token).then(() => {
            console.log(`[${tag}] Logged in as ${client.user.tag}`);
            startPresenceLoop();
        }).catch(err => {
            console.error(`[${tag}] Login failed:`, err.message);
            process.exit(1);
        });
    });
});

function startPresenceLoop() {
    function updatePresence() {
        const sql = `SELECT * FROM \`servers\` WHERE \`tag\` = ${con.escape(tag)}`;
        con.query(sql, function (err, rows) {
            if (err) return console.error(`[${tag}] DB error:`, err.message);
            if (!rows.length) return;

            const row = rows[0];
            if (row.status != 3) {
                let name = `Текущий онлайн: ${row.players + row.joined}/${row.max}`;
                let status = "online";

                if (row.status == 0) {
                    name = "Выключен";
                    status = "idle";
                } else if (row.status == 2) {
                    name = "Скоро открытие сервера";
                    status = "idle";
                }

                client.user.setPresence({
                    activities: [{ name, type: 4 }],
                    status: status
                }).catch(err => {
                    console.error(`[${tag}] Presence error:`, err.message);
                });
            }
        });

        setTimeout(updatePresence, 30_000);
    }

    updatePresence();
}