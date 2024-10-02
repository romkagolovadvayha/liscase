const { Client, GatewayIntentBits, Events, Partials } = require('discord.js');
const client = new Client({
    partials: [Partials.Channel, Partials.Message],
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
        GatewayIntentBits.DirectMessages
    ]
});

const YEAR_IN_MS = 360 * 24 * 60 * 60 * 1000; // 12 месяцев в миллисекундах
const SIX_MONTHS_IN_MS = 182 * 24 * 60 * 60 * 1000; // 6 месяцев в миллисекундах
const ROLE_NAME = "Старичок";
const ROLE_NAME_YEAR = "Пожилой";

client.once('ready', async () => {
    console.log(`Logged in as ${client.user.tag}`);

    // Периодическая проверка всех участников
    const guilds = client.guilds.cache;
    guilds.forEach(async (guild) => {
        await checkMembers(guild);
    });

    // Устанавливаем интервал для регулярной проверки (каждый час, например)
    setInterval(async () => {
        const guilds = client.guilds.cache;
        guilds.forEach(async (guild) => {
            await checkMembers(guild);
        });
    }, 60 * 60 * 1000); // 1 час
});

async function checkMembers(guild) {
    try {
        const role = guild.roles.cache.find(r => r.name === ROLE_NAME);
        if (!role) {
            console.error(`Role ${ROLE_NAME} not found in guild ${guild.name}`);
            return;
        }
        const role_year = guild.roles.cache.find(r => r.name === ROLE_NAME_YEAR);
        if (!role) {
            console.error(`Role ${ROLE_NAME_YEAR} not found in guild ${guild.name}`);
            return;
        }

        // Получаем всех участников сервера
        const members = await guild.members.fetch();

        members.forEach(async (member) => {
            const joinDate = member.joinedAt;

            if (!joinDate) {
                console.log(`Could not retrieve join date for ${member.user.tag}`);
                return;
            }

            const timeOnServer = Date.now() - joinDate.getTime();

            // Проверяем, больше ли участник на сервере, чем 6 месяцев
            if (timeOnServer >= YEAR_IN_MS) {
                // Если участник на сервере больше года, выдаем роль
                if (!member.roles.cache.has(role_year.id)) {
                    await member.roles.add(role_year);
                    if (member.roles.cache.has(role.id)) {
                        await member.roles.remove(role);
                    }
                    console.log(`Role ${ROLE_NAME_YEAR} added to ${member.user.tag}`);
                }
            } else if (timeOnServer >= SIX_MONTHS_IN_MS) {
                // Если участник на сервере больше полугода, выдаем роль
                if (!member.roles.cache.has(role.id) && !member.roles.cache.has(role_year.id)) {
                    await member.roles.add(role);
                    console.log(`Role ${ROLE_NAME} added to ${member.user.tag}`);
                }
            }
        });
    } catch (error) {
        console.error(`Error checking members in guild ${guild.name}:`, error);
    }
}

client.on(Events.MessageCreate, async (message) => {
    if (message.guildId === '1199050277773385728' && message.channelId === '1211335821555142736') {
        const messageLower = message.content.toLowerCase();
        if (messageLower.indexOf("сообще") >= 0 && messageLower.indexOf("удал") >= 0) {
            console.log(`Удаленно сообщение от пользователя "${message.author.globalName}" в канале "${message.channel.name}": ${message.content}`);
            await message.delete();
            try {
                await message.author.send(`Здравствуйте ${message.author.globalName}!\n\nВаше сообщение автоматически удалено, если у вас есть вопросы к администрации или вы хотите оставить жалобу на игрока, создайте тикет в разделе <#1211335904350838904>!`);
            } catch (e) {}
        } else if (messageLower.indexOf("админ") >= 0
            || messageLower.indexOf("пидорас") >= 0
            || messageLower.indexOf("хуесос") >= 0
            || messageLower.indexOf("модератор") >= 0
            || messageLower.indexOf("читер") >= 0
            || messageLower.indexOf("провер") >= 0) {
            console.log(`Удаленно сообщение от пользователя "${message.author.globalName}" в канале "${message.channel.name}": ${message.content}`);
            await message.delete();
            try {
                await message.author.send(`Здравствуйте ${message.author.globalName}!\n\nВаше сообщение автоматически удалено, если у вас есть вопросы к администрации или вы хотите оставить жалобу на игрока, создайте тикет в разделе <#1211335904350838904>!`);
            } catch (e) {}
        }
    }
    if (message.guildId === '1199050277773385728' && message.channelId === '1242706704798318652') {
        const messageLower = message.content.toLowerCase();
        var access = false;
        message.guild.channels.cache.forEach(async (channel) => {
            if (messageLower.indexOf(channel.id) >= 0) {
                access = true;
            }
        });
        if (!access) {
            console.log(`Удаленно сообщение от пользователя "${message.author.globalName}" в канале "${message.channel.name}": ${message.content}`);
            await message.delete();
            try {
                await message.author.send(`Здравствуйте ${message.author.globalName}!\n\nВаше сообщение автоматически удалено, потому что, нужно обязательно указать наш сервер, на котором вы ищите тимейта.\n\nУказать нужно сылкой на наш канал в дискорде, например:\nСервер: <#1263515112355008584>`);
            } catch (e) {}
        }
    }
    if (message.guildId == null && !message.author.bot) {
        try {
            console.log(`Пользователь "${message.author.globalName}" написал боту: ${message.content}`);
            await message.author.send(`Этот бот не умеет отвечать на ваши сообщения, пожалуйста оставьте тикет в разделе <#1211335904350838904>!`);
        } catch (e) {}
    }
    try {
        if (message.author.bot
            && message.channel.name.indexOf("ticket-") >= 0
            && message.content.indexOf("Здравствуйте,") >= 0
            && message.content.indexOf("<@") >= 0) {
            var userName = "";
            await message.channel.permissionOverwrites.cache.forEach(async (perm) => {
                if (message.content.indexOf(perm.id) >= 0) {
                    let thanos = client.users.fetch(perm.id);
                    await thanos.then(function(result1) {
                        userName = result1.globalName;
                    });
                }
            });
            message.channel.setName("ticket_" + userName);
        }
    } catch (e) {}
    // try {
    //     if (message.author.bot
    //         && message.channel.name.indexOf("closed-") >= 0
    //         && message.channel.name.indexOf("closed-") >= 0) {
    //         message.channel.delete();
    //     }
    // } catch (e) {}
});

// client.on(Events.ChannelCreate, async (channel) => {
//     if (channel.name.indexOf("ticket-") >= 0) {
//         console.log(channel.permissionOverwrites.cache);
//         console.log(channel.messages.cache);
//     }
// });

client.login("MTI4OTUxODMwMzcxODQwODI0NA.GldNu7.omFH49Td9D0OHzeFMm0SmIyZYPO_Cwil2nMnpc");