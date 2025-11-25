(function () {
    'use strict';

    console.log('tasks-v2.js: Script started');

    const root = document.getElementById('tasksV2Root');
    if (!root) {
        console.warn('tasks-v2.js: tasksV2Root element not found');
        return;
    }

    // Обработка клика на кнопку проверки задания
    document.addEventListener('click', function (event) {
        let target = event.target;
        if (!target || target.nodeType !== 1) {
            target = target && target.parentElement;
        }
        if (!target || !target.closest) return;

        const checkButton = target.closest('[data-action="check-task"]');
        if (!checkButton) return;

        event.preventDefault();
        event.stopPropagation();

        const taskId = checkButton.dataset.taskId;
        if (!taskId) {
            console.error('tasks-v2.js: Task ID not found');
            return;
        }

        // Проверяем, не отключена ли кнопка
        if (checkButton.classList.contains('is-disabled') || checkButton.disabled) {
            return;
        }

        checkTask(taskId, checkButton);
    });

    /**
     * Проверка выполнения задания
     * @param {number} taskId
     * @param {HTMLElement} button
     */
    function checkTask(taskId, button) {
        const messageEl = document.querySelector('[data-role="task-message"]');
        
        // Блокируем кнопку
        button.disabled = true;
        button.classList.add('is-loading');
        
        // Очищаем предыдущие сообщения
        if (messageEl) {
            messageEl.style.display = 'none';
            messageEl.className = 'tasksV2__detail-message';
            messageEl.textContent = '';
        }

        // Показываем индикатор загрузки
        const originalText = button.querySelector('span');
        const originalTextContent = originalText ? originalText.textContent : '';
        if (originalText) {
            originalText.textContent = 'Проверка...';
        }

        // Получаем CSRF токен из meta тега
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfParamMeta = document.querySelector('meta[name="csrf-param"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const csrfParam = csrfParamMeta ? csrfParamMeta.getAttribute('content') : '_csrf-frontend';
        
        // Отправляем запрос на сервер с CSRF токеном
        fetch('/tasks-v2/check/' + taskId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: csrfToken ? `${csrfParam}=${encodeURIComponent(csrfToken)}` : '',
            credentials: 'same-origin',
        })
        .then(response => {
            if (!response.ok) {
                // Если ответ не успешный, пытаемся получить JSON ошибки
                return response.json().then(err => {
                    throw new Error(err.message || 'Ошибка сервера: ' + response.status);
                }).catch(() => {
                    throw new Error('Ошибка сервера: ' + response.status + ' ' + response.statusText);
                });
            }
            return response.json();
        })
        .then(data => {
            handleCheckResponse(data, button, messageEl);
        })
        .catch(error => {
            console.error('tasks-v2.js: Error checking task', error);
            showMessage(messageEl, error.message || 'Произошла ошибка при проверке задания', 'error');
            
            // Восстанавливаем кнопку
            button.disabled = false;
            button.classList.remove('is-loading');
            if (originalText) {
                originalText.textContent = originalTextContent || 'Проверить';
            }
        });
    }

    /**
     * Обработка ответа от сервера
     * @param {Object} data
     * @param {HTMLElement} button
     * @param {HTMLElement} messageEl
     */
    function handleCheckResponse(data, button, messageEl) {
        const originalText = button.querySelector('span');
        
        if (data.success) {
            // Успешное выполнение
            showMessage(messageEl, data.message || 'Задание выполнено! Награда выдана.', 'success');
            
            // Обновляем статус задания
            updateTaskStatus(button.closest('[data-role="task-detail"]'), 'completed');
            
            // Блокируем кнопку
            button.disabled = true;
            button.classList.add('is-disabled');
            if (originalText) {
                originalText.textContent = 'Выполнено';
            }
            
            // Обновляем прогресс, если есть
            if (data.progress !== undefined && data.maxProgress !== undefined) {
                updateProgress(data.progress, data.maxProgress);
            }
            
            // Обновляем карточку задания на странице, если она видна
            updateTaskCard(button.closest('[data-role="task-detail"]').dataset.taskId, 'completed');
        } else {
            // Ошибка или задание еще не выполнено
            showMessage(messageEl, data.message || 'Задание еще не выполнено', 'error');
            
            // Восстанавливаем кнопку
            button.disabled = false;
            button.classList.remove('is-loading');
            if (originalText) {
                const originalButtonText = button.dataset.originalText || button.querySelector('span')?.textContent || 'Проверить';
                originalText.textContent = originalButtonText;
            }
            
            // Обновляем прогресс, если есть
            if (data.progress !== undefined && data.maxProgress !== undefined) {
                updateProgress(data.progress, data.maxProgress);
            }
        }
    }

    /**
     * Показать сообщение
     * @param {HTMLElement} messageEl
     * @param {string} message
     * @param {string} type
     */
    function showMessage(messageEl, message, type) {
        if (!messageEl) return;
        
        messageEl.textContent = message;
        messageEl.className = 'tasksV2__detail-message is-' + type;
        messageEl.style.display = 'block';
        
        // Автоматически скрыть сообщение через 5 секунд
        setTimeout(function() {
            messageEl.style.display = 'none';
        }, 5000);
    }

    /**
     * Обновить статус задания в модальном окне
     * @param {HTMLElement} detailEl
     * @param {string} status
     */
    function updateTaskStatus(detailEl, status) {
        if (!detailEl) return;
        
        const statusBadge = detailEl.querySelector('.tasksV2__detail-badge--status');
        if (statusBadge) {
            statusBadge.className = 'tasksV2__detail-badge tasksV2__detail-badge--status is-' + status;
            if (status === 'completed') {
                statusBadge.innerHTML = '<i class="fas fa-check"></i> Выполнено';
            }
        }
    }

    /**
     * Обновить прогресс выполнения
     * @param {number} progress
     * @param {number} maxProgress
     */
    function updateProgress(progress, maxProgress) {
        const progressText = document.querySelector('.tasksV2__detail-progress-text');
        const progressBarFill = document.querySelector('.tasksV2__detail-progress-bar-fill');
        
        if (progressText) {
            progressText.textContent = progress + ' / ' + maxProgress;
        }
        
        if (progressBarFill && maxProgress > 0) {
            const percentage = Math.min(100, (progress / maxProgress) * 100);
            progressBarFill.style.width = percentage + '%';
        }
    }

    /**
     * Обновить карточку задания на странице
     * @param {string} taskId
     * @param {string} status
     */
    function updateTaskCard(taskId, status) {
        const card = document.querySelector('[data-task-id="' + taskId + '"]');
        if (!card) return;
        
        // Обновляем классы карточки
        card.classList.remove('is-completed', 'is-limit-reached', 'is-unavailable');
        if (status === 'completed') {
            card.classList.add('is-completed');
        }
        
        // Обновляем бейдж статуса
        const statusBadge = card.querySelector('.tasksV2__card-badge--status');
        if (statusBadge) {
            statusBadge.className = 'tasksV2__card-badge tasksV2__card-badge--status is-' + status;
            if (status === 'completed') {
                statusBadge.innerHTML = '<i class="fas fa-check"></i> Выполнено';
            }
        }
    }

    // Инициализация после загрузки модального окна
    if (typeof $ !== 'undefined') {
        $(document).on('shown.bs.modal', '#modal-dialog', function () {
            console.log('tasks-v2.js: Modal opened, reinitializing');
            
            // Сохраняем оригинальный текст кнопки
            const checkButton = this.querySelector('[data-action="check-task"]');
            if (checkButton) {
                const textSpan = checkButton.querySelector('span');
                if (textSpan && !checkButton.dataset.originalText) {
                    checkButton.dataset.originalText = textSpan.textContent;
                }
            }
        });
    }

    console.log('tasks-v2.js: Initialization complete');
})();

