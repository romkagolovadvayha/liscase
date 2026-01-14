'use client';

import React from 'react';
import Image from 'next/image';
import type { StoreItem } from '@/types/store';
import StoreItemCard from './StoreItemCard';

interface StoreInventoryProps {
  items: StoreItem[];
  server: {
    id: number;
    name: string;
    tag: string;
  } | null;
  onDeliver: (itemId: number) => void;
  onReturn: (itemId: number) => void;
}

export default function StoreInventory({
  items,
  server,
  onDeliver,
  onReturn,
}: StoreInventoryProps) {
  return (
    <div className="store-inventory">
      {items.map((item) => (
        <StoreItemCard
          key={item.id}
          item={item}
          server={server}
          onDeliver={onDeliver}
          onReturn={onReturn}
        />
      ))}
    </div>
  );
}







