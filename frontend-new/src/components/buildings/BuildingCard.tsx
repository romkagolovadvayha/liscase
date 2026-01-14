'use client';

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import type { Building } from '@/types/buildings';

interface BuildingCardProps {
  building: Building;
}

export default function BuildingCard({ building }: BuildingCardProps) {
  return (
    <Link href={`/buildings/${building.id}`} className="building-card">
      {building.image && (
        <div className="building-card-image">
          <Image
            src={`/uploads/buildings/${building.image}`}
            alt={building.title}
            width={300}
            height={200}
            className="building-card-image-img"
          />
        </div>
      )}
      <div className="building-card-content">
        <h3 className="building-card-title">{building.title}</h3>
        {building.description && (
          <p className="building-card-description">{building.description}</p>
        )}
        <div className="building-card-footer">
          <div className="building-card-user">
            {building.user?.avatar && (
              <Image
                src={building.user.avatar}
                alt={building.user.username}
                width={24}
                height={24}
                className="building-card-user-avatar"
              />
            )}
            <span>{building.user?.username}</span>
          </div>
          <div className="building-card-stats">
            <span>❤️ {building.likes_count}</span>
            <span>👁️ {building.views_count}</span>
          </div>
        </div>
      </div>
    </Link>
  );
}







