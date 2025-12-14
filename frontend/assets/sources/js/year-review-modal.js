(function() {
    'use strict';

    const YearReviewModal = {
        currentScreen: 0,
        totalScreens: 0,
        modal: null,
        screens: null,
        prevBtn: null,
        nextBtn: null,
        indicators: null,

        init: function() {
            // Кнопка открытия
            const openButton = document.querySelector('.year-review-button');
            if (!openButton) {
                return;
            }

            openButton.addEventListener('click', (e) => {
                e.preventDefault();
                const serverId = openButton.getAttribute('data-server-id');
                if (serverId) {
                    this.loadAndOpen(serverId);
                }
            });

            // Инициализация модального окна
            this.modal = document.getElementById('year-review-modal');
            if (!this.modal) {
                return;
            }

            this.screens = this.modal.querySelectorAll('.year-review-screen');
            this.totalScreens = this.screens.length;
            this.prevBtn = this.modal.querySelector('.year-review-nav--prev');
            this.nextBtn = this.modal.querySelector('.year-review-nav--next');
            this.indicators = this.modal.querySelectorAll('.year-review-nav__indicator');

            // Кнопка закрытия
            const closeBtn = this.modal.querySelector('.year-review-modal__close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }

            // Закрытие по клику на оверлей
            const overlay = this.modal.querySelector('.year-review-modal__overlay');
            if (overlay) {
                overlay.addEventListener('click', () => this.close());
            }

            // Закрытие по ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                    this.close();
                }
            });

            // Навигация
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', () => this.prevScreen());
            }
            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', () => this.nextScreen());
            }

            // Индикаторы
            this.indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => this.goToScreen(index));
            });

            // Кнопки переключения серверов
            const serverButtons = this.modal.querySelectorAll('.year-review-server-btn');
            serverButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const serverId = btn.getAttribute('data-server-id');
                    if (serverId) {
                        this.loadAndOpen(serverId);
                    }
                });
            });

            // Стрелки клавиатуры
            document.addEventListener('keydown', (e) => {
                if (!this.modal.classList.contains('active')) {
                    return;
                }
                if (e.key === 'ArrowLeft') {
                    this.prevScreen();
                } else if (e.key === 'ArrowRight') {
                    this.nextScreen();
                }
            });
        },

        loadAndOpen: function(serverId) {
            const url = `/server-year-review/modal?id=${serverId}`;
            
            // Показываем загрузку
            if (this.modal) {
                this.modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Показываем сообщение о загрузке
                this.modal.innerHTML = `
                    <div class="year-review-modal__overlay"></div>
                    <div class="year-review-modal__content">
                        <div class="year-review-modal__header">
                            <h1 class="year-review-modal__title">ИТОГИ ГОДА</h1>
                        </div>
                        <div class="year-review-modal__body">
                            <div style="text-align: center; padding: 48px; font-family: 'Courier New', monospace; color: white; font-size: 24px;">Загрузка...</div>
                        </div>
                    </div>
                `;
            }

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    // Заменяем содержимое модального окна
                    if (this.modal) {
                        // modal.php возвращает полную структуру с классом year-review-modal
                        // извлекаем только внутреннее содержимое
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        const modalContent = tempDiv.querySelector('.year-review-modal');
                        
                        if (modalContent) {
                            this.modal.innerHTML = modalContent.innerHTML;
                        } else {
                            // Fallback: используем весь HTML
                            this.modal.innerHTML = html;
                        }
                        
                        // Переинициализируем после загрузки
                        this.reinit();
                        this.currentScreen = 0;
                        this.screens = this.modal.querySelectorAll('.year-review-screen');
                        this.totalScreens = this.screens.length;
                        if (this.totalScreens > 0) {
                            this.showScreen(0);
                        }
                    }
                })
                .catch(error => {
                    console.error('Ошибка загрузки метрик:', error);
                    if (this.modal) {
                        this.modal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
        },

        reinit: function() {
            this.screens = this.modal.querySelectorAll('.year-review-screen');
            this.totalScreens = this.screens.length;
            this.prevBtn = this.modal.querySelector('.year-review-nav--prev');
            this.nextBtn = this.modal.querySelector('.year-review-nav--next');
            this.indicators = this.modal.querySelectorAll('.year-review-nav__indicator');

            // Удаляем старые обработчики и добавляем новые
            if (this.prevBtn) {
                const newPrevBtn = this.prevBtn.cloneNode(true);
                this.prevBtn.parentNode.replaceChild(newPrevBtn, this.prevBtn);
                this.prevBtn = newPrevBtn;
                this.prevBtn.addEventListener('click', () => this.prevScreen());
            }
            if (this.nextBtn) {
                const newNextBtn = this.nextBtn.cloneNode(true);
                this.nextBtn.parentNode.replaceChild(newNextBtn, this.nextBtn);
                this.nextBtn = newNextBtn;
                this.nextBtn.addEventListener('click', () => this.nextScreen());
            }

            this.indicators.forEach((indicator, index) => {
                const newIndicator = indicator.cloneNode(true);
                indicator.parentNode.replaceChild(newIndicator, indicator);
                newIndicator.addEventListener('click', () => this.goToScreen(index));
            });
            // Обновляем ссылку на индикаторы
            this.indicators = this.modal.querySelectorAll('.year-review-nav__indicator');

            // Кнопки переключения серверов
            const serverButtons = this.modal.querySelectorAll('.year-review-server-btn');
            serverButtons.forEach(btn => {
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                const serverId = newBtn.getAttribute('data-server-id');
                if (serverId) {
                    newBtn.addEventListener('click', () => {
                        this.loadAndOpen(serverId);
                    });
                }
            });

            const closeBtn = this.modal.querySelector('.year-review-modal__close');
            if (closeBtn) {
                const newCloseBtn = closeBtn.cloneNode(true);
                closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                newCloseBtn.addEventListener('click', () => this.close());
            }
            
            const overlay = this.modal.querySelector('.year-review-modal__overlay');
            if (overlay) {
                const newOverlay = overlay.cloneNode(true);
                overlay.parentNode.replaceChild(newOverlay, overlay);
                newOverlay.addEventListener('click', () => this.close());
            }
        },

        open: function() {
            if (this.modal) {
                this.modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        close: function() {
            if (this.modal) {
                this.modal.classList.remove('active');
                document.body.style.overflow = '';
                this.currentScreen = 0;
            }
        },

        showScreen: function(index) {
            if (!this.screens || index < 0 || index >= this.totalScreens) {
                return;
            }

            // Скрываем все экраны
            this.screens.forEach(screen => {
                screen.style.display = 'none';
            });

            // Показываем нужный экран
            if (this.screens[index]) {
                this.screens[index].style.display = 'block';
            }

            // Обновляем кнопки навигации
            if (this.prevBtn) {
                this.prevBtn.disabled = index === 0;
            }
            if (this.nextBtn) {
                this.nextBtn.disabled = index === this.totalScreens - 1;
            }

            // Обновляем индикаторы
            this.indicators.forEach((indicator, i) => {
                if (i === index) {
                    indicator.classList.add('active');
                } else {
                    indicator.classList.remove('active');
                }
            });

            this.currentScreen = index;
        },

        prevScreen: function() {
            if (this.currentScreen > 0) {
                this.showScreen(this.currentScreen - 1);
            }
        },

        nextScreen: function() {
            if (this.currentScreen < this.totalScreens - 1) {
                this.showScreen(this.currentScreen + 1);
            }
        },

        goToScreen: function(index) {
            this.showScreen(index);
        }
    };

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => YearReviewModal.init());
    } else {
        YearReviewModal.init();
    }
})();

