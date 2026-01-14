'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Link as LinkIcon } from '@mui/icons-material';
import moment from 'moment';
import 'moment/locale/ru';
import { toast } from 'react-hot-toast';

export interface Server {
  id: number;
  tag: string;
  name: string;
  description: string;
  status: number;
  players: number;
  max: number;
  joined: number;
  ip: string;
  port: number;
  nextWipe?: number | string; // timestamp или строка
  wipeType?: string;
  monitoring?: {
    percentPlayers: number;
    percentJoined: number;
    percentQueued: number;
    percentPlayersAbsolute: number;
    percentJoinedAbsolute: number;
    percentQueuedAbsolute: number;
  };
}

export interface ServersListProps {
  servers?: Server[];
  projectStats?: {
    online?: number;
  };
  isLoading?: boolean;
}

export default function ServersList({ servers, projectStats, isLoading }: ServersListProps) {
  const pathname = usePathname();
  const [now, setNow] = useState(moment());
  const [animatedServers, setAnimatedServers] = useState<Set<number>>(new Set());

  // Определяем активный сервер по URL
  const getActiveServerTag = () => {
    const match = pathname?.match(/^\/servers\/([^\/]+)/);
    return match ? match[1] : null;
  };

  const activeServerTag = getActiveServerTag();

  useEffect(() => {
    moment.locale('ru');
    const interval = setInterval(() => {
      setNow(moment());
    }, 60000); // Обновляем каждую минуту

    return () => clearInterval(interval);
  }, []);

  // Анимация прогресс-баров по порядку
  useEffect(() => {
    if (!servers || servers.length === 0) return;
    
    const animateProgressBars = () => {
      servers.forEach((server, index) => {
        setTimeout(() => {
          setAnimatedServers((prev) => new Set(prev).add(server.id));
        }, index * 150); // Задержка 150ms между каждым сервером
      });
    };

    animateProgressBars();
  }, [servers]);

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success('IP скопирован!', {
        duration: 2000,
        position: 'top-center',
      });
    } catch (error) {
      console.error('Failed to copy:', error);
      toast.error('Не удалось скопировать', {
        duration: 2000,
        position: 'top-center',
      });
    }
  };

  const formatWipeTime = (timestamp: number | string | undefined | null): string => {
    if (!timestamp && timestamp !== 0) return '';
    try {
      // API возвращает Unix timestamp в секундах как число
      const timestampNum = typeof timestamp === 'number' ? timestamp : parseInt(String(timestamp), 10);
      if (isNaN(timestampNum)) {
        return '';
      }
      const wipeTime = moment.unix(timestampNum);
      return wipeTime.isValid() ? wipeTime.fromNow() : '';
    } catch {
      return '';
    }
  };

  // Skeleton для загрузки
  if (isLoading || !servers) {
    return (
      <section className="sidebar__widget servers-list">
        <h4 className="servers-list__title">
          <span>Наши сервера</span>
        </h4>
        <div className="servers-list__list">
          {[1, 2, 3].map((i) => (
            <div key={i} className="servers-list__server-link" style={{ pointerEvents: 'none' }}>
              <article className="servers-list__server server">
                <header className="server__header">
                  <div style={{ height: 20, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: '60%', marginBottom: 8 }}></div>
                  <div style={{ height: 16, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: '30%' }}></div>
                </header>
                <div className="server__link">
                  <div style={{ height: 14, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: '80%', marginBottom: 8 }}></div>
                  <div style={{ height: 14, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: '50%' }}></div>
                </div>
                <div className="server__progress-wrapper">
                  <div className="server__progress-wrap">
                    <div className="server__progress" style={{ width: '0%', backgroundColor: 'var(--background-hover)' }}></div>
                  </div>
                  <span className="server__progress-value">
                    <span style={{ height: 16, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: 50, display: 'inline-block' }}></span>
                  </span>
                  <div style={{ width: 32, height: 32, backgroundColor: 'var(--background-hover)', borderRadius: 4 }}></div>
                </div>
              </article>
            </div>
          ))}
        </div>
      </section>
    );
  }

  if (!servers || servers.length === 0) {
    return null;
  }

  return (
    <section className="sidebar__widget servers-list">
      <h4 className="servers-list__title">
        <span>Наши сервера</span>
        {projectStats?.online !== undefined && (
          <span className="servers-list__title-online">
            <span className="servers-list__title-online-indicator"></span>
            <span className="servers-list__title-online-value">{projectStats.online}</span>
            <span className="servers-list__title-online-label">Онлайн</span>
          </span>
        )}
      </h4>
      <div className="servers-list__list">
        {servers.map((server) => {
          const isActive = activeServerTag === server.tag;
          return (
            <Link
              key={server.id}
              href={`/servers/${server.tag}`}
              className={`servers-list__server-link ${isActive ? 'servers-list__server-link--active' : ''}`}
            >
              <article className={`servers-list__server server server_status${server.status}`}>
                <header className="server__header">
                  <h5 className="server__title">{server.name}</h5>
                  <span className="server__status">{server.wipeType || 'Вайп'}</span>
                </header>
                        <div className="server__link">
                          <span className="server__link-text">{server.description}</span>
                          {server.nextWipe && (
                            <span className="server__wipe">
                              Вайп <span className="server__wipe-time" data-time={server.nextWipe}>
                                {formatWipeTime(server.nextWipe)}
                              </span>
                            </span>
                          )}
                        </div>
                <div className="server__progress-wrapper">
                  <div className="server__progress-wrap">
                    <div
                      className={`server__progress ${animatedServers.has(server.id) ? 'server__progress--animated' : ''}`}
                      style={{
                        width: `${(server.monitoring?.percentPlayers || 0) + (server.monitoring?.percentJoined || 0)}%`,
                      }}
                    >
                      <div
                        className="server__progress-players"
                        style={{
                          width: `${(server.monitoring?.percentPlayersAbsolute || 0) + (server.monitoring?.percentJoinedAbsolute || 0)}%`,
                        }}
                      ></div>
                    </div>
                  </div>
                  <span className="server__progress-value">
                    <span className="server__players">{server.players + server.joined}</span>/{server.max}
                  </span>
                  <button
                    title="Скопировать IP"
                    className="server__button-copy"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      copyToClipboard(`connect ${server.ip}:${server.port}`);
                    }}
                  >
                    <LinkIcon className="server__button-copy-icon" />
                  </button>
                </div>
                {server.status === 0 && (
                  <div className="server__status-offline">Выключен</div>
                )}
                {server.status === 2 && (
                  <div className="server__status-wait">Скоро открытие</div>
                )}
              </article>
            </Link>
          );
        })}
      </div>
    </section>
  );
}

