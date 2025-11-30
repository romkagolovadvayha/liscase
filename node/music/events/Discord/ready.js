const { Translate } = require('../../process_tools');
const { useMainPlayer } = require('discord-player');
const { ChannelType } = require('discord.js');

module.exports = async (client) => {
    console.log(await Translate(`Logged to the client <${client.user.username}>.`));
    console.log(await Translate("Let's play some music !"));
    
    client.user.setActivity(client.config.app.playing);
    
    // Отладочная информация
    console.log('🔍 Checking auto-radio configuration...');
    console.log(`   autoRadio exists: ${!!client.config.autoRadio}`);
    if (client.config.autoRadio) {
        console.log(`   enabled: ${client.config.autoRadio.enabled}`);
        console.log(`   channelId: ${client.config.autoRadio.channelId || 'not set'}`);
        console.log(`   radioUrl: ${client.config.autoRadio.radioUrl || 'not set'}`);
    }
    
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
        
        console.log('✅ Auto-radio is enabled, connecting in 3 seconds...');
        
        // Небольшая задержка для полной инициализации бота
        setTimeout(async () => {
            await connectToRadio(client, channelId, radioUrl);
        }, 3000);
    } else {
        console.log('ℹ️  Auto-radio is disabled or not configured');
    }
}

async function connectToRadio(client, channelId, radioUrl) {
    try {
        console.log(`🔍 Fetching channel ${channelId}...`);
        const player = useMainPlayer();
        const channel = await client.channels.fetch(channelId);
        
        if (!channel) {
            console.log(`❌ Channel with ID ${channelId} not found!`);
            console.log(`   Make sure the bot has access to this channel and the ID is correct.`);
            return;
        }
        
        console.log(`✅ Channel found: ${channel.name} (Type: ${channel.type})`);
        
        // Проверяем тип канала (используем ChannelType enum для Discord.js v14)
        if (channel.type !== ChannelType.GuildVoice) {
            console.log(`❌ Channel ${channelId} is not a voice channel!`);
            console.log(`   Channel type: ${channel.type}, expected: ${ChannelType.GuildVoice}`);
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
            console.error(error.stack);
            
            // Пытаемся переподключиться, если включено авто-переподключение
            if (client.config.autoRadio && client.config.autoRadio.autoReconnect) {
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
        if (client.config.autoRadio && client.config.autoRadio.autoReconnect) {
            console.log(`🔄 Attempting to reconnect in ${client.config.autoRadio.reconnectDelay / 1000} seconds...`);
            setTimeout(() => {
                connectToRadio(client, channelId, radioUrl);
            }, client.config.autoRadio.reconnectDelay);
        }
    }
}