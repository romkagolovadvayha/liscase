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
import { logout, startSteamAuth, isAuthenticated } from '@/lib/api/auth';
import apiClient from '@/lib/api/client';
import { useSettings } from '@/hooks/useSettings';
import { useUser } from '@/providers/UserProvider';
import ImpersonateModal from '@/components/admin/ImpersonateModal';
import { getLogo, getDefaultAvatar } from '@/lib/utils/settingsImage';

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
  logo: initialLogo,
  balance: initialBalance = 0,
  avatar: initialAvatar = '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png',
  username: initialUsername = 'Player123',
  steamId: initialSteamId = '76561198012345678',
  isGuest: initialIsGuest = false,
  activeVip: initialActiveVip = null,
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
  // Инициализируем logo из пропсов (полученных на сервере) или дефолтное значение
  const [logo, setLogo] = useState<string>(initialLogo || '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg');
  const [avatar, setAvatar] = useState(initialAvatar);
  const [username, setUsername] = useState(initialUsername);
  const [steamId, setSteamId] = useState(initialSteamId);
  const [activeVip, setActiveVip] = useState(initialActiveVip);
  const [isImpersonateModalOpen, setIsImpersonateModalOpen] = useState(false);
  const themeMenuRef = useRef<HTMLLIElement>(null);
  const pathname = usePathname();
  const router = useRouter();
  const { isLoading: isRouteLoading } = useNavigationLoading();

  // Используем глобальные данные пользователя
  const { user, isGuest, isLoading: isUserLoading } = useUser();
  
  // Логируем для отладки
  useEffect(() => {
    if (user) {
      console.log('[Header] User data:', {
        id: user.id,
        username: user.username,
        isAdmin: user.isAdmin,
        roles: user.roles,
      });
    }
  }, [user]);

  // Слушаем уведомления о покупке для обновления баланса
  useEffect(() => {
    const handlePurchaseCompleted = (event: CustomEvent) => {
      const newBalance = event.detail?.newBalance;
      if (typeof newBalance === 'number') {
        setBalance(newBalance);
      } else {
        // Если баланс не передан, обновляем его с сервера
        apiClient.get('/user/balance')
          .then(response => {
            if (response.data.success) {
              setBalance(response.data.data.personal?.balance || 0);
            }
          })
          .catch(error => {
            console.warn('Failed to fetch balance after purchase:', error);
          });
      }
    };

    window.addEventListener('purchase-completed', handlePurchaseCompleted as EventListener);
    
    return () => {
      window.removeEventListener('purchase-completed', handlePurchaseCompleted as EventListener);
    };
  }, []);

  // Обновляем локальное состояние из глобальных данных пользователя
  useEffect(() => {
    if (user) {
      setUsername(user.username || 'Player123');
      setSteamId(user.steam_id || '');
      const cdnUrl = settings?.site?.cdnUrl as string | null | undefined;
      const userAvatar = user.avatar || (settings ? getDefaultAvatar(settings, cdnUrl) : null) || initialAvatar;
      setAvatar(userAvatar);
      
      // Устанавливаем информацию о VIP, если она есть
      if (user.activeVip) {
        setActiveVip({
          expires_at: user.activeVip.expires_at,
          timestamp: user.activeVip.timestamp,
        });
      } else {
        setActiveVip(null);
      }
    } else if (!isUserLoading) {
      // Если данные загружены и пользователя нет, сбрасываем значения
      setUsername(initialUsername);
      setSteamId(initialSteamId);
      setAvatar(initialAvatar);
      setActiveVip(null);
    }
  }, [user, isUserLoading]);

  // Загружаем баланс отдельно (не из UserProvider, так как он обновляется через WebSocket)
  useEffect(() => {
    if (!isGuest && isAuthenticated()) {
      apiClient.get('/user/balance')
        .then(response => {
          if (response.data.success) {
            setBalance(response.data.data.personal?.balance || 0);
          }
        })
        .catch(error => {
          console.warn('Failed to fetch balance:', error);
        });
    }
  }, [isGuest]);

  // Используем React Query для настроек (для других компонентов, не для логотипа)
  const { data: settings } = useSettings();
  
  // Инициализируем логотип из пропсов (полученных на сервере)
  useEffect(() => {
    // Используем initialLogo, переданный с сервера (приоритет)
    if (initialLogo) {
      setLogo(initialLogo);
      return;
    }
    
    // Если initialLogo не передан, пытаемся получить из настроек на клиенте
    if (settings && Object.keys(settings).length > 0) {
      const cdnUrl = settings?.site?.cdnUrl as string | null | undefined;
      const logoUrl = getLogo(settings, cdnUrl);
      if (logoUrl) {
        setLogo(logoUrl);
      }
    }
  }, [settings, initialLogo]);
  
  // Обновляем аватар по умолчанию когда настройки загружены
  useEffect(() => {
    if (settings && !user?.avatar) {
      const cdnUrl = settings.site?.cdnUrl as string | null | undefined;
      const defaultAvatarUrl = getDefaultAvatar(settings, cdnUrl);
      if (defaultAvatarUrl) {
        setAvatar(defaultAvatarUrl);
      }
    }
  }, [settings, user?.avatar]);

  // Инициализируем moment локаль
  useEffect(() => {
    if (typeof window !== 'undefined') {
      moment.locale('ru');
    }
  }, []);

  // WebSocket подключение для обновления баланса
  const wsUrl = process.env.NEXT_PUBLIC_WS_URL || (typeof window !== 'undefined' ? `ws://45.129.128.211:4889` : undefined);
  const [wsToken, setWsToken] = useState<string | undefined>(undefined);
  const [wsSteamId, setWsSteamId] = useState<string | undefined>(steamId);

  // Получаем токен для WebSocket через API (так как cookie httpOnly)
  // TODO: ws-token endpoint пока не реализован в новом API
  useEffect(() => {
    if (!isGuest && wsUrl) {
      // Временно отключаем ws-token, так как endpoint не реализован в новом API
      // apiClient.get('/auth/ws-token')
      //   .then(res => {
      //     const data = res.data;
      //     if (data.success) {
      //       console.log('[Header] Got WS token from API');
      //       setWsToken(data.data.token);
      //       setWsSteamId(data.data.steam_id);
      //     } else {
      //       console.log('[Header] Failed to get WS token:', data.message);
      //       setWsToken(undefined);
      //       setWsSteamId(undefined);
      //     }
      //   })
      //   .catch(error => {
      //     console.error('[Header] Error getting WS token:', error);
      //     setWsToken(undefined);
      //     setWsSteamId(undefined);
      //   });
      setWsToken(undefined);
      setWsSteamId(undefined);
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

  // Убираем useEffect для initialBalance - баланс теперь загружается через API
  
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
                      {user?.isAdmin && (
                        <>
                          <li className="header__user-menu-divider"></li>
                          <li>
                            <button
                              type="button"
                              className="header__user-menu-item"
                              style={{ width: '100%' }}
                              onClick={(e) => {
                                e.preventDefault();
                                setIsImpersonateModalOpen(true);
                                setIsUserMenuOpen(false);
                              }}
                            >
                              <Icon name="person" fontSize="small" />
                              <span>Войти под пользователем</span>
                            </button>
                          </li>
                        </>
                      )}
                      <li className="header__user-menu-divider"></li>
                      <li>
                        <button
                          type="button"
                          className="header__user-menu-item"
                          style={{ width: '100%' }}
                          onClick={async (e) => {
                            e.preventDefault();
                            await logout();
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
            <button 
              onClick={() => startSteamAuth()} 
              className="button button-primary button-size__s"
            >
              <span className="button__text">
                Войти через Steam
                <Icon name="steam" faFixedSize={20} />
              </span>
            </button>
          )}
        </div>
      </div>

      {/* Модальное окно для входа под пользователем */}
      <ImpersonateModal
        isOpen={isImpersonateModalOpen}
        onClose={() => setIsImpersonateModalOpen(false)}
        onSuccess={() => {
          // Страница перезагрузится автоматически после успешного входа
        }}
      />
    </header>
  );
}

