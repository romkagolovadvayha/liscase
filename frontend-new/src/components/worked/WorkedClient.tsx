'use client';

import React from 'react';

interface WorkedClientProps {
  initialData: {
    worked: Array<{
      id: number;
      user_id: number;
      server_tag: string;
      worked_date: string;
      hours: number;
      created_at: string;
    }>;
  };
}

export default function WorkedClient({ initialData }: WorkedClientProps) {
  return (
    <div className="worked-page">
      <div className="worked-container">
        <div className="worked-header">
          <h1>Отработанное время</h1>
        </div>
        <div className="worked-content">
          {initialData.worked.length === 0 ? (
            <div className="worked-empty">Нет данных об отработанном времени</div>
          ) : (
            <table className="worked-table">
              <thead>
                <tr>
                  <th>Сервер</th>
                  <th>Дата</th>
                  <th>Часы</th>
                </tr>
              </thead>
              <tbody>
                {initialData.worked.map((item) => (
                  <tr key={item.id}>
                    <td>{item.server_tag}</td>
                    <td>{new Date(item.worked_date).toLocaleDateString()}</td>
                    <td>{item.hours}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}







