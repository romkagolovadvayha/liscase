'use client';

import React from 'react';
import classNames from 'classnames';

// Импортируем Material Icons
import {
  ArrowForwardRounded,
  ArrowBackRounded,
  CalendarTodayRounded,
  LocalOfferRounded,
  WorkspacePremiumRounded,
  InfoRounded,
  NewspaperRounded,
  SearchRounded,
  RefreshRounded,
  HomeRounded,
  BarChartRounded,
  ArticleRounded,
  DnsRounded,
  PersonRounded,
  AccountBalanceWalletRounded,
  LogoutRounded,
  MoreVertRounded,
  ShoppingBagRounded,
  BlockRounded,
  DomainRounded,
  RadioRounded,
  PaletteRounded,
  DescriptionRounded,
  SupportRounded,
  MapRounded,
  TableChartRounded,
  PeopleRounded,
  CheckRounded,
  FilterListRounded,
  ClearRounded,
  CloseRounded,
  AddRounded,
  RemoveRounded,
  ArrowUpwardRounded,
  ArrowDownwardRounded,
  WarningRounded,
  ErrorRounded,
  CheckCircleRounded,
} from '@mui/icons-material';

// Импортируем Font Awesome компонент
import FontAwesomeIcon from './FontAwesomeIcon';

// Маппинг иконок для удобства использования
// Material Icons используются по умолчанию
const materialIconMap: Record<string, React.ComponentType<any>> = {
  'arrow-right': ArrowForwardRounded,
  'arrow-forward': ArrowForwardRounded,
  'arrow-left': ArrowBackRounded,
  'arrow-back': ArrowBackRounded,
  'calendar': CalendarTodayRounded,
  'calendar-alt': CalendarTodayRounded,
  'tag': LocalOfferRounded,
  'crown': WorkspacePremiumRounded,
  'info': InfoRounded,
  'newspaper': NewspaperRounded,
  'search': SearchRounded,
  'loading': RefreshRounded,
  'spinner': RefreshRounded,
  'home': HomeRounded,
  'bar-chart': BarChartRounded,
  'article': ArticleRounded,
  'dns': DnsRounded,
  'person': PersonRounded,
  'account-balance-wallet': AccountBalanceWalletRounded,
  'wallet': AccountBalanceWalletRounded,
  'logout': LogoutRounded,
  'more-vert': MoreVertRounded,
  'more': MoreVertRounded,
  'shopping-bag': ShoppingBagRounded,
  'block': BlockRounded,
  'domain': DomainRounded,
  'radio': RadioRounded,
  'palette': PaletteRounded,
  'description': DescriptionRounded,
  'support': SupportRounded,
  'map': MapRounded,
  'table-chart': TableChartRounded,
  'people': PeopleRounded,
  'check': CheckRounded,
  'check-circle': CheckCircleRounded,
  'warning': WarningRounded,
  'error': ErrorRounded,
  'filter': FilterListRounded,
  'filter-list': FilterListRounded,
  'clear': ClearRounded,
  'close': CloseRounded,
  'add': AddRounded,
  'plus': AddRounded,
  'remove': RemoveRounded,
  'minus': RemoveRounded,
  'arrow-up': ArrowUpwardRounded,
  'arrow-down': ArrowDownwardRounded,
};

// Иконки, которые нужно использовать из Font Awesome (если их нет в Material Icons)
const fontAwesomeIcons = ['steam', 'times', 'close', 'xmark', 'copy', 'link'];

interface IconProps {
  name: string;
  className?: string;
  fontSize?: 'inherit' | 'small' | 'medium' | 'large';
  color?: 'inherit' | 'action' | 'disabled' | 'primary' | 'secondary' | 'error' | 'info' | 'success' | 'warning';
  // Для Font Awesome
  faSize?: 'xs' | 'sm' | 'lg' | 'xl' | '2x' | '1x' | '2xs';
  faColor?: string;
  // Фиксированный размер в пикселях для Font Awesome
  faFixedSize?: number;
}

export default function Icon({ 
  name, 
  className, 
  fontSize = 'inherit', 
  color = 'inherit',
  faSize = '1x',
  faColor,
  faFixedSize,
}: IconProps) {
  const iconName = name.toLowerCase();
  
  // Проверяем, нужно ли использовать Font Awesome
  if (fontAwesomeIcons.includes(iconName)) {
    return (
      <FontAwesomeIcon
        icon={iconName}
        className={className}
        size={faSize}
        color={faColor}
        fixedSize={faFixedSize}
      />
    );
  }
  
  // Используем Material Icons
  const IconComponent = materialIconMap[iconName];

  if (!IconComponent) {
    // Fallback на Font Awesome, если иконка не найдена в Material Icons
    return (
      <FontAwesomeIcon
        icon={iconName}
        className={className}
        size={faSize}
        color={faColor}
        fixedSize={faFixedSize}
      />
    );
  }

  return (
    <IconComponent
      className={classNames('mui-icon', className)}
      fontSize={fontSize}
      color={color}
    />
  );
}
