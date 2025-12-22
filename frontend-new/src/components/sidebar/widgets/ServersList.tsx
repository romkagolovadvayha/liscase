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
  servers: Server[];
  projectStats?: {
    online?: number;
  };
}

export default function ServersList({ servers, projectStats }: ServersListProps) {
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

  const formatWipeTime = (timestamp: number | string | undefined): string => {
    if (!timestamp) return '';
    try {
      let wipeTime: moment.Moment;
      if (typeof timestamp === 'number') {
        wipeTime = moment.unix(timestamp);
      } else {
        const parsed = parseInt(timestamp);
        if (isNaN(parsed)) {
          // Если это строка-дата, пытаемся распарсить как дату
          wipeTime = moment(timestamp);
        } else {
          wipeTime = moment.unix(parsed);
        }
      }
      return wipeTime.isValid() ? wipeTime.fromNow() : '';
    } catch {
      return '';
    }
  };

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
                <p className="server__link">
                  <span className="server__link-text">{server.description}</span>
                  {server.nextWipe && (
                    <span className="server__wipe">
                      Вайп <span className="server__wipe-time" data-time={server.nextWipe}>
                        {formatWipeTime(server.nextWipe)}
                      </span>
                    </span>
                  )}
                </p>
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

