'use client';

import React from 'react';
import { Tooltip } from 'react-tooltip';
import Button from '@/components/forms/Button';
import Icon from '@/components/icons/Icon';

export default function TooltipElements() {
  return (
    <div className="tooltip-elements">
      <div className="tooltip-section">
        <h3 className="mb-4">Тултипы</h3>
        <div className="tooltip-examples">
          <div className="tooltip-item">
            <Button 
              variant="secondary" 
              size="small"
              data-tooltip-id="tooltip-top"
              data-tooltip-content="Тултип сверху"
              data-tooltip-place="top"
            >
              Наведите сверху
            </Button>
            <Tooltip id="tooltip-top" />
            <code className="tooltip-code">placement="top"</code>
          </div>

          <div className="tooltip-item">
            <Button 
              variant="secondary" 
              size="small"
              data-tooltip-id="tooltip-bottom"
              data-tooltip-content="Тултип снизу"
              data-tooltip-place="bottom"
            >
              Наведите снизу
            </Button>
            <Tooltip id="tooltip-bottom" />
            <code className="tooltip-code">placement="bottom"</code>
          </div>

          <div className="tooltip-item">
            <Button 
              variant="secondary" 
              size="small"
              data-tooltip-id="tooltip-left"
              data-tooltip-content="Тултип слева"
              data-tooltip-place="left"
            >
              Наведите слева
            </Button>
            <Tooltip id="tooltip-left" />
            <code className="tooltip-code">placement="left"</code>
          </div>

          <div className="tooltip-item">
            <Button 
              variant="secondary" 
              size="small"
              data-tooltip-id="tooltip-right"
              data-tooltip-content="Тултип справа"
              data-tooltip-place="right"
            >
              Наведите справа
            </Button>
            <Tooltip id="tooltip-right" />
            <code className="tooltip-code">placement="right"</code>
          </div>

          <div className="tooltip-item">
            <span 
              className="tooltip-trigger"
              data-tooltip-id="tooltip-icon"
              data-tooltip-content="Тултип с иконкой"
            >
              <Icon name="info" fontSize="small" />
            </span>
            <Tooltip id="tooltip-icon" />
            <code className="tooltip-code">С иконкой</code>
          </div>

          <div className="tooltip-item">
            <Button 
              variant="secondary" 
              size="small"
              data-tooltip-id="tooltip-long"
              data-tooltip-content="Длинный текст тултипа, который может занимать несколько строк и показывать, как работает перенос текста"
            >
              Длинный тултип
            </Button>
            <Tooltip id="tooltip-long" />
            <code className="tooltip-code">Длинный текст</code>
          </div>
        </div>
      </div>
    </div>
  );
}



