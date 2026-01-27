const { EmbedBuilder } = require('discord.js');
const { Translate } = require('../../process_tools');

module.exports = (queue) => {
    // Если включено авто-радио, не выходим из канала при пустом канале
    const client = queue.metadata.client || global.client;
    if (client && client.config && client.config.autoRadio && client.config.autoRadio.enabled) {
        // Не выходим из канала, продолжаем играть радио
        return;
    }
    
    if (queue.metadata.lyricsThread) {
        queue.metadata.lyricsThread.delete();
        queue.setMetadata({
            channel: queue.metadata.channel
        });
    }

    (async () => {
        const embed = new EmbedBuilder()
        .setAuthor({ name: await Translate(`Nobody is in the voice channel, leaving the voice channel!  <❌>`)})
        .setColor('#2f3136');

        if (queue.metadata.channel) {
            queue.metadata.channel.send({ embeds: [embed] });
        }
    })()
}
