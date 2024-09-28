const { Client, GatewayIntentBits, Events } = require('discord.js');
const client = new Client({ intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildMembers, GatewayIntentBits.GuildMessages, GatewayIntentBits.MessageContent] });

const YEAR_IN_MS = 366 * 24 * 60 * 60 * 1000; // 12 месяцев в миллисекундах
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
            if (timeOnServer >= SIX_MONTHS_IN_MS) {
                // Если участник на сервере больше полугода, выдаем роль
                if (!member.roles.cache.has(role.id)) {
                    await member.roles.add(role);
                    console.log(`Role ${ROLE_NAME} added to ${member.user.tag}`);
                }
            } else if (timeOnServer >= YEAR_IN_MS) {
                // Если участник на сервере больше года, выдаем роль
                if (!member.roles.cache.has(role_year.id)) {
                    await member.roles.add(role_year);
                    if (member.roles.cache.has(role.id)) {
                        await member.roles.remove(role);
                    }
                    console.log(`Role ${ROLE_NAME_YEAR} added to ${member.user.tag}`);
                }
            }
        });
    } catch (error) {
        console.error(`Error checking members in guild ${guild.name}:`, error);
    }
}

client.login("MTI4OTUxODMwMzcxODQwODI0NA.GldNu7.omFH49Td9D0OHzeFMm0SmIyZYPO_Cwil2nMnpc");