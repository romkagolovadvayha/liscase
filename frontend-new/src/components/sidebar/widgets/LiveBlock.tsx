import React from 'react';

export interface LiveBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function LiveBlock({ data }: LiveBlockProps) {
  // TODO: Реализовать виджет лайва
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Лайв</h4>
      <div className="stat-block__body">
        {/* Контент виджета лайва */}
      </div>
    </section>
  );
}











