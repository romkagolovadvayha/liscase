'use client';

import React from 'react';

export default function SupportSkeleton() {
  return (
    <div className="support-skeleton">
      <div className="support-skeleton-sidebar">
        <div className="support-skeleton-header">
          <div className="support-skeleton-title"></div>
        </div>
        <div className="support-skeleton-items">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="support-skeleton-item">
              <div className="support-skeleton-avatar"></div>
              <div className="support-skeleton-item-content">
                <div className="support-skeleton-line support-skeleton-line--short"></div>
                <div className="support-skeleton-line support-skeleton-line--medium"></div>
              </div>
            </div>
          ))}
        </div>
      </div>
      <div className="support-skeleton-content">
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
    </div>
  );
}






