import React from 'react';

export interface ProfileBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function ProfileBlock({ data }: ProfileBlockProps) {
  // TODO: Реализовать виджет профиля
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Профиль</h4>
      <div className="stat-block__body">
        {/* Контент виджета профиля */}
      </div>
    </section>
  );
}











