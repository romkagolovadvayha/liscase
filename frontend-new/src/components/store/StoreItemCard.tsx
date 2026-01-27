'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import type { StoreItem } from '@/types/store';

interface StoreItemCardProps {
  item: StoreItem;
  server: {
    id: number;
    name: string;
    tag: string;
  } | null;
  onDeliver: (itemId: number) => void;
  onReturn: (itemId: number) => void;
}

export default function StoreItemCard({
  item,
  server,
  onDeliver,
  onReturn,
}: StoreItemCardProps) {
  const [isDelivering, setIsDelivering] = useState(false);
  const [isReturning, setIsReturning] = useState(false);

  const canDeliver = !item.box_id && !item.sets_id && !item.parent_drop_id && server;
  const canReturn = !item.box_id && !item.sets_id && !item.parent_drop_id;

  const handleDeliver = async () => {
    if (!canDeliver || isDelivering) return;
    setIsDelivering(true);
    try {
      await onDeliver(item.id);
    } finally {
      setIsDelivering(false);
    }
  };

  const handleReturn = async () => {
    if (!canReturn || isReturning) return;
    setIsReturning(true);
    try {
      await onReturn(item.id);
    } finally {
      setIsReturning(false);
    }
  };

  return (
    <div className="store-item-card">
      {item.drop?.image && (
        <div className="store-item-card-image">
          <Image
            src={item.drop.image}
            alt={item.drop.name}
            width={100}
            height={100}
          />
        </div>
      )}
      <div className="store-item-card-content">
        <h3 className="store-item-card-name">{item.drop?.name || 'Предмет'}</h3>
        {item.category && (
          <div className="store-item-card-category">{item.category.name}</div>
        )}
        {item.count > 1 && (
          <div className="store-item-card-count">Количество: {item.count}</div>
        )}
        <div className="store-item-card-actions">
          {canDeliver && (
            <button
              onClick={handleDeliver}
              disabled={isDelivering}
              className="button button-primary"
            >
              {isDelivering ? 'Выдача...' : 'Выдать на сервер'}
            </button>
          )}
          {canReturn && (
            <button
              onClick={handleReturn}
              disabled={isReturning}
              className="button button-secondary"
            >
              {isReturning ? 'Возврат...' : 'Вернуть'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}







