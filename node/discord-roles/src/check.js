const {
    Client,
    GatewayIntentBits,
    Partials,
    Events,
    ActionRowBuilder,
    ButtonBuilder,
    ButtonStyle
} = require('discord.js');
const axios = require('axios');

// Каналы, где ChatGPT должен отвечать
const CHATGPT_CHANNELS = ['1120701864980263002', '1211335821555142736'];

// Хранилище истории сообщений для каждого канала (последние 10 сообщений)
const channelHistory = new Map();

// Функция для получения ответа от ChatGPT через API
async function getChatGptReply(message, username, channelId) {
    try {
        // Получаем историю для канала
        const history = channelHistory.get(channelId) || [];
        
        const response = await axios.post('https://api.prostoj.store/api/discord-chatgpt/reply', {
            message: message,
            username: username,
            server: 'Discord',
            chatHistory: history
        }, {
            timeout: 20000 // 20 секунд таймаут
        });
        
        if (response.data && response.data.success && response.data.reply) {
            // Обновляем историю
            history.push({ user: message });
            history.push({ bot: response.data.reply });
            
            // Оставляем только последние 10 сообщений (5 пар вопрос-ответ)
            if (history.length > 10) {
                history.splice(0, history.length - 10);
            }
            
            channelHistory.set(channelId, history);
            
            return response.data.reply;
        }
        
        return null;
    } catch (error) {
        console.error('Ошибка при получении ответа от ChatGPT:', error.message);
        return null;
    }
}

// Функция для проверки, нужно ли отвечать на сообщение
function shouldRespondToMessage(message) {
    // Не отвечаем на сообщения от ботов
    if (message.author.bot) {
        return false;
    }
    
    // Проверяем, что это нужный канал
    if (!CHATGPT_CHANNELS.includes(message.channelId)) {
        return false;
    }
    
    // Проверяем, что сообщение не пустое
    if (!message.content || message.content.trim().length === 0) {
        return false;
    }
    
    // Не отвечаем на команды бота
    if (message.content.startsWith('!')) {
        return false;
    }
    
    return true;
}

// Функция для проверки, был ли ответ от модератора/админа
async function hasStaffReply(channel, messageId) {
    try {
        // Получаем последние 20 сообщений до текущего
        const messages = await channel.messages.fetch({ limit: 20, before: messageId });
        
        for (const msg of messages.values()) {
            // Пропускаем текущее сообщение
            if (msg.id === messageId) {
                continue;
            }
            
            // Если есть сообщение от пользователя с правами модератора/админа (не бот)
            if (!msg.author.bot && msg.member) {
                // Проверяем роли (можно настроить список ролей модераторов)
                const hasModRole = msg.member.roles.cache.some(role => 
                    role.permissions.has('ManageMessages') || 
                    role.permissions.has('Administrator')
                );
                
                if (hasModRole) {
                    return true;
                }
            }
        }
        
        return false;
    } catch (error) {
        console.error('Ошибка при проверке ответов модераторов:', error.message);
        return false;
    }
}

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
                    try {
                        client.channels.fetch('1237317039396487179').then(function(channel) {
                            if (channel != null) {
                                channel.send(`Role ${ROLE_NAME_YEAR} added to <@${member.user.id}>`);
                            }
                        });
                    } catch (e) {}
                    console.log(`Role ${ROLE_NAME_YEAR} added to ${member.user.tag}`);
                }
            } else if (timeOnServer >= SIX_MONTHS_IN_MS) {
                // Если участник на сервере больше полугода, выдаем роль
                if (!member.roles.cache.has(role.id) && !member.roles.cache.has(role_year.id)) {
                    await member.roles.add(role);
                    try {
                        client.channels.fetch('1237317039396487179').then(function(channel) {
                            if (channel != null) {
                                channel.send(`Role ${ROLE_NAME_YEAR} added to <@${member.user.id}>`);
                            }
                        });
                    } catch (e) {}
                    console.log(`Role ${ROLE_NAME} added to ${member.user.tag}`);
                }
            }
        });
    } catch (error) {
        console.error(`Error checking members in guild ${guild.name}:`, error);
    }
}

client.on(Events.MessageCreate, async (message) => {
    if (message.content === '!rules') {
        try {
            const response = await axios.get('https://api.prostoj.store/servers/rules');
            const servers = response.data;

            // Создаём ActionRows с кнопками (по 5 в строке)
            const components = [];
            for (let i = 0; i < servers.length; i += 5) {
                const row = new ActionRowBuilder();
                servers.slice(i, i + 5).forEach(server => {
                    row.addComponents(
                        new ButtonBuilder()
                            .setLabel(server.name)
                            .setStyle(ButtonStyle.Link)
                            .setURL(`https://prostoj.store${server.link}`)
                    );
                });
                components.push(row);
            }

            await message.channel.send({
                content: '**📜 Правила серверов ПРОСТОЙ**\nВыберите сервер ниже, чтобы ознакомиться с его правилами:',
                components
            });

        } catch (error) {
            console.error('Ошибка при получении правил:', error);
            await message.channel.send('❌ Не удалось загрузить список серверов. Попробуйте позже.');
        }
        await message.delete();
    }
    if (message.content === '!support') {
        const row = new ActionRowBuilder().addComponents(
            new ButtonBuilder()
                .setLabel('Связаться с администрацией')
                .setStyle(ButtonStyle.Link)
                .setURL('https://moscow77.store/support')
        );

        await message.channel.send({
            content: [
                '**📩 Обратная связь**',
                'Если у вас возник вопрос или жалоба — мы всегда готовы помочь.',
                '👇 Нажмите на кнопку ниже 👇',
                '',
            ].join('\n\n'),
            components: [row]
        });
        await message.delete();
    }
    if (message.guildId === '1199050277773385728' && message.channelId === '1211335821555142736') {
        const messageLower = message.content.toLowerCase();
        if (messageLower.indexOf("админ") >= 0
            || messageLower.indexOf("пидорас") >= 0
            || messageLower.indexOf("хуесос") >= 0
            || messageLower.indexOf("модератор") >= 0
            || messageLower.indexOf("читер") >= 0
            || messageLower.indexOf("провер") >= 0
            || (messageLower.indexOf("сообще") >= 0 && messageLower.indexOf("удал") >= 0)) {
            console.log(`Удаленно сообщение от пользователя "${message.author.globalName}" в канале "${message.channel.name}": ${message.content}`);
            await message.delete();
            try {
                client.channels.fetch('1237317039396487179').then(function(channel) {
                    channel.send(`Сообщение <@${message.author.id}> удаленно в канале "${message.channel.name}".\n\`\`\`${message.content}\`\`\``);
                });
            } catch (e) {}
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
                client.channels.fetch('1237317039396487179').then(function(channel) {
                    channel.send(`Сообщение <@${message.author.id}> удаленно в канале "${message.channel.name}".\n\`\`\`${message.content}\`\`\``);
                });
            } catch (e) {}
            try {
                await message.author.send(`Здравствуйте ${message.author.globalName}!\n\nВаше сообщение автоматически удалено, потому что, нужно обязательно указать наш сервер, на котором вы ищите тимейта.\n\nУказать нужно сылкой на наш канал в дискорде, например:\nСервер: <#1263515112355008584>`);
            } catch (e) {}
        }
    }
    // ChatGPT ответы в указанных каналах
    if (shouldRespondToMessage(message)) {
        try {
            // Проверяем, был ли ответ от модератора/админа
            const staffReplied = await hasStaffReply(message.channel, message.id);
            
            if (!staffReplied) {
                // Показываем индикатор "бот печатает" (асинхронно, не ждем)
                message.channel.sendTyping();
                
                // Получаем ответ от ChatGPT
                const reply = await getChatGptReply(
                    message.content,
                    message.author.globalName || message.author.username,
                    message.channelId
                );
                
                if (reply) {
                    // Отправляем ответ
                    await message.channel.send(reply);
                }
            } else {
                console.log(`Пропущен ответ ChatGPT для сообщения ${message.id}: модератор уже ответил`);
            }
        } catch (error) {
            console.error('Ошибка при обработке ChatGPT запроса:', error.message);
        }
    }
    
    if (message.guildId == null && !message.author.bot) {
        try {
            client.channels.fetch('1237317039396487179').then(function(channel) {
                channel.send(`Пользователь "${message.author.globalName}" написал боту: ${message.content}`);
            });
        } catch (e) {}
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