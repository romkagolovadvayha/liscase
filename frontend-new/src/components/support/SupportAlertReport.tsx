'use client';

import React from 'react';
import { Info, Keyboard, AttachFile } from '@mui/icons-material';

export default function SupportAlertReport() {
  return (
    <div className="support-alert-report">
      <div className="support-alert-report-icon">
        <Info />
      </div>
      <div className="support-alert-report-content">
        <div className="support-alert-report-title">
          Как пожаловаться на игрока
        </div>
        <div className="support-alert-report-text">
          <p>
            Если вы хотите пожаловаться на игрока, нажмите в игре кнопку{' '}
            <span className="support-alert-report-key">
              <Keyboard /> F7
            </span>
            . Мы видим все ваши жалобы в игре, тикет в поддержку создавать не нужно.
          </p>
          <p>
            Если у вас есть доказательства и откаты, вы можете приложить их по кнопке{' '}
            <span className="support-alert-report-key">
              <AttachFile />
            </span>
            {' '}ниже.
          </p>
        </div>
      </div>
    </div>
  );
}

