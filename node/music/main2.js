require('dotenv').config()

const { Player } = require('discord-player');
const { Client, GatewayIntentBits } = require('discord.js');

// Читаем аргументы командной строки: node main2.js [channelId] [radioUrl]
const args = process.argv.slice(2);
const channelId = args[0] || null;
const radioUrl = args[1] || null;

global.client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.GuildVoiceStates,
        GatewayIntentBits.MessageContent
    ],
    disableMentions: 'everyone',
});

client.config = require('./config2');

// Если переданы параметры командной строки, переопределяем настройки радио
if (channelId && radioUrl) {
    console.log('📻 Radio settings from command line arguments:');
    console.log(`   Channel ID: ${channelId}`);
    console.log(`   Radio URL: ${radioUrl}`);
    
    // Инициализируем autoRadio, если его нет
    if (!client.config.autoRadio) {
        client.config.autoRadio = {};
    }
    
    client.config.autoRadio.enabled = true;
    client.config.autoRadio.channelId = channelId;
    client.config.autoRadio.radioUrl = radioUrl;
    client.config.autoRadio.reconnectDelay = 5000;
    client.config.autoRadio.autoReconnect = true;
    
    console.log('✅ Auto-radio configuration set from command line');
} else if (channelId || radioUrl) {
    console.log('⚠️  Warning: Both channel ID and radio URL must be provided!');
    console.log('   Usage: node main2.js <channelId> <radioUrl>');
    console.log('   Example: node main2.js 1234567890123456789 http://localhost:8082/stream');
} else {
    // Проверяем настройки из config
    if (client.config.autoRadio && client.config.autoRadio.enabled) {
        console.log('📻 Auto-radio enabled from config file');
    } else {
        console.log('ℹ️  Auto-radio not configured. Use: node main2.js <channelId> <radioUrl>');
    }
}

const player = new Player(client, client.config.opt.discordPlayer);
player.extractors.loadDefault();

console.clear()
require('./loader');

client.login(client.config.app.token)
.catch(async (e) => {
    if(e.message === 'An invalid token was provided.'){
    require('./process_tools')
    .throwConfigError('app', 'token', '\n\t   ❌ Invalid Token Provided! ❌ \n\tchange the token in the config file\n')}

    else{
        console.error('❌ An error occurred while trying to login to the bot! ❌ \n', e)
    }
});