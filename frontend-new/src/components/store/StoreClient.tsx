'use client';

import React, { useState, useEffect } from 'react';
import { useStoreWebSocket } from '@/hooks/useStoreWebSocket';
import type { StoreItem } from '@/types/store';
import StoreInventory from './StoreInventory';
import StoreStats from './StoreStats';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';

interface StoreClientProps {
  initialData: {
    items: StoreItem[];
    server: {
      id: number;
      name: string;
      tag: string;
      is_store: number;
    } | null;
    categories: Array<{
      id: number;
      name: string;
    }>;
    total: number;
  };
}

export default function StoreClient({ initialData }: StoreClientProps) {
  const [items, setItems] = useState(initialData.items);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  // WebSocket для real-time обновлений
  const { isConnected, onInventoryUpdate } = useStoreWebSocket({
    enabled: true,
    onInventoryUpdate: (updatedItems) => {
      setItems(updatedItems);
    },
  });

  const handleDeliver = async (itemId: number) => {
    if (!isAuthenticated()) {
      alert('Требуется авторизация');
      return;
    }

    if (!initialData.server) {
      alert('Сервер не выбран');
      return;
    }

    try {
      // TODO: store/deliver endpoint пока не реализован в новом API
      const response = await apiClient.post('/store/deliver', {
        itemId,
        serverId: initialData.server.id,
      });

      if (response.data.success) {
        // Обновляем список предметов
        setItems((prev) => prev.filter((item) => item.id !== itemId));
      }
    } catch (error) {
      console.error('Error delivering item:', error);
      alert('Ошибка при выдаче предмета');
    }
  };

  const handleReturn = async (itemId: number) => {
    if (!isAuthenticated()) {
      alert('Требуется авторизация');
      return;
    }

    try {
      // TODO: store/return endpoint пока не реализован в новом API
      const response = await apiClient.post('/store/return', { itemId });

      if (response.data.success) {
        // Обновляем список предметов
        setItems((prev) => prev.filter((item) => item.id !== itemId));
      }
    } catch (error) {
      console.error('Error returning item:', error);
      alert('Ошибка при возврате предмета');
    }
  };

  const filteredItems = selectedCategory === 'all'
    ? items
    : items.filter((item) => item.category?.id.toString() === selectedCategory);

  return (
    <div className="store-page">
      <div className="store-container">
        <div className="store-header">
          <div className="store-header-left">
            <h1>Корзина сервера</h1>
            <p>Это ваша корзина с покупками, вы можете забрать их в любой момент</p>
          </div>
          {items.length > 0 && (
            <StoreStats items={items} server={initialData.server} />
          )}
        </div>

        {!initialData.server || initialData.server.is_store === 0 ? (
          <div className="store-warning">
            ⚠️ Магазин на сервере, на котором вы находитесь, недоступен!
          </div>
        ) : items.length === 0 ? (
          <div className="store-empty">
            📭 В вашем инвентаре пока нет вещей
          </div>
        ) : (
          <>
            <div className="store-categories">
              <div
                className={`store-category ${selectedCategory === 'all' ? 'active' : ''}`}
                onClick={() => setSelectedCategory('all')}
              >
                Все
              </div>
              {initialData.categories.map((category) => (
                <div
                  key={category.id}
                  className={`store-category ${selectedCategory === category.id.toString() ? 'active' : ''}`}
                  onClick={() => setSelectedCategory(category.id.toString())}
                >
                  {category.name}
                </div>
              ))}
            </div>
            <StoreInventory
              items={filteredItems}
              server={initialData.server}
              onDeliver={handleDeliver}
              onReturn={handleReturn}
            />
          </>
        )}
      </div>
    </div>
  );
}




