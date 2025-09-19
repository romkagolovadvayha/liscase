// === Конфиг ===
const config = {
    servers: [
        { id: 40654, wipe: "Каждые две недели" },
        { id: 31271, wipe: "Еженедельно" },
    ],
    text_roulete: "БЕСПЛАТНО",
};

// https://fonts.google.com/icons?hl=ru&selected=Material+Symbols+Outlined:search:FILL@0;wght@400;GRAD@0;opsz@24&icon.size=24&icon.color=%23e3e3e3
const ICON_BUTTONS = [
    // пример из запроса: в начало .HeaderNav-module__link — иконка "search", размер 29px
    { selector: '.HeaderNav-module__link[href="/"]', icon: 'shopping_cart', place: 'prepend', size: 22, gap: 6 },
    { selector: '.SupportLink-module__link', icon: 'support_agent', place: 'prepend', size: 22, gap: 6 },
    { selector: '.HeaderNav-module__link[href="/page/pravila"]', icon: 'rule', place: 'prepend', size: 22, gap: 6 },
    { selector: '.HeaderNav-module__link[href="/page/wipe"]', icon: 'today', place: 'prepend', size: 22, gap: 6 },
    { selector: '.PlayerMenu-module__loginLink', icon: 'login', place: 'prepend', size: 22, gap: 6 },
    { selector: '.ProfileNav-module__body .ProfileNav-module__navItem[href="/profile"]', icon: 'lock_person', place: 'prepend', size: 22, gap: 6 },
    { selector: '.ProfileNav-module__body .ProfileNav-module__navItem[href="/profile/basket"]', icon: 'shopping_cart', place: 'prepend', size: 22, gap: 6 },
    { selector: '.ProfileNav-module__body .ProfileNav-module__navItem[href="/profile/history"]', icon: 'history', place: 'prepend', size: 22, gap: 6 },
    { selector: '.ProfileNav-module__wrapper .ProfileNav-module__logOut', icon: 'logout', place: 'prepend', size: 22, gap: 6 },
];

// === Анти-шифт/копирование (как в исходнике) ===
document.onkeydown = function (e) {
    // F12
    if (e.keyCode === 123) return false;

    // Ctrl+Shift+I / Ctrl+Shift+J
    if (e.ctrlKey && e.shiftKey && (e.keyCode === 'I'.charCodeAt(0) || e.keyCode === 'J'.charCodeAt(0))) {
        return false;
    }

    // Ctrl+U / Ctrl+S / Ctrl+D
    if (e.ctrlKey && (e.keyCode === 'U'.charCodeAt(0) || e.keyCode === 'S'.charCodeAt(0) || e.keyCode === 'D'.charCodeAt(0))) {
        return false;
    }
};

/*function nocopy(ev) {
  const e = ev || window.event;
  if (e && e.preventDefault) e.preventDefault();
  else if (e) e.returnValue = false;
  return false;
}

document.onmouseup      = nocopy;
document.onmousemove    = nocopy;
document.ondragstart    = nocopy;
document.onselectstart  = nocopy;
document.ontextmenu     = nocopy;
document.oncopy         = nocopy;
document.oncontextmenu  = nocopy;*/

// На загрузке страницы пометить “0” как “БЕСПЛАТНО”
document.addEventListener(
    'load',
    () => document.querySelectorAll('.Product-module__price').forEach(el => (parseInt(el.innerText) === 0 ? (el.innerText = config.text_roulete) : '')),
    true
);

// === Утилиты DOM ===
/**
 * Ожидает появления элемента.
 * @param {string} selector
 * @param {number} tries
 * @param {number} delayMs
 * @returns {Promise<Element|null>}
 */
function waitFor(selector, tries = 10, delayMs = 100) {
    return new Promise((resolve) => {
        let attempt = 0;
        const tick = () => {
            const el = document.querySelector(selector);
            if (el) return resolve(el);
            attempt++;
            if (attempt >= tries) return resolve(null);
            setTimeout(tick, delayMs);
        };
        tick();
    });
}

// === HTTP (как в исходнике) ===
function getJSON(url, callback) {
    try {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.responseType = 'json';
        xhr.onload = function () {
            const status = xhr.status;
            if (status === 200) callback(null, xhr.response);
            else callback(status, xhr.response);
        };
        xhr.onerror = function () {
            callback('NETWORK_ERROR', null);
        };
        xhr.send();
    } catch (e) {
        callback(e && e.message ? e.message : 'XHR_INIT_ERROR', null);
    }
}

// === Модификаторы интерфейса ===
let headerEl = undefined;
let copyRightEl = undefined;
let prevLinks = [];

/**
 * Переключатель активного сервера рядом с поиском.
 */
let iconWrapperReady = false;

async function addIconWrapper() {
    try {
        // если уже есть наш переключатель — только убеждаемся, что список прикреплён, и выходим
        if (document.querySelector('.select_server')) {
            const activeBox = document.querySelector('.select_server_active');
            const serversList = document.querySelector('.Servers-module__servers');
            if (activeBox && serversList && serversList.parentNode !== activeBox.parentNode) {
                activeBox.after(serversList);
            }
            return;
        }

        // ждём контейнер и оба элемента из блока серверов
        const [iconWrapper, activeEl, serversList] = await Promise.all([
            waitFor('.Search-module__iconWrapper', 20, 150),
            waitFor('.Servers-module__active', 20, 150),
            waitFor('.Servers-module__servers', 20, 150),
        ]);

        if (!iconWrapper || !activeEl || !serversList) return; // рано — уйдём молча

        // создаём переключатель
        const select = document.createElement('div');
        select.className = 'select_server';

        const activeBox = document.createElement('div');
        activeBox.className = 'select_server_active';

        // копируем содержимое активного пункта без чтения innerHTML у null
        const activeClone = activeEl.cloneNode(true);
        activeBox.appendChild(activeClone);
        select.appendChild(activeBox);

        iconWrapper.before(select);

        // переносим живой список серверов (как у вас было)
        activeBox.after(serversList);

        // обработчики кликов по пунктам — обновляют “шапку” выбранного
        document.querySelectorAll('.Servers-module__server').forEach((btn) => {
            btn.addEventListener('click', (evt) => {
                const item = evt.currentTarget || evt.target;
                const box = document.querySelector('.select_server_active');
                if (!box || !item) return;
                box.innerHTML = '';
                box.appendChild(item.cloneNode(true));
            });
        });

        iconWrapperReady = true;
    } catch (err) {
        console.warn('[addIconWrapper]', err);
    }
}

function wireServerSelectOnce() {
    const container = document.querySelector('.Servers-module__servers');
    if (!container || container.dataset.bound === '1') return;
    container.dataset.bound = '1';

    container.addEventListener('click', (evt) => {
        const btn = evt.target.closest('.Servers-module__server');
        if (!btn) return;

        // Дождёмся, пока React обновит DOM.
        // Можно setTimeout(0), но rAF надёжнее для «после перерисовки».
        requestAnimationFrame(() => {
            const box = document.querySelector('.select_server_active');
            const activeEl = document.querySelector('.Servers-module__active');
            if (!box) return;

            box.innerHTML = '';
            if (activeEl) {
                box.appendChild(activeEl.cloneNode(true));
            } else {
                // на всякий случай — если активный не успел обновиться
                box.appendChild((btn).cloneNode(true));
            }
        });
    });
}

// звать при инициализации и после любых ремоунтов/роутов
wireServerSelectOnce();
window.eventsManager?.addListener('LOCATION_CHANGED', () => wireServerSelectOnce());

// подстрахуемся от тихих ремоунтов без смены роута
/*new MutationObserver(() => wireServerSelectOnce())
  .observe(document.body, { childList: true, subtree: true });*/

/**
 * Добавляет тег wipe на карточки мониторинга.
 */
async function addMonitoring() {
    const infoProbe = await waitFor('.MonitoringServer-module__info');
    if (!infoProbe) return;

    config.servers.forEach((item) => {
        const root = document.querySelector(`[data-monitoringserverid="${item.id}"]`);
        if (!root) return;

        const infoSpan = root.querySelector('.MonitoringServer-module__info > span');
        const existsTag = root.querySelector('.server_tag');
        if (item.wipe && infoSpan && !existsTag) {
            const wrap = document.createElement('div');
            wrap.className = 'server_tag';
            wrap.textContent = item.wipe;
            infoSpan.after(wrap);
        }
    });
}

/**
 * Перенос кастомного хедера из виджета в шапку.
 */
async function addCustomWidget() {
    const customHeaderInWidgets = await waitFor('.Widgets-module__widgetWrapper .custom_header', 10, 100);
    if (!customHeaderInWidgets) return;

    const headerContainer = await waitFor('.container.headerContainer', 10, 100);
    if (!headerContainer) {
        // Если контейнера нет — просто скрываем виджет, как у вас
        const ww = customHeaderInWidgets.closest('.Widgets-module__widgetWrapper');
        if (ww) {
            ww.style.display = 'none';
            ww.classList.remove('Widgets-module__widgetWrapper');
        }
        return;
    }

    // Скрыть исходный хост-виджет и переместить .custom_header вверх
    /*const widgetWrapper = customHeaderInWidgets.closest('.Widgets-module__widgetWrapper');
    if (widgetWrapper) {
      widgetWrapper.style.display = 'none';
      widgetWrapper.classList.remove('Widgets-module__widgetWrapper');
    }

    const customHeader = document.querySelector('.custom_header');
    if (customHeader && !headerContainer.querySelector('.custom_header')) {
      const headerWrapper = document.querySelector('.Header-module__wrapper');
      if (headerWrapper) headerWrapper.after(customHeader);
    }*/
}

/**
 * Перенос копирайта в последний виджет.
 */
async function addCopyright(selector) {
    // Захватим исходный блок копирайта один раз
    if (!copyRightEl) {
        const found = document.querySelector('.DesktopCopyright-module__wrapper');
        if (found) copyRightEl = found;
    }

    const lastWidgetHost = await waitFor(selector);
    if (!lastWidgetHost || !copyRightEl) return;

    if (lastWidgetHost) lastWidgetHost.after(copyRightEl);
}

/**
 * Обновить информацию о пользователе в меню.
 */
async function updateUser(data) {
    const link = await waitFor('.PlayerMenu-module__profileLink', 10, 100);
    if (!link || !data) return;

    link.innerHTML =
        '<div class="profile_avatar"><img src="' + (data.avatar || '') + '"/></div>' +
        '<div class="profile_info">' +
        '<div class="profile_info_username">' + (data.username || '') + '</div>' +
        '<div class="profile_info_steamid">' + (data.steamId || '') + '</div>' +
        '</div>';
}

/**
 * Подсветка активных ссылок.
 */
async function activeLinks(currentPath) {
    const supportLinkProbe = await waitFor('a[href="/support"]', 10, 100);
    if (!supportLinkProbe) return;

    // Сброс прежних
    prevLinks.forEach((a) => a.classList.remove('active'));
    prevLinks = [];

    const links = document.querySelectorAll('a[href="' + currentPath + '"]');
    links.forEach((a) => {
        a.classList.add('active');
        prevLinks.push(a);
    });
}

/**
 * Перестановки элементов шапки/поиска.
 */
async function rearrangeHeaderAndSearch() {
    // Кэшируем header один раз
    if (headerEl === undefined || headerEl === null) {
        headerEl = document.querySelector('.headerContainer') || null;
    }

    // Перенести поиск перед .Categories-module__categories
    const searchWrap = document.querySelector('.Search-module__wrapper');
    const categories = document.querySelector('.Categories-module__categories');
    if (searchWrap && categories && categories.parentNode) {
        categories.parentNode.insertBefore(searchWrap, categories);
    }

    // Перенести header в начало .Shop-module__wrapper
    const shopWrap = document.querySelector('main.container > section');
    if (headerEl && shopWrap) {
        // только если он ещё не там
        if (headerEl.parentNode !== shopWrap || shopWrap.firstElementChild !== headerEl) {
            shopWrap.insertBefore(headerEl, shopWrap.firstChild);
        }
    }
}

// === Главный поток согласно документации ===
function main() {
    // 1) Сообщаем, что хотим менеджер ивентов
    window.dispatchEvent(new CustomEvent('initEventsManager'));
    window.dispatchEvent(new CustomEvent('initState'));

    // 2) Подписки на доступные ивенты
    // Смена роута
    const debounce = (fn, ms = 50) => {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), ms); };
    };

    window.eventsManager.addListener('LOCATION_CHANGED', debounce((data) => {
        addCopyright('.ProfileNav-module__wrapper > div:last-of-type');
        addCopyright('.Widgets-module__wrapper .Widgets-module__widgetWrapper:last-of-type > div');
        //addCustomWidget();
        addMonitoring();
        addIconWrapper();   // теперь безопасно
        rearrangeHeaderAndSearch();
        if (data && data.pathname) activeLinks(data.pathname);
    }, 80));

    // Мониторинг
    window.eventsManager.addListener('MONITORING_UPDATED', () => {
        addMonitoring();
    });

    // Игрок
    window.eventsManager.addListener('PLAYER_LOADED', (payload) => {
        // в описании ивента сказано: плюс данные — используем payload
        const data = payload && payload.data ? payload.data : payload;
        updateUser(data);
    });

    window.eventsManager.addListener('PLAYER_UPDATED', (data) => {
        updateUser(data);
    });

    // 3) Старт загрузки очереди событий
    //    (по примечанию: триггернет события, которые произошли до нашего кода)
    window.eventsManager.load();

    // Первичная инициализация интерфейса, если уже есть DOM
    addCopyright();
    addCustomWidget();
    addMonitoring();
    addIconWrapper();
    rearrangeHeaderAndSearch();

}

// Запуск по документации
if (window.isAppReady) {
    main();
} else {
    window.addEventListener('appReady', () => {
        main();
    });
}

// === ВСПОМОГАТЕЛЬНОЕ ===
function ensureMaterialSymbolsFont() {
    if (!document.querySelector('link[data-material-symbols]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.setAttribute('data-material-symbols', '1');
        // стандартный шрифт Material Symbols Outlined
        link.href = 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200';
        document.head.appendChild(link);
    }
}

// 2) Хелперы для чтения баланса и записи в кнопку
function getPlayerBalanceFromState() {
    if (typeof window.getState !== 'function') return null;
    const state = window.getState();
    // страховочные варианты структуры
    const balance =
        state?.player?.player?.balance ??
        state?.player?.balance ??
        state?.balance ??
        null;
    return typeof balance === 'number' ? balance : (balance ? Number(balance) : null);
}

async function writeBalanceToBtn() {
    // ждём саму кнопку (span внутри)
    const span = await waitFor('.PlayerBalance-module__btn > span', 20, 100);
    if (!span) return;

    const balance = getPlayerBalanceFromState();
    if (balance == null || isNaN(balance)) return;

    // подключим шрифт для иконки
    ensureMaterialSymbolsFont();

    // красивый формат, RU-локаль
    const formatted = new Intl.NumberFormat('ru-RU').format(balance);

    // перезаписываем целиком, чтобы не плодить иконки
    span.innerHTML = `${formatted} <span class="material-symbols-outlined" style="font-size: 18px; line-height: 19px; display: inline-flex; vertical-align: middle; margin-left: 2px;">currency_ruble</span>`;
}

// 3) Дёргай обновление в нужные моменты
// первичная попытка после load очереди
writeBalanceToBtn();

// при событиях игрока
window.eventsManager?.addListener?.('PLAYER_LOADED',  () => writeBalanceToBtn());
window.eventsManager?.addListener?.('PLAYER_UPDATED', () => writeBalanceToBtn());

// при смене роута (кнопка может монтироваться позже)
window.eventsManager?.addListener?.('LOCATION_CHANGED', () => writeBalanceToBtn());

// если ремоунт без ивента — наблюдатель подстрахует
/*new MutationObserver(() => writeBalanceToBtn())
  .observe(document.body, { childList: true, subtree: true });*/

function makeIconSpan(name, sizePx = 24, place = 'prepend', gap = 6) {
    const span = document.createElement('span');
    span.className = 'material-symbols-outlined ai-icon';
    span.textContent = name;
    span.setAttribute('data-ai-icon', '1'); // маркер, чтобы не дублировать

    // стили по умолчанию — как просили (font-size) + компактное выравнивание
    span.style.fontSize = `${sizePx}px`;
    span.style.lineHeight = '1';
    span.style.display = 'inline-flex';
    span.style.verticalAlign = 'middle';
    // аккуратный отступ с нужной стороны
    if (place === 'prepend') span.style.marginRight = `${gap}px`;
    else span.style.marginLeft = `${gap}px`;

    return span;
}

function injectIconTo(elem, rule) {
    // не вставлять, если уже есть наш маркер
    const already = elem.querySelector(':scope > .material-symbols-outlined.ai-icon[data-ai-icon="1"]');
    if (already) return;

    const iconSpan = makeIconSpan(rule.icon, rule.size ?? 24, rule.place ?? 'prepend', rule.gap ?? 6);

    if ((rule.place || 'prepend') === 'prepend') {
        elem.insertAdjacentElement('afterbegin', iconSpan);
    } else {
        elem.insertAdjacentElement('beforeend', iconSpan);
    }
}

function applyIcons(root = document) {
    ensureMaterialSymbolsFont();
    ICON_BUTTONS.forEach(rule => {
        // найдём все элементы под селектор и проставим иконки
        const nodes = root.querySelectorAll(rule.selector);
        nodes.forEach(node => injectIconTo(node, rule));
    });
}

// один раз при старте
applyIcons();

// если у тебя подключён eventsManager — переинициализируем на смене роута
try {
    window.eventsManager?.addListener?.('LOCATION_CHANGED', () => applyIcons());
} catch { /* noop */ }

// на случай ремоунтов без смены роута — лёгкий наблюдатель
const mo = new MutationObserver((mutList) => {
    // оптимизация: если добавились элементы, прогоняем только по добавленным веткам
    for (const mut of mutList) {
        for (const n of mut.addedNodes) {
            if (n.nodeType !== 1) continue; // ELEMENT_NODE
            applyIcons(n);
        }
    }
});
mo.observe(document.body, { childList: true, subtree: true });

// Клонируем .bonuses_table в конец .PlayerBalance-module__modal .boxBody
async function cloneBonusesIntoBalanceModal() {
    // ждём, пока смонтируется контейнер модалки
    const dst = await waitFor('.PlayerBalance-module__modal .boxBody', 40, 100);
    if (!dst) return;

    // убираем старую копию, чтобы не плодить
    dst.querySelectorAll('.bonuses_table[data-cloned="1"]').forEach(n => n.remove());

    // источник — первая таблица вне модалки и не ранее клонированная
    let src = document.querySelector('.bonuses_table:not([data-cloned="1"])');
    if (!src) return;
    if (src.closest('.PlayerBalance-module__modal')) {
        // если вдруг нашли внутри модалки, попробуем другую (если их несколько)
        const all = [...document.querySelectorAll('.bonuses_table:not([data-cloned="1"])')];
        src = all.find(n => !n.closest('.PlayerBalance-module__modal')) || src;
    }

    const copy = src.cloneNode(true);
    copy.dataset.cloned = '1';
    copy.classList.add('bonuses_table--cloned');

    dst.appendChild(copy);
}

// Один делегированный клик — открытие модалки
(function wireBalanceModalClone() {
    if (document.__balanceCloneBound) return;
    document.__balanceCloneBound = true;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.PlayerBalance-module__btn');
        if (!btn) return;

        // Дадим React смонтировать модалку, потом клонируем
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                cloneBonusesIntoBalanceModal();
            });
        });
    }, true); // capture, чтобы сработать даже при stopPropagation
})();

// Подстраховка: если модалку открывают программно
const balanceModalMO = new MutationObserver((mutList) => {
    for (const m of mutList) {
        for (const n of m.addedNodes) {
            if (n.nodeType !== 1) continue;
            if (
                n.matches?.('.PlayerBalance-module__modal .boxBody') ||
                n.querySelector?.('.PlayerBalance-module__modal .boxBody')
            ) {
                cloneBonusesIntoBalanceModal();
            }
        }
    }
});
balanceModalMO.observe(document.body, { childList: true, subtree: true });

// На всякий случай запуск при смене роута
window.eventsManager?.addListener?.('LOCATION_CHANGED', () => cloneBonusesIntoBalanceModal());