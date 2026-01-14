import React from 'react';

export interface TopBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function TopBlock({ data }: TopBlockProps) {
  // TODO: Реализовать виджет топа
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Топ</h4>
      <div className="stat-block__body">
        {/* Контент виджета топа */}
      </div>
    </section>
  );
}



















