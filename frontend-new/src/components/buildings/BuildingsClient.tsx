'use client';

import React, { useState, useEffect } from 'react';
import type { Building } from '@/types/buildings';
import BuildingCard from './BuildingCard';
import apiClient from '@/lib/api/client';

interface BuildingsClientProps {
  initialData?: {
    buildings: Building[];
    servers: Array<{ tag: string; name: string }>;
  };
}

export default function BuildingsClient({ initialData }: BuildingsClientProps) {
  const [buildings, setBuildings] = useState<Building[]>(initialData?.buildings || []);
  const [servers, setServers] = useState<Array<{ tag: string; name: string }>>(initialData?.servers || []);
  const [selectedServer, setSelectedServer] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      // Загружаем данные на клиенте, если не переданы через пропсы
      setIsLoading(true);
      Promise.all([
        apiClient.get('/buildings'),
        apiClient.get('/servers')
      ])
        .then(([buildingsResponse, serversResponse]) => {
          if (buildingsResponse.data.success) {
            setBuildings(buildingsResponse.data.data?.buildings || []);
          }
          if (serversResponse.data.success) {
            setServers(serversResponse.data.data?.servers || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch buildings data:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  const filteredBuildings = selectedServer
    ? buildings.filter((b) => b.server_tag === selectedServer)
    : buildings;

  if (isLoading) {
    return (
      <div className="buildings-page">
        <div className="buildings-container">
          <div className="buildings-header">
            <h1>Постройки</h1>
          </div>
          <div className="buildings-content">
            <div className="buildings-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

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
          {servers.map((server) => (
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
          {filteredBuildings.length === 0 ? (
            <div className="buildings-empty">Нет построек</div>
          ) : (
            filteredBuildings.map((building) => (
              <BuildingCard key={building.id} building={building} />
            ))
          )}
        </div>
      </div>
    </div>
  );
}







