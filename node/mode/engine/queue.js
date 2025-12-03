const Fs = require('fs');
const Path = require('path');
const EventEmitter = require('events');
const { PassThrough } = require('stream');

const Throttle = require('throttle');
const NeoBlessed = require('neo-blessed');
const { ffprobe } = require('@dropb/ffprobe');

const AbstractClasses = require('./shared/abstract-classes');
const Utils = require('../utils');
const { keys }  = require('../config');

/**
 * Class in charge of:
 * 1. A view layer for the queued up songs
 *       - 'this.box.children' contains view layer for the queued up songs
 * 2. A stream layer for the streaming of the queued up songs
 *       - 'this_songs' contains songs for the streaming
 */
class Queue extends AbstractClasses.TerminalItemBox {

    constructor(params) {
        super(params);
        this._sinks = new Map(); // map of active sinks/writables
        this._songs = []; // list of queued up songs
        this._currentSong = null;
        this._currentStreams = null; // { readable, throttle } - текущие потоки для остановки
        this._shouldSkip = false; // флаг для пропуска текущего трека
        this._isPlaying = false; // флаг для защиты от рекурсивных вызовов _playLoop
        this.stream = new EventEmitter();
    }

    init() {
        this._currentSong = Utils.readSong();
    }

    makeResponseSink() {
        const id = Utils.generateRandomId();
        const responseSink = PassThrough({
            highWaterMark: 16 * 1024 * 1024, // 16 МБ буфер для плавного стрима
            allowHalfOpen: false // Закрывать поток при ошибках
        });
        
        // Обработка ошибок в PassThrough потоке
        responseSink.on('error', (err) => {
            if (process.env.VERBOSE_LOGS === 'true') {
                console.warn(`⚠️  Sink ${id} error: ${err.message}`);
            }
            // Не удаляем сразу, даём возможность восстановиться
            // Удалим при следующей попытке записи
        });
        
        // Обработка закрытия потока
        responseSink.on('close', () => {
            // Логируем только если включен verbose режим
            if (process.env.VERBOSE_LOGS === 'true') {
                console.log(`🔒 Sink ${id} closed`);
            }
            this.removeResponseSink(id);
        });
        
        // Обработка завершения потока
        responseSink.on('end', () => {
            // Логируем только если включен verbose режим
            if (process.env.VERBOSE_LOGS === 'true') {
                console.log(`🏁 Sink ${id} ended`);
            }
            this.removeResponseSink(id);
        });
        
        // Обработка drain - когда буфер освободился
        responseSink.on('drain', () => {
            // Буфер освободился, можно продолжать запись
            if (!responseSink.destroyed) {
                responseSink.resume();
            }
        });
        
        // Убеждаемся, что поток не приостанавливается
        responseSink.resume();
        
        this._sinks.set(id, responseSink);
        // Логируем подключения только если включен verbose режим или при первом подключении
        if (process.env.VERBOSE_LOGS === 'true' || this._sinks.size === 1) {
            console.log(`➕ New sink ${id} created (${this._sinks.size} total sinks)`);
        }
        
        // Если есть текущий трек, который играет, новый клиент начнёт получать данные сразу
        // благодаря тому, что _broadcastToEverySink уже работает
        
        return { id, responseSink };
    }

    removeResponseSink(id) {
        this._sinks.delete(id);
    }

    _broadcastToEverySink(chunk) {
        // Оптимизация: если нет активных sinks, не обрабатываем
        if (this._sinks.size === 0) {
            return;
        }
        
        const deadSinks = [];
        
        for (const [id, sink] of this._sinks) {
            try {
                // Проверяем, не закрыт ли поток
                if (sink.destroyed || sink.writableEnded || !sink.writable) {
                    deadSinks.push(id);
                    continue;
                }
                
                // Пытаемся записать данные
                // Используем try-catch для обработки ошибок записи
                try {
                    const canWrite = sink.write(chunk);
                    
                    // Если буфер полон, не ждём - просто продолжаем
                    // Sink сам вызовет drain когда будет готов
                    if (!canWrite) {
                        // Не блокируем, просто продолжаем с другими sinks
                        // sink.once('drain', () => {}) - убрано для производительности
                    }
                } catch (writeErr) {
                    // Ошибка при записи - помечаем как мёртвый
                    deadSinks.push(id);
                }
            } catch (err) {
                // Если произошла ошибка при проверке, помечаем sink как мёртвый
                if (process.env.VERBOSE_LOGS === 'true') {
                    console.warn(`⚠️  Error writing to sink ${id}: ${err.message}`);
                }
                deadSinks.push(id);
            }
        }
        
        // Удаляем мёртвые sinks (делаем это асинхронно, чтобы не блокировать)
        if (deadSinks.length > 0) {
            setImmediate(() => {
                for (const id of deadSinks) {
                    try {
                        const sink = this._sinks.get(id);
                        if (sink && !sink.destroyed) {
                            sink.destroy();
                        }
                        this._sinks.delete(id);
                        // Логируем удаление только если включен verbose режим
                        if (process.env.VERBOSE_LOGS === 'true') {
                            console.log(`🗑️  Removed dead sink ${id} (${this._sinks.size} active sinks remaining)`);
                        }
                    } catch (err) {
                        // Игнорируем ошибки при удалении
                        this._sinks.delete(id);
                    }
                }
            });
        }
    }

    async _getBitRate(song) {
        try {
            // Пытаемся получить оригинальный битрейт
            let originalBitRate = 192000; // default
            try {
                const result = await ffprobe(Path.join(process.cwd(), song));
                originalBitRate = parseInt(result.format.bit_rate) || 192000;
            } catch (ffprobeError) {
                // ffprobe недоступен - используем дефолт
                console.warn(`⚠️  ffprobe not available, using default bitrate for ${Path.basename(song)}`);
            }
            
            // Ограничиваем битрейт для экономии трафика
            // Максимальный стриминговый битрейт: 96 kbps (оптимально для стрима)
            const MAX_STREAM_BITRATE = 96000;
            
            let streamBitRate = Math.min(originalBitRate, MAX_STREAM_BITRATE);
            
            // Если файл слишком высокого качества, всегда понижаем до 96 kbps
            if (originalBitRate > 128000) {
                streamBitRate = 96000; // 96 kbps - оптимально для стрима
                console.log(`🎚️  Bitrate reduced: ${originalBitRate} -> ${streamBitRate} bps (${Path.basename(song)})`);
            }
            
            return streamBitRate;
        } catch (err) {
            console.warn(`⚠️  Could not get bitrate for ${Path.basename(song)}, using default 96 kbps`);
            return 96000; // reasonable default для стрима
        }
    }

    async _playLoop() {
        // Защита от рекурсивных вызовов - предотвращаем одновременное воспроизведение нескольких треков
        if (this._isPlaying) {
            if (process.env.VERBOSE_LOGS === 'true') {
                console.log('⏸️  Already playing, skipping duplicate call');
            }
            return;
        }
        this._isPlaying = true;
        
        try {
        // Если очередь пуста, перезагружаем плейлист в очередь (loop)
        if (this._songs.length === 0) {
            const Utils = require('../utils');
            const allSongs = Utils.readSongs();
            
            if (allSongs.length > 0) {
                console.log('🔄 Queue empty, reloading playlist...');
                this._songs = [...allSongs]; // Копируем все треки снова
            } else {
                console.log('⚠️ No songs available');
                    this._isPlaying = false;
                return;
            }
        }
        
        // Берём первый трек из очереди
        const nextSong = this._songs.shift();
        
        if (!nextSong) {
            console.log('⚠️ No songs in queue');
                this._isPlaying = false;
            return;
        }
        
        // Добавляем трек в конец очереди (loop)
        this._songs.push(nextSong);
        
        // НЕ меняем this._currentSong пока не начали стримить!
        const songToPlay = nextSong;

        console.log(`\n▶️  Now playing: ${Path.basename(songToPlay)}`);
        console.log(`📋 Queue length: ${this._songs.length}`);

            // Проверяем существование файла перед чтением
            const songPath = Path.resolve(process.cwd(), songToPlay);
            
            // Проверяем существование файла (используем existsSync, но это быстрая операция)
            if (!Fs.existsSync(songPath)) {
                console.error(`❌ File not found: ${songPath}`);
                throw new Error(`File not found: ${songToPlay}`);
            }
            
            // Получаем битрейт параллельно с другими операциями
            const bitRate = await this._getBitRate(songToPlay);
            
            if (!bitRate || bitRate <= 0) {
                console.error(`❌ Invalid bitrate for ${Path.basename(songToPlay)}, using default`);
            }
            
            // ТЕПЕРЬ меняем currentSong - поток начал читать файл!
            this._currentSong = songToPlay;
            this._shouldSkip = false; // сбрасываем флаг пропуска
            
            // Создаём ReadStream с опциями для непрерывного чтения
            // Используем большой буфер как в Discord боте для плавного стрима
            const songReadable = Fs.createReadStream(songPath, {
                highWaterMark: 1 << 25, // 32 МБ буфер для плавного стрима (как в Discord боте)
                autoClose: false // Не закрывать файл автоматически
            });
            
            // Throttle с оптимизированными настройками
            // Важно: throttle не должен останавливать поток, даже если данные не читаются
            const throttleTransformable = new Throttle(bitRate / 8);
            
            // Сохраняем ссылки на потоки для возможности остановки
            this._currentStreams = {
                readable: songReadable,
                throttle: throttleTransformable
            };
            
            // Убеждаемся, что потоки не приостанавливаются
            songReadable.resume();
            throttleTransformable.resume();
            
            // КРИТИЧЕСКИ ВАЖНО: постоянно возобновляем throttle, чтобы он не останавливался
            // Это предотвращает остановку стрима между треками
            const throttleKeepAlive = setInterval(() => {
                if (!throttleTransformable.destroyed && !this._shouldSkip) {
                    try {
                        // Убеждаемся, что throttle не приостановлен
                        if (throttleTransformable.isPaused && throttleTransformable.isPaused()) {
                            throttleTransformable.resume();
                        }
                        // Также убеждаемся, что readable активен
                        if (songReadable && !songReadable.destroyed) {
                            if (songReadable.isPaused && songReadable.isPaused()) {
                                songReadable.resume();
                            }
                        }
                    } catch (err) {
                        // Игнорируем ошибки
                        clearInterval(throttleKeepAlive);
                    }
                } else {
                    clearInterval(throttleKeepAlive);
                }
            }, 100); // Проверяем каждые 100мс для максимальной надежности
            
            // Очищаем интервал при завершении
            throttleTransformable.once('end', () => {
                clearInterval(throttleKeepAlive);
            });
            
            throttleTransformable.once('error', () => {
                clearInterval(throttleKeepAlive);
            });
            
            // Обработка ошибок ReadStream - продолжаем работу
            songReadable.on('error', (err) => {
                console.error(`❌ ReadStream error: ${err.message}`);
                // Не останавливаем полностью, пытаемся продолжить
                if (!songReadable.destroyed && !this._shouldSkip) {
                    songReadable.resume();
                }
            });

            let hasEnded = false;
            let bytesStreamed = 0;
            
            // Получаем размер файла асинхронно, чтобы не блокировать поток
            let fileSize = 0;
            try {
                const fileStats = Fs.statSync(songPath);
                fileSize = fileStats.size;
            } catch (err) {
                console.warn(`⚠️  Could not get file size: ${err.message}`);
                // Используем примерный размер, если не удалось получить
                fileSize = 0;
            }
            
            // Таймаут для защиты от зависания (максимум 10 минут на трек)
            const maxDuration = 10 * 60 * 1000; // 10 минут
            const timeoutId = setTimeout(() => {
                if (!hasEnded) {
                    console.warn(`⏱️  Timeout reached for ${Path.basename(songToPlay)}, skipping...`);
                    this._shouldSkip = true;
                    if (this._currentStreams) {
                        try {
                            if (!songReadable.destroyed) {
                                songReadable.destroy();
                            }
                            if (!throttleTransformable.destroyed) {
                                throttleTransformable.destroy();
                            }
                        } catch (err) {
                            // Игнорируем ошибки
                        }
                    }
                }
            }, maxDuration);
            
            console.log(`📊 File size: ${(fileSize / 1024 / 1024).toFixed(2)} MB`);
            console.log(`🎵 Streaming bitrate: ${bitRate / 1000} kbps`);
            console.log(`👂 Active listeners: ${this._sinks.size}`);
            
            // Обработка данных
            throttleTransformable.on('data', (chunk) => {
                // Проверяем, не нужно ли пропустить трек
                if (this._shouldSkip) {
                    return;
                }
                
                bytesStreamed += chunk.length;
                
                // Стримим даже если нет активных слушателей (радио продолжает работать)
                // Всегда отправляем данные для непрерывного стрима
                // КРИТИЧЕСКИ ВАЖНО: отправляем данные немедленно, не блокируя
                try {
                this._broadcastToEverySink(chunk);
                } catch (err) {
                    // Ошибка при отправке не должна останавливать поток
                    if (process.env.VERBOSE_LOGS === 'true') {
                        console.warn(`⚠️  Error broadcasting chunk: ${err.message}`);
                    }
                }
            });
            
            // Убеждаемся, что throttle продолжает работать
            throttleTransformable.on('drain', () => {
                // Буфер освободился, можно продолжать
                if (!throttleTransformable.destroyed && !this._shouldSkip) {
                    throttleTransformable.resume();
                }
            });
            
            // Обработка паузы throttle (если буфер переполнен)
            throttleTransformable.on('pause', () => {
                // Автоматически возобновляем при паузе
                setImmediate(() => {
                    if (!throttleTransformable.destroyed && !this._shouldSkip) {
                        throttleTransformable.resume();
                    }
                });
            });
            
            // Обработка ошибок throttle в data handler - продолжаем работу
            throttleTransformable.on('error', (err) => {
                // Это не должно останавливать основной поток
                // Основная обработка ошибок в once('error') ниже
                if (process.env.VERBOSE_LOGS === 'true') {
                    console.warn(`⚠️  Throttle warning in data handler: ${err.message}`);
                }
            });
            
            // Когда трек ПОЛНОСТЬЮ закончился (когда ReadStream закончился)
            songReadable.once('end', () => {
                if (this._shouldSkip) {
                    return;
                }
                console.log(`📤 ReadStream ended: streamed ${bytesStreamed} bytes`);
                // КРИТИЧЕСКИ ВАЖНО: Не закрываем readable сразу!
                // Throttle должен закончить передачу всех данных
                // ReadStream автоматически закроется после того, как все данные будут прочитаны
                // Это обеспечивает непрерывность потока между треками
            });
            
            // Когда Throttle закончил передачу ВСЕХ данных
            throttleTransformable.once('end', () => {
                if (hasEnded) return;
                hasEnded = true;
                
                // Очищаем таймаут
                clearTimeout(timeoutId);
                
                // Сохраняем информацию о текущем треке перед очисткой
                const finishedSong = this._currentSong;
                const streamedBytes = bytesStreamed;
                const totalBytes = fileSize;
                const wasSkipped = this._shouldSkip;
                
                // Очищаем ссылки на потоки
                this._currentStreams = null;
                
                if (wasSkipped) {
                    console.log(`⏭️  Track skipped: ${Path.basename(finishedSong)}`);
                } else {
                    console.log(`✅ FINISHED: ${Path.basename(finishedSong)}`);
                    console.log(`   Streamed: ${streamedBytes} / ${totalBytes} bytes`);
                }
                console.log(`   Queue: ${this._songs.length} tracks remaining`);
                
                // Закрываем streams явно
                try {
                if (!songReadable.destroyed) {
                    songReadable.destroy();
                }
                if (!throttleTransformable.destroyed) {
                    throttleTransformable.destroy();
                    }
                } catch (err) {
                    // Игнорируем ошибки при закрытии
                }
                
                // КРИТИЧЕСКИ ВАЖНО: Немедленно начинаем следующий трек без задержки
                // Используем setImmediate для максимально быстрого перехода
                // Это гарантирует, что следующий трек начнется до того, как клиент заметит паузу
                // НЕ используем setTimeout - это создаст задержку!
                setImmediate(() => {
                    // Дополнительная проверка, что мы не в процессе остановки
                    if (!this._shouldSkip) {
                        // Сбрасываем флаг перед следующим треком
                        this._isPlaying = false;
                        this._playLoop();
                    } else {
                        this._isPlaying = false;
                    }
                });
            });
            
            // Обработка ошибок Throttle
            throttleTransformable.once('error', (err) => {
                if (hasEnded) return;
                hasEnded = true;
                
                clearTimeout(timeoutId);
                this._currentStreams = null;
                console.error(`❌ Throttle error: ${err.message}`);
                
                try {
                    if (!songReadable.destroyed) {
                songReadable.destroy();
                    }
                    if (!throttleTransformable.destroyed) {
                throttleTransformable.destroy();
                    }
                } catch (destroyErr) {
                    // Игнорируем ошибки при уничтожении
                }
                
                // Немедленно переходим к следующему треку
                setImmediate(() => {
                    this._playLoop();
                });
            });
            
            // Обработка ошибок ReadStream
            songReadable.once('error', (err) => {
                if (hasEnded) return;
                hasEnded = true;
                
                clearTimeout(timeoutId);
                this._currentStreams = null;
                console.error(`❌ ReadStream error: ${err.message}`);
                
                try {
                    if (!throttleTransformable.destroyed) {
                throttleTransformable.destroy();
                    }
                } catch (destroyErr) {
                    // Игнорируем ошибки при уничтожении
                }
                
                // Немедленно переходим к следующему треку
                setImmediate(() => {
                    this._playLoop();
                });
            });
            
            // Закрытие потока (для информации)
            songReadable.once('close', () => {
                // Это событие может сработать после 'end', поэтому проверяем hasEnded
                // Не выводим предупреждение, если трек был успешно завершён
                if (!hasEnded && !this._shouldSkip) {
                    // Это нормально, если close срабатывает после end
                    // Просто логируем для отладки, но не как ошибку
                }
            });

            this.stream.emit('play', this._currentSong);
            songReadable.pipe(throttleTransformable);
            
        } catch (err) {
            console.error(`❌ Error playing song: ${err.message}`);
            console.error(err.stack);
            
            // Очищаем ссылки на потоки при ошибке
            this._currentStreams = null;
            this._currentSong = null;
            this._isPlaying = false; // Сбрасываем флаг при ошибке
            
            // Продолжаем воспроизведение следующего трека
            // Используем process.nextTick для быстрого восстановления
            process.nextTick(() => {
                try {
                    this._playLoop();
                } catch (loopErr) {
                    console.error(`❌ Critical error in playLoop: ${loopErr.message}`);
                    this._isPlaying = false;
                    // Если даже playLoop падает, пытаемся ещё раз через 1 секунду
                    setTimeout(() => {
                        console.log('🔄 Attempting to recover...');
                        this._isPlaying = false;
                        this._playLoop();
                    }, 1000);
                }
            });
        }
    }

    startStreaming() {
        this._playLoop();
    }

    _createBoxChild(content) {

        return NeoBlessed.box({
            ...this._childConfig,
            top: this.box.children.length - 1,
            content: `${this.box.children.length}. ${content}`
        });
    }

    _boxChildrenIndexToSongsIndex(index) {
        // converts index of this.box.children array (view layer)
        // to the index of this._songs array (stream layer)
        return index - 1;
    }

    _createAndAppendToSongs(song) {
        this._songs.push(song);
    }

    _createAndAppendToBoxChildren(song) {
        this.createBoxChildAndAppend(song);
    }

    createAndAppendToQueue(song) {
        this._createAndAppendToBoxChildren(song);
        this._createAndAppendToSongs(song);
    }

    _removeFromSongs(index) {
        const adjustedIndex = this._boxChildrenIndexToSongsIndex(index);
        return this._songs.splice(adjustedIndex, 1);
    }

    _discardFromBox(index) {
        this.box.remove(this.box.children[index]);
    }

    _orderBoxChildren() {
        this.box.children.forEach((child, index) => {

            if (index !== 0) {
                child.top = index - 1;
                child.content = `${index}. ${Utils.discardFirstWord(child.content)}`;
            }
        });
    }

    _removeFromBoxChildren(index) {

        const child = this.box.children[index];
        const content = child && child.content;

        if (!content) {
            return {};
        }

        this._discardFromBox(index);
        this._orderBoxChildren();
        this._focusIndexer.decr();
    }

    removeFromQueue({ fromTop } = {}) {

        const index = fromTop ? 1 : this._focusIndexer.get();

        this._removeFromBoxChildren(index);
        const [song] = this._removeFromSongs(index);
        return song;
    }

    _changeOrderInSongs(boxChildrenIndex1, boxChildrenIndex2) {

        const songsArrayIndex1 = this._boxChildrenIndexToSongsIndex(boxChildrenIndex1);
        const songaArrayIndex2 = this._boxChildrenIndexToSongsIndex(boxChildrenIndex2);
        [
            this._songs[songsArrayIndex1], this._songs[songaArrayIndex2]
        ] = [
            this._songs[songaArrayIndex2], this._songs[songsArrayIndex1]
        ];
    }

    _changeOrderInBoxChildren(key) {

        const index1 = this._focusIndexer.get();
        const child1 = this.box.children[index1];
        child1.style.bg = this._bgBlur;

        if (key === keys.MOVE_UP) {
            this._focusIndexer.decr();
        }
        else if (key === keys.MOVE_DOWN) {
            this._focusIndexer.incr();
        }

        const index2 = this._focusIndexer.get();
        const child2 = this.box.children[index2];
        child2.style.bg = this._bgFocus;

        [
            child1.content,
            child2.content
        ] = [
            `${Utils.getFirstWord(child1.content)} ${Utils.discardFirstWord(child2.content)}`,
            `${Utils.getFirstWord(child2.content)} ${Utils.discardFirstWord(child1.content)}`,
        ];

        return { index1, index2 };
    }

    changeOrderQueue(key) {

        if (this.box.children.length === 1) {
            return;
        }
        const { index1, index2 } = this._changeOrderInBoxChildren(key);
        this._changeOrderInSongs(index1, index2);
    }

    // ====== REST API METHODS ======

    /**
     * Get current playing song
     * @returns {string|null}
     */
    getCurrentSong() {
        return this._currentSong;
    }

    /**
     * Get queue of songs
     * @returns {Array<string>}
     */
    getQueue() {
        return this._songs;
    }

    /**
     * Get current position (always 0 for now)
     * @returns {number}
     */
    getCurrentPosition() {
        return 0;
    }

    /**
     * Get listeners count
     * @returns {number}
     */
    getListenersCount() {
        return this._sinks.size;
    }

    /**
     * Skip to next track (force next song immediately)
     * Текущий трек не удаляется, остаётся в очереди
     */
    skipToNext() {
        console.log('⏭️  Skipping to next track...');
        
        // Устанавливаем флаг для пропуска текущего трека
        this._shouldSkip = true;
        
        // Останавливаем текущие потоки, если они есть
        if (this._currentStreams) {
            try {
                const { readable, throttle } = this._currentStreams;
                
                // Уничтожаем потоки
                if (readable && !readable.destroyed) {
                    readable.destroy();
                }
                if (throttle && !throttle.destroyed) {
                    throttle.destroy();
                }
                
                console.log('🛑 Current streams stopped');
            } catch (err) {
                console.warn(`⚠️  Error stopping streams: ${err.message}`);
            }
        }
        
        // Добавляем текущий трек в конец очереди, если его там ещё нет
        if (this._currentSong && !this._songs.includes(this._currentSong)) {
            this._songs.push(this._currentSong);
        }
        
        return true;
    }

    /**
     * Add track to end of queue (without UI update)
     * Used by backend when approving new tracks
     * @param {string} track - filename relative to cwd (e.g. "abc123.mp3")
     */
    addToQueue(track) {
        // Проверяем что файл существует
        const trackPath = Path.resolve(process.cwd(), track);
        
        if (!Fs.existsSync(trackPath)) {
            console.error(`❌ Track file not found: ${trackPath}`);
            throw new Error(`Track file not found: ${track}`);
        }
        
        // Добавляем в очередь (используем относительный путь как он хранится в очереди)
        this._songs.push(track);
        console.log(`➕ Track added to queue: ${track}`);
        console.log(`   Full path: ${trackPath}`);
        console.log(`   Queue length: ${this._songs.length}`);
        console.log(`   Current cwd: ${process.cwd()}`);
    }

    /**
     * Add track to front of queue (prepend to songs array)
     * @param {string} track
     */
    createAndPrependToQueue(track) {
        this._songs.unshift(track);
        console.log(`⏭️ Track added to front of queue: ${Path.basename(track)} (Queue length: ${this._songs.length})`);
        // Добавляем в начало визуального списка
        if (this.box && this.box.children.length > 0) {
            const child = this._createBoxChild(track);
            this.box.insertBefore(child, this.box.children[1] || this.box.children[0]);
            this._orderBoxChildren();
        }
    }

    /**
     * Remove specific track from queue
     * @param {string} track
     * @returns {boolean}
     */
    removeTrackFromQueue(track) {
        const index = this._songs.indexOf(track);
        if (index !== -1) {
            this._songs.splice(index, 1);
            // Обновить визуальный список
            if (this.box && this.box.children[index + 1]) {
                this._discardFromBox(index + 1);
                this._orderBoxChildren();
            }
            return true;
        }
        return false;
    }

    /**
     * Reload queue from filesystem
     */
    reloadQueue() {
        const Utils = require('../utils');
        const songs = Utils.readSongs();
        
        // Очистить текущую очередь
        this._songs = [];
        
        // Добавить все треки
        for (const song of songs) {
            this._songs.push(song);
        }
        
        // Обновить визуальный список
        if (this.box) {
            // Удалить все кроме первого элемента (заголовок)
            while (this.box.children.length > 1) {
                this._discardFromBox(1);
            }
            
            // Добавить новые
            for (const song of songs) {
                this._createAndAppendToBoxChildren(song);
            }
        }
    }
}

module.exports = Queue;
