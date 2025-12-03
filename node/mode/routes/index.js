const { queue, playlist } = require('../engine');

const plugin = {
    name: 'streamServer',
    register: async (server) => {

        // Главная страница
        server.route({
            method: 'GET',
            path: '/',
            handler: (_, h) => h.file('index.html')
        });

        // Статические файлы
        server.route({
            method: 'GET',
            path: '/{filename}',
            handler: {
                file: (req) => req.params.filename
            }
        });

        // ====== STREAMING ENDPOINT ======
        server.route({
            method: 'GET',
            path: '/stream',
            handler: (request, h) => {
                const { id, responseSink } = queue.makeResponseSink();
                request.app.sinkId = id;
                
                // КРИТИЧЕСКИ ВАЖНО: Правильные заголовки для непрерывного потокового аудио
                const response = h.response(responseSink)
                    .type('audio/mpeg')
                    .header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    .header('Pragma', 'no-cache')
                    .header('Expires', '0')
                    .header('Connection', 'keep-alive')
                    .header('Transfer-Encoding', 'chunked')
                    .header('X-Accel-Buffering', 'no') // Отключаем буферизацию в nginx
                    .code(200);
                
                return response;
            },
            options: {
                ext: {
                    onPreResponse: {
                        method: (request, h) => {
                            request.events.once('disconnect', () => {
                                if (request.app.sinkId) {
                                    queue.removeResponseSink(request.app.sinkId);
                                }
                            });
                            return h.continue;
                        }
                    }
                },
                // Отключаем таймауты для потокового контента
                timeout: {
                    server: false, // Без таймаута сервера
                    socket: false  // Без таймаута сокета
                }
            }
        });

        // ====== REST API ENDPOINTS ======

        // TEST POST endpoint - простейший тест
        server.route({
            method: 'POST',
            path: '/api/test',
            handler: (request, h) => {
                console.log('✅ TEST POST received!');
                return { success: true, message: 'POST works!' };
            }
        });

        // GET /api/status - Получить статус радиостанции
        server.route({
            method: 'GET',
            path: '/api/status',
            handler: (request, h) => {
                const currentSong = queue.getCurrentSong();
                const queueList = queue.getQueue();
                const playlistItems = playlist.getItems();
                const listenersCount = queue.getListenersCount();

                return {
                    status: 'running',
                    port: process.env.PORT,
                    listeners: listenersCount,
                    current: currentSong ? {
                        name: currentSong,
                        position: queue.getCurrentPosition(),
                    } : null,
                    queue: queueList.map((item, idx) => ({
                        position: idx + 1,
                        name: item
                    })),
                    playlist: playlistItems.map((item, idx) => ({
                        position: idx + 1,
                        name: item
                    })),
                    totalTracks: playlistItems.length,
                    queueLength: queueList.length,
                };
            }
        });

        // GET /api/queue/add - Добавить трек в очередь (ДОЛЖЕН БЫТЬ ПЕРЕД /api/queue!)
        server.route({
            method: 'GET',
            path: '/api/queue/add',
            handler: (request, h) => {
                console.log('📥 Received /api/queue/add request');
                console.log('   Query:', request.query);
                
                const { track } = request.query || {};
                if (!track) {
                    console.error('❌ Track name missing');
                    return h.response({ success: false, error: 'Track name required' }).code(400);
                }
                
                try {
                    queue.addToQueue(track);
                    console.log('✅ Track added to queue');
                    return { success: true, message: 'Track added to queue' };
                } catch (err) {
                    console.error(`❌ Error: ${err.message}`);
                    return h.response({ success: false, error: err.message }).code(400);
                }
            }
        });

        // GET /api/queue/add-first - Добавить трек первым в очередь (ДОЛЖЕН БЫТЬ ПЕРЕД /api/queue!)
        server.route({
            method: 'GET',
            path: '/api/queue/add-first',
            handler: (request, h) => {
                console.log('📥 Received /api/queue/add-first request');
                console.log('   Query:', request.query);
                
                const { track } = request.query || {};
                if (!track) {
                    console.error('❌ Track name missing');
                    return h.response({ success: false, error: 'Track name required' }).code(400);
                }
                
                try {
                    console.log(`⏭️  Adding track to front: ${track}`);
                    queue.createAndPrependToQueue(track);
                    console.log('✅ Track added successfully');
                    return { success: true, message: 'Track added to front of queue' };
                } catch (err) {
                    console.error(`❌ Error adding track: ${err.message}`);
                    return h.response({ success: false, error: err.message }).code(400);
                }
            }
        });

        // GET /api/current - Получить текущий трек
        server.route({
            method: 'GET',
            path: '/api/current',
            handler: (request, h) => {
                const currentSong = queue.getCurrentSong();
                return {
                    current: currentSong || null,
                    position: queue.getCurrentPosition(),
                };
            }
        });

        // GET /api/queue - Получить очередь
        server.route({
            method: 'GET',
            path: '/api/queue',
            handler: (request, h) => {
                const queueList = queue.getQueue();
                return {
                    queue: queueList.map((item, idx) => ({
                        position: idx + 1,
                        name: item
                    })),
                    length: queueList.length,
                };
            }
        });

        // GET /api/playlist - Получить плейлист
        server.route({
            method: 'GET',
            path: '/api/playlist',
            handler: (request, h) => {
                const playlistItems = playlist.getItems();
                return {
                    playlist: playlistItems.map((item, idx) => ({
                        position: idx + 1,
                        name: item
                    })),
                    total: playlistItems.length,
                };
            }
        });

        // POST /api/next - Переключить на следующий трек
        server.route({
            method: 'POST',
            path: '/api/next',
            handler: (request, h) => {
                try {
                    queue.skipToNext();
                    return { success: true, message: 'Skipped to next track' };
                } catch (err) {
                    return h.response({ success: false, error: err.message }).code(400);
                }
            }
        });

        // POST /api/queue/remove - Удалить трек из очереди
        server.route({
            method: 'POST',
            path: '/api/queue/remove',
            handler: (request, h) => {
                const { position } = request.payload || {};
                if (position === undefined) {
                    return h.response({ success: false, error: 'Position required' }).code(400);
                }
                
                try {
                    queue.removeFromQueueByPosition(position);
                    return { success: true, message: 'Track removed from queue' };
                } catch (err) {
                    return h.response({ success: false, error: err.message }).code(400);
                }
            }
        });

        // GET /api/reload - Перезагрузить плейлист и очередь (изменён на GET)
        server.route({
            method: 'GET',
            path: '/api/reload',
            handler: (request, h) => {
                try {
                    console.log('📥 Received /api/reload request');
                    
                    const Utils = require('../utils');
                    const songs = Utils.readSongs();
                    
                    console.log(`📂 Found ${songs.length} songs in directory`);
                    
                    // Перезагружаем очередь
                    queue.reloadQueue();
                    
                    console.log(`✅ Queue reloaded with ${songs.length} tracks`);
                    
                    return { 
                        success: true, 
                        message: 'Playlist reloaded',
                        tracksCount: songs.length 
                    };
                } catch (err) {
                    console.error(`❌ Error in /api/reload: ${err.message}`);
                    return h.response({ success: false, error: err.message }).code(500);
                }
            }
        });

        // GET /api/listeners - Получить количество слушателей
        server.route({
            method: 'GET',
            path: '/api/listeners',
            handler: (request, h) => {
                return {
                    count: queue.getListenersCount(),
                };
            }
        });

        // POST /api/queue/remove-by-filename - Удалить трек из очереди по имени файла
        server.route({
            method: 'POST',
            path: '/api/queue/remove-by-filename',
            handler: (request, h) => {
                const { track } = request.payload || {};
                
                if (!track) {
                    return h.response({ success: false, error: 'Track name required' }).code(400);
                }
                
                try {
                    const removed = queue.removeTrackFromQueue(track);
                    
                    if (removed) {
                        console.log(`🗑️  Track removed from queue: ${track}`);
                        return { 
                            success: true, 
                            message: 'Track removed from queue',
                            track: track 
                        };
                    } else {
                        return h.response({ 
                            success: false, 
                            error: 'Track not found in queue' 
                        }).code(404);
                    }
                } catch (err) {
                    console.error(`❌ Error removing track: ${err.message}`);
                    return h.response({ 
                        success: false, 
                        error: err.message 
                    }).code(500);
                }
            }
        });

        // POST /api/compress-track - Сжать трек с ухудшением качества
        server.route({
            method: 'POST',
            path: '/api/compress-track',
            handler: async (request, h) => {
                const { track, quality = '96' } = request.payload || {};
                
                if (!track) {
                    return h.response({ success: false, error: 'Track name required' }).code(400);
                }
                
                try {
                    const Path = require('path');
                    const Fs = require('fs');
                    const { spawn } = require('child_process');
                    
                    const trackPath = Path.join(process.cwd(), track);
                    
                    // Проверяем существование файла
                    if (!Fs.existsSync(trackPath)) {
                        return h.response({ 
                            success: false, 
                            error: 'Track file not found' 
                        }).code(404);
                    }
                    
                    // Создаём временный файл для сжатой версии
                    const tempPath = trackPath + '.compressed';
                    const bitRate = quality + 'k'; // 96k, 128k, etc
                    
                    console.log(`🎚️  Compressing ${track} to ${quality} kbps...`);
                    
                    // Ищем ffmpeg
                    const ffmpegCmd = process.platform === 'win32' 
                        ? 'ffmpeg.exe' 
                        : 'ffmpeg';
                    
                    return new Promise((resolve) => {
                        const ffmpeg = spawn(ffmpegCmd, [
                            '-i', trackPath,
                            '-acodec', 'libmp3lame',
                            '-ab', bitRate,
                            '-y', // overwrite
                            tempPath
                        ]);
                        
                        let errorOutput = '';
                        
                        ffmpeg.stderr.on('data', (data) => {
                            errorOutput += data.toString();
                        });
                        
                        ffmpeg.on('close', (code) => {
                            if (code === 0) {
                                // Заменяем оригинальный файл
                                Fs.unlinkSync(trackPath);
                                Fs.renameSync(tempPath, trackPath);
                                
                                const stats = Fs.statSync(trackPath);
                                
                                console.log(`✅ Compressed ${track}: ${(stats.size / 1024).toFixed(2)} KB`);
                                
                                resolve({
                                    success: true,
                                    track: track,
                                    originalSize: Fs.statSync(trackPath).size, // сейчас это уже compressed
                                    quality: quality + ' kbps'
                                });
                            } else {
                                console.error(`❌ FFmpeg error: ${errorOutput}`);
                                resolve(h.response({ 
                                    success: false, 
                                    error: 'Compression failed',
                                    details: errorOutput 
                                }).code(500));
                            }
                        });
                    });
                    
                } catch (err) {
                    console.error(`❌ Error compressing track: ${err.message}`);
                    return h.response({ 
                        success: false, 
                        error: 'Failed to compress track',
                        details: err.message 
                    }).code(500);
                }
            }
        });

        // GET /api/track-info - Получить информацию о треке (длительность, битрейт)
        server.route({
            method: 'GET',
            path: '/api/track-info',
            handler: async (request, h) => {
                const { track } = request.query || {};
                
                if (!track) {
                    return h.response({ success: false, error: 'Track name required' }).code(400);
                }
                
                try {
                    const Path = require('path');
                    const Fs = require('fs');
                    
                    const trackPath = Path.join(process.cwd(), track);
                    
                    // Проверяем существование файла
                    if (!Fs.existsSync(trackPath)) {
                        return h.response({ 
                            success: false, 
                            error: 'Track file not found' 
                        }).code(404);
                    }
                    
                    // Пытаемся использовать ffprobe если доступен
                    let duration = 0;
                    let bitRate = 128000;
                    
                    try {
                        const { ffprobe } = require('@dropb/ffprobe');
                        const result = await ffprobe(trackPath);
                        
                        duration = parseFloat(result.format.duration) || 0;
                        bitRate = parseInt(result.format.bit_rate) || 128000;
                    } catch (ffprobeError) {
                        // Fallback: оцениваем длительность по размеру файла
                        const stats = Fs.statSync(trackPath);
                        const fileSize = stats.size;
                        
                        // Предполагаем средний битрейт 192 kbps = 24000 bytes/sec
                        duration = Math.round(fileSize / 24000);
                        bitRate = 192000;
                        
                        console.log(`⚠️  ffprobe not available, estimated duration: ${duration}s`);
                    }
                    
                    const fileStats = Fs.statSync(trackPath);
                    const fileSize = fileStats.size;
                    
                    console.log(`ℹ️  Track info for ${track}: duration=${duration}s, bitrate=${bitRate}, size=${fileSize}`);
                    
                    return {
                        success: true,
                        track: track,
                        duration: duration, // секунды
                        durationFormatted: formatDuration(duration), // MM:SS
                        bitRate: bitRate,
                        fileSize: fileSize,
                        format: 'mp3'
                    };
                } catch (err) {
                    console.error(`❌ Error getting track info: ${err.message}`);
                    return h.response({ 
                        success: false, 
                        error: 'Failed to get track info',
                        details: err.message 
                    }).code(500);
                }
            }
        });
    }
};

// Вспомогательная функция для форматирования длительности
function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

module.exports = plugin;
