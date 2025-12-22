'use client';

import React from 'react';
import ServerCard from '@/components/servers/ServerCard';
import '@/styles/servers.scss';

export default function ServerElements() {
  const exampleServer = {
    id: 1,
    name: 'MAX3',
    monitoring_name: 'MAX3',
    monitoring_description: 'ПРОСТОЙ #1',
    tag: 'max3',
    ip: '195.18.27.169',
    port: 35100,
    players: 12,
    joined: 0,
    queued: 0,
    max: 200,
    team_limit: 0,
    status: 1,
    statusText: 'Онлайн',
    wipe: null,
    wipe_type: 1,
    next_wipe: '2025-01-20T12:00:00Z',
    global_wipe: null,
    secret_map: false,
    map_id: null,
    map_list_id: null,
    tags: [
      {
        id: 1,
        name: 'MAX3',
        link_name: 'max3',
        color: '#ff6134',
      },
      {
        id: 2,
        name: 'PvP',
        link_name: 'pvp',
        color: '#eb0c35',
      },
    ],
    percentPlayers: 6,
    totalPlayers: 12,
  };

  const exampleServerOffline = {
    ...exampleServer,
    id: 2,
    status: 0,
    statusText: 'Выключен',
    players: 0,
    totalPlayers: 0,
  };

  const exampleServerSoon = {
    ...exampleServer,
    id: 3,
    status: 2,
    statusText: 'Скоро откроется',
    players: 0,
    totalPlayers: 0,
  };

  return (
    <div className="server-elements">
      <div className="server-section">
        <h3 className="mb-4">Карточка сервера</h3>
        <div className="server-examples">
          <div className="server-item">
            <h4 className="mb-2">Онлайн сервер</h4>
            <ServerCard server={exampleServer} />
            <code className="server-code">status: 1 (Онлайн)</code>
          </div>

          <div className="server-item">
            <h4 className="mb-2">Выключенный сервер</h4>
            <ServerCard server={exampleServerOffline} />
            <code className="server-code">status: 0 (Выключен)</code>
          </div>

          <div className="server-item">
            <h4 className="mb-2">Скоро откроется</h4>
            <ServerCard server={exampleServerSoon} />
            <code className="server-code">status: 2 (Скоро откроется)</code>
          </div>
        </div>
      </div>
    </div>
  );
}









