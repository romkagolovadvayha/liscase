'use client';

import React, { useState } from 'react';
import { Settings } from '@mui/icons-material';
import type { SupportTicket } from '@/types/support';

interface SupportTicketHeaderProps {
  ticket: SupportTicket;
  user: {
    id: number;
    username: string;
    avatar: string;
    isAdmin: boolean;
  } | null;
  ticketUser: {
    id: number;
    username: string;
    blocked_support: boolean;
    blocked_support_at: string | null;
    status?: number;
  };
  onClose: () => void;
  onOpen: () => void;
  onMute?: (userId: number, blocked: boolean) => Promise<void>;
  onBlockChat?: (userId: number, blocked: boolean) => Promise<void>;
  onBlockAccount?: (userId: number, blocked: boolean) => Promise<void>;
  onShowUserTicketsHistory?: () => void;
}

export default function SupportTicketHeader({
  ticket,
  user,
  ticketUser,
  onClose,
  onOpen,
  onMute,
  onBlockChat,
  onBlockAccount,
  onShowUserTicketsHistory,
}: SupportTicketHeaderProps) {
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const [isMuting, setIsMuting] = useState(false);
  const [isBlockingChat, setIsBlockingChat] = useState(false);
  const [isBlockingAccount, setIsBlockingAccount] = useState(false);

  const isMuted = ticketUser.blocked_support_at && new Date(ticketUser.blocked_support_at).getTime() > Date.now();
  const isChatBlocked = ticketUser.blocked_support === true;
  const isAccountBlocked = ticketUser.status === 5;

  const handleMute = async (blocked: boolean) => {
    if (!onMute) return;
    setIsMuting(true);
    try {
      await onMute(ticketUser.id, blocked);
      setIsDropdownOpen(false);
    } catch (error) {
      console.error('Error muting user:', error);
    } finally {
      setIsMuting(false);
    }
  };

  const handleBlockChat = async (blocked: boolean) => {
    if (!onBlockChat) return;
    setIsBlockingChat(true);
    try {
      await onBlockChat(ticketUser.id, blocked);
      setIsDropdownOpen(false);
    } catch (error) {
      console.error('Error blocking chat:', error);
    } finally {
      setIsBlockingChat(false);
    }
  };

  const handleBlockAccount = async (blocked: boolean) => {
    if (!onBlockAccount) return;
    setIsBlockingAccount(true);
    try {
      await onBlockAccount(ticketUser.id, blocked);
      setIsDropdownOpen(false);
    } catch (error) {
      console.error('Error blocking account:', error);
    } finally {
      setIsBlockingAccount(false);
    }
  };

  return (
    <div className="support-ticket-header">
      <div className="support-ticket-header-title">
        <h2>{ticket.number === -1 ? 'Новый тикет' : `Тикет #${ticket.number}`}</h2>
        <span
          className={`support-ticket-header-status support-ticket-header-status--${ticket.status}`}
        >
          {ticket.status === 'open' ? 'Открыт' : 'Закрыт'}
        </span>
      </div>
      <div className="support-ticket-header-actions">
        {user && (user.isAdmin || user.username === 'moderator' || user.username === 'support') && (
          <div className="support-ticket-header-dropdown">
            <button
              type="button"
              className="button button-secondary"
              onClick={() => setIsDropdownOpen(!isDropdownOpen)}
            >
              <Settings /> Управление
            </button>
            {isDropdownOpen && (
              <div className="support-ticket-header-dropdown-menu">
                {isMuted ? (
                  <button
                    onClick={() => handleMute(false)}
                    disabled={isMuting}
                    className="support-ticket-header-dropdown-item"
                  >
                    Снять мут с игрока
                  </button>
                ) : (
                  <button
                    onClick={() => handleMute(true)}
                    disabled={isMuting}
                    className="support-ticket-header-dropdown-item"
                  >
                    Выдать мут на 30 минут
                  </button>
                )}
                <hr className="support-ticket-header-dropdown-divider" />
                {isChatBlocked ? (
                  <button
                    onClick={() => handleBlockChat(false)}
                    disabled={isBlockingChat}
                    className="support-ticket-header-dropdown-item"
                  >
                    Разблокировать чат
                  </button>
                ) : (
                  <button
                    onClick={() => handleBlockChat(true)}
                    disabled={isBlockingChat}
                    className="support-ticket-header-dropdown-item"
                  >
                    Заблокировать чат
                  </button>
                )}
                {user?.isAdmin && (
                  <>
                    <hr className="support-ticket-header-dropdown-divider" />
                    {isAccountBlocked ? (
                      <button
                        onClick={() => handleBlockAccount(false)}
                        disabled={isBlockingAccount}
                        className="support-ticket-header-dropdown-item"
                      >
                        Разблокировать аккаунт
                      </button>
                    ) : (
                      <button
                        onClick={() => handleBlockAccount(true)}
                        disabled={isBlockingAccount}
                        className="support-ticket-header-dropdown-item"
                      >
                        Заблокировать аккаунт
                      </button>
                    )}
                    <hr className="support-ticket-header-dropdown-divider" />
                    {onShowUserTicketsHistory && (
                      <button
                        onClick={() => {
                          onShowUserTicketsHistory();
                          setIsDropdownOpen(false);
                        }}
                        className="support-ticket-header-dropdown-item"
                      >
                        История тикетов пользователя
                      </button>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
        )}
        {ticket.number !== -1 && ticket.status === 'open' && (
          <button
            onClick={onClose}
            className="button button-secondary"
          >
            Закрыть тикет
          </button>
        )}
        {ticket.number !== -1 && ticket.status === 'closed' && user && (user.isAdmin || user.username === 'moderator' || user.username === 'support') && (
          <button
            onClick={onOpen}
            className="button button-secondary"
          >
            Открыть тикет
          </button>
        )}
      </div>
      {isDropdownOpen && (
        <div
          className="support-ticket-header-dropdown-overlay"
          onClick={() => setIsDropdownOpen(false)}
        />
      )}
    </div>
  );
}

