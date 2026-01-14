'use client';

import React, { useState, useEffect, useRef } from 'react';
import { toastSuccess, toastError, toastInfo } from '@/lib/toast';
import Switch from '@/components/forms/Switch';
import Input from '@/components/forms/Input';
import Button from '@/components/forms/Button';
import Icon from '@/components/icons/Icon';
import Tabs from '@/components/design-system/Tabs';
import ProfileTabs from '@/components/profile/ProfileTabs';
import ProfileSectionSkeleton from '@/components/profile/ProfileSectionSkeleton';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import '@/styles/profile.scss';

interface ProfileData {
  raid_notify: boolean;
  ban_notify: boolean;
  trade_link: string;
  telegram_chat_id: string | null;
  discord_id: string | null;
  youtube_link: string;
  twitch_link: string;
  vk_link: string;
  telegram_link: string;
  is_hide_online: boolean;
  is_hide_team: boolean;
  hasVip: boolean;
  vipDrop: { id: number; name: string } | null;
  telegramBotUsername: string;
}

export default function SettingsClient() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [activeTab, setActiveTab] = useState('general');
  const isFetchingRef = useRef(false);

  useEffect(() => {
    fetchProfile();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchProfile = async () => {
    // Защита от повторных запросов
    if (isFetchingRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      toastError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingRef.current = true;
      setLoading(true);
      const response = await apiClient.get('/user/profile');
      const data = response.data;

      if (data.success) {
        setProfile(data.data);
      } else {
        toastError('Не удалось загрузить профиль');
      }
    } catch (err: any) {
      toastError(err.message || 'Ошибка при загрузке профиля');
    } finally {
      setLoading(false);
      isFetchingRef.current = false;
    }
  };

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!isAuthenticated()) {
      toastError('Требуется авторизация');
      return;
    }

    if (!profile) return;

    try {
      setSaving(true);

      const formData = new FormData(e.currentTarget);
      const data: any = {};

      // Общие настройки
      // Оповещения можно включить только если привязан Telegram бот
      if (profile.telegram_chat_id) {
        data.raid_notify = formData.get('raid_notify') === 'on';
        data.ban_notify = formData.get('ban_notify') === 'on';
      } else {
        // Если Telegram не привязан, сбрасываем настройки
        data.raid_notify = false;
        data.ban_notify = false;
      }
      data.trade_link = formData.get('trade_link')?.toString() || '';

      // Социальные сети
      data.youtube_link = formData.get('youtube_link')?.toString() || '';
      data.twitch_link = formData.get('twitch_link')?.toString() || '';
      data.vk_link = formData.get('vk_link')?.toString() || '';
      data.telegram_link = formData.get('telegram_link')?.toString() || '';

      // Настройки приватности (только для VIP)
      if (profile.hasVip) {
        data.is_hide_online = formData.get('is_hide_online') === 'on';
        data.is_hide_team = formData.get('is_hide_team') === 'on';
      }

      // Отвязка аккаунтов
      const telegramDisabled = formData.get('telegram_disabled');
      const discordDisabled = formData.get('discord_disabled');
      
      if (telegramDisabled === '1' || telegramDisabled === 'on') {
        data.telegram_disabled = true;
      }
      if (discordDisabled === '1' || discordDisabled === 'on') {
        data.discord_disabled = true;
      }

      const response = await apiClient.put('/user/profile', data);
      const result = response.data;

      if (result.success) {
        toastSuccess('Профиль успешно обновлен');
        await fetchProfile(); // Обновляем данные
      } else {
        toastError(result.message || 'Ошибка при обновлении профиля');
      }
    } catch (err: any) {
      toastError(err.message || 'Ошибка при обновлении профиля');
    } finally {
      setSaving(false);
    }
  };

  const handleVipClick = (e: React.MouseEvent) => {
    e.preventDefault();
    if (!profile?.vipDrop) return;
    // TODO: Открыть модальное окно с товаром VIP
    toastInfo('Функция покупки VIP будет реализована позже');
  };

  const tabs = [
    { id: 'general', label: 'Общие настройки' },
    { id: 'social', label: 'Социальные сети' },
    { id: 'privacy', label: 'Приватность' },
  ];

  const profileTabs = [
    { id: 'info', label: 'Информация о пользователе', icon: 'info', href: '/profile' },
    { id: 'history', label: 'История операций', icon: 'article', href: '/profile/history' },
    { id: 'referral', label: 'Реферальная система', icon: 'people', href: '/profile/referral' },
    { id: 'settings', label: 'Настройки', icon: 'palette', href: '/profile/settings' },
  ];

  if (loading) {
    return (
      <div>
        <ProfileTabs tabs={profileTabs} />
        <ProfileSectionSkeleton />
      </div>
    );
  }

  if (!profile) {
    return (
      <div>
        <ProfileTabs tabs={profileTabs} />
        <div className="error">Не удалось загрузить профиль</div>
      </div>
    );
  }

  return (
    <div>
      <ProfileTabs tabs={profileTabs} />


      <Tabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} />

      <form onSubmit={handleSubmit}>
        {/* Общие настройки */}
        {activeTab === 'general' && (
          <section className="profile-section">
            <div className="profile-section__item">
              <div>
                <Switch
                  name="raid_notify"
                  checked={profile.raid_notify}
                  disabled={!profile.telegram_chat_id}
                  onChange={(e) => setProfile({ ...profile, raid_notify: e.target.checked })}
                  label="Включить оповещения о рейдах"
                />
                {!profile.telegram_chat_id && (
                  <div className="profile-section__hint" style={{ marginTop: '8px' }}>
                    Для включения необходимо привязать Telegram бот
                  </div>
                )}
              </div>
            </div>

            <div className="profile-section__item">
              <div>
                <Switch
                  name="ban_notify"
                  checked={profile.ban_notify}
                  disabled={!profile.telegram_chat_id}
                  onChange={(e) => setProfile({ ...profile, ban_notify: e.target.checked })}
                  label="Оповещать о банах игроков, на которых вы отправили жалобу"
                />
                {!profile.telegram_chat_id && (
                  <div className="profile-section__hint" style={{ marginTop: '8px' }}>
                    Для включения необходимо привязать Telegram бот
                  </div>
                )}
              </div>
            </div>

            <div className="profile-section__item">
              <label className="profile-section__label">
                Ваша{' '}
                <a
                  href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="profile-section__link"
                >
                  трейд ссылка
                </a>{' '}
                на обмен
              </label>
              <Input
                name="trade_link"
                type="text"
                placeholder="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url"
                defaultValue={profile.trade_link}
                style={{ maxWidth: '700px', marginTop: '8px' }}
              />
            </div>

            <Button type="submit" variant="secondary" disabled={saving} loading={saving}>
              Сохранить
            </Button>
          </section>
        )}

        {/* Социальные сети */}
        {activeTab === 'social' && (
          <section className="profile-section">
            <div className="profile-section__item">
              <div className="profile-section__social-row">
                <span className="profile-section__label">Персональный телеграм бот</span>
                {profile.telegram_chat_id ? (
                  <Button
                    type="submit"
                    name="telegram_disabled"
                    value="1"
                    variant="secondary"
                    size="small"
                  >
                    Отвязать аккаунт
                  </Button>
                ) : (
                  <a
                    href={`https://t.me/${profile.telegramBotUsername}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="button button-secondary button-size__s"
                  >
                    Привязать аккаунт
                  </a>
                )}
              </div>
            </div>

            <div className="profile-section__item">
              <div className="profile-section__social-row">
                <span className="profile-section__label">Discord</span>
                {profile.discord_id ? (
                  <Button
                    type="submit"
                    name="discord_disabled"
                    value="1"
                    variant="secondary"
                    size="small"
                  >
                    Отвязать аккаунт
                  </Button>
                ) : (
                  <a
                    href={`${process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store'}/v1/auth/discord`}
                    className="button button-secondary button-size__s"
                  >
                    Привязать аккаунт
                  </a>
                )}
              </div>
            </div>

            <div className="profile-section__divider" />

            <p className="profile-section__hint">
              Укажите ссылки на ваши профили в социальных сетях. Они будут отображаться на вашей странице статистики, чтобы другие игроки могли найти вас.
            </p>

            <div className="profile-section__item">
              <label className="profile-section__label">
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                  <Icon name="youtube" faSize="sm" />
                  YouTube канал
                </span>
              </label>
              <Input
                name="youtube_link"
                type="url"
                placeholder="https://www.youtube.com/@channel"
                defaultValue={profile.youtube_link}
                style={{ maxWidth: '700px', marginTop: '8px' }}
              />
            </div>

            <div className="profile-section__item">
              <label className="profile-section__label">
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                  <Icon name="twitch" faSize="sm" />
                  Twitch канал
                </span>
              </label>
              <Input
                name="twitch_link"
                type="url"
                placeholder="https://www.twitch.tv/username"
                defaultValue={profile.twitch_link}
                style={{ maxWidth: '700px', marginTop: '8px' }}
              />
            </div>

            <div className="profile-section__item">
              <label className="profile-section__label">
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                  <Icon name="vk" faSize="sm" />
                  VK профиль
                </span>
              </label>
              <Input
                name="vk_link"
                type="url"
                placeholder="https://vk.com/username"
                defaultValue={profile.vk_link}
                style={{ maxWidth: '700px', marginTop: '8px' }}
              />
            </div>

            <div className="profile-section__item">
              <label className="profile-section__label">
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                  <Icon name="telegram" faSize="sm" />
                  Telegram канал или группа
                </span>
              </label>
              <Input
                name="telegram_link"
                type="url"
                placeholder="https://t.me/username"
                defaultValue={profile.telegram_link}
                style={{ maxWidth: '700px', marginTop: '8px' }}
              />
            </div>

            <Button type="submit" variant="secondary" disabled={saving} loading={saving}>
              Сохранить
            </Button>
          </section>
        )}

        {/* Настройки приватности */}
        {activeTab === 'privacy' && (
          <section className="profile-section">
            <p className="profile-section__hint">
              Эти настройки позволяют скрыть определенную информацию о вас от других игроков. Доступны только для пользователей с VIP статусом.
            </p>

            <div className="profile-section__item">
              <div>
                <Switch
                  name="is_hide_online"
                  checked={profile.is_hide_online}
                  disabled={!profile.hasVip}
                  onChange={(e) => {
                    if (profile.hasVip) {
                      setProfile({ ...profile, is_hide_online: e.target.checked });
                    }
                  }}
                  label="Скрывать статус онлайн/оффлайн"
                />
                <div className="profile-section__hint" style={{ marginTop: '4px' }}>
                  Если включено, другие игроки не увидят, находитесь ли вы сейчас в игре
                </div>
                {!profile.hasVip && (
                  <div style={{ fontSize: '12px', color: 'var(--primary-color)', marginTop: '4px' }}>
                    Доступно только для VIP
                  </div>
                )}
              </div>
              {!profile.hasVip && profile.vipDrop && (
                <div style={{ marginTop: '8px' }}>
                  <Button
                    variant="primary"
                    size="small"
                    onClick={handleVipClick}
                    leftIcon="crown"
                  >
                    Купить VIP
                  </Button>
                </div>
              )}
            </div>

            <div className="profile-section__item">
              <div>
                <Switch
                  name="is_hide_team"
                  checked={profile.is_hide_team}
                  disabled={!profile.hasVip}
                  onChange={(e) => {
                    if (profile.hasVip) {
                      setProfile({ ...profile, is_hide_team: e.target.checked });
                    }
                  }}
                  label="Скрывать список команды"
                />
                <div className="profile-section__hint" style={{ marginTop: '4px' }}>
                  Если включено, другие игроки не увидят список участников вашей команды
                </div>
                {!profile.hasVip && (
                  <div style={{ fontSize: '12px', color: 'var(--primary-color)', marginTop: '4px' }}>
                    Доступно только для VIP
                  </div>
                )}
              </div>
              {!profile.hasVip && profile.vipDrop && (
                <div style={{ marginTop: '8px' }}>
                  <Button
                    variant="primary"
                    size="small"
                    onClick={handleVipClick}
                    leftIcon="crown"
                  >
                    Купить VIP
                  </Button>
                </div>
              )}
            </div>

            <Button type="submit" variant="secondary" disabled={saving} loading={saving}>
              Сохранить
            </Button>
          </section>
        )}
      </form>
    </div>
  );
}

