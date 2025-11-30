// VK Bridge инициализация
let vkBridge;

// Инициализация приложения
document.addEventListener('DOMContentLoaded', () => {
    // Инициализируем VK Bridge если доступен
    if (typeof vkBridge === 'undefined' && window.VK) {
        vkBridge = window.VK;
        vkBridge.init();
    }
    
    // Загружаем серверы при загрузке страницы
    loadServers();
    
    // Настраиваем кнопку обновления
    document.getElementById('refreshBtn').addEventListener('click', () => {
        loadServers(true);
    });
    
    // Автообновление каждые 30 секунд
    setInterval(() => {
        loadServers(false);
    }, 30000);
});

// Функция загрузки серверов
async function loadServers(showLoading = true) {
    const loadingEl = document.getElementById('loading');
    const errorEl = document.getElementById('error');
    const serversListEl = document.getElementById('serversList');
    const refreshBtn = document.getElementById('refreshBtn');
    
    try {
        if (showLoading) {
            loadingEl.style.display = 'block';
            errorEl.style.display = 'none';
            serversListEl.innerHTML = '';
            refreshBtn.classList.add('rotating');
        }
        
        const response = await fetch('https://api.prostoj.store/servers', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const servers = await response.json();
        
        loadingEl.style.display = 'none';
        errorEl.style.display = 'none';
        refreshBtn.classList.remove('rotating');
        
        displayServers(servers);
        updateLastUpdateTime();
        
    } catch (error) {
        console.error('Error loading servers:', error);
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
        refreshBtn.classList.remove('rotating');
    }
}

// Функция отображения серверов
function displayServers(servers) {
    const serversListEl = document.getElementById('serversList');
    
    if (!servers || servers.length === 0) {
        serversListEl.innerHTML = '<div class="error"><p>Серверы не найдены</p></div>';
        return;
    }
    
    serversListEl.innerHTML = servers.map(server => createServerCard(server)).join('');
}

// Функция создания карточки сервера
function createServerCard(server) {
    const isOnline = server.online !== null && server.online !== undefined;
    const onlineCount = server.online || 0;
    const maxPlayers = server.max || 0;
    const fillPercentage = maxPlayers > 0 ? (onlineCount / maxPlayers) * 100 : 0;
    
    // Определяем класс для заполнения бара
    let barClass = '';
    if (fillPercentage >= 90) {
        barClass = 'full';
    } else if (fillPercentage >= 70) {
        barClass = 'high';
    }
    
    // Парсим теги
    const tags = server.tags ? server.tags.split(', ') : [];
    
    // Форматируем теги
    const tagsHtml = tags.map(tag => {
        const tagClass = getTagClass(tag);
        return `<span class="tag ${tagClass}">${tag}</span>`;
    }).join('');
    
    return `
        <div class="server-card ${isOnline ? 'online' : 'offline'}">
            <div class="server-header">
                <div class="server-name">${escapeHtml(server.name)}</div>
                <div class="server-status ${isOnline ? 'online' : 'offline'}">
                    <span class="status-dot ${isOnline ? 'online' : 'offline'}"></span>
                    ${isOnline ? 'Онлайн' : 'Оффлайн'}
                </div>
            </div>
            <div class="server-info">
                <div class="info-row">
                    <span class="info-label">IP:Port</span>
                    <span class="info-value">${server.ip}:${server.port}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Игроки</span>
                    <div class="players-info">
                        <span class="info-value">${onlineCount} / ${maxPlayers}</span>
                        <div class="players-bar">
                            <div class="players-bar-fill ${barClass}" style="width: ${fillPercentage}%"></div>
                        </div>
                    </div>
                </div>
                ${server.joined !== undefined && server.joined !== null ? `
                <div class="info-row">
                    <span class="info-label">Подключено</span>
                    <span class="info-value">${server.joined}</span>
                </div>
                ` : ''}
                ${server.tag ? `
                <div class="info-row">
                    <span class="info-label">Тег</span>
                    <span class="info-value">${escapeHtml(server.tag)}</span>
                </div>
                ` : ''}
            </div>
            ${tagsHtml ? `<div class="server-tags">${tagsHtml}</div>` : ''}
        </div>
    `;
}

// Функция для определения класса тега
function getTagClass(tag) {
    const tagLower = tag.toLowerCase();
    
    if (tagLower.includes('oxide')) return 'oxide';
    if (tagLower.includes('russian')) return 'russian';
    if (tagLower.includes('skin')) return 'skins';
    if (tagLower.includes('x2-rate')) return 'x2-rates';
    if (tagLower.includes('x10-rate')) return 'x10-rates';
    if (tagLower.includes('weekly')) return 'weekly';
    if (tagLower.includes('biweekly')) return 'biweekly';
    if (tagLower.includes('monthly')) return 'monthly';
    if (tagLower.includes('pve')) return 'pve';
    if (tagLower.includes('no-donate')) return 'no-donate';
    
    return '';
}

// Функция экранирования HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Функция обновления времени последнего обновления
function updateLastUpdateTime() {
    const lastUpdateEl = document.getElementById('lastUpdate');
    const now = new Date();
    const timeString = now.toLocaleTimeString('ru-RU', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    });
    lastUpdateEl.textContent = `Обновлено: ${timeString}`;
}

// Обработка ошибок сети
window.addEventListener('online', () => {
    loadServers();
});

window.addEventListener('offline', () => {
    document.getElementById('error').style.display = 'block';
    document.getElementById('error').innerHTML = '<p>❌ Нет подключения к интернету</p>';
});

