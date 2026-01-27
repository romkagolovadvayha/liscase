'use client';

import React, { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import ProfileTabs from '@/components/profile/ProfileTabs';
import Tabs from '@/components/design-system/Tabs';
import Icon from '@/components/icons/Icon';
import DataTable, { DataTableColumn } from '@/components/design-system/DataTable';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import moment from 'moment';
import 'moment/locale/ru';
import '@/styles/profile.scss';

interface HistoryItem {
  comment: string;
  sum: string;
  created_at: string;
}

type SortField = 'comment' | 'created_at' | 'sum';
type SortOrder = 'asc' | 'desc';

export default function ProfileHistoryClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  const [loading, setLoading] = useState(true);
  const [history, setHistory] = useState<HistoryItem[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [sortField, setSortField] = useState<SortField>(searchParams.get('sort') as SortField || 'created_at');
  const [sortOrder, setSortOrder] = useState<SortOrder>((searchParams.get('order') as SortOrder) || 'desc');
  const [page, setPage] = useState(parseInt(searchParams.get('page') || '1', 10));
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [filterType, setFilterType] = useState<'all' | 'debit' | 'credit'>(searchParams.get('type') as 'all' | 'debit' | 'credit' || 'all');
  const isFetchingRef = useRef(false);
  const lastParamsRef = useRef<string>('');

  useEffect(() => {
    moment.locale('ru');
    const paramsKey = `${page}-${sortField}-${sortOrder}-${filterType}`;
    // Защита от повторных запросов с теми же параметрами
    if (isFetchingRef.current || lastParamsRef.current === paramsKey) {
      return;
    }
    lastParamsRef.current = paramsKey;
    fetchHistory();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, sortField, sortOrder, filterType]);

  const updateURL = useCallback((params: Record<string, string>) => {
    const newParams = new URLSearchParams();
    
    Object.entries(params).forEach(([key, value]) => {
      if (!value) return;
      
      // Не добавляем значения по умолчанию
      if (key === 'page' && value === '1') return;
      if (key === 'sort' && value === 'created_at') return;
      if (key === 'order' && value === 'desc') return;
      
      newParams.set(key, value);
    });

    const queryString = newParams.toString();
    router.push(`/profile/history${queryString ? `?${queryString}` : ''}`, { scroll: false });
  }, [router]);

  const fetchHistory = async () => {
    // Защита от повторных запросов
    if (isFetchingRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingRef.current = true;
      setLoading(true);
      setError(null);
      const params = new URLSearchParams();
      if (page > 1) params.set('page', page.toString());
      params.set('pageSize', '20');
      // Примечание: сортировка на сервере не поддерживается, фильтрация тоже

      const response = await apiClient.get(`/user/history?${params.toString()}`);
      const data = response.data;

      if (data.success && data.data) {
        // Преобразуем данные из API в формат компонента
        const operations = data.data.operations || [];
        let formattedHistory = operations.map((op: any) => ({
          comment: op.comment || '',
          sum: op.sum || '0',
          created_at: op.created_at || '',
        }));

        // Клиентская фильтрация по типу
        if (filterType !== 'all') {
          formattedHistory = formattedHistory.filter((item: any) => {
            const isNegative = item.sum.startsWith('-');
            if (filterType === 'debit') return isNegative;
            if (filterType === 'credit') return !isNegative && item.sum !== '0';
            return true;
          });
        }

        // Клиентская сортировка
        formattedHistory.sort((a: any, b: any) => {
          let comparison = 0;
          if (sortField === 'comment') {
            comparison = a.comment.localeCompare(b.comment);
          } else if (sortField === 'created_at') {
            comparison = new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
          } else if (sortField === 'sum') {
            const aSum = parseFloat(a.sum.replace(/[+\- ]/g, ''));
            const bSum = parseFloat(b.sum.replace(/[+\- ]/g, ''));
            comparison = aSum - bSum;
          }
          return sortOrder === 'asc' ? comparison : -comparison;
        });

        // Клиентская пагинация
        const totalCount = formattedHistory.length;
        const pageSize = 20;
        const totalPagesCalc = Math.ceil(totalCount / pageSize);
        const offset = (page - 1) * pageSize;
        formattedHistory = formattedHistory.slice(offset, offset + pageSize);
        
        setHistory(formattedHistory);
        setTotalPages(totalPagesCalc);
        setTotal(totalCount);
      } else {
        setError('Не удалось загрузить историю операций');
      }
    } catch (err: any) {
      if (err.response?.status === 401 || err.response?.status === 403) {
        setError('Требуется авторизация');
      } else {
        setError(err.message || 'Ошибка при загрузке истории');
      }
    } finally {
      setLoading(false);
      isFetchingRef.current = false;
    }
  };

  const formatDate = (dateString: string) => {
    return moment(dateString).fromNow();
  };

  const handleSort = useCallback((field: string) => {
    const sortFieldTyped = field as SortField;
    if (sortField === sortFieldTyped) {
      const newOrder = sortOrder === 'asc' ? 'desc' : 'asc';
      setSortOrder(newOrder);
      setPage(1);
      updateURL({ page: '1', sort: field, order: newOrder, type: filterType });
    } else {
      setSortField(sortFieldTyped);
      setSortOrder('desc');
      setPage(1);
      updateURL({ page: '1', sort: field, order: 'desc', type: filterType });
    }
  }, [sortField, sortOrder, filterType, updateURL]);

  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
    updateURL({ page: newPage.toString(), sort: sortField, order: sortOrder, type: filterType });
  }, [sortField, sortOrder, filterType, updateURL]);

  const formatSum = useCallback((sumString: string) => {
    // Извлекаем число из строки (убираем + и -)
    const amount = parseFloat(sumString.replace(/[+\- ]/g, ''));
    const isNegative = sumString.startsWith('-');
    
    // Если сумма равна 0, показываем "Бесплатно"
    if (amount === 0) {
      return { amount: 'Бесплатно', isNegative: false, isFree: true };
    }
    
    // Форматируем как деньги без копеек
    const formatted = Math.floor(amount).toLocaleString('ru-RU', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    });
    
    return { amount: formatted, isNegative, isFree: false };
  }, []);

  const columns: DataTableColumn<HistoryItem>[] = useMemo(() => [
    {
      key: 'comment',
      label: 'Детали',
      sortable: true,
      render: (item) => item.comment,
    },
    {
      key: 'created_at',
      label: 'Дата',
      sortable: true,
      render: (item) => formatDate(item.created_at),
    },
    {
      key: 'sum',
      label: 'Сумма',
      sortable: true,
      className: 'profile-history__sum-cell',
      render: (item) => {
        const { amount, isNegative, isFree } = formatSum(item.sum);
        if (isFree) {
          return (
            <span className="profile-history__sum--free">
              {amount}
            </span>
          );
        }
        return (
          <span className={isNegative ? 'profile-history__sum--minus' : 'profile-history__sum--plus'}>
            <span style={{ marginRight: '6px', verticalAlign: 'middle', display: 'inline-flex', alignItems: 'center' }}>
              <Icon
                name={isNegative ? 'minus' : 'plus'}
                fontSize="small"
              />
            </span>
            {amount} <span className="icons icons_16px icons_16px_coin"></span>
          </span>
        );
      },
    },
  ], [formatDate, formatSum]);

  const tabs = [
    { id: 'info', label: 'Информация о пользователе', icon: 'info', href: '/profile' },
    { id: 'history', label: 'История операций', icon: 'article', href: '/profile/history' },
    { id: 'referral', label: 'Реферальная система', icon: 'people', href: '/profile/referral' },
    { id: 'settings', label: 'Настройки', icon: 'palette', href: '/profile/settings' },
  ];

  const filterTabs = [
    { id: 'all', label: 'Все' },
    { id: 'debit', label: 'Списания' },
    { id: 'credit', label: 'Зачисления' },
  ];

  const handleFilterTypeChange = useCallback((tabId: string) => {
    const type = tabId as 'all' | 'debit' | 'credit';
    setFilterType(type);
    setPage(1);
    updateURL({ page: '1', type, sort: sortField, order: sortOrder });
  }, [sortField, sortOrder, updateURL]);

  return (
    <div>
      <ProfileTabs tabs={tabs} />

      <section className="profile-section">
        {/* Табы фильтрации */}
        <Tabs 
          tabs={filterTabs} 
          activeTab={filterType} 
          onChange={handleFilterTypeChange}
        />

        {error ? (
          <div className="error">{error}</div>
        ) : (
          <DataTable
            data={history}
            columns={columns}
            loading={loading}
            emptyMessage="История операций пуста."
            sortField={sortField}
            sortOrder={sortOrder}
            onSort={handleSort}
            page={page}
            totalPages={totalPages}
            total={total}
            onPageChange={handlePageChange}
            pageSize={20}
          />
        )}
      </section>
    </div>
  );
}

