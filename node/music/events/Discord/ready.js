const { Translate } = require('../../process_tools');
const { useMainPlayer } = require('discord-player');

module.exports = async (client) => {
    console.log(await Translate(`Logged to the client <${client.user.username}>.`));
    console.log(await Translate("Let's play some music !"));
    
    client.user.setActivity(client.config.app.playing);
    
    // Автоматическое подключение к радио
    if (client.config.autoRadio && client.config.autoRadio.enabled) {
        const channelId = client.config.autoRadio.channelId;
        const radioUrl = client.config.autoRadio.radioUrl;
        
        if (!channelId) {
            console.log('⚠️  AUTO_RADIO_ENABLED is true but AUTO_RADIO_CHANNEL_ID is not set!');
            return;
        }
        
        if (!radioUrl) {
            console.log('⚠️  AUTO_RADIO_ENABLED is true but AUTO_RADIO_URL is not set!');
            return;
        }
        
        // Небольшая задержка для полной инициализации бота
        setTimeout(async () => {
            await connectToRadio(client, channelId, radioUrl);
        }, 3000);
    }
}

async function connectToRadio(client, channelId, radioUrl) {
    try {
        const player = useMainPlayer();
        const channel = await client.channels.fetch(channelId);
        
        if (!channel) {
            console.log(`❌ Channel with ID ${channelId} not found!`);
            return;
        }
        
        if (channel.type !== 2) { // 2 = VoiceChannel
            console.log(`❌ Channel ${channelId} is not a voice channel!`);
            return;
        }
        
        console.log(`🔌 Connecting to voice channel: ${channel.name} (${channelId})`);
        console.log(`📻 Starting radio stream: ${radioUrl}`);
        
        try {
            const { track } = await player.play(channel, radioUrl, {
                nodeOptions: {
                    metadata: {
                        channel: channel,
                        client: client
                    },
                    volume: client.config.opt.volume,
                    leaveOnEmpty: false, // Не выходить при пустом канале
                    leaveOnEmptyCooldown: 0,
                    leaveOnEnd: false, // Не выходить при окончании
                    leaveOnEndCooldown: 0,
                }
            });
            
            console.log(`✅ Radio started successfully!`);
            console.log(`   Track: ${track.title || 'Radio Stream'}`);
            console.log(`   URL: ${radioUrl}`);
            
            // Сохраняем информацию о радио для переподключения
            client.radioConnection = {
                channelId: channelId,
                radioUrl: radioUrl,
                isConnected: true
            };
            
        } catch (error) {
            console.error(`❌ Error starting radio: ${error.message}`);
            
            // Пытаемся переподключиться, если включено авто-переподключение
            if (client.config.autoRadio.autoReconnect) {
                console.log(`🔄 Attempting to reconnect in ${client.config.autoRadio.reconnectDelay / 1000} seconds...`);
                setTimeout(() => {
                    connectToRadio(client, channelId, radioUrl);
                }, client.config.autoRadio.reconnectDelay);
            }
        }
    } catch (error) {
        console.error(`❌ Error connecting to radio: ${error.message}`);
        console.error(error.stack);
        
        // Пытаемся переподключиться
        if (client.config.autoRadio.autoReconnect) {
            console.log(`🔄 Attempting to reconnect in ${client.config.autoRadio.reconnectDelay / 1000} seconds...`);
            setTimeout(() => {
                connectToRadio(client, channelId, radioUrl);
            }, client.config.autoRadio.reconnectDelay);
        }
    }
}