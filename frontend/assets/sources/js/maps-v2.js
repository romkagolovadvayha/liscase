(function () {
    console.log('maps-v2.js: Script started');
    
    const root = document.getElementById('mapsV2Root');
    if (!root) {
        console.warn('maps-v2.js: mapsV2Root element not found, script stopped');
        return;
    }
    
    console.log('maps-v2.js: Root element found', root);

    const serverId = parseInt(root.dataset.serverId, 10);
    const voteUrlTemplate = root.dataset.voteUrl || '';
    const votersUrlTemplate = root.dataset.votersUrl || '';
    const texts = {
        vote: root.dataset.textVote || 'Проголосовать',
        download: root.dataset.textDownload || 'Скачать карту',
        emptyVoters: root.dataset.textEmptyVoters || 'Будьте первым, кто проголосует за эту карту',
        noVotes: root.dataset.textNoVotes || 'Пока никто не голосовал',
        refresh: root.dataset.textRefresh || 'Обновить',
    };

    const biomeLabelsMap = safeParseJSON(root.dataset.biomeLabels, {});
    const totalMaps = parseInt(root.dataset.totalMaps || '0', 10) || 0;
    const displayLimit = parseInt(root.dataset.displayLimit || '0', 10) || 0;
    let totalVotes = parseInt(root.dataset.totalVotes || '0', 10) || 0;

    let maps;
    try {
        maps = JSON.parse(root.dataset.maps || '[]');
    } catch (e) {
        maps = [];
        console.error('Failed to parse maps payload', e);
    }

    const mapIndex = new Map();
    maps.forEach((map, index) => {
        mapIndex.set(map.id, index);
    });

    const listEl = root.querySelector('[data-role="map-list"]');

    if (!listEl) {
        return;
    }

    let userVotedId = parseInt(root.dataset.userVotedId, 10);
    if (Number.isNaN(userVotedId)) {
        userVotedId = null;
    }

    // Множественные проголосованные карты
    let userVotedMapIds = new Set();
    try {
        const votedIdsJson = root.dataset.userVotedIds;
        if (votedIdsJson) {
            const votedIds = JSON.parse(votedIdsJson);
            if (Array.isArray(votedIds)) {
                votedIds.forEach(id => userVotedMapIds.add(parseInt(id, 10)));
            }
        }
    } catch (e) {
        console.error('Failed to parse user voted map IDs', e);
    }
    // Для обратной совместимости
    if (userVotedId !== null && !userVotedMapIds.has(userVotedId)) {
        userVotedMapIds.add(userVotedId);
    }

    let lightboxEl;

    function formatDate(dateString) {
        if (!dateString) {
            return '';
        }
        const date = new Date(dateString.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return dateString;
        }
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}.${month} ${hours}:${minutes}`;
    }

    function calculateMaxVotes() {
        return maps.reduce((max, map) => Math.max(max, map.voteCount || 0), 0);
    }

    function calculateTotalVotes() {
        return maps.reduce((sum, map) => sum + (map.voteCount || 0), 0);
    }

    function updateCards() {
        const maxVotes = calculateMaxVotes();
        totalVotes = calculateTotalVotes();
        root.dataset.totalVotes = String(totalVotes);
        
        // Получаем listEl динамически, так как он может быть пересоздан после Pjax
        const currentListEl = root.querySelector('[data-role="map-list"]');
        if (!currentListEl) {
            return;
        }
        
        currentListEl.querySelectorAll('.mapsV2__card').forEach((cardEl) => {
            const mapId = parseInt(cardEl.dataset.mapId, 10);
            const map = mapIndex.has(mapId) ? maps[mapIndex.get(mapId)] : null;
            if (!map) {
                return;
            }

            const voteCount = map.voteCount || 0;
            const isLeading = voteCount > 0 && voteCount === maxVotes && maxVotes > 0;
            const isVoted = userVotedMapIds.has(mapId);
            const isCurrentActive = false; // Модалка теперь управляется стандартным modal.js

            // Обновляем классы карточки явно (убираем старые, добавляем новые)
            cardEl.classList.remove('is-active', 'is-leading');
            if (isCurrentActive) {
                cardEl.classList.add('is-active');
            }
            if (isLeading) {
                cardEl.classList.add('is-leading');
            }

            // Обновляем счетчик голосов в чипе
            const votesBadge = cardEl.querySelector('[data-role="card-votes"]');
            if (votesBadge) {
                votesBadge.textContent = voteCount;
            }

            // Обновляем общий счетчик голосов
            const votesTotal = cardEl.querySelector('[data-role="card-votes-total"]');
            if (votesTotal) {
                votesTotal.textContent = voteCount;
            }

            // Прогресс-бар рассчитывается только в PHP, не обновляем его в JS

            // Обновляем состояние чипа с голосами
            const voteChip = cardEl.querySelector('.mapsV2__card-chip--votes');
            if (voteChip) {
                // Убираем все классы состояния, потом добавляем нужные
                voteChip.classList.remove('is-active', 'is-leading');
                if (isVoted) {
                    voteChip.classList.add('is-active');
                }
                if (isLeading) {
                    voteChip.classList.add('is-leading');
                }
            }

            // Обновляем список голосующих (если voters уже загружены)
            const votersContainer = cardEl.querySelector('[data-role="card-voters"]');
            if (votersContainer && map.voters) {
                renderCardVoters(votersContainer, map);
            }
        });
    }

    function renderCardVoters(container, map) {
        container.innerHTML = '';
        const voters = map.voters || [];
        if (!voters.length) {
            const span = document.createElement('span');
            span.className = 'mapsV2__card-voters-empty';
            span.textContent = texts.noVotes;
            container.appendChild(span);
            return;
        }

        voters.slice(0, 5).forEach((voter) => {
            const img = document.createElement('img');
            img.src = voter.avatar;
            img.alt = voter.username;
            img.title = voter.username;
            container.appendChild(img);
        });

        if (voters.length > 5) {
            const more = document.createElement('span');
            more.className = 'mapsV2__card-more';
            more.textContent = `+${voters.length - 5}`;
            container.appendChild(more);
        }
    }


    // Голосование теперь обрабатывается через Pjax, без JavaScript

    function refreshVoters(mapId, updateDetail) {
        const url = votersUrlTemplate.replace('ID_PLACEHOLDER', String(mapId));
        return fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load voters');
                }
                if (!mapIndex.has(mapId)) {
            return;
        }
                const map = maps[mapIndex.get(mapId)];
                if (!map) {
            return;
        }

                // Обновляем только voters, voteCount не трогаем (он уже обновлен из handleVote)
                map.voters = data.users || [];
                
                // Обновляем список голосовавших в модалке, если она открыта
                if (updateDetail) {
                    const detailEl = document.querySelector('[data-role="map-detail"]');
                    if (!detailEl) {
                return;
            }
                    const detailMapId = parseInt(detailEl.dataset.mapDetailId, 10);
                    if (detailMapId !== mapId) {
                return;
            }
        const list = detailEl.querySelector('[data-role="voters-list"]');
                    if (list) {
        list.innerHTML = '';
        const voters = map.voters || [];
        if (!voters.length) {
            const p = document.createElement('p');
            p.className = 'mapsV2__voters-empty';
            p.textContent = texts.emptyVoters;
            list.appendChild(p);
                        } else {
        voters.forEach((voter) => {
            const item = document.createElement('div');
            item.className = 'mapsV2__voter';

            const avatar = document.createElement('img');
            avatar.src = voter.avatar;
            avatar.alt = voter.username;

            const text = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = voter.username;
            const date = document.createElement('span');
            date.textContent = formatDate(voter.created_at);
            text.appendChild(name);
            text.appendChild(date);

            item.appendChild(avatar);
            item.appendChild(text);
            list.appendChild(item);
        });
    }
                    }
                }
                
                // Voters теперь обновляются автоматически через Pjax при голосовании
                // Можно оставить для ручного обновления через кнопку "Обновить"
            })
            .catch((error) => {
                console.error(error);
            });
    }


    function isLightboxOpen() {
        return document.documentElement.classList.contains('mapsV2__lightbox-open');
    }

    // Инициализация маркеров и монументов при загрузке модалки

    // Делегирование событий для действий внутри модалки (загружается динамически)
    document.addEventListener('click', (event) => {
        // Простая проверка: если target - не элемент, берем родителя
        let target = event.target;
        if (!target || target.nodeType !== 1) {
            target = target && target.parentElement;
        }
        if (!target || !target.closest) return;
        
        // Обработка открытия fullscreen изображения
        const fullscreenBtn = target.closest('[data-action="open-fullscreen"]');
        if (fullscreenBtn) {
            event.preventDefault();
            const src = fullscreenBtn.dataset.src;
            if (src) {
                openLightbox(src);
            }
            return;
        }

        // Обработка клика по превью для открытия fullscreen
        const preview = target.closest('[data-role="preview"]');
        if (preview && !target.closest('.mapsV2__marker')) {
            const src = preview.dataset.src;
            if (src) {
                openLightbox(src);
            }
            return;
        }

        // Форма голосования из детальной карточки отправляется через Pjax автоматически,
        // без перехвата JS событий
    });

    // Простая логика: при наведении на чип монумента показываем маркер с таким же индексом
    console.log('maps-v2.js: Setting up monument hover handlers');
    
    document.addEventListener('mouseenter', function(event) {
        let target = event.target;
        if (!target || target.nodeType !== 1) {
            target = target && target.parentElement;
        }
        if (!target || !target.closest) return;
        
        const chip = target.closest('.mapsV2__monument-chip');
        if (!chip) return;
        
        const index = chip.dataset.monumentIndex;
        if (!index) {
            console.warn('maps-v2.js: Monument chip found but no index', chip);
            return;
        }
        
        console.log('maps-v2.js: mouseenter on monument chip', { index, chip });
        
        const detailEl = document.querySelector('[data-role="map-detail"]');
        if (!detailEl) {
            console.warn('maps-v2.js: map-detail element not found');
            return;
        }
        
        // Находим маркер с таким же индексом и показываем его
        const marker = detailEl.querySelector('.mapsV2__marker[data-monument-index="' + index + '"]');
        if (marker) {
            console.log('maps-v2.js: Showing marker', { index, marker });
            marker.style.display = 'block';
            chip.classList.add('is-active');
        } else {
            console.warn('maps-v2.js: Marker not found for index', index, 'Total markers:', detailEl.querySelectorAll('.mapsV2__marker').length);
        }
    }, true);

    document.addEventListener('mouseleave', function(event) {
        let target = event.target;
        if (!target || target.nodeType !== 1) {
            target = target && target.parentElement;
        }
        if (!target || !target.closest) return;
        
        const chip = target.closest('.mapsV2__monument-chip');
        if (!chip) return;
        
        const index = chip.dataset.monumentIndex;
        if (!index) return;
        
        console.log('maps-v2.js: mouseleave on monument chip', { index, chip });
        
        const detailEl = document.querySelector('[data-role="map-detail"]');
        if (!detailEl) return;
        
        // Скрываем маркер с таким же индексом
        const marker = detailEl.querySelector('.mapsV2__marker[data-monument-index="' + index + '"]');
        if (marker) {
            console.log('maps-v2.js: Hiding marker', { index, marker });
            marker.style.display = 'none';
            chip.classList.remove('is-active');
        }
    }, true);

    // Инициализация маркеров и обновление состояния кнопки голосования после загрузки модалки
    function updateVoteButtonState() {
        const detailEl = document.querySelector('[data-role="map-detail"]');
        if (!detailEl) {
            return;
        }

        const voteBtn = detailEl.querySelector('[data-action="vote-from-detail"]');
        if (!voteBtn) {
            return;
        }

        const mapId = parseInt(voteBtn.dataset.mapId, 10);
        if (!isNaN(mapId) && mapId > 0) {
            const isVoted = userVotedMapIds.has(mapId);
            voteBtn.classList.toggle('is-active', isVoted);
            
            // Обновляем иконку и текст
            const icon = voteBtn.querySelector('i');
            const text = voteBtn.childNodes[voteBtn.childNodes.length - 1];
            if (icon) {
                icon.className = isVoted ? 'fas fa-heart' : 'far fa-heart';
            }
        }
    }

    function initializeModalContent(modalEl) {
        console.log('maps-v2.js: initializeModalContent called', modalEl);
        
        // Используем контекст модалки, если передан, иначе ищем во всем документе
        const searchContext = modalEl || document;
        
        // Используем setTimeout чтобы дать DOM время полностью обновиться после загрузки контента
        setTimeout(function() {
            // Скрываем все маркеры по умолчанию (они показываются только при наведении)
            const detailEl = searchContext.querySelector('[data-role="map-detail"]');
            if (detailEl) {
                console.log('maps-v2.js: map-detail found, hiding markers', detailEl);
                
                const markersContainer = detailEl.querySelector('[data-role="markers"]');
                if (markersContainer) {
                    console.log('maps-v2.js: markers container found', markersContainer);
                    
                    // Читаем отладочные data-атрибуты
                    const debugSize = markersContainer.getAttribute('data-debug-size');
                    const debugMonumentsCount = markersContainer.getAttribute('data-debug-monuments-count');
                    console.log('maps-v2.js: DEBUG - mapSize=', debugSize, ', monumentsCount=', debugMonumentsCount);
                    
                    const markers = markersContainer.querySelectorAll('.mapsV2__marker');
                    console.log('maps-v2.js: Found markers', markers.length);
                    markers.forEach((marker, i) => {
                        marker.style.display = 'none';
                        console.log('maps-v2.js: Hidden marker', i, marker.dataset.monumentIndex);
                    });
                } else {
                    console.warn('maps-v2.js: markers container not found');
                }
            } else {
                console.warn('maps-v2.js: map-detail element not found in initializeModalContent');
            }
        }, 50); // Небольшая задержка для полного обновления DOM
        updateVoteButtonState();
        
        // Переинициализируем Pjax для формы голосования в модалке
        if (typeof $ !== 'undefined' && typeof $.pjax !== 'undefined') {
            const detailEl = searchContext.querySelector('[data-role="map-detail"]');
            if (detailEl) {
                // Находим все Pjax контейнеры внутри модалки
                const pjaxContainers = detailEl.querySelectorAll('[id^="maps-v2-voters-pjax-"]');
                pjaxContainers.forEach((container) => {
                    // Удаляем старые обработчики если есть
                    $(container).off('pjax:submit');
                    
                    // Инициализируем Pjax для всех форм внутри контейнера
                    const containerId = container.id || container.getAttribute('id');
                    if (containerId) {
                        $(container).on('submit', 'form[data-pjax]', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const form = $(this);
                            const formAction = form.attr('action') || form.attr('href');
                            const formMethod = (form.attr('method') || 'GET').toUpperCase();
                            const formData = formMethod === 'POST' ? form.serialize() : null;
                            
                            $.pjax({
                                type: formMethod,
                                url: formAction,
                                container: '#' + containerId,
                                data: formData,
                                push: false,
                                replace: false,
                                timeout: 5000,
                                scrollTo: false
                            });
                            
                            return false;
                        });
                    }
                });
            }
        }
    }

    // Инициализация после загрузки модалки
    console.log('maps-v2.js: Setting up modal event handlers, jQuery available:', typeof $ !== 'undefined');
    
    if (typeof $ !== 'undefined') {
        $(document).on('modal.content.loaded', '#modal-dialog', function() {
            console.log('maps-v2.js: modal.content.loaded event fired');
            const modalEl = this;
            setTimeout(function() {
                initializeModalContent(modalEl);
            }, 100);
        });

        $(document).on('shown.bs.modal', '#modal-dialog', function() {
            console.log('maps-v2.js: shown.bs.modal event fired');
            const modalEl = this;
            setTimeout(function() {
                initializeModalContent(modalEl);
            }, 50);
        });
    } else {
        console.warn('maps-v2.js: jQuery not available, using native events');
        const modalEl = document.getElementById('modal-dialog');
        if (modalEl) {
            console.log('maps-v2.js: modal-dialog element found, adding native event listener');
            modalEl.addEventListener('shown.bs.modal', function() {
                console.log('maps-v2.js: shown.bs.modal (native) event fired');
                const modalEl = this;
                setTimeout(function() {
                    initializeModalContent(modalEl);
                }, 50);
            });
        } else {
            console.warn('maps-v2.js: modal-dialog element not found');
        }
    }

    // Закрытие lightbox по Escape
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isLightboxOpen()) {
            closeLightbox();
        }
    });

    function ensureLightbox() {
        if (lightboxEl) {
            return lightboxEl;
        }
        lightboxEl = document.createElement('div');
        lightboxEl.className = 'mapsV2__lightbox';
        lightboxEl.innerHTML = `
            <div class="mapsV2__lightbox-content">
                <button type="button" class="mapsV2__lightbox-close" data-action="close-lightbox">
                    <i class="fas fa-times"></i>
                </button>
                <img src="" alt="" data-role="lightbox-image">
            </div>
        `;
        lightboxEl.addEventListener('click', (event) => {
            if (event.target === lightboxEl) {
                closeLightbox();
                return;
            }
            let target = event.target;
            if (!target || target.nodeType !== 1) {
                target = target && target.parentElement;
            }
            if (!target || !target.closest) return;
            
            const closeBtn = target.closest('[data-action="close-lightbox"]');
            if (closeBtn) {
                event.preventDefault();
                event.stopPropagation();
                closeLightbox();
            }
        });
        document.body.appendChild(lightboxEl);
        return lightboxEl;
    }

    function openLightbox(src) {
        if (!src) {
            return;
        }
        const lightbox = ensureLightbox();
        const image = lightbox.querySelector('[data-role="lightbox-image"]');
        image.src = src;
        lightbox.classList.add('is-visible');
        document.documentElement.classList.add('mapsV2__lightbox-open');
    }

    function closeLightbox() {
        if (!lightboxEl) {
            return;
        }
        lightboxEl.classList.remove('is-visible');
        const image = lightboxEl.querySelector('[data-role="lightbox-image"]');
        if (image) {
            image.src = '';
        }
        document.documentElement.classList.remove('mapsV2__lightbox-open');
    }

    // Функция для переинициализации данных после Pjax обновления
    function reinitializeAfterPjax() {
        // Обновляем данные из root элемента
        const newTotalVotes = parseInt(root.dataset.totalVotes || '0', 10) || 0;
        if (newTotalVotes !== totalVotes) {
            totalVotes = newTotalVotes;
        }

        // Обновляем userVotedMapIds из root
        try {
            const votedIdsJson = root.dataset.userVotedIds;
            if (votedIdsJson) {
                const votedIds = JSON.parse(votedIdsJson);
                if (Array.isArray(votedIds)) {
                    userVotedMapIds.clear();
                    votedIds.forEach(id => userVotedMapIds.add(parseInt(id, 10)));
                }
            }
        } catch (e) {
            console.error('Failed to parse user voted map IDs after Pjax', e);
        }

        // Обновляем maps данные из HTML (из data-role="card-votes")
        const currentListEl = root.querySelector('[data-role="map-list"]');
        if (currentListEl) {
            currentListEl.querySelectorAll('.mapsV2__card').forEach((cardEl) => {
                const mapId = parseInt(cardEl.dataset.mapId, 10);
                const votesBadge = cardEl.querySelector('[data-role="card-votes"]');
                if (votesBadge && mapIndex.has(mapId)) {
                    const map = maps[mapIndex.get(mapId)];
                    if (map) {
                        map.voteCount = parseInt(votesBadge.textContent, 10) || 0;
                    }
                }
            });
        }

        // Обновляем карточки
    updateCards();
        
        // Обновляем состояние кнопки голосования в модалке, если она открыта
        updateVoteButtonState();
    }

    // Обработчик события Pjax для переинициализации после обновления
    // Используем jQuery, так как Yii2 Pjax использует jQuery события
    if (typeof $ !== 'undefined') {
        $(document).on('pjax:success', '#maps-v2-cards-pjax', function() {
            console.log('Pjax success for maps-v2-cards-pjax');
            reinitializeAfterPjax();
        });

        $(document).on('pjax:end', '#maps-v2-cards-pjax', function() {
            console.log('Pjax end for maps-v2-cards-pjax');
            reinitializeAfterPjax();
        });

        // Обновляем состояние кнопки голосования после обновления списка voters
        $(document).on('pjax:success', '[id^="maps-v2-voters-pjax-"]', function() {
            updateVoteButtonState();
            
            // Переинициализируем обработчик формы после обновления
            const pjaxContainer = this;
            if (pjaxContainer) {
                const containerId = pjaxContainer.id || pjaxContainer.getAttribute('id');
                if (containerId) {
                    $(pjaxContainer).off('submit', 'form[data-pjax]');
                    $(pjaxContainer).on('submit', 'form[data-pjax]', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const form = $(this);
                        const formAction = form.attr('action') || form.attr('href');
                        const formMethod = (form.attr('method') || 'GET').toUpperCase();
                        const formData = formMethod === 'POST' ? form.serialize() : null;
                        
                        $.pjax({
                            type: formMethod,
                            url: formAction,
                            container: '#' + containerId,
                            data: formData,
                            push: false,
                            replace: false,
                            timeout: 5000,
                            scrollTo: false
                        });
                        
                        return false;
                    });
                }
            }
            
            // После обновления voters обновляем основной список карт для синхронизации счетчиков
            const detailEl = document.querySelector('[data-role="map-detail"]');
            if (detailEl) {
                const mapId = parseInt(detailEl.dataset.mapDetailId, 10);
                if (mapId > 0 && typeof $ !== 'undefined') {
                    const mainForm = document.getElementById('vote-form');
                    if (mainForm) {
                        // Обновляем основную форму через Pjax для синхронизации счетчиков
                        $.pjax({
                            url: window.location.href.split('?')[0],
                            container: '#maps-v2-cards-pjax',
                            timeout: 5000,
                            replace: false,
                            push: false
                        });
                    }
                }
            }
        });

        // Также переинициализируем маркеры после Pjax обновления модалки (если используется)
        $(document).on('pjax:success', function(event, data) {
            // Проверяем, был ли обновлен контент модалки
            const modal = document.getElementById('modal-dialog');
            if (modal && modal.classList.contains('show')) {
                const detailEl = modal.querySelector('[data-role="map-detail"]');
                if (detailEl) {
                    setTimeout(initializeModalContent, 150);
                }
            }
        });
    } else {
        // Fallback для нативного JavaScript, если jQuery недоступен
        document.addEventListener('pjax:success', function(event) {
            const pjaxContainer = document.getElementById('maps-v2-cards-pjax');
            if (pjaxContainer && event.target === pjaxContainer) {
                reinitializeAfterPjax();
            }
        });

        document.addEventListener('pjax:complete', function(event) {
            const pjaxContainer = document.getElementById('maps-v2-cards-pjax');
            if (pjaxContainer && event.target === pjaxContainer) {
                reinitializeAfterPjax();
            }
        });
    }

    // Initial render
    console.log('maps-v2.js: Initial render, updating cards');
    updateCards();
    console.log('maps-v2.js: Initialization complete');

    function safeParseJSON(value, fallback) {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
    }
})(); 

