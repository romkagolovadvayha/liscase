'use client';

import React, { useRef, useEffect, useState } from 'react';
import classNames from 'classnames';
import Icon from '@/components/icons/Icon';

interface Tab {
  id: string;
  label: string;
  icon?: string;
}

interface TabsProps {
  tabs: Tab[];
  activeTab: string;
  onChange: (tabId: string) => void;
  className?: string;
}

export default function Tabs({ tabs, activeTab, onChange, className }: TabsProps) {
  const [sliderStyle, setSliderStyle] = useState<{ left: number; width: number } | null>(null);
  const tabsRef = useRef<HTMLDivElement>(null);
  const itemsRef = useRef<Map<string, HTMLButtonElement>>(new Map());

  useEffect(() => {
    const updateSlider = () => {
      const activeButton = itemsRef.current.get(activeTab);
      const tabsContainer = tabsRef.current;
      
      if (activeButton && tabsContainer) {
        const containerRect = tabsContainer.getBoundingClientRect();
        const buttonRect = activeButton.getBoundingClientRect();
        
        setSliderStyle({
          left: buttonRect.left - containerRect.left,
          width: buttonRect.width,
        });
        return true;
      }
      return false;
    };

    // Используем requestAnimationFrame для более надежной инициализации
    let rafId: number;
    const tryUpdate = () => {
      if (!updateSlider()) {
        // Если не получилось, пробуем еще раз
        rafId = requestAnimationFrame(tryUpdate);
      }
    };

    // Первая попытка через requestAnimationFrame
    rafId = requestAnimationFrame(tryUpdate);
    
    // Также пробуем через небольшую задержку на случай, если RAF не сработал
    const timeout = setTimeout(() => {
      updateSlider();
    }, 100);
    
    // Обновляем при изменении размера окна
    const handleResize = () => {
      updateSlider();
    };
    window.addEventListener('resize', handleResize);
    
    return () => {
      cancelAnimationFrame(rafId);
      clearTimeout(timeout);
      window.removeEventListener('resize', handleResize);
    };
  }, [activeTab, tabs]);

  return (
    <div className={classNames('tabs', className)}>
      <div className="tabs__list" ref={tabsRef}>
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            ref={(el) => {
              if (el) {
                itemsRef.current.set(tab.id, el);
              } else {
                itemsRef.current.delete(tab.id);
              }
            }}
            className={classNames('tabs__item', {
              'tabs__item--active': activeTab === tab.id,
            })}
            onClick={() => onChange(tab.id)}
          >
            {tab.icon && <Icon name={tab.icon} fontSize="small" />}
            <span>{tab.label}</span>
          </button>
        ))}
        {sliderStyle && (
          <div
            className="tabs__slider"
            style={{
              left: `${sliderStyle.left}px`,
              width: `${sliderStyle.width}px`,
            }}
          />
        )}
      </div>
    </div>
  );
}

