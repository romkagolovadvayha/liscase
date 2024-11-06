const { Client } = require('rustrcon/src/index');
const mysql = require('mysql');

const con = mysql.createConnection({
    host: process.argv[2],
    user: process.argv[3],
    password: process.argv[4],
    database: process.argv[5]
});

function checkCommand(rcon, server, i = 0) {
    const sql = `SELECT * FROM \`rcon_tasks\` WHERE \`server_tag\` = '${server}' AND \`status\` = 0`;
    con.query(sql, function (err, result2) {
        if (err) throw err;
        for (var i = 0; i < result2.length; i++) {
            rcon.send(result2[i].command, 'Artful', 10);
            const sql = `UPDATE \`rcon_tasks\` SET \`status\`=1 WHERE \`id\` = ?`;
            con.query(sql, [result2[i].id], function (err) {
                if (err) throw err;
            });
        }
    });
    i++;
    setTimeout(() => {
        checkCommand(rcon, server, i);
    }, 3e3);
}
function connectWebRcon(ip, port, password, server) {
    const rcon = new Client({
        ip: ip,
        port: port,
        password: password
    });
    rcon.login();
    rcon.on('connected', () => {
        checkCommand(rcon, server);
    });
    rcon.on('error', err => {
        console.error(err);
        process.exit(0);
    });

    rcon.on('disconnect', () => {
        console.log('Disconnected from RCON websocket');
        process.exit(0);
    });
}

con.connect(function(err) {
    if (err) throw err;
    console.log("Connected!");
    const sql = `SELECT * FROM \`servers\` WHERE \`rcon_password\` IS NOT NULL`;
    con.query(sql, function (err, result) {
        if (err) throw err;
        for (var i = 0; i < result.length; i++) {
            connectWebRcon(result[i].ip, result[i].rcon, result[i].rcon_password, result[i].tag);
        }
    });
});
