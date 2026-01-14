'use client';

import React, { useState, useEffect, useCallback } from 'react';
import type { SupportTicket } from '@/types/support';
import SupportTicketList from './SupportTicketList';
import SupportCreateForm from './SupportCreateForm';
import SupportTicketClient from './SupportTicketClient';
import { useSupport } from './SupportProvider';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import { useUser } from '@/providers/UserProvider';
import { useSettings } from '@/hooks/useSettings';
import { getDefaultAvatar } from '@/lib/utils/settingsImage';

interface SupportUser {
  id: number;
  username: string;
  avatar: string;
  blocked_support: boolean;
  blocked_support_at: string | null;
  isAdmin: boolean;
}

interface SupportClientProps {
  initialData?: {
    tickets?: SupportTicket[];
    user?: SupportUser;
  };
}

export default function SupportClient({ initialData }: SupportClientProps = {}) {
  console.log('[SupportClient] ========== COMPONENT RENDERED ==========');
  console.log('[SupportClient] Component rendered at:', new Date().toISOString());
  const { openSupport } = useSupport();
  const { user: globalUser } = useUser();
  const { data: settings } = useSettings();
  const defaultAvatar = getDefaultAvatar(settings);
  const [tickets, setTickets] = useState<SupportTicket[]>(initialData?.tickets || []);
  // Используем данные пользователя из initialData или из глобального провайдера
  const [user, setUser] = useState<SupportUser | null>(() => {
    if (initialData?.user) {
      return initialData.user;
    }
    // Преобразуем глобальные данные пользователя в формат SupportUser
    if (globalUser) {
      return {
        id: globalUser.id,
        username: globalUser.username,
        avatar: globalUser.avatar || defaultAvatar,
        blocked_support: false, // Эти данные нужно получать отдельно, если нужны
        blocked_support_at: null,
        isAdmin: globalUser.isAdmin || false,
      };
    }
    return null;
  });
  
  // Инициализируем activeTicket из initialData, если есть открытые тикеты
  const getInitialActiveTicket = (): SupportTicket | null => {
    if (initialData?.tickets && initialData.tickets.length > 0) {
      const openTickets = initialData.tickets.filter((t: SupportTicket | null | undefined): t is SupportTicket => 
        t != null && t.status === 'open'
      );
      if (openTickets.length > 0) {
        // Сортируем по updated_at (последний обновленный)
        openTickets.sort((a: SupportTicket, b: SupportTicket) => {
          const dateA = new Date(a.updated_at || a.created_at).getTime();
          const dateB = new Date(b.updated_at || b.created_at).getTime();
          return dateB - dateA;
        });
        return openTickets[0];
      }
    }
    return null;
  };
  
  const [activeTicket, setActiveTicket] = useState<SupportTicket | null>(getInitialActiveTicket());
  const [loading, setLoading] = useState(!initialData); // Если есть initialData, не показываем загрузку
  const [ticketFilter, setTicketFilter] = useState<'open' | 'closed'>('open');
  
  console.log('[SupportClient] Component state - loading:', loading, 'tickets count:', tickets.length, 'filter:', ticketFilter);

  // Загрузка данных
  const loadData = useCallback(async () => {
    console.log('[SupportClient] loadData called');
    console.log('[SupportClient] window:', typeof window);
    console.log('[SupportClient] isAuthenticated:', isAuthenticated());
    
    if (typeof window === 'undefined' || !isAuthenticated()) {
      console.log('[SupportClient] loadData early return - window undefined or not authenticated');
      return;
    }

    // Если user уже есть (из initialData), не загружаем его повторно
    if (user) {
      console.log('[SupportClient] User already loaded, skipping user data fetch');
    }

    console.log('[SupportClient] Starting to load data...');
    setLoading(true);
    try {
      // Загружаем список тикетов
      console.log('[SupportClient] Fetching tickets...');
      const ticketsResponse = await apiClient.get('/support/tickets');
      console.log('[SupportClient] Tickets API response:', ticketsResponse);
      console.log('[SupportClient] Tickets API response.data:', ticketsResponse.data);
      console.log('[SupportClient] Tickets API response structure:', JSON.stringify(ticketsResponse.data, null, 2));
      
      // Проверяем структуру ответа напрямую
      const responseData = ticketsResponse.data;
      console.log('[SupportClient] responseData.success:', responseData?.success);
      console.log('[SupportClient] responseData.data:', responseData?.data);
      console.log('[SupportClient] responseData.data?.tickets:', responseData?.data?.tickets);
      console.log('[SupportClient] Is tickets array?', Array.isArray(responseData?.data?.tickets));
      
      if (responseData?.success) {
        // API возвращает data: {tickets: [...]}
        let ticketsData: SupportTicket[] = [];
        
        // Проверяем структуру ответа - приоритет: data.tickets > data > tickets
        if (responseData.data?.tickets && Array.isArray(responseData.data.tickets)) {
          ticketsData = responseData.data.tickets;
          console.log('[SupportClient] Found tickets in responseData.data.tickets');
        } else if (Array.isArray(responseData.data)) {
          ticketsData = responseData.data;
          console.log('[SupportClient] Found tickets in responseData.data (direct array)');
        } else if (Array.isArray(responseData.tickets)) {
          ticketsData = responseData.tickets;
          console.log('[SupportClient] Found tickets in responseData.tickets');
        } else {
          console.warn('[SupportClient] No tickets found in response!', responseData);
        }
        
        console.log('[SupportClient] Tickets data extracted:', ticketsData, 'Count:', ticketsData.length);
        if (ticketsData.length > 0) {
          console.log('[SupportClient] First ticket sample:', ticketsData[0]);
        }
        console.log('[SupportClient] Setting tickets state...');
        setTickets(ticketsData);
        console.log('[SupportClient] Tickets state set, ticketsData:', ticketsData);

        // Находим последний активный тикет (только открытый, отсортированный по updated_at)
        const openTickets = ticketsData.filter((t: SupportTicket | null | undefined): t is SupportTicket => 
          t != null && t.status === 'open'
        );
        if (openTickets.length > 0) {
          // Сортируем по updated_at (последний обновленный)
          openTickets.sort((a: SupportTicket, b: SupportTicket) => {
            const dateA = new Date(a.updated_at || a.created_at).getTime();
            const dateB = new Date(b.updated_at || b.created_at).getTime();
            return dateB - dateA;
          });
          setActiveTicket(openTickets[0]);
        } else {
          // Если нет открытых тикетов, не устанавливаем activeTicket
          // Это позволит показать форму создания нового тикета
          setActiveTicket(null);
        }
      }

      // Данные пользователя берутся из глобального провайдера через useEffect
    } catch (error) {
      console.error('Error loading support data:', error);
    } finally {
      setLoading(false);
    }
  }, [user]); // Добавляем user в зависимости, чтобы не загружать повторно

  // Слушаем события обновления списка тикетов от дочерних компонентов
  useEffect(() => {
    const handleTicketListRefresh = () => {
      console.log('[SupportClient] Ticket list refresh event received, reloading tickets');
      loadData();
    };

    const handleTicketUpdated = (event: Event) => {
      const customEvent = event as CustomEvent<{ ticketId: number; status?: string }>;
      // Обновляем только конкретный тикет в списке локально, без полной перезагрузки
      setTickets((prev) => {
        const updated = prev.map((ticket) => {
          if (ticket.id === customEvent.detail.ticketId) {
            const newStatus = customEvent.detail.status as 'open' | 'closed' | undefined;
            const statusChanged = newStatus && newStatus !== ticket.status;
            
            return {
              ...ticket,
              status: (newStatus || ticket.status) as 'open' | 'closed',
              updated_at: new Date().toISOString(), // Обновляем время последнего изменения
            };
          }
          return ticket;
        });
        return updated;
      });
      
      // Обновляем activeTicket, если это текущий тикет
      setActiveTicket((prev) => {
        if (prev && prev.id === customEvent.detail.ticketId) {
          return {
            ...prev,
            status: (customEvent.detail.status || prev.status) as 'open' | 'closed',
            updated_at: new Date().toISOString(),
          };
        }
        return prev;
      });
    };

    window.addEventListener('support-ticket-list-needs-refresh', handleTicketListRefresh);
    window.addEventListener('support-ticket-updated', handleTicketUpdated);
    
    return () => {
      window.removeEventListener('support-ticket-list-needs-refresh', handleTicketListRefresh);
      window.removeEventListener('support-ticket-updated', handleTicketUpdated);
    };
  }, [loadData]);

  // Первоначальная загрузка - только если нет initialData (т.е. мы на странице /support, а не в модальном окне)
  useEffect(() => {
    console.log('[SupportClient] useEffect for loadData called, has initialData:', !!initialData);
    // Загружаем данные только если:
    // 1. Нет initialData (значит мы на странице /support, а не в модальном окне)
    // 2. Пользователь авторизован
    if (!initialData && typeof window !== 'undefined' && isAuthenticated()) {
      console.log('[SupportClient] Calling loadData from useEffect (no initialData, on support page)');
      loadData();
    } else {
      console.log('[SupportClient] useEffect early return - has initialData or not authenticated');
    }
  }, [loadData, initialData]);

  // Отслеживаем изменения tickets и обновляем activeTicket
  useEffect(() => {
    console.log('[SupportClient] tickets state changed:', tickets, 'count:', tickets.length);
    
    // Если тикеты загружены, проверяем наличие открытых тикетов
    if (tickets.length > 0) {
      const openTickets = tickets.filter((t: SupportTicket | null | undefined): t is SupportTicket => 
        t != null && t.status === 'open'
      );
      if (openTickets.length > 0) {
        // Если есть открытые тикеты и activeTicket не установлен или не открыт, устанавливаем первый открытый
        if (!activeTicket || activeTicket.status !== 'open') {
          openTickets.sort((a: SupportTicket, b: SupportTicket) => {
            const dateA = new Date(a.updated_at || a.created_at).getTime();
            const dateB = new Date(b.updated_at || b.created_at).getTime();
            return dateB - dateA;
          });
          setActiveTicket(openTickets[0]);
        }
      } else {
        // Если нет открытых тикетов, сбрасываем activeTicket, чтобы показать форму создания
        if (activeTicket) {
          setActiveTicket(null);
        }
      }
    } else {
      // Если тикетов нет, сбрасываем activeTicket
      if (activeTicket) {
        setActiveTicket(null);
      }
    }
  }, [tickets, activeTicket]);

  // Логируем перед рендером
  useEffect(() => {
    console.log('[SupportClient] About to render SupportTicketList - tickets:', tickets, 'count:', tickets.length, 'filter:', ticketFilter);
  }, [tickets, ticketFilter]);

  const handleTicketSelect = async (ticket: SupportTicket) => {
    setActiveTicket(ticket);
  };

  const handleTicketCreated = (ticket: SupportTicket) => {
    // Добавляем новый тикет в список
    setTickets((prev) => [ticket, ...prev]);
    setActiveTicket(ticket);
  };

  const handleTicketUpdated = () => {
    // Перезагружаем список тикетов
    loadData();
  };

  if (loading) {
    return (
      <div className="support-page">
        <div className="support-container">
          <div className="support-header">
            <h1>Поддержка</h1>
          </div>
          <div className="support-main">
            <div className="support-loading">
              <p>Загрузка...</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="support-page">
        <div className="support-container">
          <div className="support-header">
            <h1>Поддержка</h1>
          </div>
          <div className="support-main">
            <div className="support-error">
              <p>Ошибка загрузки данных</p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="support-page" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <div className="support-container" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
        <div className="support-header">
          <h1>Поддержка</h1>
        </div>
        <div className="support-main">
          <div className="support-sidebar">
            <SupportTicketList
              tickets={tickets}
              onSelect={handleTicketSelect}
              currentTicketId={activeTicket?.id || null}
              filter={ticketFilter}
              onFilterChange={setTicketFilter}
            />
          </div>
          <div className="support-content">
            {activeTicket ? (
              <SupportTicketClient
                initialData={{
                  ticket: activeTicket,
                  messages: [],
                  tickets: tickets,
                  user: user,
                }}
                ticketNumber={activeTicket.number}
                onTicketUpdated={handleTicketUpdated}
              />
            ) : (
              <SupportCreateForm
                onTicketCreated={handleTicketCreated}
                user={user}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
