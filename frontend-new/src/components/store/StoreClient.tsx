'use client';

import React, { useState, useEffect } from 'react';
import { useStoreWebSocket } from '@/hooks/useStoreWebSocket';
import type { StoreItem } from '@/types/store';
import StoreInventory from './StoreInventory';
import StoreStats from './StoreStats';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';

interface Server {
  id: number;
  name: string;
  tag: string;
  is_store: number;
}

interface Category {
  id: number;
  name: string;
}

interface StoreClientProps {
  initialData?: {
    items: StoreItem[];
    server: Server | null;
    categories: Category[];
    total: number;
  };
}

export default function StoreClient({ initialData }: StoreClientProps) {
  const [items, setItems] = useState<StoreItem[]>(initialData?.items || []);
  const [server, setServer] = useState<Server | null>(initialData?.server || null);
  const [categories, setCategories] = useState<Category[]>(initialData?.categories || []);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [isLoading, setIsLoading] = useState(!initialData);

  // WebSocket для real-time обновлений
  const { isConnected } = useStoreWebSocket({
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

    if (!server) {
      alert('Сервер не выбран');
      return;
    }

    try {
      // TODO: store/deliver endpoint пока не реализован в новом API
      const response = await apiClient.post('/store/deliver', {
        itemId,
        serverId: server.id,
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

  if (isLoading) {
    return (
      <div className="store-page">
        <div className="store-container">
          <div className="store-header">
            <h1>Корзина сервера</h1>
          </div>
          <div className="store-content">
            <div className="store-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="store-page">
      <div className="store-container">
        <div className="store-header">
          <div className="store-header-left">
            <h1>Корзина сервера</h1>
            <p>Это ваша корзина с покупками, вы можете забрать их в любой момент</p>
          </div>
          {items.length > 0 && server && (
            <StoreStats items={items} server={server} />
          )}
        </div>

        {!server || server.is_store === 0 ? (
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
              {categories.map((category) => (
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
              server={server}
              onDeliver={handleDeliver}
              onReturn={handleReturn}
            />
          </>
        )}
      </div>
    </div>
  );
}




