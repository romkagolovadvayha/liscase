'use client';

import React, { useState, Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { useServersData, type ServersData } from '@/hooks/useServersData';
import Link from 'next/link';
import { Skeleton, Empty, Result } from 'antd';
import AutoComplete from '@/components/design-system/AutoComplete';
import type { AutoCompleteOption } from '@/components/design-system/AutoComplete';
import { EmojiEvents as EmojiEventsIcon } from '@mui/icons-material';
import Tabs from '@/components/design-system/Tabs';
import '@/styles/servers-stats.scss';

interface TopPlayer {
  position: number;
  color: string;
  amount: number;
  steam_id: string;
  score: string | number;
  link: string;
  username: string;
  avatar: string;
  status?: boolean; // true = онлайн, false = оффлайн
}

interface TopCategory {
  label: string;
  items: TopPlayer[];
}

interface Server {
  id: number;
  tag: string;
  name: string;
  monitoring_name: string;
  status: number;
}

interface ServerStatsClientProps {
  initialData?: {
    server: Server;
    servers: Server[];
    tops: Record<string, TopCategory>;
    userTops: Record<string, { position: number }>;
    wipes: string[];
    currentWipe: string;
  };
}

// Компонент карточки топ-игрока
const TopPlayerCard = ({ player, isCurrentUser = false }: { player: TopPlayer; isCurrentUser?: boolean }) => {
  return (
    <li>
      <Link 
        href={player.link} 
        className={`the-best__list__item__wrap${!player.status ? ' the-best__list__item_offline' : ''}`}
        rel="nofollow"
      >
        <div className="the-best__list__avatar">
          <img src={player.avatar} alt={player.username} />
        </div>
        <div className={`the-best__list__item${isCurrentUser ? ' active' : ''}`}>
          <div className="the-best__list__item_header">
            <div className="the-best__list__item_header_name">{player.username}</div>
            {isCurrentUser && (
              <span className="the-best__list__item_header_label">Вы</span>
            )}
          </div>
          <div className="the-best__list__item_footer">
            {player.amount > 0 && (
              <div className="the-best__list__item_reward">
                <EmojiEventsIcon 
                  sx={{ 
                    fontSize: 16, 
                    color: player.color === 'gold' ? '#FFD700' : player.color === 'silver' ? '#C0C0C0' : '#CD7F32'
                  }} 
                />
                <span className="p3 text-text-main">{player.amount}</span>
                <span className="icons icons_16px icons_16px_coin"></span>
              </div>
            )}
            <div className="the-best__list__item_footer_score">{player.score}</div>
          </div>
        </div>
      </Link>
    </li>
  );
};

// Компонент категории топа
const TopCategoryCard = ({ 
  category, 
  topCategory, 
  userPosition 
}: { 
  category: string;
  topCategory: TopCategory;
  userPosition?: number;
}) => {
  return (
    <section className="the-best__item">
      <h3 className="text-text-main mb-20 relative z-1">{topCategory.label}</h3>
      {topCategory.items.length === 0 ? (
        <Empty 
          description="Нет данных"
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          style={{ margin: '20px 0' }}
        />
      ) : (
        <>
          <ol className="the-best__list">
            {topCategory.items.map((player) => (
              <TopPlayerCard 
                key={player.steam_id} 
                player={player}
                isCurrentUser={false}
              />
            ))}
          </ol>
          {userPosition !== undefined && userPosition > 0 && (
            <div className="mt-24 p2 text-secondary text-center flex align-center gap-x-5 justify-center">
              {userPosition <= 3 && (
                <EmojiEventsIcon 
                  sx={{ 
                    fontSize: 16, 
                    color: userPosition === 1 ? '#FFD700' : userPosition === 2 ? '#C0C0C0' : '#CD7F32'
                  }} 
                />
              )}
              Вы на {userPosition} месте
            </div>
          )}
        </>
      )}
    </section>
  );
};

function ServerStatsContent({ initialData }: ServerStatsClientProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(initialData);
  
  // Проверка на наличие данных
  if (!initialData || !initialData.server || !initialData.tops) {
    // Если данных нет, показываем skeleton (данные должны загружаться через API)
    return (
      <div className="server-stats">
        <div className="container">
          <Skeleton active paragraph={{ rows: 4 }} />
        </div>
      </div>
    );
  }

  const handleServerChange = async (tag: string) => {
    if (!data || !data.server) return;
    if (tag === data.server.tag) {
      return; // Уже на этом сервере
    }

    const changeStartTime = Date.now();
    console.log(`[ServerStatsClient] Changing server to: ${tag}`);
    setLoading(true);
    
    // Очищаем поиск при переключении сервера
    setSearchValue('');
    setSearchOptions([]);

    try {
      // Обновляем URL без полной перезагрузки
      const routerStart = Date.now();
      router.push(`/servers/${tag}`, { scroll: false });
      console.log(`[ServerStatsClient] router.push took ${Date.now() - routerStart}ms`);

      // Загружаем данные для нового сервера
      const fetchStart = Date.now();
      const response = await fetch(`/api/servers/${tag}/stats`);
      console.log(`[ServerStatsClient] fetch took ${Date.now() - fetchStart}ms, status: ${response.status}`);
      
      const parseStart = Date.now();
      const result = await response.json();
      console.log(`[ServerStatsClient] JSON parse took ${Date.now() - parseStart}ms`);

      const stateUpdateStart = Date.now();
      if (result.success && result.data) {
        setData(result.data);
        console.log(`[ServerStatsClient] State update took ${Date.now() - stateUpdateStart}ms`);
      } else {
        console.error(`[ServerStatsClient] Failed to load server stats, total time: ${Date.now() - changeStartTime}ms:`, result);
        // В случае ошибки оставляем старые данные
      }
    } catch (error) {
      console.error(`[ServerStatsClient] Error loading server stats, total time: ${Date.now() - changeStartTime}ms:`, error);
      // В случае ошибки оставляем старые данные
    } finally {
      setLoading(false);
      console.log(`[ServerStatsClient] Server change completed, total time: ${Date.now() - changeStartTime}ms`);
    }
  };

  if (!data || !data.server || !data.tops) {
    return (
      <div className="server-stats">
        <div className="container">
          <Result
            status="500"
            title="Ошибка загрузки"
            subTitle="Не удалось загрузить данные о сервере"
          />
        </div>
      </div>
    );
  }

  const categoryKeys = Object.keys(data.tops || {});
  const [searchOptions, setSearchOptions] = useState<AutoCompleteOption[]>([]);
  const [searchValue, setSearchValue] = useState('');

  // Поиск пользователей
  const handleSearch = async (value: string) => {
    setSearchValue(value);
    if (!value || value.length < 1) {
      setSearchOptions([]);
      return;
    }

    try {
      const response = await fetch(`/api/stats/search?q=${encodeURIComponent(value)}&serverId=${data.server.id}`);
      const result = await response.json();
      
      if (result.success && result.items) {
        const options: AutoCompleteOption[] = result.items.map((item: any) => ({
          value: item.steam_id,
          label: (
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <div style={{ position: 'relative' }}>
                <img 
                  src={item.avatar} 
                  alt={item.name}
                  style={{
                    width: 24,
                    height: 24,
                    borderRadius: '50%',
                    border: '2px solid var(--icon-main)',
                    objectFit: 'cover'
                  }}
                />
                {item.status && (
                  <div style={{
                    position: 'absolute',
                    bottom: -2,
                    right: -2,
                    width: 10,
                    height: 10,
                    background: '#22c55e',
                    border: '2px solid var(--background-secondary)',
                    borderRadius: '50%',
                    zIndex: 2
                  }}></div>
                )}
              </div>
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <span>{item.name}</span>
                <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Steam ID: {item.steam_id}</span>
              </div>
            </div>
          ),
          avatar: item.avatar,
          username: item.name,
          steam_id: item.steam_id,
          status: item.status,
          statsLink: item.statsLink,
        }));
        setSearchOptions(options);
      } else {
        setSearchOptions([]);
      }
    } catch (error) {
      console.error('Error searching users:', error);
      setSearchOptions([]);
    }
  };

  const handleSearchSelect = (value: string, option: AutoCompleteOption) => {
    if (option?.steam_id) {
      router.push(`/profile/${option.steam_id}`);
    } else if (option?.statsLink) {
      router.push(option.statsLink);
    }
    setSearchValue('');
    setSearchOptions([]);
  };

  // Преобразуем серверы в формат для Tabs компонента
  const serverTabs = (data.servers || []).map((server) => ({
    id: server.tag,
    label: server.monitoring_name || server.name,
  }));

  const handleTabChange = (tabId: string) => {
    console.log('[ServerStatsClient] Tab changed to:', tabId, 'current:', data.server.tag);
    if (tabId !== data.server.tag) {
      handleServerChange(tabId);
    }
  };

  return (
    <div className="server-stats">
      <div className="container">
        {/* Навигация по серверам */}
        <section className="profile-section">
          <Tabs
            tabs={serverTabs}
            activeTab={data.server.tag}
            onChange={handleTabChange}
          />
        </section>

        {/* Контролы: поиск */}
        <div className="server-stats__controls">
          <div className="server-stats__controls__search">
            <AutoComplete
              value={searchValue}
              placeholder="Введите ник или Steam ID..."
              options={searchOptions}
              onSearch={handleSearch}
              onSelect={handleSearchSelect}
              disabled={loading}
              showIcon={true}
            />
          </div>
        </div>

        {/* Контент с загрузкой */}
        {loading ? (
          <div className="page-stats__the-best">
            {Array.from({ length: 8 }).map((_, index) => (
              <section key={index} className="the-best__item">
                <Skeleton 
                  active 
                  title={{ width: '60%', style: { marginBottom: 20 } }}
                  paragraph={{ rows: 3 }}
                />
              </section>
            ))}
          </div>
        ) : (
          /* Топы по категориям - как в старой версии */
          <div className="page-stats__the-best">
            {categoryKeys.map((key) => (
              <TopCategoryCard
                key={key}
                category={key}
                topCategory={data.tops[key]}
                userPosition={data.userTops?.[key]?.position}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default function ServerStatsClient({ initialData }: ServerStatsClientProps) {
  return (
    <Suspense fallback={
      <div className="server-stats">
        <div className="container">
          <Skeleton active paragraph={{ rows: 4 }} />
        </div>
      </div>
    }>
      <ServerStatsContent initialData={initialData} />
    </Suspense>
  );
}

