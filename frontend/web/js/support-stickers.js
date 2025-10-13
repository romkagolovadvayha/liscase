/**
 * Поддержка стикеров в чате поддержки
 */
class SupportStickers {
    constructor() {
        this.stickers = [];
        this.isLoaded = false;
        this.init();
    }

    init() {
        this.loadStickers();
        this.bindEvents();
    }

    /**
     * Загрузка списка стикеров
     */
    async loadStickers() {
        // Проверяем кэш в localStorage
        const cacheKey = 'support_stickers';
        const cacheTime = 'support_stickers_cache_time';
        const now = Date.now();
        const cacheExpiry = 30 * 60 * 1000; // 30 минут

        // Если есть кэш и он не устарел
        if (localStorage.getItem(cacheKey) && localStorage.getItem(cacheTime)) {
            const cacheTimestamp = parseInt(localStorage.getItem(cacheTime));
            if (now - cacheTimestamp < cacheExpiry) {
                try {
                    this.stickers = JSON.parse(localStorage.getItem(cacheKey));
                    this.isLoaded = true;
                    this.renderStickersPanel();
                    return;
                } catch (e) {
                    console.warn('Ошибка парсинга кэша стикеров:', e);
                }
            }
        }

        try {
            const response = await fetch('/support/get-stickers');
            const data = await response.json();
            
            if (data.success) {
                this.stickers = data.stickers;
                this.isLoaded = true;
                
                // Кэшируем результат
                localStorage.setItem(cacheKey, JSON.stringify(data.stickers));
                localStorage.setItem(cacheTime, now.toString());
                
                this.renderStickersPanel();
            }
        } catch (error) {
            console.error('Ошибка загрузки стикеров:', error);
        }
    }

    /**
     * Привязка событий
     */
    bindEvents() {
        // Кнопка стикеров
        const stickerButton = document.querySelector('.sticker-button');
        console.log('Sticker button found:', stickerButton);
        if (stickerButton) {
            stickerButton.addEventListener('click', (e) => {
                console.log('Sticker button clicked');
                e.preventDefault();
                this.toggleStickersPanel();
            });
        } else {
            console.warn('Sticker button not found!');
        }

        // Закрытие панели при клике вне её
        document.addEventListener('click', (e) => {
            const stickersPanel = document.querySelector('.stickers-panel');
            const stickerButton = document.querySelector('.sticker-button');
            
            if (stickersPanel && 
                !stickersPanel.contains(e.target) && 
                !stickerButton.contains(e.target)) {
                this.hideStickersPanel();
            }
        });
    }

    /**
     * Отображение/скрытие панели стикеров
     */
    toggleStickersPanel() {
        const panel = document.querySelector('.stickers-panel');
        const button = document.querySelector('.sticker-button');
        
        console.log('toggleStickersPanel called, panel:', panel, 'current display:', panel ? panel.style.display : 'no panel');
        
        if (panel && panel.style.display === 'block') {
            this.hideStickersPanel();
        } else {
            this.showStickersPanel();
        }
    }

    /**
     * Показать панель стикеров
     */
    showStickersPanel() {
        const panel = document.querySelector('.stickers-panel');
        const button = document.querySelector('.sticker-button');
        
        if (panel) {
            panel.style.display = 'block';
            button.classList.add('active');
        }
    }

    /**
     * Скрыть панель стикеров
     */
    hideStickersPanel() {
        const panel = document.querySelector('.stickers-panel');
        const button = document.querySelector('.sticker-button');
        
        if (panel) {
            panel.style.display = 'none';
            button.classList.remove('active');
        }
    }

    /**
     * Рендеринг панели стикеров
     */
    renderStickersPanel() {
        const panel = document.querySelector('.stickers-panel');
        if (!panel) return;

        const grid = panel.querySelector('.stickers-grid') || document.createElement('div');
        grid.className = 'stickers-grid';
        grid.innerHTML = '';

        this.stickers.forEach(sticker => {
            const item = document.createElement('div');
            item.className = 'sticker-item';
            item.title = sticker.name;
            
            // Проверяем, является ли стикер видео (.webm)
            if (sticker.url.toLowerCase().endsWith('.webm')) {
                item.innerHTML = `
                    <video autoplay muted loop>
                        <source src="${sticker.url}" type="video/webm">
                        Ваш браузер не поддерживает видео.
                    </video>
                `;
            } else {
                item.innerHTML = `
                    <img src="${sticker.url}" alt="${sticker.name}" loading="lazy">
                `;
            }
            
            item.addEventListener('click', () => {
                this.sendSticker(sticker.code);
            });
            
            grid.appendChild(item);
        });

        if (!panel.querySelector('.stickers-grid')) {
            panel.appendChild(grid);
        }
    }

    /**
     * Отправка стикера напрямую
     */
    sendSticker(stickerCode) {
        // Используем оптимизированную систему поддержки, если доступна
        if (window.optimizedSupport) {
            window.optimizedSupport.sendSticker(stickerCode);
        } else {
            // Fallback к старому методу
            this.sendStickerLegacy(stickerCode);
        }
        
        // Скрываем панель стикеров
        this.hideStickersPanel();
    }

    /**
     * Отправка стикера (legacy метод)
     */
    sendStickerLegacy(stickerCode) {
        // Проверяем WebSocket соединение
        if (typeof chat === 'undefined' || !chat || chat.readyState !== 1) {
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Соединение с сервером потеряно. Попробуйте еще раз.</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
            return;
        }

        // Проверяем блокировку пользователя
        const userBlockedUntil = document.querySelector('.ticket_timer');
        if (userBlockedUntil) {
            const blockedUntil = parseInt(userBlockedUntil.dataset.time);
            if (blockedUntil > Math.floor(Date.now() / 1000)) {
                toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Доступ в чат заблокирован</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
                return;
            }
        }

        try {
            // Отправляем стикер через WebSocket
            chat.send(JSON.stringify({ 
                action: 'chat', 
                message: stickerCode, 
                chatId: typeof chatId !== 'undefined' ? chatId : null 
            }));
            
        } catch (e) {
            console.error('Ошибка отправки стикера:', e);
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Произошла ошибка при отправке стикера</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        }
    }

    /**
     * Вставка стикера в поле ввода (для обратной совместимости)
     */
    insertSticker(stickerCode) {
        // Теперь стикеры отправляются сразу, а не вставляются в поле
        this.sendSticker(stickerCode);
    }
}

// Инициализация управляется из view.php через initSupportSystem()
// Это предотвращает дублирование инициализации

