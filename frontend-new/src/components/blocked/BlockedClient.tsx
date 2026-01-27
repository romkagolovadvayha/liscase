'use client';

import React, { useState, useEffect } from 'react';
import apiClient from '@/lib/api/client';

interface BlockedItem {
  id: number;
  user_id: number;
  blocked_user_id: number;
  reason?: string;
  created_at: string;
}

interface BlockedClientProps {
  initialData?: {
    blocked: BlockedItem[];
  };
}

export default function BlockedClient({
  initialData,
}: BlockedClientProps) {
  const [blocked, setBlocked] = useState<BlockedItem[]>(initialData?.blocked || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      // Загружаем данные на клиенте, если не переданы через пропсы
      setIsLoading(true);
      apiClient.get('/user/blocked')
        .then(response => {
          if (response.data.success) {
            setBlocked(response.data.data?.blocked || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch blocked users:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  if (isLoading) {
    return (
      <div className="blocked-page">
        <div className="blocked-container">
          <div className="blocked-header">
            <h1>Заблокированные пользователи</h1>
          </div>
          <div className="blocked-content">
            <div className="blocked-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="blocked-page">
      <div className="blocked-container">
        <div className="blocked-header">
          <h1>Заблокированные пользователи</h1>
        </div>
        <div className="blocked-content">
          {blocked.length === 0 ? (
            <div className="blocked-empty">Нет заблокированных пользователей</div>
          ) : (
            <div className="blocked-list">
              {blocked.map((item) => (
                <div key={item.id} className="blocked-item">
                  <div className="blocked-item-id">ID: {item.blocked_user_id}</div>
                  {item.reason && (
                    <div className="blocked-item-reason">{item.reason}</div>
                  )}
                  <div className="blocked-item-date">
                    {new Date(item.created_at).toLocaleDateString()}
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







