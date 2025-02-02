const market = require('steam-market-search').market;
const mongoose = require('mongoose');
const Item = require('./ItemModel'); // Путь к вашей модели предмета

// Подключение к MongoDB
mongoose.connect('mongodb://SHENiiRoot:W4fEL8z9FKSv67y@127.0.0.1:27017/RustRu?authSource=admin');

const count = 100;
let start = 0;

// Функция для получения следующих 100 предметов с задержкой в 1 минуту
async function getNextItems() {
    // Получаем следующие 100 предметов
    await GetItems(start, count);
    start += count;
    await delay(30000); // Ждем 30 секунд перед следующим запросом
    // Рекурсивно вызываем эту функцию для получения следующих 100 предметов
    getNextItems();
}

// Функция для обновления или создания записи предмета
async function updateOrCreateItem(itemData) {
    try {
        // Проверяем, существует ли предмет с указанным именем
        const existingItem = await Item.findOne({ name: itemData.name });

        if (existingItem) {
            // Если предмет существует, обновляем его данные
            existingItem.price = itemData.price;
            existingItem.scrapDeposit = itemData.scrapDeposit;
            existingItem.scrapBuy = itemData.scrapBuy;
            existingItem.accept = itemData.accept;
            existingItem.image = itemData.image;
            await existingItem.save();
            console.log(`Предмет '${itemData.name}' обновлен`);
        } else {
            // Если предмет не существует, создаем новый
            const newItem = new Item(itemData);
            await newItem.save();
            console.log(`Предмет '${itemData.name}' создан`);
        }
    } catch (error) {
        console.error('Ошибка при обновлении или создании предмета:', error);
    }
}

// Функция для задержки
async function delay(ms) {
    await new Promise(resolve => setTimeout(resolve, ms));
}

// Функция для получения предметов с заданным смещением и количеством
async function GetItems(start, count) {
    market.search(252490, { start: start, count: count, query: '' }).then(async (results) => {
        // Обходим полученные результаты
        for (const item of results) {
            const sellPrice = parseFloat(item.sell_price_text.replace('$', ''));

// Переводим доллары в рубли
            const rublePrice = sellPrice * 95;

// Рассчитываем цену в скрапах с добавлением 10%
            const scrapBuy = rublePrice * 150 * 1.1;

// Рассчитываем цену для депозита (уменьшение на 20%)
            const scrapDeposit = rublePrice * 150 * 0.8;

// Создаем объект данных о предмете
            const itemData = {
                name: item.name,
                price: Math.round(rublePrice), // Цена в рублях
                scrapDeposit: Math.round(scrapDeposit), // Цена для депозита в скрапах
                scrapBuy: Math.round(scrapBuy), // Цена для покупки в скрапах
                accept: true,
                image: `https://steamcommunity-a.akamaihd.net/economy/image/${item.asset_description.icon_url_large}`
            };

// Обновляем или создаем запись предмета в базе данных
            await updateOrCreateItem(itemData);
        }
    }).catch((error) => {
        console.error('Ошибка при поиске предметов:', error);
    });
}

// Запускаем процесс получения предметов
getNextItems();