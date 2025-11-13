(function () {
    const root = document.getElementById('mapsV2Root');
    if (!root) {
        return;
    }

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
    const detailEl = root.querySelector('[data-role="map-detail"]');
    const modalEl = root.querySelector('[data-role="detail-modal"]');
    const modalDialog = modalEl ? modalEl.querySelector('.mapsV2__modal-dialog') : null;

    if (!listEl || !detailEl || !modalEl || !modalDialog) {
        return;
    }

    let userVotedId = parseInt(root.dataset.userVotedId, 10);
    if (Number.isNaN(userVotedId)) {
        userVotedId = null;
    }

    let currentIndex = 0;
    if (userVotedId !== null && mapIndex.has(userVotedId)) {
        currentIndex = mapIndex.get(userVotedId);
    }

    const csrfToken = getCsrfToken();
    let lastFocusedElement = null;
    let lightboxEl;

    function getCurrentMap() {
        return maps[currentIndex] || null;
    }

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

    function updateCards() {
        const maxVotes = calculateMaxVotes();
        listEl.querySelectorAll('.mapsV2__card').forEach((cardEl) => {
            const mapId = parseInt(cardEl.dataset.mapId, 10);
            const map = mapIndex.has(mapId) ? maps[mapIndex.get(mapId)] : null;
            if (!map) {
                return;
            }

            cardEl.classList.toggle('is-active', mapId === getCurrentMap()?.id);

            const votesBadge = cardEl.querySelector('[data-role="card-votes"]');
            if (votesBadge) {
                votesBadge.textContent = map.voteCount || 0;
            }

            const votesTotal = cardEl.querySelector('[data-role="card-votes-total"]');
            if (votesTotal) {
                votesTotal.textContent = map.voteCount || 0;
            }

            const progressBar = cardEl.querySelector('.mapsV2__card-progress-bar');
            if (progressBar) {
                const progress = maxVotes > 0 ? (map.voteCount || 0) / maxVotes * 100 : 0;
                progressBar.style.setProperty('--progress', `${progress}`);
            }

            const votersContainer = cardEl.querySelector('[data-role="card-voters"]');
            if (votersContainer) {
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

    function renderDetail(map) {
        if (!map) {
            return;
        }
        const previewContainer = detailEl.querySelector('[data-role="preview"]');
        const previewImage = detailEl.querySelector('[data-role="preview-image"]');
        const fullImageSrc = map.rawImageUrl || map.image || map.imagePreview || '';
        if (previewImage) {
            previewImage.src = map.imagePreview || map.image || previewImage.src;
            previewImage.alt = map.hash || '';
        }
        if (previewContainer) {
            previewContainer.dataset.src = fullImageSrc;
            if (fullImageSrc) {
                previewContainer.classList.add('is-clickable');
            } else {
                previewContainer.classList.remove('is-clickable');
            }
        }

        const typeEl = detailEl.querySelector('[data-role="detail-type"]');
        if (typeEl) {
            typeEl.textContent = map.type || 'Procedural';
        }

        const titleEl = detailEl.querySelector('[data-role="detail-title"]');
        if (titleEl) {
            titleEl.textContent = map.hash || '';
        }

        const voteBtn = detailEl.querySelector('[data-action="vote"]');
        if (voteBtn) {
            voteBtn.dataset.mapId = map.id;
            if (userVotedId === map.id) {
                voteBtn.classList.add('is-active');
            } else {
                voteBtn.classList.remove('is-active');
            }
        }

        const downloadBtn = detailEl.querySelector('.mapsV2__download-button');
        if (downloadBtn) {
            if (map.downloadUrl) {
                downloadBtn.href = map.downloadUrl;
                downloadBtn.classList.remove('is-hidden');
            } else {
                downloadBtn.classList.add('is-hidden');
            }
        }

        const fullscreenBtn = detailEl.querySelector('[data-action="open-fullscreen"]');
        if (fullscreenBtn) {
            if (fullImageSrc) {
                fullscreenBtn.dataset.src = fullImageSrc;
                fullscreenBtn.classList.remove('is-hidden');
            } else {
                fullscreenBtn.dataset.src = '';
                fullscreenBtn.classList.add('is-hidden');
            }
        }

        const statSize = detailEl.querySelector('[data-stat="size"]');
        if (statSize) {
            statSize.textContent = map.size ? `${map.size} x ${map.size}` : '–';
        }

        const statSeed = detailEl.querySelector('[data-stat="seed"]');
        if (statSeed) {
            statSeed.textContent = map.seed || '–';
        }

        const statSaveVersion = detailEl.querySelector('[data-stat="saveVersion"]');
        if (statSaveVersion) {
            statSaveVersion.textContent = map.saveVersion || '–';
        }

        const votesCountEl = detailEl.querySelector('[data-role="votes-count"]');
        if (votesCountEl) {
            votesCountEl.textContent = map.voteCount || 0;
        }

        const statLand = detailEl.querySelector('[data-stat="landPercentage"]');
        if (statLand) {
            statLand.textContent = map.landPercentage != null ? `${map.landPercentage}%` : '–';
        }

        const statMonuments = detailEl.querySelector('[data-stat="monuments"]');
        if (statMonuments) {
            const total = map.totalMonuments != null ? map.totalMonuments : (map.monuments ? map.monuments.length : 0);
            statMonuments.textContent = total;
        }

        renderBiomes(map);
        renderMonuments(map);
        renderMarkers(map);
        renderDetailVoters(map);
        highlightMonument(null);
    }

    function renderBiomes(map) {
        const biomesWrapper = detailEl.querySelector('[data-role="biomes"]');
        const biomesList = detailEl.querySelector('[data-role="biomes-list"]');
        if (!biomesWrapper || !biomesList) {
            return;
        }

        biomesList.innerHTML = '';
        const biomes = map.biomePercentages || {};

        const entries = Object.entries(biomes);
        if (!entries.length) {
            biomesWrapper.classList.add('is-hidden');
            return;
        }
        biomesWrapper.classList.remove('is-hidden');

        entries.forEach(([code, value]) => {
            const item = document.createElement('div');
            item.className = 'mapsV2__biome';
            const label = document.createElement('span');
            label.className = 'mapsV2__biome-label';
            label.textContent = biomeLabelsMap[code] || code.toUpperCase();
            const percent = document.createElement('span');
            percent.className = 'mapsV2__biome-value';
            const displayValue = typeof value === 'number' ? Math.round(value * 10) / 10 : value;
            percent.textContent = `${displayValue}%`;
            item.appendChild(label);
            item.appendChild(percent);
            biomesList.appendChild(item);
        });
    }

    function renderMonuments(map) {
        const monumentsWrapper = detailEl.querySelector('[data-role="monuments"]');
        const monumentsList = detailEl.querySelector('[data-role="monuments-list"]');
        if (!monumentsWrapper || !monumentsList) {
            return;
        }
        monumentsList.innerHTML = '';
        const monuments = map.monuments || [];
        if (!monuments.length) {
            monumentsWrapper.classList.add('is-hidden');
            return;
        }
        monumentsWrapper.classList.remove('is-hidden');
        monuments.slice(0, 40).forEach((monument, index) => {
            const chip = document.createElement('span');
            chip.className = 'mapsV2__monument-chip';
            chip.dataset.index = String(index);
            chip.textContent = monument.label || monument.type || '';
            chip.title = monument.label || monument.type || '';
            chip.addEventListener('mouseenter', () => highlightMonument(index));
            chip.addEventListener('mouseleave', () => highlightMonument(null));
            monumentsList.appendChild(chip);
        });
    }

    function renderMarkers(map) {
        const markersContainer = detailEl.querySelector('[data-role="markers"]');
        if (!markersContainer) {
            return;
        }
        markersContainer.innerHTML = '';
        const monuments = map.monuments || [];
        const size = map.size || 0;
        if (!monuments.length || size <= 0) {
            return;
        }

        const halfSize = size / 2;
        monuments.forEach((monument, index) => {
            const coordinates = monument.coordinates || {};
            if (typeof coordinates.x !== 'number' || typeof coordinates.y !== 'number') {
                return;
            }

            const posX = ((coordinates.x + halfSize) / size) * 100;
            const posY = 100 - ((coordinates.y + halfSize) / size) * 100;
            if (Number.isNaN(posX) || Number.isNaN(posY)) {
                return;
            }

            const marker = document.createElement('div');
            marker.className = 'mapsV2__marker';
            marker.style.left = `${Math.min(100, Math.max(0, posX))}%`;
            marker.style.top = `${Math.min(100, Math.max(0, posY))}%`;
            marker.dataset.index = String(index);
            marker.title = monument.label || monument.type || '';
            marker.addEventListener('mouseenter', () => highlightMonument(index));
            marker.addEventListener('mouseleave', () => highlightMonument(null));
            marker.addEventListener('click', (event) => event.stopPropagation());
            markersContainer.appendChild(marker);
        });
    }

    function highlightMonument(index) {
        const chips = detailEl.querySelectorAll('[data-role="monuments-list"] .mapsV2__monument-chip');
        chips.forEach((chip) => {
            const chipIndex = Number(chip.dataset.index);
            chip.classList.toggle('is-active', index !== null && chipIndex === index);
        });

        const markers = detailEl.querySelectorAll('[data-role="markers"] .mapsV2__marker');
        markers.forEach((marker) => {
            const markerIndex = Number(marker.dataset.index);
            marker.classList.toggle('is-active', index !== null && markerIndex === index);
        });
    }

    function renderDetailVoters(map) {
        const list = detailEl.querySelector('[data-role="voters-list"]');
        if (!list) {
            return;
        }
        list.innerHTML = '';
        const voters = map.voters || [];
        if (!voters.length) {
            const p = document.createElement('p');
            p.className = 'mapsV2__voters-empty';
            p.textContent = texts.emptyVoters;
            list.appendChild(p);
            return;
        }

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

    function selectMapById(mapId) {
        if (!mapIndex.has(mapId)) {
            return;
        }
        currentIndex = mapIndex.get(mapId);
        renderDetail(getCurrentMap());
        updateCards();
        scrollActiveCardIntoView(mapId);
        if (modalDialog) {
            modalDialog.scrollTop = 0;
        }
    }

    function scrollActiveCardIntoView(mapId) {
        const card = listEl.querySelector(`[data-map-id="${mapId}"]`);
        if (card && typeof card.scrollIntoView === 'function') {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function handleNavigation(direction) {
        if (!maps.length) {
            return;
        }
        currentIndex = (currentIndex + direction + maps.length) % maps.length;
        renderDetail(getCurrentMap());
        updateCards();
        const currentMap = getCurrentMap();
        if (currentMap) {
            scrollActiveCardIntoView(currentMap.id);
        }
    }

    function handleVote(mapId) {
        const url = voteUrlTemplate.replace('ID_PLACEHOLDER', String(mapId));
        const body = new URLSearchParams();
        body.append('server_id', String(serverId));
        if (csrfToken) {
            body.append(YiiCsrfParam(), csrfToken);
        }

        setVoteLoading(true);

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Vote failed');
                }

                const map = mapIndex.has(data.map_id) ? maps[mapIndex.get(data.map_id)] : null;
                if (map) {
                    map.voteCount = data.votes;
                }
                if (data.previous_map_id && mapIndex.has(data.previous_map_id) && data.previous_votes !== null) {
                    maps[mapIndex.get(data.previous_map_id)].voteCount = data.previous_votes;
                }

                userVotedId = data.voted_map_id;
                root.dataset.userVotedId = String(userVotedId);

                renderDetail(getCurrentMap());
                updateCards();

                Promise.all([
                    refreshVoters(data.map_id, true),
                    data.previous_map_id ? refreshVoters(data.previous_map_id, false) : Promise.resolve(),
                ]).catch((error) => console.error('Failed to refresh voters', error));
            })
            .catch((error) => {
                console.error(error);
                alert(error.message || 'Не удалось проголосовать. Повторите позже.');
            })
            .finally(() => setVoteLoading(false));
    }

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
                map.voters = data.users || [];
                map.voteCount = data.total || map.voteCount;
                if (updateDetail && getCurrentMap()?.id === mapId) {
                    renderDetail(map);
                }
                updateCards();
            })
            .catch((error) => {
                console.error(error);
            });
    }

    function setVoteLoading(isLoading) {
        const voteBtn = detailEl.querySelector('[data-action="vote"]');
        if (!voteBtn) {
            return;
        }
        if (isLoading) {
            voteBtn.classList.add('is-loading');
            voteBtn.disabled = true;
        } else {
            voteBtn.classList.remove('is-loading');
            voteBtn.disabled = false;
        }
    }

    function YiiCsrfParam() {
        if (window.yii && typeof window.yii.getCsrfParam === 'function') {
            return window.yii.getCsrfParam();
        }
        return '_csrf-frontend';
    }

    function getCsrfToken() {
        if (window.yii && typeof window.yii.getCsrfToken === 'function') {
            return window.yii.getCsrfToken();
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function isModalOpen() {
        return modalEl.classList.contains('is-active');
    }

    function openModal() {
        if (isModalOpen()) {
            return;
        }
        modalEl.classList.add('is-active');
        modalEl.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('mapsV2__modal-open');
        const closeButton = modalEl.querySelector('[data-action="close-modal"]');
        if (closeButton) {
            closeButton.focus({ preventScroll: true });
        }
    }

    function closeModal() {
        if (!isModalOpen()) {
            return;
        }
        modalEl.classList.remove('is-active');
        modalEl.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('mapsV2__modal-open');
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus({ preventScroll: true });
        }
    }

    function isLightboxOpen() {
        return document.documentElement.classList.contains('mapsV2__lightbox-open');
    }

    listEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="open-detail"]');
        if (!button) {
            return;
        }
        event.preventDefault();
        const card = button.closest('[data-map-id]');
        if (card) {
            const mapId = parseInt(card.dataset.mapId, 10);
            lastFocusedElement = button;
            selectMapById(mapId);
            openModal();
        }
    });

    modalEl.addEventListener('click', (event) => {
        const actionElement = event.target.closest('[data-action="close-modal"]');
        if (actionElement) {
            event.preventDefault();
            closeModal();
        }
    });

    detailEl.addEventListener('click', (event) => {
        const actionBtn = event.target.closest('[data-action]');
        if (!actionBtn) {
            return;
        }
        const action = actionBtn.dataset.action;
        if (action === 'prev-map') {
            handleNavigation(-1);
        } else if (action === 'next-map') {
            handleNavigation(1);
        } else if (action === 'vote') {
            const currentMap = getCurrentMap();
            if (currentMap) {
                handleVote(currentMap.id);
            }
        } else if (action === 'open-fullscreen') {
            const src = actionBtn.dataset.src;
            if (src) {
                openLightbox(src);
            }
        } else if (action === 'refresh-voters') {
            const currentMap = getCurrentMap();
            if (currentMap) {
                refreshVoters(currentMap.id, true);
            }
        } else if (action === 'close-modal') {
            closeModal();
        }
    });

    detailEl.addEventListener('click', (event) => {
        const preview = event.target.closest('[data-role="preview"]');
        if (preview && !event.target.closest('.mapsV2__marker')) {
            const src = preview.dataset.src;
            if (src) {
                openLightbox(src);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (isLightboxOpen()) {
            closeLightbox();
            return;
        }
        if (isModalOpen()) {
            closeModal();
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
            if (event.target === lightboxEl || event.target.dataset.action === 'close-lightbox') {
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

    // Initial render
    const initialMap = getCurrentMap() || (maps.length > 0 ? maps[0] : null);
    if (initialMap) {
        selectMapById(initialMap.id);
    } else {
        updateCards();
    }
    updateCards();

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

