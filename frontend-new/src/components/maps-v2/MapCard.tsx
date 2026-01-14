'use client';

import React from 'react';
import Image from 'next/image';
import type { Map } from '@/types/maps';

interface MapCardProps {
  map: Map;
  onVote: (mapId: number) => void;
}

export default function MapCard({ map, onVote }: MapCardProps) {
  return (
    <div className="map-card">
      {map.image && (
        <div className="map-card-image">
          <Image
            src={`/uploads/maps/${map.image}`}
            alt={map.name}
            width={300}
            height={200}
            className="map-card-image-img"
          />
        </div>
      )}
      <div className="map-card-content">
        <h3 className="map-card-name">{map.name}</h3>
        {map.description && (
          <p className="map-card-description">{map.description}</p>
        )}
        <div className="map-card-footer">
          <div className="map-card-votes">
            <span>Голосов: {map.votes_count}</span>
          </div>
          <button
            className={`map-card-vote-button ${map.has_voted ? 'active' : ''}`}
            onClick={() => onVote(map.id)}
          >
            {map.has_voted ? '✓ Проголосовано' : 'Голосовать'}
          </button>
        </div>
      </div>
    </div>
  );
}







