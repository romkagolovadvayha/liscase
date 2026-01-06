const Path = require('path');
const Fs = require('fs');
const { ffprobe } = require('@dropb/ffprobe');
const Radio = require('node-internet-radio');

/**
 * Get metadata from MP3 file
 * Uses node-internet-radio approach for metadata extraction
 */
async function getTrackMetadata(filePath) {
    try {
        const fullPath = Path.resolve(process.cwd(), filePath);
        
        if (!Fs.existsSync(fullPath)) {
            return null;
        }

        // Try to get metadata from file using ffprobe
        try {
            const probeResult = await ffprobe(fullPath);
            const tags = probeResult.format?.tags || {};
            
            return {
                title: tags.title || tags.TITLE || Path.basename(filePath, '.mp3'),
                artist: tags.artist || tags.ARTIST || 'Unknown Artist',
                album: tags.album || tags.ALBUM || '',
                genre: tags.genre || tags.GENRE || '',
                year: tags.date || tags.YEAR || tags.date || '',
                duration: parseFloat(probeResult.format?.duration) || 0,
                bitrate: parseInt(probeResult.format?.bit_rate) || 128000,
                format: probeResult.format?.format_name || 'mp3'
            };
        } catch (ffprobeError) {
            // Fallback: extract basic info from filename
            const filename = Path.basename(filePath, '.mp3');
            return {
                title: filename,
                artist: 'Unknown Artist',
                album: '',
                genre: '',
                year: '',
                duration: 0,
                bitrate: 128000,
                format: 'mp3'
            };
        }
    } catch (err) {
        console.warn(`⚠️  Error getting metadata for ${filePath}: ${err.message}`);
        return null;
    }
}

/**
 * Get station info in node-internet-radio format
 * Uses node-internet-radio API for compatibility
 * @param {string} streamUrl - URL потока или путь к файлу
 * @param {Function} callback - callback функция
 */
function getStationInfo(streamUrl, callback) {
    // Если это URL потока (начинается с http:// или https://), используем node-internet-radio
    if (streamUrl.startsWith('http://') || streamUrl.startsWith('https://')) {
        Radio.getStationInfo(streamUrl, callback);
        return;
    }
    
    // Для локальных файлов используем наш метод
    getTrackMetadata(streamUrl)
        .then(metadata => {
            if (!metadata) {
                return callback(new Error('Could not get metadata'));
            }
            
            // Format in node-internet-radio style
            callback(null, {
                name: metadata.title,
                title: metadata.title,
                artist: metadata.artist,
                album: metadata.album,
                genre: metadata.genre,
                year: metadata.year,
                duration: metadata.duration,
                bitrate: metadata.bitrate
            });
        })
        .catch(err => {
            callback(err);
        });
}

/**
 * Get station info from file (backward compatibility)
 * @deprecated Use getStationInfo instead
 */
function getStationInfoFromFile(filePath, callback) {
    getStationInfo(filePath, callback);
}

/**
 * Format metadata for API response
 */
function formatMetadataForAPI(metadata) {
    if (!metadata) {
        return null;
    }
    
    return {
        title: metadata.title,
        artist: metadata.artist,
        album: metadata.album || null,
        genre: metadata.genre || null,
        year: metadata.year || null,
        duration: metadata.duration || 0,
        durationFormatted: formatDuration(metadata.duration || 0),
        bitrate: metadata.bitrate || 128000,
        format: metadata.format || 'mp3'
    };
}

/**
 * Format duration in MM:SS format
 */
function formatDuration(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

module.exports = {
    getTrackMetadata,
    getStationInfo,
    getStationInfoFromFile, // для обратной совместимости
    formatMetadataForAPI,
    formatDuration,
    Radio // экспортируем для прямого использования
};

