module.exports = {
    app: {
        token: process.env.DISCORD_TOKEN || 'MTI4NzUzODM3NDEzOTE4MzEyNA.GXBr_6.ucLQQppRxE2nJGB8jO0sTrwRhJ0_tbvKNV7NCU',
        playing: 'Prostoj.store ❤️',
        global: true,
        guild: process.env.GUILD_ID || '1237317039396487179',
        extraMessages: false,
        loopMessage: false,
        lang: 'ru',
        enableEmojis: true,
    },

    emojis:{
        'back': '⏪',
        'skip': '⏩',
        'ResumePause': '⏯️',
        'savetrack': '💾',
        'volumeUp': '🔊',
        'volumeDown': '🔉',
        'loop': '🔁',
    },

    opt: {
        DJ: {
            enabled: false,
            roleName: '',
            commands: []
        },
        Translate_Timeout: 10000,
        maxVol: 100,
        spotifyBridge: true,
        volume: 20,
        leaveOnEmpty: false,
        leaveOnEmptyCooldown: 30000,
        leaveOnEnd: false,
        leaveOnEndCooldown: 30000,
        discordPlayer: {
            ytdlOptions: {
                quality: 'highestaudio',
                highWaterMark: 1 << 25
            }
        }
    },
    
    // Автоматическое подключение к радио
    autoRadio: {
        enabled: process.env.AUTO_RADIO_ENABLED === 'true' || false,
        channelId: process.env.AUTO_RADIO_CHANNEL_ID || null, // ID голосового канала
        radioUrl: process.env.AUTO_RADIO_URL || 'http://localhost:8081/stream', // URL радио потока
        reconnectDelay: 5000, // Задержка перед переподключением (мс)
        autoReconnect: true // Автоматически переподключаться при отключении
    }
};
