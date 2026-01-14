'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Home,
  BarChart,
  ShoppingBag,
  Block,
  Article,
  Dns,
  Domain,
  Radio,
  Palette,
  Description,
  Info,
  CalendarToday,
  Support,
  Map,
  TableChart,
  People,
  ChevronLeft,
  ChevronRight,
} from '@mui/icons-material';
import classNames from 'classnames';

interface MenuItem {
  label: string;
  href: string;
  icon: React.ReactNode;
  visibility?: boolean;
}

interface LeftMenuProps {}

export default function LeftMenu({}: LeftMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const pathname = usePathname();

  // Загружаем состояние из localStorage при монтировании
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const savedState = localStorage.getItem('leftMenuOpen');
      if (savedState === 'true') {
        setIsOpen(true);
        document.body.classList.add('left-menu-open');
      }
    }
  }, []);

  // Сохраняем состояние в localStorage и обновляем body класс
  useEffect(() => {
    if (typeof window !== 'undefined') {
      localStorage.setItem('leftMenuOpen', isOpen.toString());
      if (isOpen) {
        document.body.classList.add('left-menu-open');
      } else {
        document.body.classList.remove('left-menu-open');
      }
    }
  }, [isOpen]);

  const toggleMenu = () => {
    setIsOpen(!isOpen);
  };

  const isActive = (href: string) => {
    if (href === '/') {
      return pathname === '/';
    }
    return pathname.startsWith(href);
  };

  // Определяем пункты меню (можно будет расширить с настройками)
  const menuItems: MenuItem[] = [
    {
      label: 'Главная страница',
      href: '/',
      icon: <Home />,
    },
    {
      label: 'Статистика',
      href: '/servers',
      icon: <BarChart />,
    },
    {
      label: 'Получение предметов',
      href: '/store',
      icon: <ShoppingBag />,
    },
    {
      label: 'Бан-лист',
      href: '/banlist',
      icon: <Block />,
    },
    {
      label: 'Новости',
      href: '/posts',
      icon: <Article />,
    },
    {
      label: 'Наши сервера',
      href: '/servers',
      icon: <Dns />,
    },
    {
      label: 'Постройки',
      href: '/buildings',
      icon: <Domain />,
    },
    {
      label: 'Радиостанции',
      href: '/radio',
      icon: <Radio />,
    },
    {
      label: 'Ваши скины',
      href: '/custom-skins',
      icon: <Palette />,
    },
    {
      label: 'Правила серверов',
      href: '/servers/rules',
      icon: <Description />,
    },
    {
      label: 'Как получать скины',
      href: '/skindrops',
      icon: <Info />,
    },
    {
      label: 'Календарь вайпов',
      href: '/wipe-calendar',
      icon: <CalendarToday />,
    },
    {
      label: 'Поддержка',
      href: '/support',
      icon: <Support />,
    },
    {
      label: 'Выбор карты',
      href: '/maps',
      icon: <Map />,
    },
    {
      label: 'Таблица рейдера',
      href: '/raid-table',
      icon: <TableChart />,
    },
    {
      label: 'Реферальная система',
      href: '/referral',
      icon: <People />,
    },
  ];

  return (
    <aside className={classNames('left-menu', { 'left-menu--open': isOpen })}>
      <nav className="left-menu__nav">
        {/* Пункты меню */}
        <ul className="left-menu__list">
          {/* Кнопка свернуть/развернуть */}
          <li className="left-menu__item">
            <button
              className="left-menu__link left-menu__toggle"
              onClick={toggleMenu}
              aria-label={isOpen ? 'Свернуть меню' : 'Развернуть меню'}
              aria-expanded={isOpen}
            >
              <span className="left-menu__icon">
                {isOpen ? <ChevronLeft /> : <ChevronRight />}
              </span>
              {isOpen && <span className="left-menu__label">Свернуть меню</span>}
            </button>
          </li>
          
          {menuItems.map((item, index) => {
            if (item.visibility === false) return null;

            return (
              <li key={index} className="left-menu__item">
                <Link
                  href={item.href}
                  className={classNames('left-menu__link', {
                    'left-menu__link--active': isActive(item.href),
                  })}
                  title={!isOpen ? item.label : undefined}
                >
                  <span className="left-menu__icon">{item.icon}</span>
                  {isOpen && <span className="left-menu__label">{item.label}</span>}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </aside>
  );
}

