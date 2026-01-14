'use client';

import React from 'react';

interface BlockedClientProps {
  initialData: {
    blocked: Array<{
      id: number;
      user_id: number;
      blocked_user_id: number;
      reason?: string;
      created_at: string;
    }>;
  };
}

export default function BlockedClient({
  initialData,
}: BlockedClientProps) {
  return (
    <div className="blocked-page">
      <div className="blocked-container">
        <div className="blocked-header">
          <h1>Заблокированные пользователи</h1>
        </div>
        <div className="blocked-content">
          {initialData.blocked.length === 0 ? (
            <div className="blocked-empty">Нет заблокированных пользователей</div>
          ) : (
            <div className="blocked-list">
              {initialData.blocked.map((item) => (
                <div key={item.id} className="blocked-item">
                  <div className="blocked-item-id">ID: {item.blocked_user_id}</div>
                  {item.reason && (
                    <div className="blocked-item-reason">{item.reason}</div>
                  )}
                  <div className="blocked-item-date">
                    {new Date(item.created_at).toLocaleDateString()}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}







