import React from 'react';

export interface BuildingsBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function BuildingsBlock({ data }: BuildingsBlockProps) {
  // TODO: Реализовать виджет построек
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Постройки</h4>
      <div className="stat-block__body">
        {/* Контент виджета построек */}
      </div>
    </section>
  );
}











