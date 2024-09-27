const { Client, GatewayIntentBits } = require("discord.js");
const mysql = require('mysql');

const client = new Client({
    intents: [GatewayIntentBits.Guilds]
});
const con = mysql.createConnection({
    host: process.argv[2],
    user: process.argv[3],
    password: process.argv[4],
    database: process.argv[5]
});
con.connect(function(err) {
    if (err) throw err;
    console.log("Connected!");
    const sql = `SELECT * FROM \`servers\` WHERE \`tag\` LIKE '${process.argv[6]}'`;
    con.query(sql, function (err, result) {
        if (err) throw err;
        client.login(result[0].discord_token).then(message => {
            void function() {
                function presence(i = 0) {
                    con.query(sql, function (err, result2) {
                        if (err) throw err;
                        var name = "Текущий онлайн: " + (result2[0].players + result2[0].joined) + "/" + result2[0].max;
                        var status = "online";
                        if (result2[0].status != 1) {
                            name = "Скоро открытие сервера";
                            status = "idle";
                        }
                        client.user.setPresence({
                            activities: [
                                {
                                    name: name,
                                    type: 4
                                }
                            ],
                            status: status
                        });
                    });
                    i++;
                    setTimeout(() => {
                        presence(i);
                    }, 30e3);
                }
                presence();
            }();
        });
    });
});
