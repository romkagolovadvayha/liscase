'use client';

import React, { useState, useEffect } from 'react';
import apiClient from '@/lib/api/client';

interface Wipe {
  id: number;
  server_tag: string;
  wipe_date: string;
  description?: string;
  created_at: string;
}

interface WipeCalendarClientProps {
  initialData?: {
    wipes: Wipe[];
  };
}

export default function WipeCalendarClient({
  initialData,
}: WipeCalendarClientProps) {
  const [wipes, setWipes] = useState<Wipe[]>(initialData?.wipes || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      setIsLoading(true);
      apiClient.get('/wipe-calendar')
        .then(response => {
          if (response.data.success) {
            setWipes(response.data.data?.wipes || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch wipe calendar:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  if (isLoading) {
    return (
      <div className="wipe-calendar-page">
        <div className="wipe-calendar-container">
          <div className="wipe-calendar-header">
            <h1>Календарь вайпов</h1>
          </div>
          <div className="wipe-calendar-content">
            <div className="wipe-calendar-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="wipe-calendar-page">
      <div className="wipe-calendar-container">
        <div className="wipe-calendar-header">
          <h1>Календарь вайпов</h1>
        </div>
        <div className="wipe-calendar-content">
          {wipes.length === 0 ? (
            <div className="wipe-calendar-empty">Нет запланированных вайпов</div>
          ) : (
            <div className="wipe-calendar-list">
              {wipes.map((wipe) => (
                <div key={wipe.id} className="wipe-calendar-item">
                  <div className="wipe-calendar-item-date">
                    {new Date(wipe.wipe_date).toLocaleDateString()}
                  </div>
                  <div className="wipe-calendar-item-server">{wipe.server_tag}</div>
                  {wipe.description && (
                    <div className="wipe-calendar-item-description">
                      {wipe.description}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}







