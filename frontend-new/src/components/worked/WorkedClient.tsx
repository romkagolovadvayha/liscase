'use client';

import React, { useState, useEffect } from 'react';
import apiClient from '@/lib/api/client';

interface WorkedItem {
  id: number;
  user_id: number;
  server_tag: string;
  worked_date: string;
  hours: number;
  created_at: string;
}

interface WorkedClientProps {
  initialData?: {
    worked: WorkedItem[];
  };
}

export default function WorkedClient({ initialData }: WorkedClientProps) {
  const [worked, setWorked] = useState<WorkedItem[]>(initialData?.worked || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      setIsLoading(true);
      apiClient.get('/user/worked')
        .then(response => {
          if (response.data.success) {
            setWorked(response.data.data?.worked || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch worked hours:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  if (isLoading) {
    return (
      <div className="worked-page">
        <div className="worked-container">
          <div className="worked-header">
            <h1>Отработанное время</h1>
          </div>
          <div className="worked-content">
            <div className="worked-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="worked-page">
      <div className="worked-container">
        <div className="worked-header">
          <h1>Отработанное время</h1>
        </div>
        <div className="worked-content">
          {worked.length === 0 ? (
            <div className="worked-empty">Нет данных об отработанном времени</div>
          ) : (
            <table className="worked-table">
              <thead>
                <tr>
                  <th>Сервер</th>
                  <th>Дата</th>
                  <th>Часы</th>
                </tr>
              </thead>
              <tbody>
                {worked.map((item) => (
                  <tr key={item.id}>
                    <td>{item.server_tag}</td>
                    <td>{new Date(item.worked_date).toLocaleDateString()}</td>
                    <td>{item.hours}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}







