'use client';

import React from 'react';
import { Tooltip } from 'react-tooltip';
import 'react-tooltip/dist/react-tooltip.css';

export default function TooltipProvider({ children }: { children: React.ReactNode }) {
  return (
    <>
      {children}
      {/* Глобальные tooltips для всех элементов с data-tooltip-id */}
      <Tooltip id="product-count-tooltip" />
      <Tooltip id="stat-trak-tooltip" />
      <Tooltip id="awards-tooltip" />
      <Tooltip id="banlist-tooltip" />
      <Tooltip id="history-tooltip" />
    </>
  );
}


