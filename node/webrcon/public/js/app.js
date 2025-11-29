// API базовый URL
const API_BASE = '';

// Состояние приложения
let servers = [];
let selectedServer = null; // Общий выбранный сервер
let history = [];
let plugins = [];
let admins = []; // Список админов
let consoleEventSource = null; // EventSource для консоли
let lastConsoleCommand = null; // Последняя команда для предотвращения дублирования

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    loadServers();
    loadHistory();
    setupEventListeners();
    setupMobileMenu();
    
    // Автообновление каждые 5 секунд
    setInterval(() => {
        loadServers();
    }, 5000);
});

// Настройка мобильного меню
function setupMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const closeSidebar = document.getElementById('closeSidebar');
    
    // Создаем overlay для закрытия сайдбара
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    document.body.appendChild(overlay);
    
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebarFunc() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', openSidebar);
    }
    
    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFunc);
    }
    
    overlay.addEventListener('click', closeSidebarFunc);
    
    // Сохраняем функцию закрытия для использования в selectServer
    window.closeMobileSidebar = closeSidebarFunc;
}

// Настройка обработчиков событий
function setupEventListeners() {
    document.getElementById('refreshBtn').addEventListener('click', () => {
        loadServers();
        loadHistory();
    });
    
    document.getElementById('sendBtn').addEventListener('click', sendCommand);
    
    document.getElementById('commandInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendCommand();
        }
    });
    
    document.getElementById('clearConsoleBtn').addEventListener('click', clearConsole);
    
    document.getElementById('refreshHistoryBtn').addEventListener('click', loadHistory);
    
    // Плагины
    document.getElementById('loadPluginsBtn').addEventListener('click', loadPlugins);
    
    // Админы
    document.getElementById('loadAdminsBtn').addEventListener('click', loadAdmins);
    document.getElementById('addAdminBtn').addEventListener('click', addAdmin);
    document.getElementById('addAdminSteamId').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            addAdmin();
        }
    });
    
    // Вкладки
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
    
    // Подсказки команд
    document.querySelectorAll('.hint').forEach(hint => {
        hint.addEventListener('click', () => {
            const command = hint.getAttribute('data-command');
            document.getElementById('commandInput').value = command;
            document.getElementById('commandInput').focus();
        });
    });
}

// Переключение вкладок
function switchTab(tabName) {
    // Убираем активный класс со всех вкладок и контента
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Активируем выбранную вкладку
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`tab-${tabName}`).classList.add('active');
    
    // Автоматически загружаем данные при переключении на вкладку
    if (tabName === 'console' && selectedServer) {
        connectToConsole(selectedServer);
    } else if (tabName === 'plugins' && selectedServer) {
        loadPlugins();
    } else if (tabName === 'admins' && selectedServer) {
        loadAdmins();
    } else if (tabName === 'history') {
        loadHistory();
    }
}

// Загрузка списка серверов
async function loadServers() {
    try {
        const response = await fetch(`${API_BASE}/api/servers`);
        const data = await response.json();
        
        if (data.success) {
            servers = data.servers;
            updateServerSelect();
            updateStatusIndicator();
        } else {
            showError('Ошибка загрузки серверов');
        }
    } catch (error) {
        console.error('Ошибка загрузки серверов:', error);
        showError('Не удалось загрузить список серверов');
    }
}

// Выбор сервера
function selectServer(tag) {
    selectedServer = tag;
    renderServers(); // Обновляем отображение для выделения выбранного сервера
    
    // Подключаемся к консоли выбранного сервера
    connectToConsole(tag);
    
    // Закрываем мобильное меню при выборе сервера
    if (window.innerWidth <= 768 && window.closeMobileSidebar) {
        window.closeMobileSidebar();
    }
    
    // Автоматически загружаем данные для активной вкладки
    const activeTab = document.querySelector('.tab-btn.active')?.getAttribute('data-tab');
    if (selectedServer) {
        if (activeTab === 'plugins') {
            loadPlugins();
        } else if (activeTab === 'admins') {
            loadAdmins();
        }
    }
}

// Подключение к консоли сервера
function connectToConsole(server) {
    // Закрываем предыдущее соединение
    if (consoleEventSource) {
        consoleEventSource.close();
        consoleEventSource = null;
    }
    
    if (!server) {
        const consoleOutput = document.getElementById('consoleOutput');
        consoleOutput.innerHTML = '<div class="console-placeholder">Выберите сервер в сайдбаре для просмотра консоли...</div>';
        return;
    }
    
    try {
        const consoleOutput = document.getElementById('consoleOutput');
        consoleOutput.innerHTML = '<div class="loading">Подключение к консоли сервера...</div>';
        
        // Используем Server-Sent Events для получения сообщений в реальном времени
        consoleEventSource = new EventSource(`${API_BASE}/api/console/${encodeURIComponent(server)}`);
        
        let isConnected = false;
        
        consoleEventSource.onopen = () => {
            console.log('EventSource подключен');
            isConnected = true;
        };
        
        consoleEventSource.onmessage = (event) => {
            try {
                // Игнорируем ping сообщения
                if (event.data.trim() === 'ping' || event.data.startsWith(':')) {
                    return;
                }
                
                const message = JSON.parse(event.data);
                
                // Убираем сообщение о загрузке после первого сообщения
                if (!isConnected && (consoleOutput.querySelector('.loading') || consoleOutput.querySelector('.console-placeholder'))) {
                    consoleOutput.innerHTML = '';
                    isConnected = true;
                }
                
                // Обрабатываем разные типы сообщений
                if (message.type === 'command') {
                    // Команда уже отправлена через консольные слушатели на сервере
                    // Отображаем её только один раз
                    addConsoleLine(`> ${message.message}`, 'command');
                } else if (message.type === 'error') {
                    addConsoleLine(message.message, 'error');
                } else if (message.type === 'info') {
                    addConsoleLine(message.message, 'server');
                } else {
                    // Обычное сообщение (ответ на команду или серверное сообщение)
                    if (message.message && message.message.trim()) {
                        addConsoleLine(message.message, message.type);
                    }
                }
            } catch (err) {
                console.error('Ошибка парсинга сообщения консоли:', err, event.data);
            }
        };
        
        consoleEventSource.onerror = (err) => {
            console.error('Ошибка EventSource:', err);
            const consoleOutput = document.getElementById('consoleOutput');
            
            if (consoleEventSource.readyState === EventSource.CLOSED) {
                if (consoleOutput.innerHTML.includes('Подключение') || consoleOutput.innerHTML.includes('loading')) {
                    consoleOutput.innerHTML = '<div class="result-error">Ошибка подключения к консоли сервера. Убедитесь, что сервер подключен.</div>';
                }
            } else if (consoleEventSource.readyState === EventSource.CONNECTING) {
                // Все еще подключается, не показываем ошибку
            }
        };
    } catch (err) {
        console.error('Ошибка подключения к консоли:', err);
        const consoleOutput = document.getElementById('consoleOutput');
        consoleOutput.innerHTML = '<div class="result-error">Ошибка подключения к консоли: ' + escapeHtml(err.message) + '</div>';
    }
}

// Добавление строки в консоль
function addConsoleLine(message, type = 0) {
    const consoleOutput = document.getElementById('consoleOutput');
    
    // Убираем placeholder если есть
    if (consoleOutput.querySelector('.console-placeholder') || consoleOutput.querySelector('.loading')) {
        consoleOutput.innerHTML = '';
    }
    
    // Проверка на дублирование команд
    if (type === 'command' || type === 'Command') {
        const commandText = message.startsWith('>') ? message.substring(1).trim() : message.trim();
        const now = Date.now();
        // Если та же команда была добавлена менее 1 секунды назад, пропускаем
        if (lastConsoleCommand && lastConsoleCommand.text === commandText && (now - lastConsoleCommand.time) < 1000) {
            return; // Пропускаем дубликат
        }
        lastConsoleCommand = { text: commandText, time: now };
    }
    
    const timestamp = new Date().toLocaleTimeString('ru-RU');
    const line = document.createElement('div');
    line.className = 'console-line';
    
    // Определяем класс в зависимости от типа сообщения
    if (type === 'command' || type === 'Command') {
        line.className += ' command';
        // Команда уже содержит ">", не добавляем еще раз
        if (!message.startsWith('>')) {
            message = `> ${message}`;
        }
    } else if (type === 'error' || type === 'Error') {
        line.className += ' error';
    } else if (type === 2 || type === '2') {
        line.className += ' server';
    } else if (message.toLowerCase().includes('error') || message.toLowerCase().includes('ошибка')) {
        line.className += ' error';
    } else if (type === 0 || type === '0' || type === 'Generic') {
        line.className += ' response';
    }
    
    line.innerHTML = `<span class="console-timestamp">[${timestamp}]</span>${escapeHtml(message)}`;
    consoleOutput.appendChild(line);
    
    // Автопрокрутка вниз
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
    
    // Ограничиваем количество строк (последние 1000)
    const lines = consoleOutput.querySelectorAll('.console-line');
    if (lines.length > 1000) {
        lines[0].remove();
    }
}

// Очистка консоли
function clearConsole() {
    const consoleOutput = document.getElementById('consoleOutput');
    consoleOutput.innerHTML = '';
}

// Отображение серверов в сайдбаре
function renderServers() {
    const container = document.getElementById('serversList');
    
    if (servers.length === 0) {
        container.innerHTML = '<div class="empty-state">Серверы не найдены</div>';
        return;
    }
    
    container.innerHTML = servers.map(server => `
        <div class="sidebar-server-card ${selectedServer === server.tag ? 'selected' : ''}" 
             data-tag="${server.tag}">
            <div class="sidebar-server-card-header">
                <div class="sidebar-server-name" title="${escapeHtml(server.name)}">${escapeHtml(server.name)}</div>
                <div class="sidebar-server-status ${server.connected ? 'connected' : 'disconnected'}">
                    <span>${server.connected ? '●' : '○'}</span>
                </div>
            </div>
            <div class="sidebar-server-info">
                <div class="sidebar-server-info-item">
                    <span><strong>Тег:</strong> ${escapeHtml(server.tag)}</span>
                </div>
                <div class="sidebar-server-info-item">
                    <span><strong>IP:</strong> ${escapeHtml(server.ip)}:${server.port}</span>
                </div>
                <div class="sidebar-server-info-item">
                    <span><strong>Игроки:</strong> ${server.players || 0}/${server.max || 0}</span>
                </div>
                ${server.fps !== null && server.fps !== undefined && !isNaN(server.fps) ? `
                <div class="sidebar-server-info-item">
                    <span><strong>FPS:</strong> ${Math.round(server.fps)}</span>
                </div>
                ` : ''}
                ${server.queueLength > 0 ? `
                <div class="sidebar-server-info-item">
                    <span><strong>Очередь:</strong> ${server.queueLength}</span>
                </div>
                ` : ''}
            </div>
        </div>
    `).join('');
    
    // Обработчики клика на карточки серверов
    container.querySelectorAll('.sidebar-server-card').forEach(card => {
        card.addEventListener('click', () => {
            const tag = card.getAttribute('data-tag');
            selectServer(tag);
        });
    });
}

// Обновление селекта серверов (теперь просто рендерим карточки)
function updateServerSelect() {
    renderServers();
}

// Обновление индикатора статуса
function updateStatusIndicator() {
    const indicator = document.getElementById('statusIndicator');
    const connectedCount = servers.filter(s => s.connected).length;
    const totalCount = servers.length;
    
    if (totalCount === 0) {
        indicator.className = 'status-indicator offline';
        indicator.title = 'Нет серверов';
    } else if (connectedCount === totalCount) {
        indicator.className = 'status-indicator online';
        indicator.title = `Все серверы подключены (${connectedCount}/${totalCount})`;
    } else {
        indicator.className = 'status-indicator offline';
        indicator.title = `Подключено: ${connectedCount}/${totalCount}`;
    }
}

// Отправка команды
async function sendCommand() {
    const server = selectedServer;
    const command = document.getElementById('commandInput').value.trim();
    
    if (!server) {
        showError('Выберите сервер в сайдбаре');
        return;
    }
    
    if (!command) {
        showError('Введите команду');
        return;
    }
    
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    sendBtn.textContent = '⏳ Отправка...';
    
    try {
        const response = await fetch(`${API_BASE}/send`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server, command })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Команда и результат будут добавлены автоматически через EventSource
            // Очистка поля команды
            document.getElementById('commandInput').value = '';
            // Обновление истории
            loadHistory();
        } else {
            // Ошибка будет добавлена автоматически через EventSource
        }
    } catch (error) {
        console.error('Ошибка отправки команды:', error);
        addConsoleLine(`Ошибка отправки: ${error.message}`, 'error');
    } finally {
        sendBtn.disabled = false;
        sendBtn.textContent = '📤 Отправить';
    }
}

// Загрузка истории команд
let isLoadingHistory = false; // Флаг для предотвращения одновременных запросов

async function loadHistory() {
    // Предотвращаем одновременные запросы
    if (isLoadingHistory) {
        return;
    }
    
    isLoadingHistory = true;
    
    try {
        // Используем выбранный сервер для фильтрации истории
        const url = `${API_BASE}/api/history?limit=50${selectedServer ? `&server=${encodeURIComponent(selectedServer)}` : ''}`;
        
        // Добавляем таймаут для запроса (10 секунд)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            history = data.history;
            renderHistory();
        } else {
            showError('Ошибка загрузки истории: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            console.error('Загрузка истории: таймаут запроса');
            showError('Таймаут загрузки истории');
        } else {
            console.error('Ошибка загрузки истории:', error);
            showError('Не удалось загрузить историю: ' + error.message);
        }
    } finally {
        isLoadingHistory = false;
    }
}

// Отображение истории
function renderHistory() {
    const container = document.getElementById('historyList');
    
    if (history.length === 0) {
        container.innerHTML = '<div class="empty-state">История пуста</div>';
        return;
    }
    
    container.innerHTML = history.map(item => {
        const date = new Date(item.created_at);
        const dateStr = date.toLocaleString('ru-RU');
        const statusClass = item.status == 1 ? 'success' : 'error';
        const statusText = item.status == 1 ? 'Успешно' : 'Ошибка';
        
        return `
            <div class="history-item">
                <div class="history-item-header">
                    <div>
                        <strong>${escapeHtml(item.server_tag)}</strong>
                        <span class="history-status ${statusClass}">${statusText}</span>
                    </div>
                    <div>${dateStr}</div>
                </div>
                <div class="history-command">${escapeHtml(item.command)}</div>
                ${item.result ? `<div class="history-result">${escapeHtml(item.result)}</div>` : ''}
            </div>
        `;
    }).join('');
}

// Показать ошибку
function showError(message) {
    addConsoleLine(`Ошибка: ${message}`, 'error');
}

// Загрузка списка плагинов
async function loadPlugins() {
    const server = selectedServer;
    
    if (!server) {
        const container = document.getElementById('pluginsList');
        container.innerHTML = '<div class="empty-state">Выберите сервер в сайдбаре для просмотра плагинов</div>';
        return;
    }
    
    const container = document.getElementById('pluginsList');
    const loadBtn = document.getElementById('loadPluginsBtn');
    
    loadBtn.disabled = true;
    loadBtn.textContent = '⏳ Загрузка...';
    container.innerHTML = '<div class="loading">Загрузка плагинов...</div>';
    
    try {
        const response = await fetch(`${API_BASE}/api/plugins?server=${encodeURIComponent(server)}`);
        const data = await response.json();
        
        if (data.success) {
            plugins = data.plugins;
            renderPlugins();
        } else {
            container.innerHTML = `<div class="result-error">Ошибка: ${escapeHtml(data.error || 'Неизвестная ошибка')}</div>`;
        }
    } catch (error) {
        console.error('Ошибка загрузки плагинов:', error);
        container.innerHTML = `<div class="result-error">Ошибка: ${escapeHtml(error.message)}</div>`;
    } finally {
        loadBtn.disabled = false;
        loadBtn.textContent = '🔄 Загрузить список';
    }
}

// Отображение плагинов
function renderPlugins() {
    const container = document.getElementById('pluginsList');
    
    if (plugins.length === 0) {
        container.innerHTML = '<div class="empty-state">Плагины не найдены</div>';
        return;
    }
    
    const loadedPlugins = plugins.filter(p => p.loaded);
    const unloadedPlugins = plugins.filter(p => !p.loaded);
    
    // Сортируем плагины: сначала загруженные, потом отключенные
    const sortedPlugins = [...plugins].sort((a, b) => {
        if (a.loaded && !b.loaded) return -1;
        if (!a.loaded && b.loaded) return 1;
        return a.name.localeCompare(b.name);
    });
    
    container.innerHTML = `
        <div class="plugins-stats">
            <span class="stat-item">Всего установлено: <strong>${plugins.length}</strong></span>
            <span class="stat-item loaded">● Загружено: <strong>${loadedPlugins.length}</strong></span>
            <span class="stat-item unloaded">○ Отключено: <strong>${unloadedPlugins.length}</strong></span>
        </div>
        <div class="plugins-grid">
            ${sortedPlugins.map(plugin => `
                <div class="plugin-card ${plugin.loaded ? 'loaded' : 'unloaded'}">
                    <div class="plugin-header">
                        <div class="plugin-name" title="${escapeHtml(plugin.name)}">${escapeHtml(plugin.name)}</div>
                        <div class="plugin-status ${plugin.loaded ? 'status-loaded' : 'status-unloaded'}">
                            ${plugin.loaded ? '● Загружен' : '○ Отключен'}
                        </div>
                    </div>
                    ${plugin.version ? `<div class="plugin-info">📦 Версия: ${escapeHtml(plugin.version)}</div>` : ''}
                    ${plugin.author ? `<div class="plugin-info">👤 Автор: ${escapeHtml(plugin.author)}</div>` : ''}
                    <div class="plugin-actions">
                        <button class="btn btn-primary btn-sm" data-action="reload" data-plugin="${escapeHtml(plugin.name).replace(/"/g, '&quot;')}">
                            🔄 Перезагрузить
                        </button>
                        <button class="btn btn-warning btn-sm" data-action="unload" data-plugin="${escapeHtml(plugin.name).replace(/"/g, '&quot;')}">
                            ⏸ Отключить
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    // Добавляем обработчики событий для кнопок плагинов
    container.querySelectorAll('.plugin-actions button').forEach(button => {
        button.addEventListener('click', (e) => {
            const action = button.getAttribute('data-action');
            const pluginName = button.getAttribute('data-plugin');
            pluginAction(action, pluginName);
        });
    });
}

// Действие с плагином (unload/reload/load) - глобальная функция для onclick
window.pluginAction = async function(action, pluginName) {
    const server = selectedServer;
    
    if (!server) {
        showError('Выберите сервер в сайдбаре');
        return;
    }
    
    const actionText = {
        'unload': 'отключения',
        'reload': 'перезагрузки',
        'load': 'загрузки'
    }[action] || 'выполнения действия';
    
    if (!confirm(`Вы уверены, что хотите ${actionText} плагин "${pluginName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/plugins/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server, plugin: pluginName })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Обновляем список плагинов
            loadPlugins();
            // Обновляем историю
            loadHistory();
            // Показываем результат
            const resultOutput = document.getElementById('resultOutput');
            resultOutput.innerHTML = `<div class="result-success">Плагин "${escapeHtml(pluginName)}" успешно ${actionText === 'отключения' ? 'отключен' : actionText === 'перезагрузки' ? 'перезагружен' : 'загружен'}</div>`;
        } else {
            showError(`Ошибка ${actionText}: ${escapeHtml(data.error || 'Неизвестная ошибка')}`);
        }
    } catch (error) {
        console.error(`Ошибка ${actionText} плагина:`, error);
        showError(`Ошибка: ${escapeHtml(error.message)}`);
    }
};

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Загрузка списка админов
async function loadAdmins() {
    const server = selectedServer;
    
    if (!server) {
        const container = document.getElementById('adminsList');
        container.innerHTML = '<div class="empty-state">Выберите сервер в сайдбаре для просмотра админов</div>';
        return;
    }
    
    const container = document.getElementById('adminsList');
    const loadBtn = document.getElementById('loadAdminsBtn');
    
    loadBtn.disabled = true;
    loadBtn.textContent = '⏳ Загрузка...';
    container.innerHTML = '<div class="loading">Загрузка списка админов...</div>';
    
    try {
        const response = await fetch(`${API_BASE}/api/admins?server=${encodeURIComponent(server)}`);
        const data = await response.json();
        
        if (data.success) {
            admins = data.admins || [];
            renderAdmins();
        } else {
            container.innerHTML = `<div class="result-error">Ошибка: ${escapeHtml(data.error || 'Неизвестная ошибка')}</div>`;
        }
    } catch (error) {
        console.error('Ошибка загрузки админов:', error);
        container.innerHTML = `<div class="result-error">Ошибка: ${escapeHtml(error.message)}</div>`;
    } finally {
        loadBtn.disabled = false;
        loadBtn.textContent = '🔄 Загрузить список';
    }
}

// Отображение админов
function renderAdmins() {
    const container = document.getElementById('adminsList');
    
    if (admins.length === 0) {
        container.innerHTML = '<div class="empty-state">Админы не найдены</div>';
        return;
    }
    
    container.innerHTML = `
        <div class="admins-stats">
            <span class="stat-item">Всего админов: <strong>${admins.length}</strong></span>
        </div>
        <div class="admins-grid">
            ${admins.map(admin => `
                <div class="admin-card">
                    <div class="admin-header">
                        <div class="admin-steamid" title="${escapeHtml(admin.steamId)}">${escapeHtml(admin.steamId)}</div>
                    </div>
                    ${admin.name ? `<div class="admin-info">👤 ${escapeHtml(admin.name)}</div>` : ''}
                    <div class="admin-actions">
                        <button class="btn btn-danger btn-sm" onclick="removeAdmin('${escapeHtml(admin.steamId).replace(/'/g, "\\'")}')">
                            🗑️ Удалить
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// Добавление админа
async function addAdmin() {
    const server = selectedServer;
    const steamId = document.getElementById('addAdminSteamId').value.trim();
    
    if (!server) {
        showError('Выберите сервер в сайдбаре');
        return;
    }
    
    if (!steamId) {
        showError('Введите Steam ID');
        return;
    }
    
    // Проверка формата Steam ID (должен быть 17 цифр)
    if (!/^\d{17}$/.test(steamId)) {
        showError('Неверный формат Steam ID. Должно быть 17 цифр (например: 76561198012345678)');
        return;
    }
    
    const addBtn = document.getElementById('addAdminBtn');
    addBtn.disabled = true;
    addBtn.textContent = '⏳ Добавление...';
    
    try {
        const response = await fetch(`${API_BASE}/api/admins/add`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server, steamId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Очищаем поле ввода
            document.getElementById('addAdminSteamId').value = '';
            // Обновляем список админов
            loadAdmins();
            // Обновляем историю
            loadHistory();
        } else {
            showError(`Ошибка: ${data.error || 'Неизвестная ошибка'}`);
        }
    } catch (error) {
        console.error('Ошибка добавления админа:', error);
        showError(`Ошибка: ${error.message}`);
    } finally {
        addBtn.disabled = false;
        addBtn.textContent = '➕ Добавить';
    }
}

// Удаление админа
window.removeAdmin = async function(steamId) {
    const server = selectedServer;
    
    if (!server) {
        showError('Выберите сервер в сайдбаре');
        return;
    }
    
    if (!confirm(`Вы уверены, что хотите удалить админа ${steamId}?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api/admins/remove`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server, steamId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Обновляем список админов
            loadAdmins();
            // Обновляем историю
            loadHistory();
        } else {
            showError(`Ошибка: ${data.error || 'Неизвестная ошибка'}`);
        }
    } catch (error) {
        console.error('Ошибка удаления админа:', error);
        showError(`Ошибка: ${error.message}`);
    }
};

