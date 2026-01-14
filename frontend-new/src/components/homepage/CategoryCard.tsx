'use client';

import React from 'react';
import classNames from 'classnames';

interface CategoryCardProps {
  id: number;
  name: string;
  image?: string;
  isActive?: boolean;
  onClick?: (id: number) => void;
}

export default function CategoryCard({ id, name, image, isActive, onClick }: CategoryCardProps) {
  return (
    <div
      className={classNames('category', { category_active: isActive })}
      data-id={id}
      onClick={() => onClick && onClick(id)}
    >
      {image && <img src={image} alt={name} className="category__image" loading="lazy" />}
      <p className="category__title">{name}</p>
    </div>
  );
}



















