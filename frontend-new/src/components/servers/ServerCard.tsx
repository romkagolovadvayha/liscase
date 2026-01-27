'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import moment from 'moment';
import 'moment/locale/ru';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';

if (typeof window !== 'undefined') {
  moment.locale('ru');
}

interface ServerTag {
  id: number;
  name: string;
  link_name: string;
  color: string;
  title?: string;
  short_description?: string;
}

interface ServerCardProps {
  server: {
    id: number;
    name: string;
    monitoring_name: string;
    monitoring_description: string;
    tag: string;
    ip: string;
    port: number;
    players: number;
    joined: number;
    queued: number;
    max: number;
    team_limit: number;
    status: number;
    statusText: string;
    wipe: string | null;
    wipe_type: number;
    next_wipe: string | null;
    global_wipe: string | null;
    secret_map: boolean;
    map_id: number | null;
    map_list_id: number | null;
    tags: ServerTag[];
    percentPlayers: number;
    totalPlayers: number;
  };
}

export default function ServerCard({ server }: ServerCardProps) {
  const [copied, setCopied] = useState(false);

  const handleCopyIP = async () => {
    const ipText = `connect ${server.ip}:${server.port}`;
    try {
      await navigator.clipboard.writeText(ipText);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (error) {
      console.error('Failed to copy:', error);
    }
  };

  const formatWipeDate = (date: string | null) => {
    if (!date) return null;
    return moment(date).format('DD.MM.YYYY HH:mm');
  };

  const getWipeTypeText = () => {
    // wipe_type: 1 = недельный, 2 = 14-дневный и т.д.
    if (server.wipe_type === 1) return 'Недельный';
    if (server.wipe_type === 2) return '14-дневный';
    return 'Вайп';
  };

  const connectUrl = `steam://rungameid/252490//+connect ${server.ip}:${server.port}`;

  return (
    <article className={`servers_page_item_wrap server_status${server.status}`} data-server-id={server.id}>
      <div className="servers_page_item">
        <header className="servers_page_item_header">
          <h5 className="servers_page_item_header_title">
            {server.monitoring_description} [{server.monitoring_name}]
          </h5>
          
          <div className="servers_page_item_header_content">
            {server.tags.length > 0 && (
              <div className="server_info_serversTags">
                {server.tags.map((tag) => (
                  <Link
                    key={tag.id}
                    href={`/servers/tag/${tag.link_name}`}
                    className="servers_page_item_header_status"
                    style={{ backgroundColor: tag.color }}
                  >
                    {tag.name}
                  </Link>
                ))}
              </div>
            )}
            
            {server.team_limit > 0 && (
              <span className="servers_page_item_header_status">
                <Icon name="people" fontSize="small" />
                <span>Лимит {server.team_limit} человека</span>
              </span>
            )}
            
            {server.status === 1 && (
              <span className="servers_page_item_header_status">
                <span className="text-online">
                  <Icon name="person" fontSize="small" />
                  {server.totalPlayers}
                </span>/{server.max}
              </span>
            )}
            
            {server.status === 0 && (
              <span className="servers_page_item_header_status text-online">
                Идет перезагрузка
              </span>
            )}
          </div>
        </header>

        <div className="servers_page_item_actions">
          <div 
            className="servers_page_item_ip btn-clipboard"
            onClick={handleCopyIP}
            title="Скопировать IP"
            data-clipboard-text={`connect ${server.ip}:${server.port}`}
          >
            <span className="servers_page_item_ip_content">
              connect {server.ip}:{server.port}
            </span>
            {copied && <span className="servers_page_item_ip_copied">Скопировано!</span>}
          </div>

          <div className="servers_page_item_actions_r">
            {server.status === 1 && (
              <Button
                as="a"
                href={connectUrl}
                variant="primary"
                size="small"
                leftIcon="dns"
                iconSize="small"
              >
                Подключиться к серверу
              </Button>
            )}
            
            <Button
              as="a"
              href={`/servers/${server.tag}/wipe-info`}
              variant="secondary"
              size="small"
              leftIcon="calendar"
              iconSize="small"
            >
              Когда вайп
            </Button>
            
            {server.map_list_id && !server.secret_map && (
              <Button
                as="a"
                href={`/maps-v2/detail/${server.map_list_id}?server_id=${server.id}`}
                variant="secondary"
                size="small"
                leftIcon="map"
                iconSize="small"
                className="servers_page_item_actions_r_image"
              >
                Текущая карта
              </Button>
            )}
            
            <Button
              as="a"
              href={`/servers/${server.tag}/rules`}
              variant="secondary"
              size="small"
              leftIcon="description"
              iconSize="small"
            >
              Правила сервера
            </Button>
            
            <Button
              as="a"
              href={`/servers/${server.tag}/stats`}
              variant="secondary"
              size="small"
              leftIcon="bar-chart"
              iconSize="small"
            >
              Статистика
            </Button>
          </div>
        </div>
      </div>
    </article>
  );
}

