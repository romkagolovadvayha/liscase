'use client';

import React from 'react';
import { FontAwesomeIcon as FAIcon } from '@fortawesome/react-fontawesome';
import {
  faSteam,
} from '@fortawesome/free-brands-svg-icons';
import {
  faCrown,
  faInfoCircle,
  faCalendarAlt,
  faTag,
  faArrowRight,
  faNewspaper,
  faTimes,
  faClose,
  faXmark,
  faSpinner,
} from '@fortawesome/free-solid-svg-icons';
import {
  faCalendar as farCalendar,
} from '@fortawesome/free-regular-svg-icons';

// Маппинг иконок Font Awesome
const faIconMap: Record<string, any> = {
  // Brands
  'steam': faSteam,
  
  // Solid
  'crown': faCrown,
  'info': faInfoCircle,
  'info-circle': faInfoCircle,
  'calendar': faCalendarAlt,
  'calendar-alt': faCalendarAlt,
  'tag': faTag,
  'arrow-right': faArrowRight,
  'arrow-forward': faArrowRight,
  'newspaper': faNewspaper,
  'times': faTimes,
  'close': faClose,
  'xmark': faXmark,
  'loading': faSpinner,
  'spinner': faSpinner,
  
  // Regular
  'calendar-regular': farCalendar,
};

interface FontAwesomeIconProps {
  icon: string;
  className?: string;
  size?: 'xs' | 'sm' | 'lg' | 'xl' | '2x' | '1x' | '2xs';
  color?: string;
  style?: React.CSSProperties;
  // Позволяем задать фиксированный размер в пикселях
  fixedSize?: number;
}

// Маппинг размеров Font Awesome в пиксели
const sizeMap: Record<string, number> = {
  'xs': 10,
  'sm': 14,
  '1x': 16,
  'lg': 20,
  'xl': 24,
  '2x': 32,
  '2xs': 7,
};

export default function FontAwesomeIcon({ 
  icon, 
  className, 
  size = '1x',
  color,
  style,
  fixedSize,
}: FontAwesomeIconProps) {
  const iconName = icon.toLowerCase();
  const faIcon = faIconMap[iconName];

  if (!faIcon) {
    console.warn(`Font Awesome icon "${icon}" not found in iconMap`);
    return null;
  }

  // Для Steam делаем размер еще крупнее, если не указан явно
  const finalSize = iconName === 'steam' && size === '1x' ? 'xl' : size;
  
  // Если задан фиксированный размер, используем его через inline стили
  const iconStyle = fixedSize 
    ? { ...style, fontSize: `${fixedSize}px`, width: `${fixedSize}px`, height: `${fixedSize}px` }
    : style;

  return (
    <FAIcon
      icon={faIcon}
      className={className}
      size={finalSize}
      color={color}
      style={iconStyle}
    />
  );
}

