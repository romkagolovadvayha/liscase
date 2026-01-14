'use client';

import React from 'react';
import Image from 'next/image';

interface CustomSkinsClientProps {
  initialData: {
    skins: Array<{
      id: number;
      user_id: number;
      name: string;
      image: string;
      created_at: string;
    }>;
  };
}

export default function CustomSkinsClient({
  initialData,
}: CustomSkinsClientProps) {
  return (
    <div className="custom-skins-page">
      <div className="custom-skins-container">
        <div className="custom-skins-header">
          <h1>Кастомные скины</h1>
        </div>
        <div className="custom-skins-content">
          {initialData.skins.length === 0 ? (
            <div className="custom-skins-empty">У вас нет кастомных скинов</div>
          ) : (
            <div className="custom-skins-grid">
              {initialData.skins.map((skin) => (
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







