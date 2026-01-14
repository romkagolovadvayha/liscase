'use client';

import React, { useMemo } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import type { SupportTicket } from '@/types/support';
import classNames from 'classnames';
import { useSettings } from '@/hooks/useSettings';
import { getDefaultAvatar } from '@/lib/utils/settingsImage';

interface SupportTicketListProps {
  tickets: SupportTicket[];
  onSelect: (ticket: SupportTicket) => void;
  currentTicketId: number | null;
  filter?: 'open' | 'closed'; // Сделаем опциональным с дефолтным значением
  onFilterChange?: (filter: 'open' | 'closed') => void;
}

export default function SupportTicketList({
  tickets,
  onSelect,
  currentTicketId,
  filter = 'open', // Дефолтное значение
  onFilterChange,
}: SupportTicketListProps) {
  const { data: settings } = useSettings();
  const defaultAvatar = getDefaultAvatar(settings);
  
  // Фильтруем тикеты по статусу
  const filteredTickets = useMemo(() => {
    console.log('[SupportTicketList] Filtering tickets - tickets:', tickets, 'filter:', filter);
    if (tickets.length > 0) {
      console.log('[SupportTicketList] Tickets statuses:', tickets
        .filter(t => t && t.id)
        .map(t => ({ id: t.id, status: t.status })));
    }
    // Фильтруем только валидные тикеты (с id и status)
    const validTickets = tickets.filter((ticket) => ticket && ticket.id && ticket.status);
    const filtered = validTickets.filter((ticket) => ticket.status === filter);
    console.log('[SupportTicketList] Filtered result:', filtered);
    return filtered;
  }, [tickets, filter]);

  // Проверяем, есть ли тикеты каждого типа (только валидные тикеты)
  const validTickets = tickets.filter((t) => t && t.id && t.status);
  const hasOpenTickets = validTickets.some((t) => t.status === 'open');
  const hasClosedTickets = validTickets.some((t) => t.status === 'closed');
  const openTicketsCount = validTickets.filter((t) => t.status === 'open').length;
  const closedTicketsCount = validTickets.filter((t) => t.status === 'closed').length;

  const handleFilterChange = (newFilter: 'open' | 'closed') => {
    console.log('[SupportTicketList] Filter change requested:', newFilter);
    if (onFilterChange) {
      onFilterChange(newFilter);
    } else {
      console.warn('[SupportTicketList] onFilterChange is not provided');
    }
  };

  return (
    <div className="support-ticket-list">
      <div className="support-ticket-list-header">
        <h2>Тикеты</h2>
      </div>
      <div className="support-ticket-list-filter">
        <button
          type="button"
          className={classNames('support-ticket-list-filter-btn', {
            'support-ticket-list-filter-btn--active': filter === 'open',
          })}
          onClick={() => handleFilterChange('open')}
          disabled={!hasOpenTickets && !hasClosedTickets}
        >
          Открытые
          {openTicketsCount > 0 && (
            <span className="support-ticket-list-filter-count">
              {openTicketsCount}
            </span>
          )}
        </button>
        <button
          type="button"
          className={classNames('support-ticket-list-filter-btn', {
            'support-ticket-list-filter-btn--active': filter === 'closed',
          })}
          onClick={() => handleFilterChange('closed')}
          disabled={!hasOpenTickets && !hasClosedTickets}
        >
          Закрытые
          {closedTicketsCount > 0 && (
            <span className="support-ticket-list-filter-count">
              {closedTicketsCount}
            </span>
          )}
        </button>
      </div>
      <div className="support-ticket-list-items">
        {filteredTickets.length === 0 ? (
          <div className="support-ticket-list-empty">
            <p>Нет тикетов</p>
          </div>
        ) : (
          filteredTickets.map((ticket) => (
            <div
              key={ticket.id}
              className={classNames('support-ticket-item', {
                'support-ticket-item--active': ticket.id === currentTicketId,
                'support-ticket-item--closed': ticket.status === 'closed',
              })}
              onClick={() => onSelect(ticket)}
            >
              <div className="support-ticket-item-content">
                <div className="support-ticket-item-avatar">
                  <Image
                    src={ticket.user?.avatar || defaultAvatar}
                    alt={ticket.user?.username || 'Пользователь'}
                    width={40}
                    height={40}
                    className="support-ticket-item-avatar-img"
                  />
                </div>
                <div className="support-ticket-item-info">
                  <div className="support-ticket-item-header">
                    <span className="support-ticket-item-number">
                      #{ticket.number}
                    </span>
                    {(ticket.unread_count ?? 0) > 0 && (
                      <span className="support-ticket-item-unread">
                        {ticket.unread_count}
                      </span>
                    )}
                  </div>
                  <div className="support-ticket-item-user">
                    {ticket.user?.username || 'Пользователь'}
                  </div>
                  <div className="support-ticket-item-status">
                    {ticket.status === 'open' ? 'Открыт' : 'Закрыт'}
                  </div>
                </div>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

