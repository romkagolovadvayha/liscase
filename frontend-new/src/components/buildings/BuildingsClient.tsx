'use client';

import React, { useState } from 'react';
import type { Building } from '@/types/buildings';
import BuildingCard from './BuildingCard';

interface BuildingsClientProps {
  initialData: {
    buildings: Building[];
    servers: Array<{ tag: string; name: string }>;
  };
}

export default function BuildingsClient({ initialData }: BuildingsClientProps) {
  const [buildings, setBuildings] = useState(initialData.buildings);
  const [selectedServer, setSelectedServer] = useState<string | null>(null);

  const filteredBuildings = selectedServer
    ? buildings.filter((b) => b.server_tag === selectedServer)
    : buildings;

  return (
    <div className="buildings-page">
      <div className="buildings-container">
        <div className="buildings-header">
          <h1>Постройки</h1>
        </div>
        <div className="buildings-filters">
          <button
            className={`buildings-filter ${selectedServer === null ? 'active' : ''}`}
            onClick={() => setSelectedServer(null)}
          >
            Все серверы
          </button>
          {initialData.servers.map((server) => (
            <button
              key={server.tag}
              className={`buildings-filter ${selectedServer === server.tag ? 'active' : ''}`}
              onClick={() => setSelectedServer(server.tag)}
            >
              {server.name}
            </button>
          ))}
        </div>
        <div className="buildings-grid">
          {filteredBuildings.map((building) => (
            <BuildingCard key={building.id} building={building} />
          ))}
        </div>
      </div>
    </div>
  );
}







