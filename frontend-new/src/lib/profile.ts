import { query } from '@/lib/db';
import type { PlayerProfileData, Award } from '@/types/profile';

// Экспортируем для обратной совместимости
export type { PlayerProfileData, Award } from '@/types/profile';

export async function getPlayerProfileData(steamId: string): Promise<PlayerProfileData | null> {
  try {
    if (!steamId) {
      console.error('[getPlayerProfileData] Steam ID is empty');
      return null;
    }

    console.log(`[getPlayerProfileData] Fetching profile for steamId: ${steamId}`);

    // Получаем данные пользователя (без условия is_stats, чтобы находить всех пользователей)
    const users = await query<any>(`
      SELECT 
        u.id,
        u.username,
        u.steam_id,
        u.status,
        u.last_visit_server_at,
        u.server_id,
        up.avatar,
        up.youtube_link,
        up.twitch_link,
        up.vk_link,
        up.telegram_link
      FROM user u
      LEFT JOIN user_profile up ON u.id = up.user_id
      WHERE u.steam_id = ?
      LIMIT 1
    `, [steamId]);

    let user = users && users.length > 0 ? users[0] : null;

    console.log(`[getPlayerProfileData] User query result:`, user ? { id: user.id, username: user.username, server_id: user.server_id } : 'not found');
    console.log(`[getPlayerProfileData] Users array length:`, users?.length || 0);

    // Если пользователь не найден в таблице user, ищем его в statistics
    if (!user) {
      console.log(`[getPlayerProfileData] User not found in user table, checking statistics table for steamId: ${steamId}`);
      
      const [statUser] = await query<any>(`
        SELECT DISTINCT
          steam_id,
          server_tag
        FROM statistics
        WHERE steam_id = ?
        LIMIT 1
      `, [steamId]);

      if (statUser) {
        console.log(`[getPlayerProfileData] Found user in statistics table:`, statUser);
        
        // Создаем минимальный объект пользователя на основе данных из statistics
        user = {
          id: 0,
          username: `Player_${steamId.slice(-8)}`, // Используем последние 8 цифр steam_id как имя
          steam_id: statUser.steam_id,
          status: false,
          last_visit_server_at: null,
          server_id: null,
          avatar: null,
        };

        // Пытаемся найти сервер по server_tag
        if (statUser.server_tag) {
          const [serverData] = await query<any>(`
            SELECT id
            FROM servers
            WHERE tag = ? AND status IN (1, 2, 0)
            LIMIT 1
          `, [statUser.server_tag]);
          
          if (serverData) {
            user.server_id = serverData.id;
          }
        }
      } else {
        console.error(`[getPlayerProfileData] User not found in user or statistics table for steamId: ${steamId}`);
        return null;
      }
    }

    // Получаем информацию о сервере (если есть server_id)
    let server: any = null;
    if (user.server_id) {
      const [serverData] = await query<any>(`
        SELECT 
          id,
          tag,
          name,
          monitoring_name,
          wipe,
          next_wipe
        FROM servers
        WHERE id = ? AND status IN (1, 2, 0)
        LIMIT 1
      `, [user.server_id]);
      server = serverData || null;
    }

    // Определяем текущий вайп (только если есть сервер)
    let currentWipe: string | null = null;
    if (server) {
      if (server.wipe && server.next_wipe) {
        const wipeDate = new Date(server.wipe + ' UTC');
        const nextWipeDate = new Date(server.next_wipe + ' UTC');
        const formatDate = (date: Date) => {
          const year = date.getUTCFullYear();
          const month = String(date.getUTCMonth() + 1).padStart(2, '0');
          const day = String(date.getUTCDate()).padStart(2, '0');
          return `${year}-${month}-${day}`;
        };
        currentWipe = `${formatDate(wipeDate)}/${formatDate(nextWipeDate)}`;
      }

      if (!currentWipe && server.tag) {
        const [lastWipe] = await query<any>(`
          SELECT DISTINCT wipe
          FROM statistics
          WHERE server_tag = ?
          ORDER BY id DESC
          LIMIT 1
        `, [server.tag]);
        
        if (lastWipe) {
          currentWipe = lastWipe.wipe;
        }
      }
    }

    // Если нет сервера или вайпа, возвращаем данные пользователя с пустой статистикой
    if (!server || !currentWipe) {
      // Форматируем аватар
      const defaultAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';
      const cdnUrl = process.env.CDN_URL || '';
      let avatar = '';
      if (user.avatar && user.avatar.trim()) {
        const avatarPath = user.avatar.trim();
        if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
          avatar = avatarPath;
        } else {
          const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
          avatar = `${baseUrl}${avatarPath.startsWith('/') ? avatarPath : '/' + avatarPath}`;
        }
      }
      if (!avatar) {
        const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
        avatar = `${baseUrl}${defaultAvatar}`;
      }

      // Определяем статус онлайн (за последние 10 минут)
      const now = Math.floor(Date.now() / 1000);
      const tenMinutesAgo = now - (10 * 60);
      const lastVisit = user.last_visit_server_at ? new Date(user.last_visit_server_at).getTime() / 1000 : 0;
      const isOnline = lastVisit >= tenMinutesAgo;

      // Получаем награды даже если нет сервера
      let awards: Award[] = [];
      let awardsStats = { completed: 0, total: 0 };
      try {
        const tasksV2 = await query<any>(`
          SELECT 
            t.id,
            t.title,
            t.image_path,
            t.sort
          FROM tasks_v2 t
          WHERE t.is_active = 1
          ORDER BY t.sort ASC
        `);

        const userCompletions = user.id > 0 ? await query<any>(`
          SELECT 
            task_id,
            count_completed
          FROM task_v2_user_completion
          WHERE user_id = ? AND count_completed > 0
        `, [user.id]) : [];

        const completionsMap = new Map(userCompletions.map((c: any) => [c.task_id, c.count_completed]));
        const defaultAwardImage = '/images/awards/default.png';

        awards = tasksV2.map((task: any) => {
          const completed = completionsMap.has(task.id);
          let image = defaultAwardImage;
          
          if (task.image_path) {
            const imagePath = task.image_path.trim();
            if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
              image = imagePath;
            } else {
              const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
              image = imagePath.startsWith('/') 
                ? `${baseUrl}${imagePath}`
                : `${baseUrl}/${imagePath}`;
            }
          } else {
            const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
            image = `${baseUrl}${defaultAwardImage}`;
          }

          return {
            id: task.id,
            name: task.title,
            image,
            completed,
          };
        });

        awards.sort((a, b) => {
          if (a.completed === b.completed) return 0;
          return a.completed ? -1 : 1;
        });

        const completedCount = awards.filter(a => a.completed).length;
        awardsStats = {
          completed: completedCount,
          total: awards.length,
        };
      } catch (error: any) {
        console.warn('[getPlayerProfileData] Tasks tables not found, skipping awards:', error);
      }

      return {
        user: {
          id: user.id,
          username: user.username,
          steam_id: user.steam_id,
          avatar,
          status: isOnline,
          youtube_link: user.youtube_link || null,
          twitch_link: user.twitch_link || null,
          vk_link: user.vk_link || null,
          telegram_link: user.telegram_link || null,
        },
        server: server ? {
          id: server.id,
          tag: server.tag,
          name: server.name,
          monitoring_name: server.monitoring_name,
        } : {
          id: 0,
          tag: '',
          name: 'Не указан',
          monitoring_name: 'Не указан',
        },
        stats: {
          kills: 0,
          deaths: 0,
          kd: 0,
          scientists: 0,
          wounded: 0,
          tcs_destroyed: 0,
          nude_kills: 0,
          hits_head: 0,
          hits_neck: 0,
          hits_chest: 0,
          hits_lowerspine: 0,
          hits_lefthand: 0,
          hits_leftleg: 0,
          hits_leftfoot: 0,
          hits_righthand: 0,
          hits_rightleg: 0,
          hits_rightfoot: 0,
          'sulfur.ore': 0,
          wood: 0,
          'metal.ore': 0,
          stones: 0,
          playtime: 0,
          crate_open: 0,
          barrel: 0,
          wipes: 0,
          team_members: 0,
          referrals_count: 0,
          comments_count: 0,
          buildings_count: 0,
        },
        weapons: [],
        explosives: [],
        fishing: [],
        hunters: [],
        ferm: [],
        food: [],
        tea: [],
        pie: [],
        medical: [],
        levelCards: [],
        statsBlocks: [],
        awards,
        awardsStats,
        currentWipe: null,
      };
    }

    // Получаем статистику за текущий вайп (если есть сервер и вайп)
    const statistics = server && currentWipe ? await query<any>(`
      SELECT 
        \`key\`,
        value
      FROM statistics
      WHERE steam_id = ?
        AND server_tag = ?
        AND wipe = ?
    `, [steamId, server.tag, currentWipe]) : [];

    // Преобразуем статистику в объект
    const stats: Record<string, number> = {};
    statistics.forEach((stat: any) => {
      stats[stat.key] = Number(stat.value) || 0;
    });

    // Вычисляем K/D
    const kills = stats.kills || 0;
    const deaths = stats.deaths || 0;
    const kd = deaths > 0 ? (kills / deaths).toFixed(2) : kills > 0 ? kills.toFixed(2) : '0.00';

    // Получаем оружие из таблицы kills (если есть сервер и вайп)
    const weaponsData = server && currentWipe ? await query<any>(`
      SELECT 
        weapon,
        COUNT(*) as count
      FROM statistics_kills
      WHERE steam_id = ?
        AND server_tag = ?
        AND wipe = ?
        AND weapon IS NOT NULL
        AND weapon != ''
      GROUP BY weapon
      ORDER BY count DESC
    `, [steamId, server.tag, currentWipe]) : [];

    // Получаем изображения и названия из таблицы drop с JOIN к drop_image
    const drops = await query<any>(`
      SELECT 
        d.eng_name,
        d.name,
        di.image
      FROM \`drop\` d
      LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 3
      WHERE d.eng_name != ''
    `);

    const images: Record<string, string> = {};
    const names: Record<string, string> = {};
    const cdnUrl = process.env.CDN_URL || '';
    
    drops.forEach((drop: any) => {
      if (drop.eng_name) {
        names[drop.eng_name] = drop.name || '';
        if (drop.image) {
          const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
          const imagePath = drop.image.startsWith('/') ? drop.image : '/' + drop.image;
          images[drop.eng_name] = `${baseUrl}/uploads${imagePath}`;
        } else {
          images[drop.eng_name] = '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png';
        }
      }
    });

    // Форматируем оружие
    const weapons = weaponsData.map((item: any) => ({
      weapon: item.weapon,
      count: Number(item.count),
      image: images[item.weapon] || '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png',
      name: names[item.weapon] || 'Без названия',
    }));

    // Форматируем аватар
    const defaultAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';
    let avatar = '';
    if (user.avatar && user.avatar.trim()) {
      const avatarPath = user.avatar.trim();
      if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
        avatar = avatarPath;
      } else {
        const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
        avatar = `${baseUrl}${avatarPath.startsWith('/') ? avatarPath : '/' + avatarPath}`;
      }
    }
    if (!avatar) {
      const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
      avatar = `${baseUrl}${defaultAvatar}`;
    }

    // Определяем статус онлайн (за последние 10 минут)
    const now = Math.floor(Date.now() / 1000);
    const tenMinutesAgo = now - (10 * 60);
    const lastVisit = user.last_visit_server_at ? new Date(user.last_visit_server_at).getTime() / 1000 : 0;
    const isOnline = lastVisit >= tenMinutesAgo;

    // Получаем количество вайпов (если есть сервер)
    let wipes = 0;
    if (server && server.tag) {
      const [wipesResult] = await query<any>(`
        SELECT COUNT(DISTINCT wipe) as wipes_count
        FROM statistics
        WHERE steam_id = ? AND server_tag = ?
      `, [steamId, server.tag]);
      wipes = wipesResult?.wipes_count || 0;
    }

    // Подготовка данных для всех блоков
    const getImage = (key: string) => images[key] || '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png';
    const getName = (key: string) => names[key] || 'Без названия';
    const getParam = (key: string) => stats[key] || 0;

    // Взрывные устройства
    const explosiveItems = [
      { key: 'c4thrown', score: 1 },
      { key: 'satchelsthrown', score: 0.2 },
      { key: 'rocket_basic', score: 0.5, combined: ['rocket_basic_rpg'] },
      { key: 'rocket_hv', score: 0.1, combined: ['rocket_hv_rpg'] },
      { key: 'rocket_fire', score: 0.1, combined: ['rocket_fire_rpg'] },
      { key: 'ammo_explosive', score: 0.01 },
      { key: 'grenade.f1.deployed', score: 0.02 },
      { key: 'grenade.molotov.deployed', score: 0.05 },
      { key: 'grenade.beancan.deployed', score: 0.05 },
    ];

    const explosives = explosiveItems.map(item => {
      let count = getParam(item.key);
      if (item.combined) {
        item.combined.forEach((k: string) => {
          count += getParam(k);
        });
      }
      const cleanKey = item.key.replace('.deployed', '');
      return {
        key: item.key,
        name: getName(cleanKey),
        image: getImage(cleanKey),
        count,
        score: item.score,
      };
    }).filter(item => item.count > 0);

    // Рыбалка
    const fishItems = [
      { key: 'f_fish.anchovy', name: 'Анчоус', score: 10 },
      { key: 'f_fish.catfish', name: 'Сом', score: 32 },
      { key: 'f_fish.herring', name: 'Сельдь', score: 10 },
      { key: 'f_fish.orangeroughy', name: 'Большеголов', score: 37 },
      { key: 'f_fish.salmon', name: 'Лосось', score: 22 },
      { key: 'f_fish.sardine', name: 'Сардина', score: 10 },
      { key: 'f_fish.smallshark', name: 'Акула', score: 45 },
      { key: 'f_fish.troutsmall', name: 'Форель', score: 15 },
      { key: 'f_fish.yellowperch', name: 'Окунь', score: 25 },
    ];

    const fishing = fishItems.map(item => ({
      key: item.key,
      name: item.name,
      image: getImage(item.key),
      count: getParam(item.key),
      score: item.score,
    })).filter(item => item.count > 0).sort((a, b) => b.score - a.score);

    // Охота
    const wolf = getParam('wolf') || (getParam('wolf2') + getParam('wolf'));
    const hunters = [
      { key: 'boar', name: 'Кабаны', image: '/images/hunters/Boar.png' },
      { key: 'horse', name: 'Лошади', image: '/images/hunters/Horse.png' },
      { key: 'wolf', name: 'Волки', count: wolf, image: '/images/hunters/Wolf.png' },
      { key: 'bear', name: 'Медведи', count: getParam('bear') + getParam('polarbear'), image: '/images/hunters/bear.png' },
      { key: 'stag', name: 'Олени', image: '/images/hunters/Stag.png' },
      { key: 'chicken', name: 'Курицы', image: '/images/hunters/Chicken.png' },
      { key: 'simpleshark', name: 'Акулы', image: '/images/hunters/shark2.png' },
      { key: 'panther', name: 'Пантеры', image: '/images/hunters/panther.png' },
      { key: 'crocodile', name: 'Крокодилы', image: '/images/hunters/crocodile.png' },
      { key: 'tiger', name: 'Тигры', image: '/images/hunters/tiger.png' },
    ].map(item => ({
      ...item,
      count: item.count !== undefined ? item.count : getParam(item.key),
    })).filter(item => item.count > 0);

    // Фермерство
    const fermItems = [
      { key: 'gathered_cloth', name: 'Ткань', score: 0.05 },
      { key: 'gathered_corn', name: 'Кукуруза', score: 0.3 },
      { key: 'gathered_potato', name: 'Картофель', score: 0.4 },
      { key: 'gathered_pumpkin', name: 'Тыква', score: 0.5 },
      { key: 'gathered_blue.berry', name: 'Синие ягоды', score: 0.5 },
      { key: 'gathered_yellow.berry', name: 'Желтые ягоды', score: 0.5 },
      { key: 'gathered_red.berry', name: 'Красные ягоды', score: 0.5 },
      { key: 'gathered_white.berry', name: 'Белые ягоды', score: 0.5 },
      { key: 'gathered_green.berry', name: 'Зеленые ягоды', score: 0.5 },
      { key: 'gathered_black.berry', name: 'Черные ягоды', score: 1 },
      { key: 'gathered_orchid', name: 'Орхидея', score: 0.3 },
      { key: 'gathered_rose', name: 'Розы', score: 0.3 },
      { key: 'gathered_sunflower', name: 'Подсолнух', score: 0.3 },
      { key: 'gathered_wheat', name: 'Пшеница', score: 0.3 },
    ];

    const ferm = fermItems.map(item => ({
      key: item.key,
      name: item.name,
      image: getImage(item.key),
      count: getParam(item.key),
      score: item.score,
    })).filter(item => item.count > 0).sort((a, b) => b.count - a.count);

    // Еда (mod_ префикс)
    const foodItems: Array<{ key: string; name: string; image: string; count: number }> = [];
    Object.keys(stats).forEach(key => {
      if (key.startsWith('mod_') && !key.includes('pie') && !key.includes('tea') && !key.includes('largemedkit')) {
        const cleanKey = key.replace('mod_', '');
        foodItems.push({
          key: cleanKey,
          name: getName(cleanKey),
          image: getImage(cleanKey),
          count: getParam(key),
        });
      }
    });
    foodItems.sort((a, b) => b.count - a.count);

    // Чай (mod_ с tea)
    const teaItems: Array<{ key: string; name: string; image: string; count: number }> = [];
    Object.keys(stats).forEach(key => {
      if (key.startsWith('mod_') && key.includes('tea')) {
        const cleanKey = key.replace('mod_', '');
        teaItems.push({
          key: cleanKey,
          name: getName(cleanKey),
          image: getImage(cleanKey),
          count: getParam(key),
        });
      }
    });
    teaItems.sort((a, b) => b.count - a.count);

    // Пироги (mod_ с pie)
    const pieItems: Array<{ key: string; name: string; image: string; count: number }> = [];
    Object.keys(stats).forEach(key => {
      if (key.startsWith('mod_') && key.includes('pie')) {
        const cleanKey = key.replace('mod_', '');
        pieItems.push({
          key: cleanKey,
          name: getName(cleanKey),
          image: getImage(cleanKey),
          count: getParam(key),
        });
      }
    });
    pieItems.sort((a, b) => b.count - a.count);

    // Медицина
    const medicalItems = [
      { key: 'largemedkit', param: 'mod_largemedkit' },
      { key: 'syringe', param: 'syringe' },
      { key: 'bandage', param: 'bandage' },
    ];

    const medical = medicalItems.map(item => ({
      key: item.key,
      name: getName(item.key),
      image: getImage(item.key),
      count: getParam(item.param),
    })).filter(item => item.count > 0);

    // Карты доступа
    const levelCards = [
      { key: 'card_level_3', name: getName('card_level_3'), image: getImage('card_level_3'), count: getParam('card_level_3') },
      { key: 'card_level_2', name: getName('card_level_2'), image: getImage('card_level_2'), count: getParam('card_level_2') },
      { key: 'card_level_1', name: getName('card_level_1'), image: getImage('card_level_1'), count: getParam('card_level_1') },
    ].filter(item => item.count > 0);

    // Статистические блоки
    const statsBlocks = [
      { key: 'bone.fragments', name: 'Обломки костей', image: getImage('bone.fragments'), count: getParam('bone.fragments') },
      { key: 'fat.animal', name: 'Животный жир', image: getImage('fat.animal'), count: getParam('fat.animal') },
      { key: 'leather', name: 'Кожа', image: getImage('leather'), count: getParam('leather') },
      { key: 'parachuteseat', name: 'Прыжков с парашюта', image: getImage('parachute'), count: getParam('parachuteseat') },
      { key: 'stash', name: 'Выкопано тайников', image: getImage('small-stash'), count: getParam('stash') },
      { key: 'scrap', name: 'Скрап', image: getImage('scrap'), count: getParam('scrap') },
    ].filter(item => item.count > 0);

    // Получаем награды (tasks_v2)
    let awards: Award[] = [];
    let awardsStats = { completed: 0, total: 0 };

    try {
      // Получаем все активные задания
      const tasksV2 = await query<any>(`
        SELECT 
          t.id,
          t.title,
          t.image_path,
          t.sort
        FROM tasks_v2 t
        WHERE t.is_active = 1
        ORDER BY t.sort ASC
      `);

      // Получаем выполненные задания пользователя
      const userCompletions = user.id > 0 ? await query<any>(`
        SELECT 
          task_id,
          count_completed
        FROM task_v2_user_completion
        WHERE user_id = ? AND count_completed > 0
      `, [user.id]) : [];

      const completionsMap = new Map(userCompletions.map((c: any) => [c.task_id, c.count_completed]));

      // Формируем массив наград (все задания, отсортированные: сначала выполненные)
      const cdnUrl = process.env.CDN_URL || '';
      const defaultAwardImage = '/images/awards/default.png';

      awards = tasksV2.map((task: any) => {
        const completed = completionsMap.has(task.id);
        let image = defaultAwardImage;
        
        if (task.image_path) {
          const imagePath = task.image_path.trim();
          if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
            image = imagePath;
          } else {
            const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
            image = imagePath.startsWith('/') 
              ? `${baseUrl}${imagePath}`
              : `${baseUrl}/${imagePath}`;
          }
        } else {
          const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
          image = `${baseUrl}${defaultAwardImage}`;
        }

        return {
          id: task.id,
          name: task.title,
          image,
          completed,
        };
      });

      // Сортируем: сначала выполненные
      awards.sort((a, b) => {
        if (a.completed === b.completed) return 0;
        return a.completed ? -1 : 1;
      });

      // Подсчитываем статистику
      const completedCount = awards.filter(a => a.completed).length;
      awardsStats = {
        completed: completedCount,
        total: awards.length,
      };
    } catch (error: any) {
      // Если таблица не существует, просто пропускаем награды
      console.warn('[getPlayerProfileData] Tasks tables not found, skipping awards:', error);
    }

    // Получаем дополнительные метрики
    let teamMembers = 0;
    let referralsCount = 0;
    let commentsCount = 0;
    let buildingsCount = 0;

    try {
      // Игроков в команде (для текущего вайпа, если есть сервер)
      if (server && currentWipe && user.id > 0) {
        const teamData = await query<any>(`
          SELECT COUNT(DISTINCT t2.user_id) as count
          FROM teams t
          INNER JOIN teams t2 ON t.leader_user_id = t2.leader_user_id 
            AND t.server_id = t2.server_id 
            AND t.wipe = t2.wipe
          WHERE (t.leader_user_id = ? OR t2.user_id = ?)
            AND t.server_id = ?
            AND t.wipe = ?
        `, [user.id, user.id, server.id, currentWipe]);
        teamMembers = teamData && teamData.length > 0 ? Number(teamData[0].count) || 0 : 0;
      }

      // Приглашенных пользователей (реферальная система)
      if (user.id > 0) {
        const referralsData = await query<any>(`
          SELECT COUNT(*) as count
          FROM user_tree
          WHERE parent_user_id = ?
        `, [user.id]);
        referralsCount = referralsData && referralsData.length > 0 ? Number(referralsData[0].count) || 0 : 0;
      }

      // Комментариев пользователя
      if (user.id > 0) {
        const commentsData = await query<any>(`
          SELECT COUNT(*) as count
          FROM media_comment
          WHERE user_id = ? AND status = 1
        `, [user.id]);
        commentsCount = commentsData && commentsData.length > 0 ? Number(commentsData[0].count) || 0 : 0;
      }

      // Построек (где пользователь является резидентом)
      if (user.id > 0) {
        const buildingsData = await query<any>(`
          SELECT COUNT(DISTINCT br.building_id) as count
          FROM building_resident br
          INNER JOIN building b ON br.building_id = b.id
          WHERE br.user_id = ? AND b.status = 1
        `, [user.id]);
        buildingsCount = buildingsData && buildingsData.length > 0 ? Number(buildingsData[0].count) || 0 : 0;
      }
    } catch (error: any) {
      // Если таблицы не существуют, просто пропускаем эти метрики
      console.warn('[getPlayerProfileData] Error fetching additional metrics:', error);
    }

    // Получаем список команды
    let teamMembersList: any[] = [];
    try {
      if (server && currentWipe && user.id > 0) {
        // Сначала находим команду, где пользователь является участником
        const userTeam = await query<any>(`
          SELECT leader_user_id, user_id
          FROM teams
          WHERE user_id = ?
            AND server_id = ?
            AND wipe = ?
          LIMIT 1
        `, [user.id, server.id, currentWipe]);

        if (userTeam && userTeam.length > 0) {
          const leaderUserId = userTeam[0].leader_user_id;
          
          // Получаем всех участников команды (с тем же leader_user_id)
          const teamRows = await query<any>(`
            SELECT 
              t.leader_user_id,
              t.user_id,
              u.id,
              u.username,
              u.steam_id,
              u.last_visit_server_at,
              up.avatar,
              up.is_hide_team,
              up.is_hide_online,
              uv.id as vip_id
            FROM teams t
            INNER JOIN user u ON t.user_id = u.id
            LEFT JOIN user_profile up ON u.id = up.user_id
            LEFT JOIN user_vip uv ON u.id = uv.user_id AND uv.expires_at > NOW()
            WHERE t.leader_user_id = ?
              AND t.server_id = ?
              AND t.wipe = ?
            ORDER BY t.user_id = t.leader_user_id DESC, u.username ASC
          `, [leaderUserId, server.id, currentWipe]);

          const cdnUrl = process.env.CDN_URL || '';
          const defaultAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';

          // Сначала добавляем лидера, потом остальных участников
          const leaderRows = teamRows.filter((row: any) => row.user_id === row.leader_user_id);
          const memberRows = teamRows.filter((row: any) => row.user_id !== row.leader_user_id);
          const sortedRows = [...leaderRows, ...memberRows];

          for (const row of sortedRows) {
            // Пропускаем пользователей со скрытым списком команды (только если есть VIP)
            if (row.vip_id && row.is_hide_team) {
              continue;
            }

            // Определяем статус онлайн (за последние 10 минут)
            const now = Math.floor(Date.now() / 1000);
            const tenMinutesAgo = now - (10 * 60);
            const lastVisit = row.last_visit_server_at ? new Date(row.last_visit_server_at).getTime() / 1000 : 0;
            const isOnlineStatus = lastVisit >= tenMinutesAgo;

            // Определяем, скрыт ли статус
            const isHidden = row.is_hide_online === 1;
            const displayStatus = isHidden ? null : isOnlineStatus;

            // Форматируем аватар
            let memberAvatar = '';
            if (row.avatar && row.avatar.trim()) {
              const avatarPath = row.avatar.trim();
              if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
                memberAvatar = avatarPath;
              } else {
                const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
                memberAvatar = avatarPath.startsWith('/')
                  ? `${baseUrl}${avatarPath}`
                  : `${baseUrl}/${avatarPath}`;
              }
            }
            if (!memberAvatar) {
              const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
              memberAvatar = `${baseUrl}${defaultAvatar}`;
            }

            // Форматируем дату визита
            let dateVisitFormatted = null;
            if (row.last_visit_server_at) {
              dateVisitFormatted = new Date(row.last_visit_server_at).toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
              });
            }

            teamMembersList.push({
              id: row.id,
              username: row.username,
              steam_id: row.steam_id,
              avatar: memberAvatar,
              is_online: displayStatus,
              is_hidden: displayStatus === null,
              is_leader: row.leader_user_id === row.user_id,
              link: `/profile/${row.steam_id}`,
              date_visit: dateVisitFormatted,
              time_visit: row.last_visit_server_at ? Math.floor(new Date(row.last_visit_server_at).getTime() / 1000) : null,
            });
          }
        }
      }
    } catch (error: any) {
      console.warn('[getPlayerProfileData] Error fetching team members:', error);
    }

    // Получаем список убийств
    let killsList: any[] = [];
    try {
      if (server && currentWipe) {
        const killsData = await query<any>(`
          SELECT 
            k.id,
            k.type,
            k.steam_id,
            k.dead,
            k.weapon,
            k.distance,
            k.signs,
            k.wears,
            k.created_at
          FROM statistics_kills k
          WHERE (k.steam_id = ? OR k.dead = ?)
            AND k.server_tag = ?
            AND k.wipe = ?
            AND k.dead != ''
          ORDER BY k.created_at DESC
          LIMIT 30
        `, [steamId, steamId, server.tag, currentWipe]);

        // Получаем информацию об оружии
        const weaponNames = new Set<string>();
        killsData.forEach((kill: any) => {
          if (kill.weapon) {
            weaponNames.add(kill.weapon);
          }
        });

        const weaponImages: Record<string, string> = {};
        if (weaponNames.size > 0) {
          const weaponDrops = await query<any>(`
            SELECT 
              d.eng_name,
              d.name,
              di.image
            FROM \`drop\` d
            LEFT JOIN drop_image di ON d.id = di.drop_id AND di.type = 3
            WHERE d.eng_name IN (${Array.from(weaponNames).map(() => '?').join(',')})
          `, Array.from(weaponNames));

          weaponDrops.forEach((drop: any) => {
            const cdnUrl = process.env.CDN_URL || '';
            let image = '';
            if (drop.image) {
              const imagePath = drop.image.trim();
              if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
                image = imagePath;
              } else {
                const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
                // Путь из базы может быть с /uploads или без, нормализуем его
                let normalizedPath = imagePath;
                if (!normalizedPath.startsWith('/')) {
                  normalizedPath = `/${normalizedPath}`;
                }
                // Если путь не содержит /uploads, добавляем его
                if (!normalizedPath.includes('/uploads')) {
                  normalizedPath = `/uploads${normalizedPath}`;
                }
                image = `${baseUrl}${normalizedPath}`;
              }
            } else {
              // Дефолтное изображение, если нет картинки
              const baseUrl = cdnUrl.endsWith('/') ? cdnUrl.slice(0, -1) : cdnUrl;
              image = `${baseUrl}/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png`;
            }
            weaponImages[drop.eng_name] = image;
          });
        }

        // Получаем информацию о пользователях
        const steamIds = new Set<string>();
        killsData.forEach((kill: any) => {
          if (kill.steam_id && kill.steam_id.length === 17) {
            steamIds.add(kill.steam_id);
          }
          if (kill.dead && kill.dead.length === 17) {
            steamIds.add(kill.dead);
          }
        });

        const usersMap: Record<string, any> = {};
        if (steamIds.size > 0) {
          const users = await query<any>(`
            SELECT id, username, steam_id
            FROM user
            WHERE steam_id IN (${Array.from(steamIds).map(() => '?').join(',')})
          `, Array.from(steamIds));

          users.forEach((u: any) => {
            usersMap[u.steam_id] = u;
          });
        }

        // Формируем список убийств
        killsList = killsData.map((kill: any) => {
          const signs = kill.signs ? JSON.parse(kill.signs) : null;
          const isBot = kill.type === 'scientists' || kill.dead.startsWith('bot_');
          
          const killerUser = usersMap[kill.steam_id];
          const deadUser = usersMap[kill.dead];

          return {
            id: kill.id,
            type: kill.type,
            steam_id: kill.steam_id,
            dead: kill.dead,
            weapon: kill.weapon,
            weapon_name: kill.weapon,
            weapon_image: kill.weapon ? weaponImages[kill.weapon] || null : null,
            distance: Number(kill.distance) || 0,
            name: killerUser ? killerUser.username : null,
            link: killerUser ? `/profile/${killerUser.steam_id}` : null,
            dead_name: deadUser ? deadUser.username : null,
            dead_link: deadUser ? `/profile/${deadUser.steam_id}` : null,
            deadLink: null, // Будет сформирован на фронтенде
            signs,
            wears: kill.wears,
            bot: isBot,
            animal: null,
            animal2: null,
            created_at: kill.created_at,
          };
        });
      }
    } catch (error: any) {
      console.warn('[getPlayerProfileData] Error fetching kills:', error);
    }

    return {
      user: {
        id: user.id,
        username: user.username,
        steam_id: user.steam_id,
        avatar,
        status: isOnline,
        youtube_link: user.youtube_link || null,
        twitch_link: user.twitch_link || null,
        vk_link: user.vk_link || null,
        telegram_link: user.telegram_link || null,
      },
      server: server ? {
        id: server.id,
        tag: server.tag,
        name: server.name,
        monitoring_name: server.monitoring_name,
      } : {
        id: 0,
        tag: '',
        name: 'Не указан',
        monitoring_name: 'Не указан',
      },
      stats: {
        kills: stats.kills || 0,
        deaths: stats.deaths || 0,
        kd: parseFloat(kd),
        scientists: stats.scientists || 0,
        wounded: stats.wounded || 0,
        tcs_destroyed: stats.tcs_destroyed || 0,
        nude_kills: stats.nude_kills || 0,
        hits_head: stats.hits_head || 0,
        hits_neck: stats.hits_neck || 0,
        hits_chest: stats.hits_chest || 0,
        hits_lowerspine: stats.hits_lowerspine || 0,
        hits_lefthand: stats.hits_lefthand || 0,
        hits_leftleg: stats.hits_leftleg || 0,
        hits_leftfoot: stats.hits_leftfoot || 0,
        hits_righthand: stats.hits_righthand || 0,
        hits_rightleg: stats.hits_rightleg || 0,
        hits_rightfoot: stats.hits_rightfoot || 0,
        'sulfur.ore': stats['sulfur.ore'] || 0,
        wood: stats.wood || 0,
        'metal.ore': stats['metal.ore'] || 0,
        stones: stats.stones || 0,
        playtime: stats.playtime || 0,
        crate_open: stats.crate_open || 0,
        barrel: stats.barrel || 0,
        wipes,
        team_members: teamMembers,
        referrals_count: referralsCount,
        comments_count: commentsCount,
        buildings_count: buildingsCount,
      },
      weapons,
      explosives,
      fishing,
      hunters,
      ferm,
      food: foodItems.slice(0, 10),
      tea: teaItems.slice(0, 10),
      pie: pieItems.slice(0, 10),
      medical,
      levelCards,
      statsBlocks,
      awards,
      awardsStats,
      currentWipe,
      teamMembers: teamMembersList,
      kills: killsList,
    };
  } catch (error: any) {
    console.error(`[getPlayerProfileData] Error for steamId ${steamId}:`, error);
    console.error('[getPlayerProfileData] Error stack:', error?.stack);
    // В случае ошибки все равно возвращаем null, чтобы показать 404
    return null;
  }
}

