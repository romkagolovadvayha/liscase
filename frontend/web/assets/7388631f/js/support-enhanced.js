/**
 * Улучшенная система поддержки с автосохранением, восстановлением соединения и улучшенным UX
 */
class SupportChat {
    constructor() {
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.messageQueue = [];
        this.isPageVisible = true;
        this.lastMessageId = null;
        this.autoRefreshInterval = null;
        this.connectionStatus = 'disconnected';
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.startConnectionMonitoring();
        this.setupPageVisibilityHandler();
        this.setupAutoRefresh();
        this.loadPendingMessages();
    }

    bindEvents() {
        // Обработка отправки сообщений
        $(document).on('click', '#supportMessageSend', () => this.sendMessage());
        $(document).on('keypress', '#supportMessage', (e) => {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Обработка файлов
        $(document).on('change', '#supportMessageFile', (e) => this.handleFileUpload(e));

        // Обработка стикеров
        $(document).on('click', '.sticker-btn', () => this.showStickerPicker());
        $(document).on('click', '.sticker-item', (e) => this.selectSticker(e));

        // Обработка ссылок в сообщениях
        $(document).on('click', '.support_message_text a', (e) => {
            e.stopPropagation();
            // Открываем ссылку в новой вкладке
            window.open(e.target.href, '_blank');
        });

        // Обработка эмодзи
        $(document).on('click', '.emoji-btn', () => this.showEmojiPicker());
        $(document).on('click', '.emoji-item', (e) => this.selectEmoji(e));
    }

    setupPageVisibilityHandler() {
        // Обработка сворачивания/разворачивания браузера
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.isPageVisible = false;
            } else {
                this.isPageVisible = true;
                this.onPageVisible();
            }
        });

        // Обработка фокуса окна
        window.addEventListener('focus', () => {
            this.onPageVisible();
        });
    }

    onPageVisible() {
        // При возврате в браузер проверяем соединение и обновляем чат
        setTimeout(() => {
            this.checkConnection();
            this.refreshChat();
        }, 500);
    }

    setupAutoRefresh() {
        // Автообновление чата каждые 30 секунд
        this.autoRefreshInterval = setInterval(() => {
            if (this.isPageVisible && !this.isConnected) {
                this.refreshChat();
            }
        }, 30000);
    }

    startConnectionMonitoring() {
        // Мониторинг состояния WebSocket соединения
        setInterval(() => {
            this.checkConnection();
        }, 5000);
    }

    checkConnection() {
        if (typeof chat !== 'undefined' && chat) {
            const wasConnected = this.isConnected;
            this.isConnected = chat.readyState === WebSocket.OPEN;
            
            if (wasConnected !== this.isConnected) {
                this.updateConnectionStatus();
                
                if (this.isConnected) {
                    this.onReconnected();
                } else {
                    this.onDisconnected();
                }
            }
        }
    }

    updateConnectionStatus() {
        const statusElement = $('#connection-status');
        if (statusElement.length === 0) {
            // Создаем индикатор статуса соединения
            $('.support_messages_form').prepend(`
                <div id="connection-status" class="connection-status">
                    <span class="status-indicator"></span>
                    <span class="status-text">Проверка соединения...</span>
                </div>
            `);
        }

        const statusText = $('.status-text');
        const statusIndicator = $('.status-indicator');

        if (this.isConnected) {
            statusText.text('Подключено');
            statusIndicator.removeClass('disconnected').addClass('connected');
        } else {
            statusText.text('Соединение потеряно');
            statusIndicator.removeClass('connected').addClass('disconnected');
        }
    }

    onReconnected() {
        console.log('WebSocket переподключен');
        this.reconnectAttempts = 0;
        
        // Отправляем все сообщения из очереди
        this.sendQueuedMessages();
        
        // Обновляем чат
        this.refreshChat();
        
        // Показываем уведомление
        this.showNotification('Соединение восстановлено', 'success');
    }

    onDisconnected() {
        console.log('WebSocket отключен');
        this.showNotification('Потеряно соединение. Сообщения будут отправлены при восстановлении связи.', 'warning');
    }

    sendQueuedMessages() {
        while (this.messageQueue.length > 0) {
            const message = this.messageQueue.shift();
            this.sendMessageToServer(message);
        }
    }

    sendMessage() {
        const $messageInput = $('#supportMessage');
        const $fileInput = $('#supportMessageFile');
        const message = $messageInput.val().trim();
        const file = $fileInput[0].files[0];

        if (!message && !file) return;

        // Создаем объект сообщения
        const messageData = {
            message: message,
            file: file,
            timestamp: Date.now(),
            id: 'temp_' + Date.now()
        };

        // Добавляем в очередь если нет соединения
        if (!this.isConnected) {
            this.messageQueue.push(messageData);
            this.showNotification('Сообщение сохранено. Будет отправлено при восстановлении связи.', 'info');
        }

        // Отправляем сообщение
        this.sendMessageToServer(messageData);

        // Очищаем форму
        $messageInput.val('');
        $fileInput.val('');
        this.resizeTextarea();
    }

    sendMessageToServer(messageData) {
        if (this.isConnected && typeof chat !== 'undefined') {
            try {
                const payload = {
                    action: 'chat',
                    message: messageData.message,
                    chatId: chatId
                };

                if (messageData.file) {
                    payload.file = messageData.file;
                }

                chat.send(JSON.stringify(payload));
                
                // Показываем сообщение локально
                this.addMessageToChat(messageData, true);
                
            } catch (error) {
                console.error('Ошибка отправки сообщения:', error);
                this.messageQueue.push(messageData);
                this.showNotification('Ошибка отправки. Сообщение сохранено.', 'error');
            }
        } else {
            // Сохраняем в localStorage для последующей отправки
            this.savePendingMessage(messageData);
        }
    }

    savePendingMessage(messageData) {
        const pendingMessages = JSON.parse(localStorage.getItem('pending_support_messages') || '[]');
        pendingMessages.push(messageData);
        localStorage.setItem('pending_support_messages', JSON.stringify(pendingMessages));
    }

    loadPendingMessages() {
        const pendingMessages = JSON.parse(localStorage.getItem('pending_support_messages') || '[]');
        
        if (pendingMessages.length > 0) {
            this.showNotification(`У вас ${pendingMessages.length} неотправленных сообщений`, 'info');
            
            // Показываем кнопку для отправки
            this.showResendButton(pendingMessages);
        }
    }

    showResendButton(pendingMessages) {
        const resendHtml = `
            <div id="resend-messages" class="resend-messages">
                <button onclick="supportChat.resendPendingMessages()" class="btn btn-primary btn-sm">
                    Отправить ${pendingMessages.length} сообщений
                </button>
            </div>
        `;
        
        $('.support_messages_form').prepend(resendHtml);
    }

    resendPendingMessages() {
        const pendingMessages = JSON.parse(localStorage.getItem('pending_support_messages') || '[]');
        
        pendingMessages.forEach(message => {
            this.sendMessageToServer(message);
        });
        
        // Очищаем localStorage
        localStorage.removeItem('pending_support_messages');
        
        // Убираем кнопку
        $('#resend-messages').remove();
    }

    refreshChat() {
        // Загружаем новые сообщения через AJAX
        $.ajax({
            url: '/support/refresh-chat',
            method: 'GET',
            data: { chatId: chatId, lastMessageId: this.lastMessageId },
            success: (response) => {
                if (response.success && response.messages.length > 0) {
                    response.messages.forEach(message => {
                        this.addMessageToChat(message, false);
                    });
                    this.lastMessageId = response.lastMessageId;
                    this.scrollToBottom();
                }
            },
            error: (error) => {
                console.error('Ошибка обновления чата:', error);
            }
        });
    }

    addMessageToChat(messageData, isOwn = false) {
        const messageHtml = this.createMessageHtml(messageData, isOwn);
        $('#chat').append(messageHtml);
        
        // Обрабатываем ссылки
        this.processLinks();
        
        // Обрабатываем стикеры
        this.processStickers();
        
        this.scrollToBottom();
    }

    createMessageHtml(messageData, isOwn) {
        const messageClass = isOwn ? 'own' : 'other';
        const processedMessage = this.processMessageContent(messageData.message);
        
        return `
            <div class="support_message ${messageClass}" data-message-id="${messageData.id}">
                <div class="support_message_content">
                    <div class="support_message_text">${processedMessage}</div>
                    <div class="support_message_time">${this.formatTime(messageData.timestamp)}</div>
                </div>
            </div>
        `;
    }

    processMessageContent(message) {
        // Обрабатываем ссылки
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        message = message.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        
        // Обрабатываем эмодзи
        message = this.processEmojis(message);
        
        // Обрабатываем стикеры
        message = this.processStickers(message);
        
        // Переносы строк
        message = message.replace(/\n/g, '<br>');
        
        return message;
    }

    processLinks() {
        $('.support_message_text').each(function() {
            const $this = $(this);
            const text = $this.html();
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const processedText = text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener" class="message-link">$1</a>');
            $this.html(processedText);
        });
    }

    processEmojis(text) {
        const emojiMap = {
            ':)': '😊',
            ':(': '😢',
            ':D': '😃',
            ':P': '😛',
            ';)': '😉',
            ':|': '😐',
            ':o': '😮',
            ':*': '😘',
            '<3': '❤️',
            '</3': '💔'
        };

        for (const [shortcut, emoji] of Object.entries(emojiMap)) {
            text = text.replace(new RegExp(shortcut.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), emoji);
        }

        return text;
    }

    processStickers() {
        // Обрабатываем стикеры в сообщениях
        $('.support_message_text').each(function() {
            const $this = $(this);
            let text = $this.html();
            
            // Заменяем стикеры на изображения
            const stickerRegex = /\[sticker:(\w+)\]/g;
            text = text.replace(stickerRegex, '<img src="/stickers/$1.png" class="message-sticker" alt="стикер">');
            
            $this.html(text);
        });
    }

    showStickerPicker() {
        if ($('#sticker-picker').length === 0) {
            const stickerHtml = `
                <div id="sticker-picker" class="sticker-picker">
                    <div class="sticker-picker-header">
                        <span>Выберите стикер</span>
                        <button class="close-sticker-picker">&times;</button>
                    </div>
                    <div class="sticker-grid">
                        ${this.generateStickerGrid()}
                    </div>
                </div>
            `;
            
            $('body').append(stickerHtml);
            
            // Обработчики событий
            $('.close-sticker-picker').on('click', () => $('#sticker-picker').remove());
            $('.sticker-item').on('click', (e) => this.selectSticker(e));
        }
    }

    generateStickerGrid() {
        const stickers = ['happy', 'sad', 'angry', 'love', 'thumbsup', 'thumbsdown', 'laugh', 'cry', 'wink', 'surprised'];
        return stickers.map(sticker => 
            `<div class="sticker-item" data-sticker="${sticker}">
                <img src="/stickers/${sticker}.png" alt="${sticker}">
            </div>`
        ).join('');
    }

    selectSticker(e) {
        const sticker = $(e.currentTarget).data('sticker');
        const messageInput = $('#supportMessage');
        const currentMessage = messageInput.val();
        messageInput.val(currentMessage + `[sticker:${sticker}]`);
        messageInput.focus();
        
        $('#sticker-picker').remove();
    }

    showEmojiPicker() {
        if ($('#emoji-picker').length === 0) {
            const emojiHtml = `
                <div id="emoji-picker" class="emoji-picker">
                    <div class="emoji-picker-header">
                        <span>Выберите эмодзи</span>
                        <button class="close-emoji-picker">&times;</button>
                    </div>
                    <div class="emoji-grid">
                        ${this.generateEmojiGrid()}
                    </div>
                </div>
            `;
            
            $('body').append(emojiHtml);
            
            // Обработчики событий
            $('.close-emoji-picker').on('click', () => $('#emoji-picker').remove());
            $('.emoji-item').on('click', (e) => this.selectEmoji(e));
        }
    }

    generateEmojiGrid() {
        const emojis = ['😊', '😂', '😍', '😢', '😮', '😡', '👍', '👎', '❤️', '🔥', '💯', '🎉', '👏', '🙏', '😎', '🤔'];
        return emojis.map(emoji => 
            `<div class="emoji-item" data-emoji="${emoji}">${emoji}</div>`
        ).join('');
    }

    selectEmoji(e) {
        const emoji = $(e.currentTarget).data('emoji');
        const messageInput = $('#supportMessage');
        const currentMessage = messageInput.val();
        messageInput.val(currentMessage + emoji);
        messageInput.focus();
        
        $('#emoji-picker').remove();
    }

    handleFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Проверяем размер файла (максимум 10MB)
        if (file.size > 10 * 1024 * 1024) {
            this.showNotification('Файл слишком большой. Максимальный размер: 10MB', 'error');
            return;
        }

        // Показываем прогресс загрузки
        this.showUploadProgress();

        // Загружаем файл
        this.uploadFile(file);
    }

    showUploadProgress() {
        const progressHtml = `
            <div id="upload-progress" class="upload-progress">
                <div class="upload-progress-bar">
                    <div class="upload-progress-fill"></div>
                </div>
                <span class="upload-progress-text">Загрузка файла...</span>
            </div>
        `;
        
        $('.support_messages_form').append(progressHtml);
    }

    uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('chatId', chatId);

        $.ajax({
            url: '/support/upload-file',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: () => {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $('.upload-progress-fill').css('width', percentComplete + '%');
                    }
                });
                return xhr;
            },
            success: (response) => {
                if (response.success) {
                    this.showNotification('Файл загружен успешно', 'success');
                    // Отправляем сообщение с файлом
                    this.sendMessageWithFile(response.fileUrl, file.name);
                } else {
                    this.showNotification('Ошибка загрузки файла: ' + response.error, 'error');
                }
            },
            error: (error) => {
                this.showNotification('Ошибка загрузки файла', 'error');
                console.error('Upload error:', error);
            },
            complete: () => {
                $('#upload-progress').remove();
                $('#supportMessageFile').val('');
            }
        });
    }

    sendMessageWithFile(fileUrl, fileName) {
        const messageText = `📎 Файл: ${fileName}`;
        const messageData = {
            message: messageText,
            fileUrl: fileUrl,
            fileName: fileName,
            timestamp: Date.now(),
            id: 'temp_' + Date.now()
        };

        this.sendMessageToServer(messageData);
    }

    showNotification(message, type = 'info') {
        if (typeof toastr !== 'undefined') {
            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            toastr[type](`<i class='${iconMap[type]}'></i><div class='toast-message_text'>${message}</div>`, '', {
                progressBar: true,
                positionClass: 'toast-top-right',
                escapeHtml: false,
                timeOut: 5000
            });
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    scrollToBottom() {
        const $wrap = $('.support_messages_wrap');
        if ($wrap.length) {
            $wrap.scrollTop($wrap[0].scrollHeight);
        }
    }

    resizeTextarea() {
        const $textarea = $('#supportMessage');
        if ($textarea.length) {
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea[0].scrollHeight + 'px');
        }
    }
}

// Инициализация улучшенной системы поддержки
let supportChat;

$(document).ready(() => {
    if ($('#supportMessage').length > 0) {
        supportChat = new SupportChat();
    }
});

// Добавляем CSS стили
const supportStyles = `
<style>
.connection-status {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 8px;
    border-radius: 4px;
    font-size: 12px;
}

.connection-status.connected {
    background-color: #d4edda;
    color: #155724;
}

.connection-status.disconnected {
    background-color: #f8d7da;
    color: #721c24;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-indicator.connected {
    background-color: #28a745;
}

.status-indicator.disconnected {
    background-color: #dc3545;
}

.resend-messages {
    padding: 8px 12px;
    margin-bottom: 8px;
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}

.sticker-picker, .emoji-picker {
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 300px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
}

.sticker-picker-header, .emoji-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #eee;
    font-weight: bold;
}

.close-sticker-picker, .close-emoji-picker {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #999;
}

.sticker-grid, .emoji-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    padding: 12px;
}

.sticker-item, .emoji-item {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.sticker-item:hover, .emoji-item:hover {
    background-color: #f0f0f0;
}

.sticker-item img {
    width: 32px;
    height: 32px;
}

.emoji-item {
    font-size: 24px;
}

.message-link {
    color: #007bff;
    text-decoration: underline;
}

.message-sticker {
    width: 32px;
    height: 32px;
    vertical-align: middle;
}

.upload-progress {
    margin-top: 8px;
    padding: 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.upload-progress-bar {
    width: 100%;
    height: 4px;
    background-color: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.upload-progress-fill {
    height: 100%;
    background-color: #007bff;
    width: 0%;
    transition: width 0.3s ease;
}

.upload-progress-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
    display: block;
}

.support_message_text a {
    color: #007bff;
    text-decoration: underline;
    word-break: break-all;
}

.support_message_text a:hover {
    color: #0056b3;
}
</style>
`;

// Добавляем стили в head
$('head').append(supportStyles);






