'use client';

import React, { useMemo, useCallback } from 'react';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import '@/styles/data-table.scss';

export interface DataTableColumn<T = any> {
  key: string;
  label: string;
  sortable?: boolean;
  render?: (item: T, index: number) => React.ReactNode;
  width?: string;
  className?: string;
}

export interface DataTableProps<T = any> {
  data: T[];
  columns: DataTableColumn<T>[];
  loading?: boolean;
  emptyMessage?: string;
  sortField?: string;
  sortOrder?: 'asc' | 'desc';
  onSort?: (field: string) => void;
  page?: number;
  totalPages?: number;
  total?: number;
  onPageChange?: (page: number) => void;
  pageSize?: number;
  className?: string;
}

export default function DataTable<T = any>({
  data,
  columns,
  loading = false,
  emptyMessage = 'Данные не найдены',
  sortField,
  sortOrder = 'desc',
  onSort,
  page = 1,
  totalPages = 1,
  total = 0,
  onPageChange,
  pageSize = 20,
  className = '',
}: DataTableProps<T>) {
  const handleSort = useCallback((field: string) => {
    if (onSort) {
      onSort(field);
    }
  }, [onSort]);

  const handlePageChange = useCallback((newPage: number) => {
    if (onPageChange && newPage >= 1 && newPage <= totalPages) {
      onPageChange(newPage);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, [onPageChange, totalPages]);

  if (loading) {
    return (
      <div className="data-table-wrapper">
        <div className="data-table-loading">
          <div className="data-table-spinner"></div>
          <span>Загрузка...</span>
        </div>
      </div>
    );
  }

  if (data.length === 0) {
    return (
      <div className="data-table-wrapper">
        <div className="data-table-empty">
          <Icon name="block" fontSize="large" />
          <p>{emptyMessage}</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`data-table-wrapper ${className}`}>
      <table className="data-table">
        <thead>
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                className={`
                  ${column.sortable ? 'data-table-th-sortable' : ''}
                  ${sortField === column.key ? `data-table-th-sortable--${sortOrder}` : ''}
                  ${column.className || ''}
                `}
                style={{ width: column.width }}
                onClick={() => column.sortable && handleSort(column.key)}
              >
                <span>{column.label}</span>
                {column.sortable && sortField === column.key && (
                  <Icon name={sortOrder === 'asc' ? 'arrow-up' : 'arrow-down'} fontSize="small" />
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((item, index) => (
            <tr key={index}>
              {columns.map((column) => (
                <td key={column.key} className={column.className}>
                  {column.render ? column.render(item, index) : String((item as any)[column.key] || '')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>

      {/* Пагинация */}
      {totalPages > 1 && (
        <div className="data-table-pagination">
          <Button
            variant="secondary"
            onClick={() => handlePageChange(page - 1)}
            disabled={page === 1}
            leftIcon="arrow-back"
            size="medium"
          >
            Назад
          </Button>
          
          <div className="data-table-pagination-info">
            Страница {page} из {totalPages} ({total} записей)
          </div>

          <Button
            variant="secondary"
            onClick={() => handlePageChange(page + 1)}
            disabled={page === totalPages}
            rightIcon="arrow-forward"
            size="medium"
          >
            Вперед
          </Button>
        </div>
      )}
    </div>
  );
}
