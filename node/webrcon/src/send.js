const { Client } = require('rustrcon/src/index');

const rcon = new Client({
    ip: process.argv[2],
    port: process.argv[3],
    password: process.argv[4]
});

rcon.login();

rcon.on('connected', () => {
    rcon.send(process.argv[5], 'Artful', 10);

    setTimeout(() => {
        rcon.destroy();
    }, 5000);
});

rcon.on('error', err => {
    console.error(err);
    process.exit(0);
});

rcon.on('disconnect', () => {
    console.log('Disconnected from RCON websocket');
    process.exit(0);
});

rcon.on('message', message => {
    console.log(message);
    process.exit(0);
});
