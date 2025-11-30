const { EmbedBuilder } = require('discord.js');
const { Translate } = require('../../process_tools');
const { useMainPlayer } = require('discord-player');

module.exports = (queue) => {
    if (queue.metadata.lyricsThread) {
        queue.metadata.lyricsThread.delete();
        queue.setMetadata({
            channel: queue.metadata.channel
        });
    }

    (async () => {
        const embed = new EmbedBuilder()
        .setAuthor({ name: await Translate(`Disconnected from the voice channel, clearing the queue! <❌>`)})
        .setColor('#2f3136');

        if (queue.metadata.channel) {
            queue.metadata.channel.send({ embeds: [embed] });
        }
        
        // Проверяем, нужно ли переподключиться к радио
        const client = queue.metadata.client || global.client;
        if (client && client.config && client.config.autoRadio && client.config.autoRadio.enabled && client.config.autoRadio.autoReconnect) {
            if (client.radioConnection && client.radioConnection.isConnected) {
                console.log('🔄 Radio disconnected, attempting to reconnect...');
                client.radioConnection.isConnected = false;
                
                setTimeout(async () => {
                    const channelId = client.config.autoRadio.channelId;
                    const radioUrl = client.config.autoRadio.radioUrl;
                    await reconnectToRadio(client, channelId, radioUrl);
                }, client.config.autoRadio.reconnectDelay);
            }
        }
    })()
}

// Общая функция для переподключения к радио
async function reconnectToRadio(client, channelId, radioUrl) {
    try {
        const player = useMainPlayer();
        const channel = await client.channels.fetch(channelId);
        
        if (!channel || channel.type !== 2) {
            console.log(`❌ Cannot reconnect: Channel ${channelId} not found or not a voice channel!`);
            return;
        }
        
        console.log(`🔄 Reconnecting to radio: ${radioUrl}`);
        
        const { track } = await player.play(channel, radioUrl, {
            nodeOptions: {
                metadata: {
                    channel: channel,
                    client: client
                },
                volume: client.config.opt.volume,
                leaveOnEmpty: false,
                leaveOnEmptyCooldown: 0,
                leaveOnEnd: false,
                leaveOnEndCooldown: 0,
            }
        });
        
        console.log(`✅ Radio reconnected successfully!`);
        if (client.radioConnection) {
            client.radioConnection.isConnected = true;
        }
        
    } catch (error) {
        console.error(`❌ Error reconnecting to radio: ${error.message}`);
        
        // Пытаемся ещё раз
        if (client.config.autoRadio.autoReconnect) {
            setTimeout(() => {
                reconnectToRadio(client, channelId, radioUrl);
            }, client.config.autoRadio.reconnectDelay);
        }
    }
}
