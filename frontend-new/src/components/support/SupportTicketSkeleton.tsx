'use client';

import React from 'react';

export default function SupportTicketSkeleton() {
  return (
    <div className="support-ticket-skeleton">
      <div className="support-skeleton-content-header">
        <div className="support-skeleton-line support-skeleton-line--long"></div>
      </div>
      <div className="support-skeleton-messages">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="support-skeleton-message">
            <div className="support-skeleton-avatar"></div>
            <div className="support-skeleton-message-bubble"></div>
          </div>
        ))}
      </div>
      <div className="support-skeleton-form"></div>
    </div>
  );
}






