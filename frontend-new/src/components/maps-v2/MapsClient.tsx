'use client';

import React, { useState, useEffect } from 'react';
import type { Map } from '@/types/maps';
import MapCard from './MapCard';
import apiClient from '@/lib/api/client';

interface MapsClientProps {
  initialData?: {
    maps: Map[];
  };
}

export default function MapsClient({ initialData }: MapsClientProps) {
  const [maps, setMaps] = useState<Map[]>(initialData?.maps || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      setIsLoading(true);
      apiClient.get('/maps-v2')
        .then(response => {
          if (response.data.success) {
            setMaps(response.data.data?.maps || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch maps:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  const handleVote = async (mapId: number) => {
    try {
      const response = await fetch(`/api/maps-v2/${mapId}/vote`, {
        method: 'POST',
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to vote');
      }

      const result = await response.json();

      // Обновляем состояние
      setMaps((prev) =>
        prev.map((map) =>
          map.id === mapId
            ? {
                ...map,
                has_voted: result.voted,
                votes_count: result.voted ? map.votes_count + 1 : Math.max(map.votes_count - 1, 0),
              }
            : map
        )
      );
    } catch (error) {
      console.error('Error voting for map:', error);
      alert('Ошибка при голосовании');
    }
  };

  if (isLoading) {
    return (
      <div className="maps-page">
        <div className="maps-container">
          <div className="maps-header">
            <h1>Карты серверов</h1>
          </div>
          <div className="maps-content">
            <div className="maps-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="maps-page">
      <div className="maps-container">
        <div className="maps-header">
          <h1>Карты серверов</h1>
        </div>
        <div className="maps-grid">
          {maps.length === 0 ? (
            <div className="maps-empty">Нет карт</div>
          ) : (
            maps.map((map) => (
              <MapCard key={map.id} map={map} onVote={handleVote} />
            ))
          )}
        </div>
      </div>
    </div>
  );
}







