import React from 'react';

export interface TeamsBlockProps {
  data: {
    [key: string]: any;
  };
}

export default function TeamsBlock({ data }: TeamsBlockProps) {
  // TODO: Реализовать виджет команд
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Команды</h4>
      <div className="stat-block__body">
        {/* Контент виджета команд */}
      </div>
    </section>
  );
}











