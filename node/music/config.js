module.exports = {
    app: {
        token: process.env.DISCORD_TOKEN || 'MTI4NzUzODM3NDEzOTE4MzEyNA.GXBr_6.ucLQQppRxE2nJGB8jO0sTrwRhJ0_tbvKNV7NCU',
        playing: 'Prostoj.store ❤️',
        global: true,
        guild: process.env.GUILD_ID || '1199050277773385728',
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
        volume: 75,
        leaveOnEmpty: true,
        leaveOnEmptyCooldown: 30000,
        leaveOnEnd: true,
        leaveOnEndCooldown: 30000,
        discordPlayer: {
            ytdlOptions: {
                quality: 'highestaudio',
                highWaterMark: 1 << 25
            }
        }
    }
};
