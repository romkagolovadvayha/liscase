'use client';

import React, { useState, useRef, useEffect, useMemo } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { Avatar } from 'antd';
import Icon from '@/components/icons/Icon';
import { useWebSocket } from '@/hooks/useWebSocket';
import { useNavigationLoading } from '@/hooks/useNavigationLoading';
import { WorkspacePremiumRounded } from '@mui/icons-material';
import moment from 'moment';
import 'moment/locale/ru';
import StatsLink from './StatsLink';

type Theme = 'original' | 'glamour' | 'winter' | 'summer' | 'dark';

interface MenuItem {
  label: string;
  href: string;
  icon?: string;
}

interface UserMenu {
  label: string;
  href: string;
  icon?: string;
}

interface HeaderProps {
  logo?: string;
  balance?: number;
  avatar?: string;
  username?: string;
  steamId?: string;
  isGuest?: boolean;
  menuItems?: MenuItem[];
  userMenuItems?: UserMenu[];
  activeVip?: {
    expires_at: string;
    timestamp: number;
  } | null;
}

export default function Header({
  logo = '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg',
  balance: initialBalance = 0,
  avatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
  username = 'Player123',
  steamId = '76561198012345678',
  isGuest = false,
  activeVip = null,
  menuItems = [
    { label: 'Маркет скинов', href: '/market/skins', icon: 'shopping-bag' },
    { label: 'Главная', href: '/', icon: 'home' },
    { label: 'Статистика', href: '/servers', icon: 'bar-chart' },
    { label: 'Получение предметов', href: '/store', icon: 'shopping-bag' },
    { label: 'Бан-лист', href: '/banlist', icon: 'block' },
    { label: 'Новости', href: '/posts', icon: 'article' },
    { label: 'Наши сервера', href: '/servers', icon: 'dns' },
    { label: 'Постройки', href: '/buildings', icon: 'domain' },
    { label: 'Радиостанции', href: '/radio', icon: 'radio' },
    { label: 'Ваши скины', href: '/custom-skins', icon: 'palette' },
    { label: 'Правила серверов', href: '/servers/rules', icon: 'description' },
    { label: 'Как получать скины', href: '/skindrops', icon: 'info' },
    { label: 'Календарь вайпов', href: '/wipe-calendar', icon: 'calendar' },
    { label: 'Поддержка', href: '/support', icon: 'support' },
    { label: 'Выбор карты', href: '/maps', icon: 'map' },
    { label: 'Таблица рейдера', href: '/raid-table', icon: 'table-chart' },
    { label: 'Реферальная система', href: '/referral', icon: 'people' },
    { label: 'Вайп-блок', href: '/servers/wipe-block', icon: 'calendar' },
  ],
  userMenuItems = [
    { label: 'Профиль', href: '/profile', icon: 'person' },
    { label: 'Настройки', href: '/profile/settings', icon: 'info' },
    { label: 'Бонусы и задания', href: '/tasks-v2', icon: 'crown' },
    { label: 'Вывод скинов', href: '/user/skins', icon: 'palette' },
    { label: 'Моя корзина', href: '/user/inventory', icon: 'shopping-bag' },
    { label: 'Пополнить баланс', href: '/user/payment', icon: 'account-balance-wallet' },
  ],
}: HeaderProps) {
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [isMoreMenuOpen, setIsMoreMenuOpen] = useState(false);
  const [isThemeMenuOpen, setIsThemeMenuOpen] = useState(false);
  const [currentTheme, setCurrentTheme] = useState<Theme>('original');
  const [loadingHref, setLoadingHref] = useState<string | null>(null);
  const [balance, setBalance] = useState(initialBalance);
  const themeMenuRef = useRef<HTMLLIElement>(null);
  const pathname = usePathname();
  const router = useRouter();
  const { isLoading: isRouteLoading } = useNavigationLoading();

  // Инициализируем moment локаль
  useEffect(() => {
    if (typeof window !== 'undefined') {
      moment.locale('ru');
    }
  }, []);

  // WebSocket подключение для обновления баланса
  const wsUrl = process.env.NEXT_PUBLIC_WS_URL || (typeof window !== 'undefined' ? `ws://${window.location.hostname}:4888` : undefined);
  const [wsToken, setWsToken] = useState<string | undefined>(undefined);
  const [wsSteamId, setWsSteamId] = useState<string | undefined>(steamId);

  // Получаем токен для WebSocket через API (так как cookie httpOnly)
  useEffect(() => {
    if (!isGuest && wsUrl) {
      fetch('/api/auth/ws-token')
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            console.log('[Header] Got WS token from API');
            setWsToken(data.token);
            setWsSteamId(data.steam_id);
          } else {
            console.log('[Header] Failed to get WS token:', data.message);
            setWsToken(undefined);
            setWsSteamId(undefined);
          }
        })
        .catch(error => {
          console.error('[Header] Error getting WS token:', error);
          setWsToken(undefined);
          setWsSteamId(undefined);
        });
    } else {
      setWsToken(undefined);
      setWsSteamId(undefined);
    }
  }, [isGuest, wsUrl]);

  const wsEnabled = !isGuest && !!wsToken && !!wsSteamId && !!wsUrl;

  useWebSocket({
    url: wsUrl,
    enabled: wsEnabled,
    token: wsToken,
    steamId: wsSteamId,
    onBalanceUpdate: (newBalance, balanceStr) => {
      console.log('[Header] Balance updated via WebSocket:', { newBalance, balanceStr });
      setBalance(newBalance);
    },
  });

  // Логирование для отладки
  useEffect(() => {
    if (typeof window !== 'undefined') {
      console.log('[Header] WebSocket setup:', {
        wsEnabled,
        wsUrl,
        isGuest,
        hasWsToken: !!wsToken,
        hasWsSteamId: !!wsSteamId,
      });
    }
  }, [wsEnabled, wsUrl, isGuest, wsToken, wsSteamId]);

  // Обновляем баланс при изменении initialBalance
  useEffect(() => {
    setBalance(initialBalance);
  }, [initialBalance]);
  
  // Функция для вычисления видимых элементов на основе ширины экрана
  const calculateVisibleItems = (items: MenuItem[]) => {
    if (typeof window === 'undefined') {
      // SSR: возвращаем первые 2 пункта по умолчанию, чтобы минимизировать "прыжок"
      return { visible: items.slice(0, 2), hidden: items.slice(2) };
    }
    
    const width = window.innerWidth;
    let visibleCount: number;
    
    if (width >= 1920) {
      visibleCount = 6;
    } else if (width >= 1440) {
      visibleCount = 4;
    } else if (width >= 1200) {
      visibleCount = 3;
    } else if (width >= 992) {
      visibleCount = 2;
    } else if (width >= 768) {
      visibleCount = 1;
    } else {
      visibleCount = 0;
    }

    const visible = items.slice(0, visibleCount);
    const hidden = items.slice(visibleCount);
    return { visible, hidden };
  };

  // Инициализируем с первыми 2 пунктами по умолчанию
  const [visibleItems, setVisibleItems] = useState<MenuItem[]>(menuItems.slice(0, 2));
  const [hiddenItems, setHiddenItems] = useState<MenuItem[]>(menuItems.slice(2));
  const userMenuRef = useRef<HTMLDivElement>(null);
  const moreMenuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Загружаем сохраненную тему из localStorage
    if (typeof window !== 'undefined') {
      const savedTheme = localStorage.getItem('theme') as Theme | null;
      if (savedTheme && savedTheme !== currentTheme) {
        setCurrentTheme(savedTheme);
        const root = document.documentElement;
        if (savedTheme === 'original') {
          root.removeAttribute('data-theme');
        } else {
          root.setAttribute('data-theme', savedTheme);
        }
      }
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const applyTheme = (theme: Theme) => {
    const root = document.documentElement;
    if (theme === 'original') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', theme);
    }
    localStorage.setItem('theme', theme);
  };

  const handleThemeChange = (theme: Theme) => {
    setCurrentTheme(theme);
    applyTheme(theme);
    setIsThemeMenuOpen(false);
  };

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      const target = event.target as HTMLElement;
      
      // Проверяем, не кликнули ли мы в подменю темы или в пункт меню темы
      const themeSubmenu = target?.closest('.header__theme-submenu');
      const themeMenuItem = target?.closest('.header__user-menu-item-wrapper');
      
      if (isUserMenuOpen && userMenuRef.current && !userMenuRef.current.contains(target as Node)) {
        // Если кликнули не в подменю темы и не в пункт меню темы, закрываем меню
        if (!themeSubmenu && !themeMenuItem) {
          setIsUserMenuOpen(false);
          setIsThemeMenuOpen(false);
        }
      }
      
      // Если кликнули вне подменю темы, но внутри меню пользователя, закрываем только подменю темы
      if (isThemeMenuOpen && themeMenuItem && !themeSubmenu) {
        // Не закрываем, если кликнули на сам пункт "Тема сайта"
        const themeMenuButton = target?.closest('.header__user-menu-item--with-submenu');
        if (!themeMenuButton) {
          setIsThemeMenuOpen(false);
        }
      }
      
      // Если кликнули полностью вне меню пользователя и подменю темы, закрываем все
      if (isThemeMenuOpen && !themeMenuItem && !themeSubmenu) {
        setIsThemeMenuOpen(false);
      }
      
      if (isMoreMenuOpen && moreMenuRef.current && !moreMenuRef.current.contains(target as Node)) {
        setIsMoreMenuOpen(false);
      }
    };

    if (isUserMenuOpen || isMoreMenuOpen || isThemeMenuOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isUserMenuOpen, isMoreMenuOpen, isThemeMenuOpen]);

  // Стабилизируем menuItems с помощью useMemo, чтобы избежать бесконечного цикла
  const stableMenuItems = useMemo(() => menuItems, [JSON.stringify(menuItems.map(item => ({ label: item.label, href: item.href })))]);

  // Функция для определения видимых элементов меню на основе размера экрана
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const checkVisibleItems = () => {
      const { visible, hidden } = calculateVisibleItems(stableMenuItems);
      setVisibleItems((prevVisible) => {
        // Проверяем, изменились ли элементы, чтобы избежать бесконечного цикла
        const visibleChanged = prevVisible.length !== visible.length || 
            prevVisible.some((item, index) => item.label !== visible[index]?.label || item.href !== visible[index]?.href);
        return visibleChanged ? visible : prevVisible;
      });
      setHiddenItems((prevHidden) => {
        // Проверяем, изменились ли элементы, чтобы избежать бесконечного цикла
        const hiddenChanged = prevHidden.length !== hidden.length || 
            prevHidden.some((item, index) => item.label !== hidden[index]?.label || item.href !== hidden[index]?.href);
        return hiddenChanged ? hidden : prevHidden;
      });
    };

    // Проверяем сразу при монтировании
    checkVisibleItems();
    
    // Используем debounce для resize, чтобы избежать частых обновлений
    let resizeTimer: NodeJS.Timeout;
    const handleResize = () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        checkVisibleItems();
      }, 150);
    };

    window.addEventListener('resize', handleResize);

    return () => {
      window.removeEventListener('resize', handleResize);
      clearTimeout(resizeTimer);
    };
  }, [stableMenuItems]);

  const formatBalance = (amount: number) => {
    return new Intl.NumberFormat('ru-RU').format(amount);
  };

  const handleLinkClick = (href: string, e: React.MouseEvent<HTMLAnchorElement>) => {
    if (href === pathname) {
      e.preventDefault();
      return;
    }
    setLoadingHref(href);
  };

  useEffect(() => {
    // Сбрасываем состояние загрузки когда путь изменился
    if (loadingHref && pathname === loadingHref) {
      setLoadingHref(null);
    }
  }, [pathname, loadingHref]);

  const isActive = (href: string) => {
    if (href === '/') {
      return pathname === '/';
    }
    return pathname.startsWith(href);
  };

  const isLoading = (href: string) => {
    // Проверяем как локальное состояние загрузки, так и состояние навигации
    return loadingHref === href || isRouteLoading(href);
  };

  return (
    <header className="header">
      <div className="header__container">
        {/* Логотип и меню */}
        <div className="header__left">
          <Link href="/" className="header__logo">
            <img src={logo} alt="Logo" />
          </Link>
          <nav className="header__nav">
            {visibleItems.map((item, index) => {
              const isStats = item.label === 'Статистика';
              const linkClassName = `header__nav-item ${isActive(item.href) ? 'header__nav-item--active' : ''} ${isLoading(item.href) ? 'header__nav-item--loading' : ''}`;
              const linkContent = (
                <>
                  {item.icon && <Icon name={isLoading(item.href) ? 'loading' : item.icon} fontSize="small" />}
                  <span>{item.label}</span>
                </>
              );
              
              if (isStats) {
                return (
                  <StatsLink
                    key={index}
                    className={linkClassName}
                  >
                    {linkContent}
                  </StatsLink>
                );
              }
              
              return (
                <Link
                  key={index}
                  href={item.href}
                  className={linkClassName}
                  onClick={(e) => handleLinkClick(item.href, e)}
                  prefetch={true}
                >
                  {linkContent}
                </Link>
              );
            })}
            {hiddenItems.length > 0 && (
              <div className="header__more" ref={moreMenuRef}>
                <button
                  className="header__nav-item header__more-button"
                  onClick={() => setIsMoreMenuOpen(!isMoreMenuOpen)}
                  aria-label="Еще"
                  aria-expanded={isMoreMenuOpen}
                >
                  <Icon name="more-vert" fontSize="small" />
                  <span>Еще</span>
                </button>
                {isMoreMenuOpen && (
                  <div className="header__more-menu">
                    <ul className="header__more-menu-list">
                      {hiddenItems.map((item, index) => {
                        const isStats = item.label === 'Статистика';
                        const linkClassName = `header__more-menu-item ${isActive(item.href) ? 'header__more-menu-item--active' : ''} ${isLoading(item.href) ? 'header__more-menu-item--loading' : ''}`;
                        const linkContent = (
                          <>
                            {item.icon && <Icon name={isLoading(item.href) ? 'loading' : item.icon} fontSize="small" />}
                            <span>{item.label}</span>
                          </>
                        );
                        
                        return (
                          <li key={index}>
                            {isStats ? (
                              <StatsLink
                                className={linkClassName}
                              >
                                {linkContent}
                              </StatsLink>
                            ) : (
                              <Link
                                href={item.href}
                                className={linkClassName}
                                onClick={(e) => {
                                  e.preventDefault();
                                  handleLinkClick(item.href, e);
                                  setIsMoreMenuOpen(false);
                                }}
                                prefetch={true}
                              >
                                {linkContent}
                              </Link>
                            )}
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                )}
              </div>
            )}
          </nav>
        </div>

        {/* Баланс и аватар / Кнопка входа */}
        <div className="header__right">
          {!isGuest && (
            <>
              <div className="header__balance">
                <span className="header__balance-amount">{formatBalance(balance)}</span>
                <span className="icons icons_16px icons_16px_coin"></span>
              </div>
              <div className="header__user" ref={userMenuRef}>
                <button
                  className="header__user-button"
                  onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                  aria-label="Меню пользователя"
                  aria-expanded={isUserMenuOpen}
                >
                  <Avatar src={avatar} alt={username} className="header__user-avatar" size="default" />
                </button>
                {isUserMenuOpen && (
                  <div className="header__user-menu">
                    <div className="header__user-menu-header">
                      <Avatar src={avatar} alt={username} className="header__user-menu-avatar" size="large" />
                      <div className="header__user-menu-info">
                        <span className="header__user-menu-username">{username}</span>
                        {steamId && (
                          <span className="header__user-menu-steam-id">Steam ID: {steamId}</span>
                        )}
                      </div>
                    </div>
                    {activeVip && (
                      <div className="header__user-menu-vip">
                        <WorkspacePremiumRounded className="header__user-menu-vip-icon" fontSize="small" />
                        <div className="header__user-menu-vip-info">
                          <span className="header__user-menu-vip-label">VIP статус</span>
                          <span className="header__user-menu-vip-time">
                            До: {moment.unix(activeVip.timestamp).format('DD.MM.YYYY HH:mm')}
                          </span>
                        </div>
                      </div>
                    )}
                    <ul className="header__user-menu-list">
                      {userMenuItems.map((item, index) => (
                        <li key={index}>
                          <Link href={item.href} className="header__user-menu-item">
                            {item.icon && <Icon name={item.icon} fontSize="small" />}
                            <span>{item.label}</span>
                          </Link>
                        </li>
                      ))}
                      <li ref={themeMenuRef} className="header__user-menu-item-wrapper">
                        <button
                          type="button"
                          className="header__user-menu-item header__user-menu-item--with-submenu"
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            setIsThemeMenuOpen(!isThemeMenuOpen);
                          }}
                          onMouseEnter={() => {
                            if (isUserMenuOpen) {
                              setIsThemeMenuOpen(true);
                            }
                          }}
                        >
                          <Icon name="palette" fontSize="small" />
                          <span>Тема сайта</span>
                          <Icon name="arrow-right" fontSize="small" />
                        </button>
                        {isThemeMenuOpen && isUserMenuOpen && (
                          <div
                            className="header__theme-submenu"
                            onClick={(e) => e.stopPropagation()}
                            onMouseEnter={() => setIsThemeMenuOpen(true)}
                            onMouseLeave={() => {
                              // Не закрываем при уходе мыши, только при клике вне
                            }}
                          >
                            <ul className="header__theme-submenu-list">
                              {[
                                { id: 'original' as Theme, label: 'Оригинальный', emoji: '🎨' },
                                { id: 'glamour' as Theme, label: 'Гламурный', emoji: '💅' },
                                { id: 'winter' as Theme, label: 'Зимний', emoji: '❄️' },
                                { id: 'summer' as Theme, label: 'Летний', emoji: '☀️' },
                                { id: 'dark' as Theme, label: 'Темный', emoji: '🌙' },
                              ].map((theme) => (
                                <li key={theme.id}>
                                  <button
                                    className={`header__theme-submenu-item ${
                                      currentTheme === theme.id ? 'header__theme-submenu-item--active' : ''
                                    }`}
                                    onClick={() => handleThemeChange(theme.id)}
                                  >
                                    <span className="header__theme-submenu-emoji">{theme.emoji}</span>
                                    <span>{theme.label}</span>
                                    {currentTheme === theme.id && (
                                      <Icon name="check" fontSize="small" />
                                    )}
                                  </button>
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                      </li>
                      <li className="header__user-menu-divider"></li>
                      <li>
                        <button
                          type="button"
                          className="header__user-menu-item"
                          style={{ width: '100%' }}
                          onClick={async (e) => {
                            e.preventDefault();
                            try {
                              const response = await fetch('/api/auth/logout', {
                                method: 'POST',
                              });
                              if (response.ok) {
                                // Перезагружаем страницу для обновления состояния
                                window.location.href = '/';
                              }
                            } catch (error) {
                              console.error('Error logging out:', error);
                            }
                          }}
                        >
                          <Icon name="logout" fontSize="small" />
                          <span>Выйти</span>
                        </button>
                      </li>
                    </ul>
                  </div>
                )}
              </div>
            </>
          )}
          {isGuest && (
            <Link href="/api/auth/steam" className="button button-primary button-size__s">
              <span className="button__text">
                Войти через Steam
                <Icon name="steam" faFixedSize={20} />
              </span>
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}

