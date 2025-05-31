const { fork } = require("child_process");
const mysql = require("mysql");

const con = mysql.createConnection({
    host: process.argv[2],
    user: process.argv[3],
    password: process.argv[4],
    database: process.argv[5]
});

const runningBots = new Map(); // tag -> child_process

function checkAndStartBots() {
    const sql = `SELECT * FROM \`servers\` WHERE \`discord_token\` IS NOT NULL AND \`discord_token\` != ''`;

    con.query(sql, (err, servers) => {
        if (err) return console.error("MySQL error:", err);

        const currentTags = new Set(servers.map(s => s.tag));

        // Запускаем ботов для новых записей
        servers.forEach(server => {
            if (!runningBots.has(server.tag)) {
                console.log(`🟢 Запускаем нового бота: ${server.tag}`);
                const child = fork("bot.js", [
                    process.argv[2], // host
                    process.argv[3], // user
                    process.argv[4], // password
                    process.argv[5], // database
                    server.tag       // server tag
                ]);

                child.on("exit", code => {
                    console.log(`🔴 Бот с тегом ${server.tag} завершился (код: ${code})`);
                    runningBots.delete(server.tag);
                });

                runningBots.set(server.tag, child);
            }
        });

        // Если нужно: можно завершать ботов, которых удалили из базы
        for (const [tag, child] of runningBots.entries()) {
            if (!currentTags.has(tag)) {
                console.log(`🛑 Сервер ${tag} удалён — останавливаем бота`);
                child.kill();
                runningBots.delete(tag);
            }
        }
    });
}

// Первичная проверка и запуск
con.connect(err => {
    if (err) throw err;
    console.log("Connected to MySQL!");
    checkAndStartBots();
    setInterval(checkAndStartBots, 60_000); // проверка каждые 60 секунд
});