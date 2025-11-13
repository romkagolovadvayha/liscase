(function () {
    const root = document.getElementById('maps-v2-root');
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

    if (!listEl || !detailEl) {
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

    function getCurrentMap() {
        return maps[currentIndex] || null;
    }

    function calculateMaxVotes() {
        return maps.reduce((max, map) => Math.max(max, map.voteCount || 0), 0);
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

    function updateCards() {
        const maxVotes = calculateMaxVotes();
        listEl.querySelectorAll('.maps-v2__card').forEach((cardEl) => {
            const mapId = parseInt(cardEl.dataset.mapId, 10);
            const map = mapIndex.has(mapId) ? maps[mapIndex.get(mapId)] : null;
            if (!map) {
                return;
            }

            cardEl.classList.toggle('is-active', mapId === getCurrentMap()?.id);

            const votesStrong = cardEl.querySelector('.maps-v2__card-votes strong');
            if (votesStrong) {
                votesStrong.textContent = map.voteCount || 0;
            }

            const barEl = cardEl.querySelector('.maps-v2__card-progress-bar');
            if (barEl) {
                const progress = maxVotes > 0 ? (map.voteCount || 0) / maxVotes * 100 : 0;
                barEl.style.setProperty('--progress', `${progress}`);
            }

            const votersContainer = cardEl.querySelector('.maps-v2__card-voters');
            if (votersContainer) {
                renderCardVoters(votersContainer, map);
            }
        });
    }

    function renderCardVoters(container, map) {
        container.innerHTML = '';
        const voters = map.voters || [];
        if (!voters.length) {
            container.classList.add('maps-v2__card-voters--empty');
            container.textContent = texts.noVotes;
            return;
        }

        container.classList.remove('maps-v2__card-voters--empty');
        voters.slice(0, 5).forEach((voter) => {
            const img = document.createElement('img');
            img.src = voter.avatar;
            img.alt = voter.username;
            img.title = voter.username;
            container.appendChild(img);
        });
        if (voters.length > 5) {
            const span = document.createElement('span');
            span.className = 'maps-v2__card-more';
            span.textContent = `+${voters.length - 5}`;
            container.appendChild(span);
        }
    }

    function renderDetail(map) {
        if (!map) {
            return;
        }
        const preview = detailEl.querySelector('[data-role="preview"]');
        if (preview) {
            preview.src = map.imagePreview || map.image || preview.src;
            preview.alt = map.hash || '';
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

        const downloadBtn = detailEl.querySelector('.maps-v2__download-button');
        if (downloadBtn) {
            if (map.downloadUrl) {
                downloadBtn.href = map.downloadUrl;
                downloadBtn.classList.remove('is-hidden');
            } else {
                downloadBtn.classList.add('is-hidden');
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
        renderDetailVoters(map);
    }

    function renderBiomes(map) {
        const biomesWrapper = detailEl.querySelector('[data-role="biomes"]');
        const biomesList = detailEl.querySelector('[data-role="biomes-list"]');
        if (!biomesWrapper || !biomesList) {
            return;
        }

        biomesList.innerHTML = '';
        const biomes = map.biomePercentages || {};
        const biomeLabels = {
            s: 'Snow',
            d: 'Desert',
            f: 'Forest',
            t: 'Tundra',
            j: 'Jungle',
            arctic: 'Arctic',
        };

        const entries = Object.entries(biomes);
        if (!entries.length) {
            biomesWrapper.classList.add('is-hidden');
            return;
        }
        biomesWrapper.classList.remove('is-hidden');

        entries.forEach(([code, value]) => {
            const item = document.createElement('div');
            item.className = 'maps-v2__biome';
            const label = document.createElement('span');
            label.className = 'maps-v2__biome-label';
            label.textContent = biomeLabels[code] || code.toUpperCase();
            const percent = document.createElement('span');
            percent.className = 'maps-v2__biome-value';
            percent.textContent = `${Math.floor(value * 10) / 10}%`;
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
        monuments.slice(0, 40).forEach((monument) => {
            const chip = document.createElement('span');
            chip.className = 'maps-v2__monument-chip';
            chip.textContent = monument.type || '';
            chip.title = monument.type || '';
            monumentsList.appendChild(chip);
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
            p.className = 'maps-v2__voters-empty';
            p.textContent = texts.emptyVoters;
            list.appendChild(p);
            return;
        }

        voters.forEach((voter) => {
            const item = document.createElement('div');
            item.className = 'maps-v2__voter';

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

    listEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="select-map"]');
        if (button) {
            const card = button.closest('[data-map-id]');
            if (card) {
                selectMapById(parseInt(card.dataset.mapId, 10));
            }
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
        } else if (action === 'refresh-voters') {
            const currentMap = getCurrentMap();
            if (currentMap) {
                refreshVoters(currentMap.id, true);
            }
        }
    });

    // Initial render
    const initialMapId = getCurrentMap()?.id || (maps[0] && maps[0].id);
    if (initialMapId) {
        selectMapById(initialMapId);
    } else {
        updateCards();
    }
    updateCards();
})(); 

