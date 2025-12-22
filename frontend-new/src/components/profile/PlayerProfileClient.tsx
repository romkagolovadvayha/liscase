'use client';

import React, { useState, useEffect } from 'react';
import { Card, Avatar, Tag } from 'antd';
import { RadarChart } from '@mui/x-charts';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
  faSteam,
  faYoutube,
  faTwitch,
  faVk,
  faTelegram,
} from '@fortawesome/free-brands-svg-icons';
import Tabs from '@/components/design-system/Tabs';
import DataTable, { DataTableColumn } from '@/components/design-system/DataTable';
import Icon from '@/components/icons/Icon';
import { useProfileData } from '@/hooks/useProfileData';
import type { PlayerProfileData, KillItem } from '@/types/profile';
import '@/styles/profile-player.scss';

interface PlayerProfileClientProps {
  initialData: PlayerProfileData;
}

export default function PlayerProfileClient({ initialData }: PlayerProfileClientProps) {
  const { 
    user, 
    server, 
    stats, 
    weapons, 
    explosives, 
    fishing, 
    hunters, 
    ferm, 
    food, 
    tea, 
    pie, 
    medical, 
    levelCards, 
    statsBlocks,
    awards,
    awardsStats,
    currentWipe,
    teamMembers = [],
    kills = [],
  } = initialData;

  // Состояния для "Показать еще"
  const [expandedWeapons, setExpandedWeapons] = useState(false);
  const [expandedExplosives, setExpandedExplosives] = useState(false);
  const [expandedFood, setExpandedFood] = useState(false);
  const [expandedFerm, setExpandedFerm] = useState(false);
  const [expandedAwards, setExpandedAwards] = useState(false);
  
  // Состояние для активного таба
  const [activeTab, setActiveTab] = useState('combat');

  // Состояние для пагинации убийств
  const [killsPage, setKillsPage] = useState(1);
  const [killsSortField, setKillsSortField] = useState<'distance' | 'created_at'>('created_at');
  const [killsSortOrder, setKillsSortOrder] = useState<'asc' | 'desc'>('desc');

  // Форматируем время игры
  const formatPlayTime = (seconds: number): string => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
      return `${hours}ч ${minutes}м`;
    }
    return `${minutes}м`;
  };

  // Форматируем число
  const formatNumber = (num: number): string => {
    return num.toLocaleString('ru-RU');
  };

  // Блок категории
  const CategoryBlock = ({ 
    title, 
    items, 
    showScore = false, 
    className = '',
    visibleCount = 6,
    expanded = false,
    onExpandChange
  }: { 
    title: string; 
    items: Array<{ key?: string; name: string; image: string; count: number; score?: number }>; 
    showScore?: boolean;
    className?: string;
    visibleCount?: number;
    expanded?: boolean;
    onExpandChange?: (expanded: boolean) => void;
  }) => {
    if (!items || items.length === 0) return null;

    const visibleItems = expanded ? items : items.slice(0, visibleCount);
    const hiddenCount = items.length - visibleCount;

    return (
      <section className={`page-stats__block ${className}`}>
        <header className="flex items-center justify-space-between mb-24 transition-all">
          <h3 className="flex items-center gap-x-12">
            {title}
          </h3>
        </header>

        <div className="page-stats__categories">
          {visibleItems.map((item, index) => (
            <div key={item.key || index} className="page-stats__category category">
              <h5 className="category__count-and-img">
                <span>
                  {formatNumber(item.count)}
                  {showScore && item.score && (
                    <span className="category__x" title={`Множитель для рейтинга игроков x${item.score}`}>
                      x{item.score}
                    </span>
                  )}
                </span>
                <img src={item.image} alt={item.name} className="w-64 h-64 object-contain" />
              </h5>
              <p className="category__title">{item.name}</p>
            </div>
          ))}
        </div>

        {hiddenCount > 0 && onExpandChange && (
          <button 
            type="button" 
            className="button button-secondary w-full mt-16"
            onClick={() => onExpandChange(!expanded)}
          >
            <span>{expanded ? 'Скрыть' : `Показать еще ${hiddenCount}`}</span>
            <i className={`fas fa-chevron-down ${expanded ? 'rotated' : ''}`}></i>
          </button>
        )}
      </section>
    );
  };

  // Определяем видимые награды
  const visibleAwards = expandedAwards ? awards : awards.slice(0, 8);
  const hiddenAwardsCount = awards.length - 8;

  // Показываем/скрываем скрытые награды
  useEffect(() => {
    const hiddenItems = document.querySelectorAll('.awards-item-hidden');
    hiddenItems.forEach((item) => {
      (item as HTMLElement).style.display = expandedAwards ? '' : 'none';
    });
  }, [expandedAwards]);

  // Обновляем цвета текста в графике попаданий
  useEffect(() => {
    const chartContainer = document.querySelector('.player-profile__hits-chart');
    if (chartContainer) {
      const textElements = chartContainer.querySelectorAll('text, tspan');
      const getComputedColor = () => {
        const root = document.documentElement;
        return getComputedStyle(root).getPropertyValue('--text-secondary').trim() || '#999';
      };
      
      textElements.forEach((el) => {
        const textEl = el as SVGTextElement;
        textEl.setAttribute('fill', getComputedColor());
      });

      // Также обновляем цвета линий сетки
      const gridLines = chartContainer.querySelectorAll('.MuiRadarGrid-radial, .MuiRadarGrid-divider');
      const borderColor = () => {
        const root = document.documentElement;
        return getComputedStyle(root).getPropertyValue('--border-color-default').trim() || '#ddd';
      };
      
      gridLines.forEach((el) => {
        const lineEl = el as SVGPathElement;
        lineEl.setAttribute('stroke', borderColor());
        lineEl.setAttribute('stroke-opacity', '0.3');
      });
    }
  }, [activeTab]);

  // Табы для статистики
  const tabs = [
    { id: 'combat', label: 'Общая информация' },
    { id: 'team', label: 'Команда' },
    { id: 'kills', label: 'Убийства' },
    { id: 'resources', label: 'Ресурсы и добыча' },
    { id: 'activity', label: 'Активность' },
    { id: 'awards', label: 'Награды' },
  ];

  return (
    <div className="player-profile-page">
      <div className="player-profile__layout">
        {/* Левая колонка: Профиль и информация */}
        <aside className="player-profile__sidebar">
          <section className="page-stats__block profile">
            <div className={`profile__wrapper ${user.status ? '' : 'profile_offline'}`}>
              <div className="profile__image">
                <Avatar src={user.avatar} size={140} className="player-profile__avatar" />
              </div>
              <div className="profile__info">
                <h3 className="text-primary-colors-main flex items-center justify-center gap-x-8 profile__username">
                  {user.username}
                </h3>
                {server.name !== 'Не указан' && (
                  <p className="profile__server text-text-main">
                    <span className="text-text-secondary">Сервер:</span>{' '}
                    <span style={{ color: 'var(--primary-colors-main)' }}>{server.monitoring_name}</span>
                  </p>
                )}
                {stats.playtime > 0 ? (
                  <p className="profile__playtime text-text-main">
                    <span className="text-text-secondary">Играл за вайп:</span>{' '}
                    <span style={{ color: 'var(--online)' }}>{formatPlayTime(stats.playtime)}</span>
                  </p>
                ) : (
                  <p className="profile__playtime text-text-secondary">Не заходил на сервер</p>
                )}
              </div>
            </div>
          </section>

          {/* Соцсети */}
          {(user.youtube_link || user.twitch_link || user.vk_link || user.telegram_link || user.steam_id) && (
            <section className="page-stats__block-without-hover profile__social">
              <div className="profile__social-links">
                {user.steam_id && (
                  <a
                    href={`https://steamcommunity.com/profiles/${user.steam_id}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="profile__social-link"
                    title="Steam"
                  >
                    <FontAwesomeIcon icon={faSteam} />
                  </a>
                )}
                {user.youtube_link && (
                  <a
                    href={user.youtube_link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="profile__social-link"
                    title="YouTube"
                  >
                    <FontAwesomeIcon icon={faYoutube} />
                  </a>
                )}
                {user.twitch_link && (
                  <a
                    href={user.twitch_link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="profile__social-link"
                    title="Twitch"
                  >
                    <FontAwesomeIcon icon={faTwitch} />
                  </a>
                )}
                {user.vk_link && (
                  <a
                    href={user.vk_link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="profile__social-link"
                    title="VK"
                  >
                    <FontAwesomeIcon icon={faVk} />
                  </a>
                )}
                {user.telegram_link && (
                  <a
                    href={user.telegram_link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="profile__social-link"
                    title="Telegram"
                  >
                    <FontAwesomeIcon icon={faTelegram} />
                  </a>
                )}
              </div>
            </section>
          )}
        </aside>

        {/* Правая колонка: Табы и статистика */}
        <main className="player-profile__content">
          <Tabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} />

          {/* Боевая статистика */}
          {activeTab === 'combat' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              {/* Основная статистика и график попаданий */}
              <div className="page-stats__two-blocks">
                {/* Основная статистика */}
                <section className="page-stats__block-without-hover w-50p">
                  <header className="flex items-center justify-space-between mb-24 transition-all">
                    <h3 className="flex items-center gap-x-12">Основная статистика</h3>
                  </header>
                  <div className="user__stats stats">
                    <div className="stats__item">
                      <p>Убийств</p>
                      <p>{formatNumber(stats.kills)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Смертей</p>
                      <p>{formatNumber(stats.deaths)}</p>
                    </div>
                    <div className="stats__item">
                      <p>K/D</p>
                      <p>{stats.kd.toFixed(2)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Убил ботов</p>
                      <p>{formatNumber(stats.scientists)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Нокнут</p>
                      <p>{formatNumber(stats.wounded)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Зарейдил</p>
                      <p>{formatNumber(stats.tcs_destroyed)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Вайпов</p>
                      <p>{formatNumber(stats.wipes)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Убил голых</p>
                      <p>{formatNumber(stats.nude_kills)}</p>
                    </div>
                    <div className="stats__item">
                      <p>В команде</p>
                      <p>{formatNumber(stats.team_members)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Пригласил</p>
                      <p>{formatNumber(stats.referrals_count)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Комментариев</p>
                      <p>{formatNumber(stats.comments_count)}</p>
                    </div>
                    <div className="stats__item">
                      <p>Построек</p>
                      <p>{formatNumber(stats.buildings_count)}</p>
                    </div>
                  </div>
                </section>

                {/* График попаданий */}
                <section className="page-stats__block-without-hover page-stats__gamer-stats_wrap w-50p" style={{ height: '400px' }}>
                  <h3 className="mb-32">Статистика по попаданиям</h3>
                  <div className="player-profile__hits-chart">
                    <RadarChart
                      height={300}
                      series={[
                        {
                          data: [
                            stats.hits_head,
                            stats.hits_neck,
                            stats.hits_chest,
                            stats.hits_lefthand,
                            stats.hits_leftleg,
                            stats.hits_righthand,
                            stats.hits_rightleg,
                          ],
                        },
                      ]}
                      radar={{
                        max: Math.max(
                          stats.hits_head,
                          stats.hits_neck,
                          stats.hits_chest,
                          stats.hits_lefthand,
                          stats.hits_leftleg,
                          stats.hits_righthand,
                          stats.hits_rightleg,
                          1
                        ),
                        metrics: [
                          'Голова',
                          'Шея',
                          'Грудь',
                          'Левая рука',
                          'Левая нога',
                          'Правая рука',
                          'Правая нога',
                        ],
                      }}
                      sx={{
                        '& .MuiChartsAxis-line': {
                          stroke: 'var(--border-color-default) !important',
                        },
                        '& .MuiChartsAxis-tick': {
                          stroke: 'var(--border-color-default) !important',
                        },
                        '& .MuiChartsAxis-tickLabel': {
                          fill: 'var(--text-secondary) !important',
                          fontSize: 12,
                        },
                        '& .MuiRadarGrid-radial': {
                          stroke: 'var(--border-color-default) !important',
                          strokeOpacity: 0.3,
                        },
                        '& .MuiRadarGrid-divider': {
                          stroke: 'var(--border-color-default) !important',
                          strokeOpacity: 0.3,
                        },
                        '& .MuiRadarGrid-stripe': {
                          fill: 'var(--background-tertiary) !important',
                          fillOpacity: 0.1,
                        },
                        '& .MuiRadarSeriesPlot-area': {
                          stroke: 'var(--primary-colors-main) !important',
                          fill: 'var(--primary-colors-main) !important',
                          fillOpacity: 0.2,
                          strokeWidth: 2,
                        },
                        '& .MuiRadarSeriesPlot-mark': {
                          fill: 'var(--primary-colors-main) !important',
                          stroke: 'var(--primary-colors-main) !important',
                        },
                        '& .MuiChartsLegend-root': {
                          display: 'flex !important',
                        },
                        '& .MuiChartsLegend-label': {
                          color: 'var(--text-main) !important',
                          fill: 'var(--text-main) !important',
                        },
                        '& .MuiChartsLabelMark-fill': {
                          fill: 'var(--primary-colors-main) !important',
                        },
                        '& text': {
                          fill: 'var(--text-secondary) !important',
                        },
                        '& tspan': {
                          fill: 'var(--text-secondary) !important',
                        },
                      }}
                    />
                  </div>
                </section>
              </div>

              {explosives && explosives.length > 0 && (
                <section className="w-50p mt-24">
                  <header className="flex items-center justify-space-between mb-24 transition-all">
                    <h4 className="flex items-center gap-x-12">Взрывные устройства</h4>
                  </header>
                  <div className="page-stats__categories">
                    {explosives.map((item, index) => (
                      <div key={item.key || index} className="page-stats__category category">
                        <h5 className="category__count-and-img">
                          <span>
                            {formatNumber(item.count)}
                            {item.score && (
                              <span className="category__x" title={`Множитель для рейтинга игроков x${item.score}`}>
                                x{item.score}
                              </span>
                            )}
                          </span>
                          <img src={item.image} alt={item.name} className="w-64 h-64 object-contain" />
                        </h5>
                        <p className="category__title">{item.name}</p>
                      </div>
                    ))}
                  </div>
                </section>
              )}

              {weapons && weapons.length > 0 && (
                <section className="w-50p mt-24">
                  <header className="flex items-center justify-space-between mb-24 transition-all">
                    <h4 className="flex items-center gap-x-12">Орудия убийства</h4>
                  </header>
                  <div className="page-stats__categories">
                    {weapons.map((weapon, index) => (
                      <div key={weapon.weapon || index} className="page-stats__category category">
                        <h5 className="category__count-and-img">
                          <span>{formatNumber(weapon.count)}</span>
                          <img src={weapon.image} alt={weapon.name} className="w-64 h-64 object-contain" />
                        </h5>
                        <p className="category__title">{weapon.name}</p>
                      </div>
                    ))}
                  </div>
                </section>
              )}
            </div>
          )}

          {/* Команда */}
          {activeTab === 'team' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              {teamMembers && teamMembers.length > 0 ? (
                <section className="page-stats__block-without-hover team-widget">
                  <header className="flex items-center justify-space-between mb-24 transition-all">
                    <h3 className="flex items-center gap-x-12">Команда</h3>
                    <div className="team-widget__stats">
                      <span className="team-widget__stat team-widget__stat--online">
                        <Icon name="circle" fontSize="small" />
                        {teamMembers.filter(m => m.is_online).length}
                      </span>
                      <span className="team-widget__stat">
                        {teamMembers.length}
                      </span>
                    </div>
                  </header>
                  <ul className="team-widget__list">
                    {teamMembers.map((member) => (
                      <li key={member.id} className={`team-member ${member.is_hidden ? 'team-member--hidden' : ''}`}>
                        <div className={`team-member__avatar ${member.is_hidden ? 'team-member__avatar--hidden' : (!member.is_online ? 'team-member__avatar--offline' : '')}`}>
                          <Avatar src={member.avatar} alt={member.username} size={40} />
                          {member.is_online && <span className="team-member__status"></span>}
                        </div>
                        <div className="team-member__content">
                          <div className="team-member__info">
                            <a href={member.link} rel="nofollow" className="team-member__name">
                              {member.username}
                            </a>
                            {member.is_leader && (
                              <span className="team-member__badge team-member__badge--leader">
                                <Icon name="crown" fontSize="small" />
                                лидер
                              </span>
                            )}
                          </div>
                          <div className="team-member__status-text">
                            {member.is_hidden ? (
                              <>
                                <Icon name="eye-slash" fontSize="small" />
                                <span>Скрыт</span>
                              </>
                            ) : member.is_online ? (
                              <span>Онлайн</span>
                            ) : (
                              <>
                                <Icon name="clock" fontSize="small" />
                                <span>Был онлайн {member.date_visit}</span>
                              </>
                            )}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                </section>
              ) : (
                <section className="page-stats__block-without-hover">
                  <p className="text-text-secondary">Игрок не состоит в команде</p>
                </section>
              )}
            </div>
          )}

          {/* Убийства */}
          {activeTab === 'kills' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              {kills && kills.length > 0 ? (
                <DataTable
                  data={[...kills]
                    .slice(0, 30) // Берем только последние 30 записей
                    .sort((a, b) => {
                      if (killsSortField === 'distance') {
                        const diff = a.distance - b.distance;
                        return killsSortOrder === 'asc' ? diff : -diff;
                      }
                      if (killsSortField === 'created_at') {
                        const diff = new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
                        return killsSortOrder === 'asc' ? diff : -diff;
                      }
                      return 0;
                    })
                    .slice((killsPage - 1) * 20, killsPage * 20)}
                    columns={[
                      {
                        key: 'weapon',
                        label: 'Оружие',
                        sortable: false,
                        width: '80px',
                        render: (kill: KillItem) => (
                          <div className="kill-table-weapon">
                            {kill.weapon_image ? (
                              <img 
                                src={kill.weapon_image} 
                                alt={kill.weapon_name || kill.weapon || ''} 
                                loading="lazy"
                                style={{ width: '48px', height: '48px', objectFit: 'contain' }}
                                onError={(e) => {
                                  (e.target as HTMLImageElement).src = '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png';
                                }}
                              />
                            ) : (
                              <i className="fas fa-skull-crossbones" style={{ fontSize: '24px', color: 'var(--text-secondary)' }}></i>
                            )}
                          </div>
                        ),
                      },
                      {
                        key: 'action',
                        label: 'Действие',
                        sortable: false,
                        render: (kill: KillItem) => {
                          if (kill.type === 'suicides') {
                            return (
                              <div className="kill-table-action">
                                {kill.name ? (
                                  <a href={kill.link || '#'} className="link">{kill.name}</a>
                                ) : (
                                  <span>Неизвестный</span>
                                )}
                                <span className="text-text-secondary ml-8">совершил самоубийство</span>
                              </div>
                            );
                          }
                          if (kill.type === 'animal') {
                            return (
                              <div className="kill-table-action">
                                {kill.name ? (
                                  <a href={kill.link || '#'} className="link">{kill.name}</a>
                                ) : (
                                  <span>Неизвестный</span>
                                )}
                                <span className="text-text-secondary ml-8">убил {kill.animal2 || 'животное'}</span>
                              </div>
                            );
                          }
                          if (kill.type === 'deaths') {
                            return (
                              <div className="kill-table-action">
                                <span className="text-text-secondary">{kill.animal || 'Животное'} убил</span>
                                {kill.name ? (
                                  <a href={kill.link || '#'} className="link ml-8">{kill.name}</a>
                                ) : (
                                  <span className="ml-8">Неизвестный</span>
                                )}
                              </div>
                            );
                          }
                          if (kill.type === 'scientists') {
                            return (
                              <div className="kill-table-action">
                                {kill.name ? (
                                  <a href={kill.link || '#'} className="link">{kill.name}</a>
                                ) : (
                                  <span>Неизвестный</span>
                                )}
                                <span className="text-text-secondary ml-8">убил бота</span>
                              </div>
                            );
                          }
                          if (kill.type === 'kill') {
                            return (
                              <div className="kill-table-action">
                                {kill.bot ? (
                                  <span className="text-text-secondary">Бот</span>
                                ) : kill.name ? (
                                  <a href={kill.link || '#'} className="link">{kill.name}</a>
                                ) : (
                                  <span>Неизвестный</span>
                                )}
                                <i className="fas fa-arrow-right mx-8" style={{ color: 'var(--text-secondary)' }}></i>
                                {kill.dead_name ? (
                                  <a href={kill.dead_link || '#'} className="link">{kill.dead_name}</a>
                                ) : (
                                  <span>Неизвестный</span>
                                )}
                              </div>
                            );
                          }
                          return null;
                        },
                      },
                      {
                        key: 'distance',
                        label: 'Дистанция',
                        sortable: true,
                        width: '120px',
                        render: (kill: KillItem) => (
                          <div className="flex items-center gap-x-8">
                            <i className="fas fa-ruler" style={{ fontSize: '12px', color: 'var(--text-secondary)' }}></i>
                            <span>{kill.distance} м</span>
                          </div>
                        ),
                      },
                      {
                        key: 'badges',
                        label: 'Метки',
                        sortable: false,
                        width: '200px',
                        render: (kill: KillItem) => (
                          <div className="flex items-center gap-x-8 flex-wrap">
                            {kill.signs && kill.signs.includes('sleep') && (
                              <span className="kill-badge kill-badge--sleep">
                                <i className="fas fa-bed"></i>
                                Спящий
                              </span>
                            )}
                            {kill.signs && kill.signs.includes('team') && (
                              <span className="kill-badge kill-badge--team">
                                <i className="fas fa-user-friends"></i>
                                Тимейт
                              </span>
                            )}
                            {!kill.wears && (
                              <span className="kill-badge kill-badge--naked">
                                <i className="fas fa-user"></i>
                                Голый
                              </span>
                            )}
                          </div>
                        ),
                      },
                      {
                        key: 'created_at',
                        label: 'Дата',
                        sortable: true,
                        width: '180px',
                        render: (kill: KillItem) => (
                          <span className="text-text-secondary">
                            {new Date(kill.created_at).toLocaleString('ru-RU', {
                              day: '2-digit',
                              month: '2-digit',
                              year: 'numeric',
                              hour: '2-digit',
                              minute: '2-digit',
                            })}
                          </span>
                        ),
                      },
                    ]}
                    emptyMessage="История убийств пуста"
                    sortField={killsSortField}
                    sortOrder={killsSortOrder}
                    onSort={(field) => {
                      const sortFieldTyped = field as 'distance' | 'created_at';
                      if (killsSortField === sortFieldTyped) {
                        setKillsSortOrder(killsSortOrder === 'asc' ? 'desc' : 'asc');
                        setKillsPage(1);
                      } else {
                        setKillsSortField(sortFieldTyped);
                        setKillsSortOrder('desc');
                        setKillsPage(1);
                      }
                    }}
                    page={killsPage}
                    totalPages={Math.ceil(Math.min(kills.length, 30) / 20)}
                    total={Math.min(kills.length, 30)}
                    onPageChange={(newPage) => {
                      setKillsPage(newPage);
                      window.scrollTo({ top: 0, behavior: 'smooth' });
                    }}
                    pageSize={20}
                  />
              ) : (
                <div className="data-table-wrapper">
                  <div className="data-table-empty">
                    <p className="text-text-secondary">История убийств пуста</p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Ресурсы и добыча */}
          {activeTab === 'resources' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              <section className="page-stats__block-without-hover">
                <header className="flex items-center justify-space-between mb-24 transition-all">
                  <h3 className="flex items-center gap-x-12">Ресурсы</h3>
                </header>
                <div className="page-stats__categories">
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats['sulfur.ore'])}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Сера" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Серная руда</p>
                  </div>
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats['metal.ore'])}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Металл" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Железная руда</p>
                  </div>
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats.stones)}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Камень" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Камни</p>
                  </div>
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats.wood)}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Дерево" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Дерево</p>
                  </div>
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats.barrel)}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Бочки" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Разбито бочек</p>
                  </div>
                  <div className="page-stats__category category">
                    <h5 className="category__count-and-img">
                      <span>{formatNumber(stats.crate_open)}</span>
                      <img src="/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png" alt="Ящики" className="w-64 h-64 object-contain" />
                    </h5>
                    <p className="category__title">Открыто ящиков</p>
                  </div>
                </div>
              </section>

              <CategoryBlock
                title="Рыболовство"
                items={fishing}
                showScore={true}
              />

              <CategoryBlock
                title="Охота"
                items={hunters}
                className="page-stats__block-without-hover"
              />

              <CategoryBlock
                title="Фермерство"
                items={ferm}
                showScore={true}
                visibleCount={3}
                expanded={expandedFerm}
                onExpandChange={setExpandedFerm}
              />
            </div>
          )}

          {/* Активность */}
          {activeTab === 'activity' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              <div className="page-stats__two-blocks">
                <CategoryBlock
                  title="Любимая еда"
                  items={food}
                  visibleCount={3}
                  expanded={expandedFood}
                  onExpandChange={setExpandedFood}
                  className="w-50p"
                />
                {statsBlocks && statsBlocks.length > 0 && (
                  <section className="page-stats__block-without-hover w-50p">
                    <div className="page-stats__categories page-stats__categories__blocks">
                      {statsBlocks.map((item, index) => (
                        <div key={item.key || index} className="page-stats__category category">
                          <h5 className="category__count-and-img">
                            <span>{formatNumber(item.count)}</span>
                            <img src={item.image} alt={item.name} className="w-64 h-64 object-contain" />
                          </h5>
                          <p className="category__title">{item.name}</p>
                        </div>
                      ))}
                    </div>
                  </section>
                )}
              </div>

              <CategoryBlock
                title="Чаепитие"
                items={tea}
                className="page-stats__block-without-hover"
              />

              <CategoryBlock
                title="Пироги"
                items={pie}
                className="page-stats__block-without-hover"
              />

              <div className="page-stats__two-blocks">
                <CategoryBlock
                  title="Карты доступа"
                  items={levelCards}
                  className="page-stats__block-without-hover w-50p"
                />
                <CategoryBlock
                  title="Медицина"
                  items={medical}
                  className="page-stats__block-without-hover w-50p"
                />
              </div>
            </div>
          )}

          {/* Награды */}
          {activeTab === 'awards' && (
            <div className="flex flex-column gap-x-12 gap-y-12">
              {awards && awards.length > 0 ? (
                <section className="page-stats__block-without-hover">
                  <header className="flex items-center justify-space-between mb-24 transition-all">
                    <h3 className="flex items-center gap-x-12">
                      Награды
                      <span
                        className="icons icons_24px icons_24px_info icons_hover"
                        data-tooltip-id="awards-tooltip"
                        data-tooltip-content="Выполни все задания, чтобы получить награду"
                      ></span>
                    </h3>
                    {awardsStats && (
                      <a href="/tasks-v2" className="awards__stats-link">
                        выполнено {awardsStats.completed} из {awardsStats.total} заданий
                      </a>
                    )}
                  </header>

                  <div className="page-stats__awards">
                    {visibleAwards.map((award) => (
                      <div key={award.id} className={`award ${!award.completed ? 'award_is-not-completed' : ''}`}>
                        <img src={award.image} alt={award.name} className="award__image" />
                        <p className="p2">{award.name}</p>
                      </div>
                    ))}
                    {!expandedAwards && awards.slice(8).map((award) => (
                      <div key={award.id} className={`award awards-item-hidden ${!award.completed ? 'award_is-not-completed' : ''}`} style={{ display: 'none' }}>
                        <img src={award.image} alt={award.name} className="award__image" />
                        <p className="p2">{award.name}</p>
                      </div>
                    ))}
                  </div>

                  {hiddenAwardsCount > 0 && (
                    <button 
                      type="button" 
                      className="button button-secondary w-full mt-16 awards-show-more-btn"
                      onClick={() => setExpandedAwards(!expandedAwards)}
                    >
                      <span className="awards-show-more-text">
                        {expandedAwards ? 'Скрыть' : `Показать еще ${hiddenAwardsCount}`}
                      </span>
                      <i className={`fas fa-chevron-down ${expandedAwards ? 'awards-show-more-icon--rotated' : ''}`}></i>
                    </button>
                  )}
                </section>
              ) : (
                <section className="page-stats__block-without-hover">
                  <p className="text-text-secondary">Награды отсутствуют</p>
                </section>
              )}
            </div>
          )}
        </main>
      </div>
    </div>
  );
}
