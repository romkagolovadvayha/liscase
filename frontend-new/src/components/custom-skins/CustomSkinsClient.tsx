'use client';

import React, { useState, useEffect } from 'react';
import Image from 'next/image';
import apiClient from '@/lib/api/client';

interface Skin {
  id: number;
  user_id: number;
  name: string;
  image: string;
  created_at: string;
}

interface CustomSkinsClientProps {
  initialData?: {
    skins: Skin[];
  };
}

export default function CustomSkinsClient({
  initialData,
}: CustomSkinsClientProps) {
  const [skins, setSkins] = useState<Skin[]>(initialData?.skins || []);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      // Загружаем данные на клиенте, если не переданы через пропсы
      setIsLoading(true);
      apiClient.get('/user/custom-skins')
        .then(response => {
          if (response.data.success) {
            setSkins(response.data.data?.skins || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch custom skins:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  if (isLoading) {
    return (
      <div className="custom-skins-page">
        <div className="custom-skins-container">
          <div className="custom-skins-header">
            <h1>Кастомные скины</h1>
          </div>
          <div className="custom-skins-content">
            <div className="custom-skins-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="custom-skins-page">
      <div className="custom-skins-container">
        <div className="custom-skins-header">
          <h1>Кастомные скины</h1>
        </div>
        <div className="custom-skins-content">
          {skins.length === 0 ? (
            <div className="custom-skins-empty">У вас нет кастомных скинов</div>
          ) : (
            <div className="custom-skins-grid">
              {skins.map((skin) => (
                <div key={skin.id} className="custom-skin-card">
                  {skin.image && (
                    <Image
                      src={`/uploads/skins/${skin.image}`}
                      alt={skin.name}
                      width={200}
                      height={200}
                      className="custom-skin-card-image"
                    />
                  )}
                  <div className="custom-skin-card-name">{skin.name}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}







