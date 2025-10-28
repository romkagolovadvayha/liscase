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
        this.stream = new EventEmitter();
    }

    init() {
        this._currentSong = Utils.readSong();
    }

    makeResponseSink() {
        const id = Utils.generateRandomId();
        const responseSink = PassThrough();
        this._sinks.set(id, responseSink);
        return { id, responseSink };
    }

    removeResponseSink(id) {
        this._sinks.delete(id);
    }

    _broadcastToEverySink(chunk) {
        for (const [, sink] of this._sinks) {
            sink.write(chunk);
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
        // Если очередь пуста, перезагружаем плейлист в очередь (loop)
        if (this._songs.length === 0) {
            const Utils = require('../utils');
            const allSongs = Utils.readSongs();
            
            if (allSongs.length > 0) {
                console.log('🔄 Queue empty, reloading playlist...');
                this._songs = [...allSongs]; // Копируем все треки снова
            } else {
                console.log('⚠️ No songs available');
                return;
            }
        }
        
        // Берём первый трек из очереди
        const nextSong = this._songs.shift();
        
        if (!nextSong) {
            console.log('⚠️ No songs in queue');
            return;
        }
        
        // Добавляем трек в конец очереди (loop)
        this._songs.push(nextSong);
        
        // НЕ меняем this._currentSong пока не начали стримить!
        const songToPlay = nextSong;

        console.log(`\n▶️  Now playing: ${Path.basename(songToPlay)}`);
        console.log(`📋 Queue length: ${this._songs.length}`);

        try {
            const bitRate = await this._getBitRate(songToPlay);
            
            if (!bitRate || bitRate <= 0) {
                console.error(`❌ Invalid bitrate for ${Path.basename(songToPlay)}, using default`);
            }
            
            // ТЕПЕРЬ меняем currentSong - поток начал читать файл!
            this._currentSong = songToPlay;
            
            const songReadable = Fs.createReadStream(songToPlay);
            const throttleTransformable = new Throttle(bitRate / 8);

            let hasEnded = false;
            let bytesStreamed = 0;
            const fileStats = Fs.statSync(this._currentSong);
            const fileSize = fileStats.size;
            
            console.log(`📊 File size: ${(fileSize / 1024 / 1024).toFixed(2)} MB`);
            console.log(`🎵 Streaming bitrate: ${bitRate / 1000} kbps`);
            
            // Обработка данных
            throttleTransformable.on('data', (chunk) => {
                bytesStreamed += chunk.length;
                this._broadcastToEverySink(chunk);
            });
            
            // Когда трек ПОЛНОСТЬЮ закончился (когда ReadStream закончился)
            songReadable.once('end', () => {
                console.log(`📤 ReadStream ended: streamed ${bytesStreamed} bytes`);
                // Ждём пока Throttle закончит передачу всех данных
            });
            
            // Когда Throttle закончил передачу ВСЕХ данных
            throttleTransformable.once('end', () => {
                if (hasEnded) return;
                hasEnded = true;
                
                console.log(`✅ FINISHED: ${Path.basename(this._currentSong)}`);
                console.log(`   Streamed: ${bytesStreamed} / ${fileSize} bytes`);
                console.log(`   Queue: ${this._songs.length} tracks remaining\n`);
                
                // Закрываем streams явно
                if (!songReadable.destroyed) {
                    songReadable.destroy();
                }
                if (!throttleTransformable.destroyed) {
                    throttleTransformable.destroy();
                }
                
                // Небольшая задержка перед следующим треком
                setTimeout(() => {
                    this._playLoop();
                }, 500);
            });
            
            // Обработка ошибок Throttle
            throttleTransformable.once('error', (err) => {
                if (hasEnded) return;
                hasEnded = true;
                
                console.error(`❌ Throttle error: ${err.message}`);
                songReadable.destroy();
                throttleTransformable.destroy();
                
                setTimeout(() => this._playLoop(), 500);
            });
            
            // Обработка ошибок ReadStream
            songReadable.once('error', (err) => {
                if (hasEnded) return;
                hasEnded = true;
                
                console.error(`❌ ReadStream error: ${err.message}`);
                throttleTransformable.destroy();
                
                setTimeout(() => this._playLoop(), 500);
            });
            
            // Закрытие потока (для информации)
            songReadable.once('close', () => {
                if (!hasEnded) {
                    console.log(`🔒 ReadStream closed (file not fully streamed!)`);
                }
            });

            this.stream.emit('play', this._currentSong);
            songReadable.pipe(throttleTransformable);
            
        } catch (err) {
            console.error(`❌ Error playing song: ${err.message}`);
            setTimeout(() => this._playLoop(), 500);
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
        // Не нужно ничего делать - просто инициирует переход к следующему треку
        // Текущий трек уже в конце очереди благодаря _playLoop
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
