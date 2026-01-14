import React from 'react';

export interface KillsBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function KillsBlock({ data }: KillsBlockProps) {
  // TODO: Реализовать виджет убийств
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Убийства</h4>
      <div className="stat-block__body">
        {/* Контент виджета убийств */}
      </div>
    </section>
  );
}



















