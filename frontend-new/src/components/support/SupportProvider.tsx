'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import SupportModal from './SupportModal';
import SupportIcon from './SupportIcon';
import SupportAuthModal from './SupportAuthModal';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';

interface SupportContextType {
  openSupport: (ticketNumber?: number) => void;
  closeSupport: () => void;
  isOpen: boolean;
  isLoading: boolean;
}

const SupportContext = createContext<SupportContextType | undefined>(undefined);

export function useSupport() {
  const context = useContext(SupportContext);
  if (!context) {
    throw new Error('useSupport must be used within SupportProvider');
  }
  return context;
}

export function SupportProvider({ children }: { children: React.ReactNode }) {
  const [isOpen, setIsOpen] = useState(false); // Не открываем автоматически
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false); // Модальное окно авторизации
  const [ticketNumber, setTicketNumber] = useState<number | undefined>();
  const [supportData, setSupportData] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(false);

  const fetchSupportData = async (ticketNum?: number) => {
    setIsLoading(true);
    try {
      if (ticketNum) {
        // Загружаем данные тикета и список всех тикетов параллельно
        // Данные пользователя берем из глобального провайдера
        const [ticketResponse, ticketsResponse] = await Promise.all([
          apiClient.get(`/support/tickets/${ticketNum}`),
          apiClient.get('/support/tickets'),
        ]);
        
        if (ticketResponse.data.success) {
          const ticketData = ticketResponse.data.data;
          let ticketsData: any[] = [];
          
          // Извлекаем список тикетов из ответа
          if (ticketsResponse.data.success) {
            const ticketsDataResponse = ticketsResponse.data.data;
            if (ticketsDataResponse?.tickets && Array.isArray(ticketsDataResponse.tickets)) {
              ticketsData = ticketsDataResponse.tickets;
            } else if (Array.isArray(ticketsDataResponse)) {
              ticketsData = ticketsDataResponse;
            }
          }
          
          // Данные пользователя берем из глобального провайдера (через useUser)
          // Они будут переданы в SupportModal, который передаст их в SupportClient
          
          // Преобразуем данные в формат, ожидаемый компонентами
          // user будет получен из глобального провайдера в SupportModal
          setSupportData({
            ticket: ticketData.ticket,
            messages: ticketData.messages || [],
            tickets: ticketsData, // Список всех тикетов для навигации
            reports: [],
            user: null, // Данные пользователя будут получены из UserProvider
          });
        }
      } else {
        // Загружаем список тикетов - НЕ устанавливаем supportData, пусть SupportClient загрузит сам
        // Это позволит SupportClient загрузить данные и управлять состоянием
        setSupportData(null);
      }
    } catch (error: any) {
      console.error('Error fetching support data:', error);
      // Если токен истек, не устанавливаем null, чтобы не сломать UI
      // Пользователь может обновить страницу или перелогиниться
      if (error?.response?.status === 401) {
        console.warn('JWT token expired. Please refresh the page or login again.');
      }
      setSupportData(null);
    } finally {
      setIsLoading(false);
    }
  };

  const openSupport = async (ticketNum?: number) => {
    // Проверяем авторизацию перед открытием
    if (!isAuthenticated()) {
      setIsAuthModalOpen(true);
      return;
    }
    
    // Мгновенно открываем модальное окно и показываем skeleton
    setIsOpen(true);
    setIsLoading(true);
    
    // Если переключаемся на другой тикет, сразу обновляем номер
    const isSwitchingTicket = ticketNum && ticketNum !== ticketNumber;
    
    if (isSwitchingTicket) {
      // Мгновенное переключение: обновляем номер тикета
      setTicketNumber(ticketNum);
      // Сохраняем список тикетов, чтобы он оставался видимым
      if (supportData?.tickets) {
        setSupportData({ ...supportData, ticket: undefined, messages: undefined });
      } else {
        setSupportData(null);
      }
    } else if (!ticketNum) {
      // Если тикет не указан, очищаем данные для показа формы создания
      setTicketNumber(undefined);
      setSupportData(null);
    } else if (ticketNum !== ticketNumber) {
      // Если это новый тикет, обновляем номер
      setTicketNumber(ticketNum);
      setSupportData(null);
    }
    
    // Загружаем данные асинхронно
    if (!ticketNum) {
      // Если тикет не указан, ищем последний открытый тикет
      try {
        const response = await apiClient.get('/support/tickets');
        if (response.data.success) {
          const data = response.data.data;
          const tickets = data.tickets || [];
          // Ищем последний открытый тикет (сортируем по updated_at DESC и берем первый открытый)
          const openTickets = tickets.filter((t: any) => t.status === 'open');
          if (openTickets.length > 0) {
            // Сортируем по updated_at (последний обновленный)
            openTickets.sort((a: any, b: any) => {
              const dateA = new Date(a.updated_at || a.created_at).getTime();
              const dateB = new Date(b.updated_at || b.created_at).getTime();
              return dateB - dateA;
            });
            ticketNum = openTickets[0].number;
            setTicketNumber(ticketNum);
          }
          // Если открытых тикетов нет, ticketNum останется undefined
          // и будет показана форма создания нового тикета
        }
      } catch (error) {
        console.error('Error fetching tickets:', error);
      }
    }
    
    // Загружаем данные асинхронно
    await fetchSupportData(ticketNum);
  };

  const closeSupport = () => {
    setIsOpen(false);
    setTicketNumber(undefined);
    setSupportData(null);
  };

  useEffect(() => {
    const handleOpenSupport = (e: CustomEvent) => {
      openSupport();
    };

    const handleOpenSupportAuth = (e: CustomEvent) => {
      setIsAuthModalOpen(true);
    };

    window.addEventListener('openSupport' as any, handleOpenSupport);
    window.addEventListener('openSupportAuth' as any, handleOpenSupportAuth);

    return () => {
      window.removeEventListener('openSupport' as any, handleOpenSupport);
      window.removeEventListener('openSupportAuth' as any, handleOpenSupportAuth);
    };
  }, []);

  const closeAuthModal = () => {
    setIsAuthModalOpen(false);
  };

  const handleAuthSuccess = () => {
    setIsAuthModalOpen(false);
    // После успешной авторизации открываем поддержку
    openSupport();
  };

  return (
    <SupportContext.Provider value={{ openSupport, closeSupport, isOpen, isLoading }}>
      {children}
      <SupportIcon />
      {isAuthModalOpen && (
        <SupportAuthModal
          isOpen={isAuthModalOpen}
          onClose={closeAuthModal}
          onAuthSuccess={handleAuthSuccess}
        />
      )}
      {isOpen && (
        <SupportModal
          isOpen={isOpen}
          onClose={closeSupport}
          initialData={supportData}
          ticketNumber={ticketNumber}
          isLoading={isLoading}
        />
      )}
    </SupportContext.Provider>
  );
}

