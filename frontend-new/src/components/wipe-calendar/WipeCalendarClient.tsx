'use client';

import React from 'react';

interface WipeCalendarClientProps {
  initialData: {
    wipes: Array<{
      id: number;
      server_tag: string;
      wipe_date: string;
      description?: string;
      created_at: string;
    }>;
  };
}

export default function WipeCalendarClient({
  initialData,
}: WipeCalendarClientProps) {
  return (
    <div className="wipe-calendar-page">
      <div className="wipe-calendar-container">
        <div className="wipe-calendar-header">
          <h1>Календарь вайпов</h1>
        </div>
        <div className="wipe-calendar-content">
          {initialData.wipes.length === 0 ? (
            <div className="wipe-calendar-empty">Нет запланированных вайпов</div>
          ) : (
            <div className="wipe-calendar-list">
              {initialData.wipes.map((wipe) => (
                <div key={wipe.id} className="wipe-calendar-item">
                  <div className="wipe-calendar-item-date">
                    {new Date(wipe.wipe_date).toLocaleDateString()}
                  </div>
                  <div className="wipe-calendar-item-server">{wipe.server_tag}</div>
                  {wipe.description && (
                    <div className="wipe-calendar-item-description">
                      {wipe.description}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}







