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
                            // Показываем первый экран без анимации слайда, но с анимацией чисел
                            this.screens.forEach((screen, i) => {
                                screen.style.display = i === 0 ? 'block' : 'none';
                                // Применяем цвета к метрикам
                                if (i === 0) {
                                    this.applyColorsToScreen(screen);
                                }
                            });
                            this.updateNavigation(0);
                            // Запускаем анимацию чисел для первого экрана
                            setTimeout(() => {
                                this.animateNumbers(this.screens[0]);
                            }, 300);
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

            const prevIndex = this.currentScreen;
            const direction = index > prevIndex ? 'right' : 'left';

            // Скрываем все экраны с анимацией
            this.screens.forEach((screen, i) => {
                if (i === prevIndex && i !== index) {
                    screen.classList.remove('slide-in-right', 'slide-in-left');
                    screen.style.display = 'none';
                }
            });

            // Показываем нужный экран с анимацией
            if (this.screens[index]) {
                this.screens[index].style.display = 'block';
                // Применяем цвета к метрикам
                this.applyColorsToScreen(this.screens[index]);
                // Добавляем класс анимации
                this.screens[index].classList.remove('slide-in-right', 'slide-in-left');
                // Принудительный reflow для анимации
                void this.screens[index].offsetWidth;
                this.screens[index].classList.add(direction === 'right' ? 'slide-in-right' : 'slide-in-left');
                
                // Анимация чисел
                this.animateNumbers(this.screens[index]);
            }

            // Обновляем навигацию
            this.updateNavigation(index);

            this.currentScreen = index;
        },

        updateNavigation: function(index) {
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
        },

        animateNumbers: function(screen) {
            // Анимация чисел в значениях метрик
            const valueElements = screen.querySelectorAll('.year-review-metric__value');
            valueElements.forEach((el, index) => {
                const originalText = el.textContent;
                const numberMatch = originalText.match(/[\d\s]+/);
                if (numberMatch) {
                    const number = parseInt(numberMatch[0].replace(/\s/g, ''));
                    if (!isNaN(number) && number > 0) {
                        const unit = originalText.replace(/[\d\s]+/, '').trim();
                        el.style.opacity = '0';
                        el.style.transform = 'scale(0.5)';
                        
                        setTimeout(() => {
                            el.style.transition = 'all 0.8s ease-out';
                            el.style.opacity = '1';
                            el.style.transform = 'scale(1)';
                            
                            // Анимация счетчика от 0 до числа
                            this.animateCounter(el, 0, number, unit, 800);
                        }, index * 100);
                    } else {
                        // Если число 0 или не найдено, просто показываем с анимацией
                        el.style.opacity = '0';
                        el.style.transform = 'scale(0.5)';
                        setTimeout(() => {
                            el.style.transition = 'all 0.8s ease-out';
                            el.style.opacity = '1';
                            el.style.transform = 'scale(1)';
                        }, index * 100);
                    }
                } else {
                    // Если числа нет, просто показываем с анимацией
                    el.style.opacity = '0';
                    el.style.transform = 'scale(0.5)';
                    setTimeout(() => {
                        el.style.transition = 'all 0.8s ease-out';
                        el.style.opacity = '1';
                        el.style.transform = 'scale(1)';
                    }, index * 100);
                }
            });
            
            // Анимация итогового значения
            const totalValue = screen.querySelector('.year-review-total__value');
            if (totalValue) {
                const originalText = totalValue.textContent;
                const number = parseInt(originalText.replace(/\s/g, ''));
                if (!isNaN(number) && number > 0) {
                    totalValue.style.opacity = '0';
                    totalValue.style.transform = 'scale(0.5)';
                    setTimeout(() => {
                        totalValue.style.transition = 'all 1s ease-out';
                        totalValue.style.opacity = '1';
                        totalValue.style.transform = 'scale(1)';
                        this.animateCounter(totalValue, 0, number, '', 1000);
                    }, 200);
                } else {
                    totalValue.style.opacity = '0';
                    totalValue.style.transform = 'scale(0.5)';
                    setTimeout(() => {
                        totalValue.style.transition = 'all 1s ease-out';
                        totalValue.style.opacity = '1';
                        totalValue.style.transform = 'scale(1)';
                    }, 200);
                }
            }
        },

        animateCounter: function(element, start, end, unit, duration) {
            const startTime = performance.now();
            const formatNumber = (num) => {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            };
            
            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Используем easing функцию для плавности
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.floor(start + (end - start) * easeOutQuart);
                
                element.textContent = formatNumber(current) + (unit ? ' ' + unit : '');
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    // Убеждаемся, что финальное значение установлено
                    element.textContent = formatNumber(end) + (unit ? ' ' + unit : '');
                }
            };
            
            requestAnimationFrame(animate);
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
        },

        applyColorsToScreen: function(screen) {
            const screenColor = screen.getAttribute('data-color') || '#00ff88';
            const metrics = screen.querySelectorAll('.year-review-metric');
            
            metrics.forEach(metric => {
                const metricColor = metric.getAttribute('data-color') || screenColor;
                // Применяем цвет к границе и тени
                metric.style.setProperty('--metric-color', metricColor);
                metric.style.borderColor = metricColor;
                metric.style.boxShadow = `
                    0 0 20px ${this.hexToRgba(metricColor, 0.3)},
                    inset 0 0 20px ${this.hexToRgba(metricColor, 0.1)}
                `;
                
                // Применяем цвет к заголовку
                const title = metric.querySelector('.year-review-metric__title');
                if (title) {
                    title.style.color = metricColor;
                    title.style.textShadow = `0 0 10px ${metricColor}`;
                }
                
                // Применяем цвет к иконке
                const icon = metric.querySelector('.year-review-metric__icon');
                if (icon) {
                    if (icon.classList.contains('year-review-metric__icon--fa')) {
                        icon.style.color = metricColor;
                        icon.style.textShadow = `0 0 10px ${metricColor}`;
                    } else {
                        icon.style.filter = `drop-shadow(0 0 5px ${metricColor})`;
                    }
                }
                
                // Применяем цвет к значениям
                const value = metric.querySelector('.year-review-metric__value');
                if (value) {
                    value.style.color = metricColor;
                    value.style.textShadow = `0 0 10px ${metricColor}`;
                }
                
                // Применяем цвет к элементам списка
                const items = metric.querySelectorAll('.year-review-metric__item');
                items.forEach(item => {
                    if (!item.classList.contains('year-review-metric__item--gold') &&
                        !item.classList.contains('year-review-metric__item--silver') &&
                        !item.classList.contains('year-review-metric__item--bronze')) {
                        item.style.borderColor = this.hexToRgba(metricColor, 0.3);
                        item.addEventListener('mouseenter', function() {
                            this.style.borderColor = metricColor;
                            this.style.background = this.hexToRgba(metricColor, 0.1);
                            this.style.boxShadow = `0 0 15px ${this.hexToRgba(metricColor, 0.3)}`;
                        }.bind(this));
                        item.addEventListener('mouseleave', function() {
                            this.style.borderColor = this.hexToRgba(metricColor, 0.3);
                            this.style.background = 'rgba(0, 0, 0, 0.4)';
                            this.style.boxShadow = 'none';
                        }.bind(this));
                    }
                });
            });
        },

        hexToRgba: function(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
    };

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => YearReviewModal.init());
    } else {
        YearReviewModal.init();
    }
})();

