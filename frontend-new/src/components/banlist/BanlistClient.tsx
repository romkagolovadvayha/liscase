'use client';

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import moment from 'moment';
import 'moment/locale/ru';
import { Avatar } from 'antd';
import Icon from '@/components/icons/Icon';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import FormGroup from '@/components/forms/FormGroup';
import DataTable, { DataTableColumn } from '@/components/design-system/DataTable';
import { useBanlistData, type BanlistFilters, type BanlistResponse } from '@/hooks/useBanlistData';
import '@/styles/banlist.scss';

// Устанавливаем локаль для moment на клиенте
if (typeof window !== 'undefined') {
  moment.locale('ru');
}

interface Ban {
  id: number;
  username: string;
  steam_id: string;
  avatar: string;
  reason: string;
  banned_at: string;
  unbanned_at: string | null;
  server_id: number | null;
  server_name: string;
  server_tag?: string;
  first_seen: string | null;
}

interface Server {
  id: number;
  name: string;
  tag: string;
}

interface BanlistClientProps {
  servers: Server[];
  initialData?: BanlistResponse;
}

export default function BanlistClient({ servers, initialData }: BanlistClientProps) {
  const [page, setPage] = useState(1);

  // Фильтры
  const [steamId, setSteamId] = useState('');
  const [reason, setReason] = useState('');
  const [serverId, setServerId] = useState('');
  
  // Сортировка
  const [sortField, setSortField] = useState<'username' | 'server' | 'first_seen' | 'banned_at' | 'reason'>('banned_at');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');

  // Используем React Query для кэширования данных
  const filters: BanlistFilters = useMemo(() => ({
    page: page > 1 ? page : undefined,
    steam_id: steamId || undefined,
    reason: reason || undefined,
    server_id: serverId || undefined,
    sort: sortField,
    order: sortOrder,
  }), [page, steamId, reason, serverId, sortField, sortOrder]);

  const { data, isLoading, isFetching } = useBanlistData(filters, {
    initialData,
  });

  const bans = data?.data || [];
  const totalPages = data?.pagination.totalPages || 1;
  const total = data?.pagination.total || 0;
  const loading = isLoading || isFetching;

  // Обработчики фильтров
  const handleFilterChange = useCallback((key: string, value: string) => {
    setPage(1); // Сбрасываем на первую страницу при изменении фильтра

    // Обновляем состояние фильтров
    if (key === 'steam_id') setSteamId(value);
    if (key === 'reason') setReason(value);
    if (key === 'server_id') setServerId(value);
  }, []);

  // Очистка фильтров
  const handleClearFilters = useCallback(() => {
    setSteamId('');
    setReason('');
    setServerId('');
    setPage(1);
  }, []);

  // Изменение страницы
  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, []);

  // Форматирование даты
  const formatDate = useCallback((date: string | null) => {
    if (!date) return 'Никогда';
    return moment(date).format('DD.MM.YYYY HH:mm:ss');
  }, []);

  // Проверка, есть ли активные фильтры
  const hasActiveFilters = useMemo(() => {
    return !!(steamId || reason || serverId);
  }, [steamId, reason, serverId]);

  const handleSort = useCallback((field: string) => {
    const sortFieldTyped = field as 'username' | 'server' | 'first_seen' | 'banned_at' | 'reason';
    if (sortField === sortFieldTyped) {
      const newOrder = sortOrder === 'asc' ? 'desc' : 'asc';
      setSortOrder(newOrder);
      setPage(1);
    } else {
      setSortField(sortFieldTyped);
      setSortOrder('desc');
      setPage(1);
    }
  }, [sortField, sortOrder]);

  const columns: DataTableColumn<Ban>[] = useMemo(() => [
    {
      key: 'avatar',
      label: '',
      sortable: false,
      width: '50px',
      className: 'banlist-th-avatar',
      render: (ban) => (
        <Avatar
          src={ban.avatar}
          alt={ban.username}
          className="banlist-avatar"
          size="default"
        />
      ),
    },
    {
      key: 'username',
      label: 'Steam ID / Ник',
      sortable: true,
      render: (ban) => (
        <div className="banlist-user">
          <div className="banlist-username">{ban.username}</div>
          <div className="banlist-steam-id">{ban.steam_id}</div>
        </div>
      ),
    },
    {
      key: 'server',
      label: 'Сервер',
      sortable: true,
      render: (ban) => ban.server_name,
    },
    {
      key: 'first_seen',
      label: 'Впервые замечен',
      sortable: true,
      render: (ban) => (
        <div className="banlist-date">
          <div className="banlist-date-banned">
            <Icon name="calendar" fontSize="small" />
            <span>{formatDate(ban.first_seen)}</span>
          </div>
        </div>
      ),
    },
    {
      key: 'banned_at',
      label: 'Дата бана',
      sortable: true,
      render: (ban) => (
        <div className="banlist-date">
          <div className="banlist-date-banned">
            <Icon name="calendar" fontSize="small" />
            <span>{formatDate(ban.banned_at)}</span>
          </div>
          {ban.unbanned_at && (
            <div className="banlist-date-unbanned">
              <Icon name="check" fontSize="small" />
              <span>Снят: {formatDate(ban.unbanned_at)}</span>
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'reason',
      label: 'Причина',
      sortable: true,
      render: (ban) => (
        <div className="banlist-reason">{ban.reason}</div>
      ),
    },
  ], [formatDate]);

  return (
    <div className="banlist-page">
      <div className="banlist-header">
        <h1 className="banlist-title">
          Бан лист
          <span 
            className="banlist-title-icon"
            data-tooltip-id="banlist-tooltip"
            data-tooltip-content="Список игроков получивших бан на наших серверах."
          >
            <Icon name="info" fontSize="small" />
          </span>
        </h1>
      </div>

      {/* Фильтры */}
      <div className="banlist-filters">
        <div className="banlist-filters-row">
          <FormGroup label="Steam ID / Ник">
            <Input
              type="text"
              placeholder="Поиск по Steam ID или нику"
              value={steamId}
              onChange={(e) => handleFilterChange('steam_id', e.target.value)}
              leftIcon="person"
            />
          </FormGroup>

          <FormGroup label="Причина">
            <Input
              type="text"
              placeholder="Поиск по причине"
              value={reason}
              onChange={(e) => handleFilterChange('reason', e.target.value)}
              leftIcon="description"
            />
          </FormGroup>

          <FormGroup label="Сервер">
            <Select
              value={serverId}
              onChange={(e) => handleFilterChange('server_id', e.target.value)}
            >
              <option value="">Все сервера</option>
              {servers.map((server) => (
                <option key={server.id} value={server.id.toString()}>
                  {server.name}
                </option>
              ))}
            </Select>
          </FormGroup>
        </div>
      </div>

      {/* Таблица */}
      <DataTable
        data={bans}
        columns={columns}
        loading={loading}
        emptyMessage="Записи не найдены"
        sortField={sortField}
        sortOrder={sortOrder}
        onSort={handleSort}
        page={page}
        totalPages={totalPages}
        total={total}
        onPageChange={handlePageChange}
        pageSize={20}
      />
    </div>
  );
}

