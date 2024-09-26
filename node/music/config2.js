module.exports = {
    app: {
        token: process.env.DISCORD_TOKEN || 'MTI4ODYwNzIxOTA5MjI5MTY2Ng.G7BGLE.yPAvmnUOIViIff0aLEQ0bCvt5zLp_Ko8bsAfCU',
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
    }
};
