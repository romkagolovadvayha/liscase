'use client';

import React from 'react';
import Icon from '@/components/icons/Icon';
import { Tooltip } from 'react-tooltip';

export default function TypographyElements() {
  return (
    <div className="typography-elements">
      <div className="typography-section">
        <h3 className="mb-4">Заголовки</h3>
        <div className="typography-examples">
          <div className="typography-item">
            <h1>H1 Заголовок — 48×54</h1>
            <code className="typography-code">h1</code>
          </div>
          <div className="typography-item">
            <h2>H2 Заголовок — 32×40</h2>
            <code className="typography-code">h2</code>
          </div>
          <div className="typography-item">
            <h3>H3 Заголовок — 24×32</h3>
            <code className="typography-code">h3</code>
          </div>
          <div className="typography-item">
            <h4>H4 Заголовок — 20×24</h4>
            <code className="typography-code">h4 или .h4</code>
          </div>
          <div className="typography-item">
            <h5>H5 Заголовок — 16×24</h5>
            <code className="typography-code">h5</code>
          </div>
          <div className="typography-item">
            <p className="lead">Lead текст — 20×24 (Regular)</p>
            <code className="typography-code">.lead</code>
          </div>
        </div>
      </div>

      <div className="typography-section">
        <h3 className="mb-4">Тексты</h3>
        <div className="typography-examples">
          <div className="typography-item">
            <p className="p0">P0 Текст — 18×28 (Regular)</p>
            <code className="typography-code">p или .p0</code>
          </div>
          <div className="typography-item">
            <p className="p1">P1 Текст — 16×24 (Regular)</p>
            <code className="typography-code">.p1</code>
          </div>
          <div className="typography-item">
            <p className="p2">P2 Текст — 14×20 (Regular)</p>
            <code className="typography-code">.p2</code>
          </div>
          <div className="typography-item">
            <p className="p3">P3 Текст — 12×16 (Regular)</p>
            <code className="typography-code">.p3</code>
          </div>
        </div>
      </div>

      <div className="typography-section">
        <h3 className="mb-4">Заголовок страницы</h3>
        <div className="typography-examples">
          <div className="typography-item">
            <h1 className="page-title">
              Заголовок страницы
              <span 
                className="page-title-icon"
                data-tooltip-id="typography-tooltip"
                data-tooltip-content="Подсказка для заголовка"
                data-tooltip-place="top"
              >
                <Icon name="info" fontSize="small" />
              </span>
              <Tooltip id="typography-tooltip" />
            </h1>
            <code className="typography-code">.page-title с .page-title-icon</code>
          </div>
        </div>
      </div>

      <div className="typography-section">
        <h3 className="mb-4">Цвета текста</h3>
        <div className="typography-examples">
          <div className="typography-item">
            <p className="p2 text-text-main">Основной текст (--text-main)</p>
            <code className="typography-code">.text-text-main</code>
          </div>
          <div className="typography-item">
            <p className="p2 text-text-primary">Основной текст (--text-primary)</p>
            <code className="typography-code">.text-text-primary</code>
          </div>
          <div className="typography-item">
            <p className="p2 text-text-secondary">Вторичный текст (--text-secondary)</p>
            <code className="typography-code">.text-text-secondary</code>
          </div>
          <div className="typography-item">
            <p className="p2 text-text-teritiary">Третичный текст (--text-teritiary)</p>
            <code className="typography-code">.text-text-teritiary</code>
          </div>
        </div>
      </div>

      <div className="typography-section">
        <h3 className="mb-4">Выравнивание текста</h3>
        <div className="typography-examples">
          <div className="typography-item">
            <p className="p2 text-left">Текст слева</p>
            <code className="typography-code">.text-left</code>
          </div>
          <div className="typography-item">
            <p className="p2 text-center">Текст по центру</p>
            <code className="typography-code">.text-center</code>
          </div>
          <div className="typography-item">
            <p className="p2 text-right">Текст справа</p>
            <code className="typography-code">.text-right</code>
          </div>
        </div>
      </div>
    </div>
  );
}

