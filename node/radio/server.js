const express = require('express');
const fs = require('fs');
const path = require('path');
const app = express();

// Получаем название папки с треками через аргумент командной строки
const musicDirectoryName = process.argv[2];
const port = process.argv[3];

if (!musicDirectoryName) {
    console.error('Не указана папка с треками!');
    process.exit(1);
}

// Формируем полный путь к папке с музыкой
const musicDirectory = path.join(__dirname, musicDirectoryName);

// Получаем список всех MP3 файлов в указанной папке
let availableTracks = [];
fs.readdirSync(musicDirectory).forEach(file => {
    if (path.extname(file).toLowerCase() === '.mp3') {
        availableTracks.push(file);
    }
});

// Проверка, что в папке есть хотя бы один MP3 файл
if (availableTracks.length === 0) {
    console.error('В указанной папке нет MP3 файлов!');
    process.exit(1);
}

let currentTrackIndex = 0;

// Функция для получения следующего трека
function getNextTrack() {
    const track = availableTracks[currentTrackIndex];
    currentTrackIndex = (currentTrackIndex + 1) % availableTracks.length; // Возвращаемся к первому треку, если достигли конца списка
    return track;
}

// Обработчик для доступа к музыке по URL
app.get('/stream', (req, res) => {
    // Устанавливаем правильный заголовок для аудио
    res.setHeader('Content-Type', 'audio/mpeg');

    // Функция для отправки трека
    function sendTrack() {
        const track = getNextTrack(); // Получаем следующий трек из списка
        const filePath = path.join(musicDirectory, track);

        // Проверка наличия файла
        if (fs.existsSync(filePath)) {
            const readStream = fs.createReadStream(filePath);

            // Переход к следующему треку, когда текущий завершится
            readStream.on('end', () => {
                console.log(`Трек ${track} завершен, переключение на следующий.`);
                sendTrack(); // Отправляем следующий трек после окончания текущего
            });

            // Отправляем трек через поток
            readStream.pipe(res, { end: false }); // Не заканчиваем ответ, чтобы поток продолжался
        } else {
            res.status(404).send('Файл не найден');
        }
    }

    sendTrack(); // Начинаем с первого трека
});

// Запуск сервера
app.listen(port, () => {
    console.log(`Сервер запущен на http://localhost:${port}`);
});
