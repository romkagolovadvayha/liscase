'use client';

import React from 'react';
import type { StoreItem } from '@/types/store';

interface StoreStatsProps {
  items: StoreItem[];
  server: {
    id: number;
    name: string;
    tag: string;
  } | null;
}

export default function StoreStats({ items, server }: StoreStatsProps) {
  return (
    <div className="store-stats">
      <div className="store-stat">
        <div className="store-stat-icon">📦</div>
        <div className="store-stat-content">
          <div className="store-stat-label">Всего предметов</div>
          <div className="store-stat-value">{items.length}</div>
        </div>
      </div>
      {server && (
        <div className="store-stat">
          <div className="store-stat-icon">🎮</div>
          <div className="store-stat-content">
            <div className="store-stat-label">Сервер</div>
            <div className="store-stat-value">{server.name}</div>
          </div>
        </div>
      )}
    </div>
  );
}







