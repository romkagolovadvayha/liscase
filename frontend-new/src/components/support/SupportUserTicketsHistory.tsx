'use client';

import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useSupport } from './SupportProvider';
import type { SupportTicket } from '@/types/support';
import apiClient from '@/lib/api/client';
import classNames from 'classnames';
import { Close } from '@mui/icons-material';

interface SupportUserTicketsHistoryProps {
  userId: number;
  currentTicketId: number;
  isAdmin: boolean;
  onClose?: () => void;
}

export default function SupportUserTicketsHistory({
  userId,
  currentTicketId,
  isAdmin,
  onClose,
}: SupportUserTicketsHistoryProps) {
  const { openSupport } = useSupport();
  const [userTickets, setUserTickets] = useState<SupportTicket[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    console.log('[SupportUserTicketsHistory] useEffect called - userId:', userId, 'isAdmin:', isAdmin, 'currentTicketId:', currentTicketId);
    if (!isAdmin || !userId) {
      console.log('[SupportUserTicketsHistory] Early return - not admin or no userId');
      return;
    }

    const loadUserTickets = async () => {
      console.log('[SupportUserTicketsHistory] Loading user tickets for userId:', userId);
      setLoading(true);
      try {
        const response = await apiClient.get(`/support/user-tickets?userId=${userId}`);
        console.log('[SupportUserTicketsHistory] API response:', response.data);
        if (response.data.success) {
          const ticketsData = response.data.data?.tickets || [];
          console.log('[SupportUserTicketsHistory] Loaded tickets:', ticketsData, 'count:', ticketsData.length);
          setUserTickets(ticketsData);
        }
      } catch (error) {
        console.error('[SupportUserTicketsHistory] Error loading user tickets:', error);
      } finally {
        setLoading(false);
      }
    };

    loadUserTickets();
  }, [userId, isAdmin, currentTicketId]);

  console.log('[SupportUserTicketsHistory] Render check - isAdmin:', isAdmin, 'userTickets.length:', userTickets.length, 'userId:', userId, 'loading:', loading);

  if (!isAdmin) {
    console.log('[SupportUserTicketsHistory] Not admin, returning null');
    return null;
  }

  const content = (
    <>
      <div 
        className="support-user-tickets-history-overlay"
        onClick={onClose}
      />
      <div className="support-user-tickets-history">
        <div className="support-user-tickets-history-header">
          <h3>История тикетов пользователя</h3>
          {onClose && (
            <button
              type="button"
              className="support-user-tickets-history-close"
              onClick={onClose}
              aria-label="Закрыть"
            >
              <Close />
            </button>
          )}
        </div>
      <div className="support-user-tickets-history-list">
        {loading ? (
          <div className="support-user-tickets-history-loading">
            <p>Загрузка...</p>
          </div>
        ) : userTickets.length <= 1 ? (
          <div className="support-user-tickets-history-loading">
            <p>У пользователя только один тикет</p>
          </div>
        ) : (
          userTickets.map((ticket) => (
            <div
              key={ticket.id}
              className={classNames('support-user-tickets-history-item', {
                'support-user-tickets-history-item--active': ticket.id === currentTicketId,
              })}
              onClick={() => openSupport(ticket.number)}
            >
              <div className="support-user-tickets-history-item-number">
                #{ticket.number}
              </div>
              <div className="support-user-tickets-history-item-status">
                <span
                  className={classNames('support-user-tickets-history-item-status-badge', {
                    'support-user-tickets-history-item-status-badge--open': ticket.status === 'open',
                    'support-user-tickets-history-item-status-badge--closed': ticket.status === 'closed',
                  })}
                >
                  {ticket.status === 'open' ? 'Открыт' : 'Закрыт'}
                </span>
              </div>
              <div className="support-user-tickets-history-item-date">
                {new Date(ticket.updated_at || ticket.created_at).toLocaleDateString('ru-RU', {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
                })}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
    </>
  );

  // Рендерим через portal, чтобы быть поверх всего
  if (typeof window !== 'undefined') {
    return createPortal(content, document.body);
  }

  return null;
}

