'use client';

import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useSearchParams, useRouter, usePathname } from 'next/navigation';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import { Avatar } from 'antd';
import { toastSuccess, toastError, toastWarning, toastInfo } from '@/lib/toast';
import ProfileTabs from '@/components/profile/ProfileTabs';
import ProfileSectionSkeleton from '@/components/profile/ProfileSectionSkeleton';
import Tabs from '@/components/design-system/Tabs';
import Button from '@/components/forms/Button';
import Input from '@/components/forms/Input';
import Icon from '@/components/icons/Icon';
import DataTable, { DataTableColumn } from '@/components/design-system/DataTable';
import moment from 'moment';
import 'moment/locale/ru';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import '@/styles/profile.scss';

interface ReferralStats {
  partnerLink: string;
  referralPercent: number;
  referralClicks: number;
  registeredCount: number;
  playedCount: number;
  referralBalance: number;
}

interface ReferralListItem {
  userId: number;
  username: string;
  avatar: string;
  createdAt: string;
  hasBonus: boolean;
  hasSkinSent: boolean;
  hasHourInServer?: boolean;
  canGetReward?: boolean;
}

export default function ProfileReferralClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pathname = usePathname();
  
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<ReferralStats | null>(null);
  const [partnerLink, setPartnerLink] = useState<string>('');
  const [promocode, setPromocode] = useState<string>('');
  const [promocodeInput, setPromocodeInput] = useState<string>('');
  const [savingPromocode, setSavingPromocode] = useState(false);
  const [referralList, setReferralList] = useState<ReferralListItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [copiedLink, setCopiedLink] = useState(false);
  const [copiedPromocode, setCopiedPromocode] = useState(false);
  const [sortField, setSortField] = useState<'username' | 'createdAt' | 'hasBonus'>(searchParams.get('sort') as 'username' | 'createdAt' | 'hasBonus' || 'createdAt');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>((searchParams.get('order') as 'asc' | 'desc') || 'desc');
  const [page, setPage] = useState(parseInt(searchParams.get('page') || '1', 10));
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const isFetchingStatsRef = useRef(false);
  const isFetchingLinkRef = useRef(false);
  const isFetchingListRef = useRef(false);
  const lastActiveTabRef = useRef<string>('');

  // Устанавливаем локаль один раз
  useEffect(() => {
    moment.locale('ru');
  }, []);

  // Загрузка статистики (для вкладки "Условия программы")
  const fetchStats = useCallback(async () => {
    // Защита от повторных запросов
    if (isFetchingStatsRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingStatsRef.current = true;
      setLoading(true);
      setError(null);
      // Используем новый endpoint для условий программы
      const [conditionsResponse, inviteResponse] = await Promise.all([
        apiClient.get('/user/partner/conditions'),
        apiClient.get('/user/partner/invite')
      ]);
      
      const conditionsData = conditionsResponse.data;
      const inviteData = inviteResponse.data;

      if (inviteData.success) {
        setStats({
          partnerLink: inviteData.data.referral_link || inviteData.data.partnerLink || '',
          referralPercent: inviteData.data.referral_percent || 0,
          referralClicks: inviteData.data.referral_clicks || 0,
          registeredCount: inviteData.data.registered_count || 0,
          playedCount: inviteData.data.played_count || 0,
          referralBalance: inviteData.data.referral_balance || 0,
        });
        setPartnerLink(inviteData.data.referral_link || inviteData.data.partnerLink || '');
      } else {
        setError('Не удалось загрузить статистику реферальной системы');
      }
    } catch (err: any) {
      setError(err.message || 'Ошибка при загрузке статистики');
    } finally {
      setLoading(false);
      isFetchingStatsRef.current = false;
    }
  }, []);

  // Загрузка партнерской ссылки и промокода (для вкладки "Как приглашать?")
  const fetchPartnerLink = useCallback(async () => {
    // Защита от повторных запросов
    if (isFetchingLinkRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingLinkRef.current = true;
      setLoading(true);
      setError(null);
      // Используем новый endpoint для приглашения
      const [inviteResponse, promocodeResponse] = await Promise.all([
        apiClient.get('/user/partner/invite'),
        apiClient.get('/user/partner/promocode')
      ]);
      
      const inviteData = inviteResponse.data;
      const promocodeData = promocodeResponse.data;

      if (inviteData.success) {
        setPartnerLink(inviteData.data.referral_link || inviteData.data.partnerLink || '');
      } else {
        setError('Не удалось загрузить партнерскую ссылку');
      }

      if (promocodeData.success) {
        setPromocode(promocodeData.data.promocode || '');
        setPromocodeInput(promocodeData.data.promocode || '');
      }
    } catch (err: any) {
      setError(err.message || 'Ошибка при загрузке данных');
    } finally {
      setLoading(false);
      isFetchingLinkRef.current = false;
    }
  }, []);

  // Создание/обновление промокода
  const handleSavePromocode = async () => {
    if (!isAuthenticated()) {
      toastError('Требуется авторизация');
      return;
    }

    if (!promocodeInput.trim()) {
      toastWarning('Введите промокод');
      return;
    }

    try {
      setSavingPromocode(true);
      // TODO: referral/promocode endpoint пока не реализован в новом API
      const response = await apiClient.post('/user/partner/promocode', {
        promocode: promocodeInput,
      });

      const data = response.data;

      if (data.success) {
        setPromocode(data.promocode);
        toastSuccess('Промокод успешно создан');
      } else {
        toastError(data.message || 'Ошибка при создании промокода');
      }
    } catch (err: any) {
      toastError(err.message || 'Ошибка при создании промокода');
    } finally {
      setSavingPromocode(false);
    }
  };

  // Загрузка списка рефералов (для вкладки "Мои рефералы")
  const fetchReferralList = useCallback(async () => {
    // Защита от повторных запросов
    if (isFetchingListRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingListRef.current = true;
      setLoading(true);
      setError(null);
      // Используем новый endpoint для списка рефералов
      const response = await apiClient.get('/user/partner/referrals');
      const data = response.data;

      if (data.success && data.data) {
        // Нормализуем данные рефералов
        const referrals = (data.data.referrals || []).map((ref: any) => ({
          id: ref.id || ref.userId,
          userId: ref.userId || ref.id,
          username: ref.username || '',
          avatar: ref.avatar || '',
          createdAt: ref.created_at || ref.createdAt || '',
          hasBonus: ref.hasBonus || false,
          hasSkinSent: ref.hasSkinSent || false,
          hasHourInServer: ref.hasHourInServer || false,
          canGetReward: ref.canGetReward || false,
        }));
        setReferralList(referrals);
        setTotalPages(1); // TODO: pagination пока не поддерживается
        setTotal(data.data.total || referrals.length || 0);
      } else {
        setError('Не удалось загрузить список рефералов');
      }
    } catch (err: any) {
      setError(err.message || 'Ошибка при загрузке списка рефералов');
    } finally {
      setLoading(false);
      isFetchingListRef.current = false;
    }
  }, [page, sortField, sortOrder]);

  // Определяем активную вкладку реферальной системы
  const referralTabs = [
    { id: 'conditions', label: 'Условия программы', href: '/profile/referral' },
    { id: 'invite', label: 'Как приглашать?', href: '/profile/referral/invite' },
    { id: 'list', label: 'Мои рефералы', href: '/profile/referral/list' },
  ];

  const activeReferralTab = referralTabs.find(tab => {
    if (tab.href === '/profile/referral' && pathname === '/profile/referral') return true;
    if (tab.href !== '/profile/referral' && pathname.startsWith(tab.href)) return true;
    return false;
  })?.id || 'conditions';

  // Загружаем данные в зависимости от активной вкладки
  useEffect(() => {
    // Защита от повторных запросов при той же вкладке
    if (lastActiveTabRef.current === activeReferralTab) {
      return;
    }
    lastActiveTabRef.current = activeReferralTab;

    if (activeReferralTab === 'conditions') {
      fetchStats();
    } else if (activeReferralTab === 'invite') {
      fetchPartnerLink();
    } else if (activeReferralTab === 'list') {
      fetchReferralList();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeReferralTab]);

  const updateURL = useCallback((params: Record<string, string>) => {
    const newParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (!value) return;
      
      // Не добавляем значения по умолчанию
      if (key === 'page' && value === '1') return;
      if (key === 'sort' && value === 'createdAt') return;
      if (key === 'order' && value === 'desc') return;
      
      newParams.set(key, value);
    });

    const queryString = newParams.toString();
    router.push(`/profile/referral${queryString ? `?${queryString}` : ''}`, { scroll: false });
  }, [router]);

  const handleCopy = (type: 'link' | 'promocode') => {
    if (type === 'link') {
      setCopiedLink(true);
      toastSuccess('Ссылка скопирована в буфер обмена');
      setTimeout(() => setCopiedLink(false), 2000);
    } else {
      setCopiedPromocode(true);
      toastSuccess('Промокод скопирован в буфер обмена');
      setTimeout(() => setCopiedPromocode(false), 2000);
    }
  };

  const handleGetBonus = async (userId: number) => {
    if (!isAuthenticated()) {
      toastError('Требуется авторизация');
      return;
    }

    try {
      // TODO: referral/bonus endpoint пока не реализован в новом API
      const response = await apiClient.post(`/user/partner-bonus/${userId}`);
      const data = response.data;

      if (data.success) {
        toastSuccess('Награда успешно получена');
        // Обновляем данные в зависимости от активной вкладки
        if (activeReferralTab === 'list') {
          await fetchReferralList();
        } else if (activeReferralTab === 'conditions') {
          await fetchStats();
        }
      } else {
        toastError(data.message || 'Ошибка при получении награды');
      }
    } catch (err: any) {
      toastError(err.message || 'Ошибка при получении награды');
    }
  };

  const formatDate = (dateString: string) => {
    return moment(dateString).fromNow();
  };

  const handleSort = useCallback((field: string) => {
    const sortFieldTyped = field as 'username' | 'createdAt' | 'hasBonus';
    if (sortField === sortFieldTyped) {
      const newOrder = sortOrder === 'asc' ? 'desc' : 'asc';
      setSortOrder(newOrder);
      setPage(1);
      updateURL({ page: '1', sort: field, order: newOrder });
    } else {
      setSortField(sortFieldTyped);
      setSortOrder('desc');
      setPage(1);
      updateURL({ page: '1', sort: field, order: 'desc' });
    }
  }, [sortField, sortOrder, updateURL]);

  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
    updateURL({ page: newPage.toString(), sort: sortField, order: sortOrder });
  }, [sortField, sortOrder, updateURL]);

  const columns: DataTableColumn<ReferralListItem>[] = useMemo(() => [
    {
      key: 'avatar',
      label: '',
      sortable: false,
      width: '50px',
      render: (referral) => (
        <Avatar
          src={referral.avatar}
          alt={referral.username}
          className="profile-referral__avatar"
          size="default"
        />
      ),
    },
    {
      key: 'username',
      label: 'Ник',
      sortable: true,
      render: (referral) => referral.username,
    },
    {
      key: 'hasBonus',
      label: 'Более часа на сервере',
      sortable: true,
      render: (referral) => (referral.hasHourInServer || referral.hasBonus || referral.hasSkinSent) ? 'Да' : 'Нет',
    },
    {
      key: 'createdAt',
      label: 'Дата регистрации',
      sortable: true,
      render: (referral) => formatDate(referral.createdAt),
    },
    {
      key: 'reward',
      label: 'Награда',
      sortable: false,
      render: (referral) => (
        referral.hasBonus && referral.hasSkinSent ? (
          <Button variant="secondary" size="small" disabled>
            Получено
          </Button>
        ) : (referral.hasHourInServer || referral.canGetReward) ? (
          <Button
            variant="secondary"
            size="small"
            onClick={() => handleGetBonus(referral.userId)}
          >
            Получить награду
          </Button>
        ) : (
          <Button variant="secondary" size="small" disabled>
            Недоступно
          </Button>
        )
      ),
    },
  ], [formatDate, handleGetBonus]);

  const tabs = [
    { id: 'info', label: 'Информация о пользователе', icon: 'info', href: '/profile' },
    { id: 'history', label: 'История операций', icon: 'article', href: '/profile/history' },
    { id: 'referral', label: 'Реферальная система', icon: 'people', href: '/profile/referral' },
    { id: 'settings', label: 'Настройки', icon: 'palette', href: '/profile/settings' },
  ];

  const handleReferralTabChange = (tabId: string) => {
    const tab = referralTabs.find(t => t.id === tabId);
    if (tab) {
      router.push(tab.href);
    }
  };

  return (
    <div>
      <ProfileTabs tabs={tabs} />

      <section className="profile-section">
        <Tabs 
          tabs={referralTabs.map(tab => ({ id: tab.id, label: tab.label }))} 
          activeTab={activeReferralTab} 
          onChange={handleReferralTabChange}
        />
        
        {/* Условия программы */}
        {activeReferralTab === 'conditions' && (
          <>
            {loading ? (
              <ProfileSectionSkeleton />
            ) : error ? (
              <div className="error">{error}</div>
            ) : !stats ? (
              <p className="profile-section__hint">
                Реферальная система недоступна.
              </p>
            ) : (
              <div className="profile-referral__conditions">
                <div className="profile-section__item">
                  <p className="profile-section__hint" style={{ marginBottom: '24px' }}>
                    Реферальная программа — это отличная возможность заработать бонусы, приглашая друзей на наш сервер. 
                    Каждый новый участник, который зарегистрируется по вашей ссылке и проведет на сервере более часа, 
                    принесет вам награду. Чем больше активных рефералов, тем больше ваша прибыль!
                  </p>
                  <p className="profile-section__hint">
                    Чтобы начать зарабатывать, просто поделитесь своей персональной ссылкой с друзьями через вкладку 
                    "Как приглашать?". После того как ваш друг зарегистрируется и поиграет более часа, вы получите 
                    бонус, который можно вывести в магазин.
                  </p>
                </div>

                <div className="profile-referral__stats-table">
                  <table className="profile-referral__table">
                    <thead>
                      <tr>
                        <th>Показатель</th>
                        <th>Значение</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Ваш процент</td>
                        <td className="profile-referral__table-value">{stats.referralPercent.toLocaleString('ru-RU')}%</td>
                      </tr>
                      <tr>
                        <td>Переходов по ссылке</td>
                        <td className="profile-referral__table-value">{stats.referralClicks.toLocaleString('ru-RU')}</td>
                      </tr>
                      <tr>
                        <td>Зарегистрированных</td>
                        <td className="profile-referral__table-value">{stats.registeredCount.toLocaleString('ru-RU')}</td>
                      </tr>
                      <tr>
                        <td>Поигравших более часа</td>
                        <td className="profile-referral__table-value">{stats.playedCount.toLocaleString('ru-RU')}</td>
                      </tr>
                      <tr>
                        <td>Доступно к выводу</td>
                        <td className="profile-referral__table-value">
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', justifyContent: 'flex-end' }}>
                            {stats.referralBalance.toLocaleString('ru-RU')} 
                            <span className="icons icons_16px icons_16px_coin"></span>
                            {stats.referralBalance > 0 && (
                              <Button
                                variant="secondary"
                                size="small"
                                onClick={() => {
                                  // TODO: Открыть модальное окно для перевода
                                  toastInfo('Функция перевода будет реализована позже');
                                }}
                                style={{ marginLeft: '8px' }}
                              >
                                Перевести в магазин
                              </Button>
                            )}
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </>
        )}

        {/* Как приглашать? */}
        {activeReferralTab === 'invite' && (
          <>
            {loading ? (
              <ProfileSectionSkeleton />
            ) : error ? (
              <div className="error">{error}</div>
            ) : !partnerLink ? (
              <p className="profile-section__hint">
                Реферальная система недоступна.
              </p>
            ) : (
              <div className="profile-referral__invite">
                <div className="profile-referral__invite-block">
                  <div className="profile-referral__invite-header">
                    <Icon name="link" fontSize="medium" />
                    <h3 className="profile-referral__invite-title">Персональная ссылка</h3>
                  </div>
                  <p className="profile-referral__invite-description">
                    Поделитесь этой ссылкой с друзьями. Когда они зарегистрируются и поиграют более часа на сервере, вы получите награду!
                  </p>
                  <CopyToClipboard text={partnerLink} onCopy={() => handleCopy('link')}>
                    <div style={{ cursor: 'pointer' }}>
                      <Input
                        type="text"
                        value={partnerLink}
                        readOnly
                        rightIcon={copiedLink ? "check" : "copy"}
                        onRightIconClick={() => {}}
                        rightIconTitle={copiedLink ? 'Скопировано!' : 'Скопировать ссылку'}
                        className="profile-referral__invite-input"
                      />
                    </div>
                  </CopyToClipboard>
                </div>

                <div className="profile-referral__invite-block">
                  <div className="profile-referral__invite-header">
                    <Icon name="tag" fontSize="medium" />
                    <h3 className="profile-referral__invite-title">Собственный промокод</h3>
                  </div>
                  {promocode ? (
                    <>
                      <p className="profile-referral__invite-description">
                        Ваш промокод создан. Друзья могут использовать его при регистрации.
                      </p>
                      <CopyToClipboard text={promocode} onCopy={() => handleCopy('promocode')}>
                        <div style={{ cursor: 'pointer' }}>
                          <Input
                            type="text"
                            value={promocode}
                            readOnly
                            rightIcon={copiedPromocode ? "check" : "copy"}
                            onRightIconClick={() => {}}
                            rightIconTitle={copiedPromocode ? 'Скопировано!' : 'Скопировать промокод'}
                            className="profile-referral__invite-input"
                          />
                        </div>
                      </CopyToClipboard>
                    </>
                  ) : (
                    <>
                      <p className="profile-referral__invite-description">
                        Создайте уникальный промокод, который ваши друзья смогут использовать при регистрации. Разрешены только латинские буквы, цифры и дефис (5-120 символов).
                      </p>
                      <Input
                        type="text"
                        value={promocodeInput}
                        onChange={(e) => setPromocodeInput(e.target.value)}
                        placeholder="Введите промокод (5-120 символов)"
                        className="profile-referral__invite-input"
                      />
                      <Button
                        variant="primary"
                        size="medium"
                        onClick={handleSavePromocode}
                        disabled={savingPromocode || !promocodeInput.trim()}
                        style={{ marginTop: '16px', width: '100%' }}
                      >
                        {savingPromocode ? 'Создание...' : 'Создать промокод'}
                      </Button>
                    </>
                  )}
                </div>
              </div>
            )}
          </>
        )}

        {/* Мои рефералы */}
        {activeReferralTab === 'list' && (
          <div className="profile-referral__referrals">
            {error ? (
              <div className="error">{error}</div>
            ) : (
              <DataTable
                data={referralList}
                columns={columns}
                loading={loading}
                emptyMessage="У вас пока нет приглашенных пользователей."
                sortField={sortField}
                sortOrder={sortOrder}
                onSort={handleSort}
                page={page}
                totalPages={totalPages}
                total={total}
                onPageChange={handlePageChange}
                pageSize={10}
              />
            )}
          </div>
        )}
      </section>
    </div>
  );
}
