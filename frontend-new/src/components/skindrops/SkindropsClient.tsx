'use client';

import React from 'react';

interface SkindropsClientProps {
  initialData: {
    drops: Array<{
      id: number;
      user_id: number;
      skin_id: number;
      opened_at: string | null;
      created_at: string;
    }>;
  };
}

export default function SkindropsClient({
  initialData,
}: SkindropsClientProps) {
  return (
    <div className="skindrops-page">
      <div className="skindrops-container">
        <div className="skindrops-header">
          <h1>Скиндропы</h1>
        </div>
        <div className="skindrops-content">
          {initialData.drops.length === 0 ? (
            <div className="skindrops-empty">У вас нет скиндропов</div>
          ) : (
            <div className="skindrops-list">
              {initialData.drops.map((drop) => (
                <div key={drop.id} className="skindrop-item">
                  <div className="skindrop-item-id">#{drop.id}</div>
                  <div className="skindrop-item-status">
                    {drop.opened_at ? 'Открыт' : 'Не открыт'}
                  </div>
                  <div className="skindrop-item-date">
                    {new Date(drop.created_at).toLocaleDateString()}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}







