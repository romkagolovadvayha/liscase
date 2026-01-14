'use client';

import React, { useState } from 'react';
import type { Map } from '@/types/maps';
import MapCard from './MapCard';

interface MapsClientProps {
  initialData: {
    maps: Map[];
  };
}

export default function MapsClient({ initialData }: MapsClientProps) {
  const [maps, setMaps] = useState(initialData.maps);

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

  return (
    <div className="maps-page">
      <div className="maps-container">
        <div className="maps-header">
          <h1>Карты серверов</h1>
        </div>
        <div className="maps-grid">
          {maps.map((map) => (
            <MapCard key={map.id} map={map} onVote={handleVote} />
          ))}
        </div>
      </div>
    </div>
  );
}







