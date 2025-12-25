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
const CHATGPT_CHANNELS = ['1120701864980263002', '1211335821555142736', '1288617959136301086'];

// Хранилище истории сообщений для каждого канала (последние 10 сообщений)
const channelHistory = new Map();

// Буфер сообщений для каждого канала (собираем сообщения перед отправкой)
const messageBuffer = new Map(); // channelId -> { messages: [], timer: null }

// Таймаут сбора сообщений (2 минуты)
const MESSAGE_COLLECTION_TIMEOUT = 10 * 1000; // 2 минуты в миллисекундах

// Функция для получения ответа от ChatGPT через API (обрабатывает несколько сообщений)
async function getChatGptReply(messages, channelId) {
    try {
        // Получаем историю для канала
        const history = channelHistory.get(channelId) || [];
        
        // Формируем объединенное сообщение из всех собранных сообщений
        const combinedMessage = messages.map(msg => `${msg.username}: ${msg.content}`).join('\n');
        
        console.log(`[ChatGPT] Запрос для канала ${channelId} (${messages.length} сообщений): ${combinedMessage.substring(0, 100)}...`);
        
        const response = await axios.post('https://api.prostoj.store/api/discord-chat-gpt/reply', {
            message: combinedMessage,
            username: messages[messages.length - 1].username, // Используем имя последнего отправителя
            server: 'Discord',
            chatHistory: history
        }, {
            timeout: 30000, // 30 секунд таймаут (увеличено для обработки нескольких сообщений)
            validateStatus: function (status) {
                // Разрешаем обрабатывать все статусы, чтобы не выбрасывать исключение
                return status < 600;
            }
        });
        
        console.log(`[ChatGPT] Ответ API (статус ${response.status}):`, JSON.stringify(response.data));
        
        // Проверяем статус ответа
        if (response.status === 200 && response.data && response.data.success && response.data.reply) {
            // Обновляем историю
            messages.forEach(msg => {
                history.push({ user: `${msg.username}: ${msg.content}` });
            });
            history.push({ bot: response.data.reply });
            
            // Оставляем только последние 10 сообщений (5 пар вопрос-ответ)
            if (history.length > 10) {
                history.splice(0, history.length - 10);
            }
            
            channelHistory.set(channelId, history);
            
            console.log(`[ChatGPT] Успешно получен ответ: ${response.data.reply.substring(0, 100)}...`);
            return response.data.reply;
        } else {
            // Обрабатываем ошибки
            if (response.status === 500) {
                console.error(`[ChatGPT] Ошибка 500 от сервера. Ответ:`, JSON.stringify(response.data));
                console.error(`[ChatGPT] Это может быть проблема с OpenAI API или настройками на сервере`);
            } else if (response.status >= 400) {
                console.error(`[ChatGPT] Ошибка API (${response.status}):`, response.data);
            } else {
                console.warn(`[ChatGPT] API вернул неожиданный ответ:`, response.data);
            }
            
            if (response.data && response.data.message) {
                console.warn(`[ChatGPT] Сообщение об ошибке: ${response.data.message}`);
            }
        }
        
        return null;
    } catch (error) {
        if (error.response) {
            // Сервер ответил с кодом ошибки
            console.error(`[ChatGPT] Ошибка API (${error.response.status}):`, JSON.stringify(error.response.data));
            if (error.response.status === 500) {
                console.error(`[ChatGPT] Внутренняя ошибка сервера. Проверьте логи на сервере.`);
            }
        } else if (error.request) {
            // Запрос был отправлен, но ответа не получено
            console.error('[ChatGPT] Нет ответа от API:', error.message);
            console.error('[ChatGPT] Возможно, сервер недоступен или превышен таймаут');
        } else {
            // Ошибка при настройке запроса
            console.error('[ChatGPT] Ошибка запроса:', error.message);
            console.error('[ChatGPT] Stack:', error.stack);
        }
        return null;
    }
}

// Функция для обработки накопленных сообщений и отправки ответа
async function processBufferedMessages(channel) {
    const channelId = channel.id;
    const buffer = messageBuffer.get(channelId);
    
    if (!buffer || buffer.messages.length === 0) {
        return;
    }
    
    // Очищаем таймер
    if (buffer.timer) {
        clearTimeout(buffer.timer);
        buffer.timer = null;
    }
    
    // Получаем копию сообщений и очищаем буфер
    const messages = [...buffer.messages];
    buffer.messages = [];
    messageBuffer.set(channelId, buffer);
    
    // Проверяем, был ли ответ от модератора/админа за последние 5 минут
    try {
        const lastMessage = messages[messages.length - 1];
        const staffReplied = await hasStaffReply(channel, lastMessage.messageId);
        
        if (staffReplied) {
            console.log(`[ChatGPT] Пропущен ответ для ${messages.length} сообщений: модератор уже ответил`);
            return;
        }
        
        // Показываем индикатор "бот печатает"
        channel.sendTyping();
        
        // Получаем ответ от ChatGPT
        const reply = await getChatGptReply(messages, channelId);
        
        if (reply) {
            // Отправляем ответ
            console.log(`[ChatGPT] Отправка ответа в канал ${channelId} (на ${messages.length} сообщений)`);
            await channel.send(reply);
            console.log(`[ChatGPT] Ответ успешно отправлен`);
        } else {
            console.warn(`[ChatGPT] Ответ не получен, сообщение не отправлено`);
        }
    } catch (error) {
        console.error('[ChatGPT] Ошибка при обработке буфера сообщений:', error.message);
        console.error('[ChatGPT] Stack trace:', error.stack);
    }
}

// Функция для добавления сообщения в буфер
function addMessageToBuffer(message, channel) {
    const channelId = channel.id;
    
    // Получаем или создаем буфер для канала
    let buffer = messageBuffer.get(channelId);
    if (!buffer) {
        buffer = { messages: [], timer: null };
        messageBuffer.set(channelId, buffer);
    }
    
    // Добавляем сообщение в буфер
    buffer.messages.push({
        content: message.content,
        username: message.author.globalName || message.author.username,
        messageId: message.id,
        timestamp: Date.now()
    });
    
    // Очищаем предыдущий таймер
    if (buffer.timer) {
        clearTimeout(buffer.timer);
    }
    
    // Устанавливаем новый таймер на 2 минуты
    buffer.timer = setTimeout(() => {
        processBufferedMessages(channel);
    }, MESSAGE_COLLECTION_TIMEOUT);
    
    messageBuffer.set(channelId, buffer);
    
    console.log(`[ChatGPT] Сообщение добавлено в буфер для канала ${channelId}. Всего сообщений: ${buffer.messages.length}`);
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

// ID ролей, которые блокируют ответы ChatGPT
const BLOCKING_ROLE_IDS = ['1453659091649036401', '1453659405940686990'];
const BLOCKING_TIMEOUT_MS = 5 * 60 * 1000; // 5 минут в миллисекундах

// Функция для проверки, был ли ответ от модератора/админа или от специальных ролей
async function hasStaffReply(channel, messageId) {
    try {
        const now = Date.now();
        // Получаем последние 50 сообщений для проверки (чтобы охватить 5 минут)
        const messages = await channel.messages.fetch({ limit: 50, before: messageId });
        
        for (const msg of messages.values()) {
            // Пропускаем текущее сообщение
            if (msg.id === messageId) {
                continue;
            }
            
            // Проверяем, не старше ли сообщение 5 минут
            const messageAge = now - msg.createdTimestamp;
            if (messageAge > BLOCKING_TIMEOUT_MS) {
                continue; // Сообщение старше 5 минут, пропускаем
            }
            
            // Если есть сообщение от пользователя с правами модератора/админа (не бот)
            if (!msg.author.bot && msg.member) {
                // Проверяем специальные роли, которые блокируют ответы
                const hasBlockingRole = msg.member.roles.cache.some(role => 
                    BLOCKING_ROLE_IDS.includes(role.id)
                );
                
                if (hasBlockingRole) {
                    console.log(`Сообщение от роли ${msg.member.roles.cache.find(r => BLOCKING_ROLE_IDS.includes(r.id))?.name} блокирует ответы ChatGPT на 5 минут`);
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

// Обработка ошибок WebSocket (525 и другие)
client.on('error', (error) => {
    console.error('[Discord] Ошибка клиента:', error.message);
    // Не критично - бот продолжит работу
});

client.on('warn', (warning) => {
    console.warn('[Discord] Предупреждение:', warning);
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
            // Добавляем сообщение в буфер (ответ будет отправлен через 2 минуты после последнего сообщения)
            addMessageToBuffer(message, message.channel);
        } catch (error) {
            console.error('[ChatGPT] Ошибка при добавлении сообщения в буфер:', error.message);
            console.error('[ChatGPT] Stack trace:', error.stack);
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