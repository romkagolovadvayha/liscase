'use client';

import React, { useState, useEffect } from 'react';
import apiClient from '@/lib/api/client';

interface Drop {
  id: number;
  user_id: number;
  skin_id: number;
  opened_at: string | null;
  created_at: string;
}

interface SkindropsClientProps {
  initialData?: {
    drops: Drop[];
  };
}

export default function SkindropsClient({
  initialData,
}: SkindropsClientProps) {
  const [drops, setDrops] = useState<Drop[]>(initialData?.drops || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      setIsLoading(true);
      apiClient.get('/user/skindrops')
        .then(response => {
          if (response.data.success) {
            setDrops(response.data.data?.drops || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch skindrops:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  if (isLoading) {
    return (
      <div className="skindrops-page">
        <div className="skindrops-container">
          <div className="skindrops-header">
            <h1>Скиндропы</h1>
          </div>
          <div className="skindrops-content">
            <div className="skindrops-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="skindrops-page">
      <div className="skindrops-container">
        <div className="skindrops-header">
          <h1>Скиндропы</h1>
        </div>
        <div className="skindrops-content">
          {drops.length === 0 ? (
            <div className="skindrops-empty">У вас нет скиндропов</div>
          ) : (
            <div className="skindrops-list">
              {drops.map((drop) => (
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







