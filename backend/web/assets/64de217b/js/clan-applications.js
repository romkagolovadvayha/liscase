/**
 * JavaScript для управления заявками в кланы
 */

class ClanApplications {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        // Обработка принятия заявки
        document.querySelectorAll('.accept-application-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const applicationId = e.target.dataset.applicationId;
                if (confirm(this.getTranslation('Вы уверены, что хотите принять эту заявку?'))) {
                    this.acceptApplication(applicationId);
                }
            });
        });
        
        // Обработка отклонения заявки
        document.querySelectorAll('.reject-application-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const applicationId = e.target.dataset.applicationId;
                if (confirm(this.getTranslation('Вы уверены, что хотите отклонить эту заявку?'))) {
                    this.rejectApplication(applicationId);
                }
            });
        });
        
        // Обработка проверки банов
        document.querySelectorAll('.check-bans-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const steamId = e.target.dataset.steamId;
                const applicationItem = e.target.closest('.application-item');
                this.checkBans(steamId, applicationItem);
            });
        });
    }

    async acceptApplication(applicationId) {
        try {
            const response = await fetch('/clans/accept-application?id=' + applicationId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                // Удаляем заявку из списка
                const applicationElement = document.querySelector(`[data-application-id="${applicationId}"]`);
                if (applicationElement) {
                    applicationElement.remove();
                }
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error accepting application:', error);
            this.showNotification(this.getTranslation('Произошла ошибка'), 'error');
        }
    }

    async rejectApplication(applicationId) {
        try {
            const response = await fetch('/clans/reject-application?id=' + applicationId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(data.message, 'success');
                // Удаляем заявку из списка
                const applicationElement = document.querySelector(`[data-application-id="${applicationId}"]`);
                if (applicationElement) {
                    applicationElement.remove();
                }
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error rejecting application:', error);
            this.showNotification(this.getTranslation('Произошла ошибка'), 'error');
        }
    }

    async checkBans(steamId, applicationItem) {
        const bansInfo = applicationItem.querySelector('.bans-info');
        const bansList = applicationItem.querySelector('.bans-list');
        const checkBtn = applicationItem.querySelector('.check-bans-btn');
        
        // Блокируем кнопку и показываем загрузку
        checkBtn.disabled = true;
        checkBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${this.getTranslation('Проверяем...')}`;
        
        try {
            const response = await fetch(`/clans/check-bans?steamId=${steamId}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.bansExist) {
                    bansList.innerHTML = '';
                    data.bans.forEach(ban => {
                        const banItem = document.createElement('div');
                        banItem.className = 'ban-item';
                        banItem.innerHTML = `
                            <div>
                                <div class="ban-server">${ban.serverName}</div>
                                <div class="ban-reason">${ban.reason}</div>
                            </div>
                            <div class="ban-date">
                                <div>${ban.date}</div>
                                <div>${ban.unbanned_date}</div>
                            </div>
                        `;
                        bansList.appendChild(banItem);
                    });
                    bansInfo.style.display = 'block';
                } else {
                    bansList.innerHTML = `<p class="text-text-secondary">${this.getTranslation('Баны не найдены')}</p>`;
                    bansInfo.style.display = 'block';
                }
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error checking bans:', error);
            this.showNotification(this.getTranslation('Произошла ошибка при проверке банов'), 'error');
        } finally {
            // Восстанавливаем кнопку
            checkBtn.disabled = false;
            checkBtn.innerHTML = `<i class="fas fa-search"></i> ${this.getTranslation('Проверить баны')}`;
        }
    }

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    getTranslation(key) {
        // Простая система переводов - в реальном проекте используйте i18n
        const translations = {
            'Вы уверены, что хотите принять эту заявку?': 'Вы уверены, что хотите принять эту заявку?',
            'Вы уверены, что хотите отклонить эту заявку?': 'Вы уверены, что хотите отклонить эту заявку?',
            'Проверяем...': 'Проверяем...',
            'Проверить баны': 'Проверить баны',
            'Баны не найдены': 'Баны не найдены',
            'Произошла ошибка': 'Произошла ошибка',
            'Произошла ошибка при проверке банов': 'Произошла ошибка при проверке банов'
        };
        return translations[key] || key;
    }

    showNotification(message, type) {
        // Простая система уведомлений - в реальном проекте используйте toast библиотеку
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            max-width: 300px;
            word-wrap: break-word;
            ${type === 'success' ? 'background: #28a745;' : 'background: #dc3545;'}
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Автоматически удаляем уведомление через 5 секунд
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
}

// Инициализируем при загрузке DOM
document.addEventListener('DOMContentLoaded', function() {
    new ClanApplications();
});
