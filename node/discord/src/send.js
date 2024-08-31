const { Client, GatewayIntentBits } = require("discord.js");
const client = new Client({
    intents: [GatewayIntentBits.Guilds]
});

client.login(process.argv[3]).then(message => {
    client.user.setPresence({
        activities: [
            {
                name: process.argv[2],
                type: 4
            }
        ],
        status: 'online'
    });
   setTimeout(() => {
        client.destroy();
        process.exit(0);
    }, 10);
});