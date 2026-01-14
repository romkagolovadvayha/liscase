'use client';

import React from 'react';
import '@/styles/fullscreen-loader.scss';
import { useSettings } from '@/hooks/useSettings';
import { getLogo } from '@/lib/utils/settingsImage';

interface FullScreenLoaderProps {
  logo?: string;
}

export default function FullScreenLoader({ 
  logo: logoProp 
}: FullScreenLoaderProps) {
  const { data: settings } = useSettings();
  const cdnUrl = settings?.site?.cdnUrl as string | null | undefined;
  const logo = logoProp || getLogo(settings, cdnUrl);
  return (
    <div className="fullscreen-loader">
      <div className="fullscreen-loader__content">
        <div className="fullscreen-loader__logo">
          <img 
            src={logo} 
            alt="Logo" 
            className="fullscreen-loader__logo-image"
          />
        </div>
        <div className="fullscreen-loader__spinner">
          <div className="spinner"></div>
        </div>
      </div>
    </div>
  );
}

