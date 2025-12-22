import React from 'react';

export interface ServerInfoProps {
  data: {
    name?: string;
    description?: string;
    online?: number;
    max?: number;
    [key: string]: any;
  };
}

export default function ServerInfo({ data }: ServerInfoProps) {
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">
        {data.name || 'Информация о сервере'}
      </h4>
      <div className="stat-block__body">
        {data.description && <p>{data.description}</p>}
        {data.online !== undefined && (
          <div className="stat-block__online">
            <span className="stat-block__online-value">{data.online}</span>
            {data.max && <span>/{data.max}</span>}
            <span className="stat-block__online-label">Онлайн</span>
          </div>
        )}
      </div>
    </section>
  );
}











