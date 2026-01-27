'use client';

import React, { useState, useEffect, useCallback } from 'react';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';

interface Raid {
  id: number;
  server_tag: string;
  name: string;
  description?: string;
  start_time: string;
  end_time: string;
  created_at: string;
}

export default function RaidTableClient() {
  const [raids, setRaids] = useState<Raid[]>([]);
  const [loading, setLoading] = useState(true);

  // Загрузка данных
  const loadData = useCallback(async () => {
    if (typeof window === 'undefined' || !isAuthenticated()) {
      return;
    }

    setLoading(true);
    try {
      // Загружаем список рейдов
      const response = await apiClient.get('/raid-table');
      if (response.data.success) {
        const raidsData = response.data.data.raids || [];
        setRaids(raidsData);
      }
    } catch (error) {
      console.error('Error loading raid table data:', error);
      setRaids([]);
    } finally {
      setLoading(false);
    }
  }, []);

  // Первоначальная загрузка
  useEffect(() => {
    if (typeof window !== 'undefined' && isAuthenticated()) {
      loadData();
    }
  }, [loadData]);

  if (loading) {
    return (
      <div className="raid-table-page">
        <div className="raid-table-container">
          <div className="raid-table-header">
            <h1>Таблица рейдов</h1>
          </div>
          <div className="raid-table-content">
            <div className="raid-table-loading">
              <p>Загрузка...</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="raid-table-page">
      <div className="raid-table-container">
        <div className="raid-table-header">
          <h1>Таблица рейдов</h1>
        </div>
        <div className="raid-table-content">
          {raids.length === 0 ? (
            <div className="raid-table-empty">Нет данных о рейдах</div>
          ) : (
            <table className="raid-table">
              <thead>
                <tr>
                  <th>Сервер</th>
                  <th>Название</th>
                  <th>Время начала</th>
                  <th>Время окончания</th>
                </tr>
              </thead>
              <tbody>
                {raids.map((raid) => (
                  <tr key={raid.id}>
                    <td>{raid.server_tag}</td>
                    <td>{raid.name}</td>
                    <td>{new Date(raid.start_time).toLocaleString('ru-RU')}</td>
                    <td>{new Date(raid.end_time).toLocaleString('ru-RU')}</td>
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
