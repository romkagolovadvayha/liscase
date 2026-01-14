'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Remove, Fullscreen, FullscreenExit, Close } from '@mui/icons-material';
import SupportTicketClient from './SupportTicketClient';
import SupportClient from './SupportClient';
import SupportSkeleton from './SupportSkeleton';
import SupportTicketSkeleton from './SupportTicketSkeleton';
import SupportTicketList from './SupportTicketList';
import { useSupport } from './SupportProvider';
import type { SupportTicket, SupportMessage } from '@/types/support';

interface SupportModalProps {
  isOpen: boolean;
  onClose: () => void;
  initialData?: {
    ticket?: SupportTicket;
    messages?: SupportMessage[];
    tickets?: SupportTicket[];
    reports?: Array<{
      id: number;
      user: {
        id: number;
        username: string;
        steam_id: string;
        avatar?: string;
      };
      reason: string;
      created_at: string;
    }>;
    user: {
      id: number;
      username: string;
      avatar: string;
      blocked_support: boolean;
      blocked_support_at: string | null;
      isAdmin: boolean;
    };
  };
  ticketNumber?: number;
  isLoading?: boolean;
}

type ModalState = 'normal' | 'minimized' | 'maximized';

export default function SupportModal({
  isOpen,
  onClose,
  initialData,
  ticketNumber,
  isLoading = false,
}: SupportModalProps) {
  const { openSupport } = useSupport();
  const [modalState, setModalState] = useState<ModalState>('normal');
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [size, setSize] = useState({ width: 1200, height: 800 });
  const [isDragging, setIsDragging] = useState(false);
  const [isResizing, setIsResizing] = useState(false);
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 });
  const [resizeStart, setResizeStart] = useState({ x: 0, y: 0, width: 0, height: 0 });
  const modalRef = useRef<HTMLDivElement>(null);
  const headerRef = useRef<HTMLDivElement>(null);
  const [windowSize, setWindowSize] = useState({ width: typeof window !== 'undefined' ? window.innerWidth : 1200, height: typeof window !== 'undefined' ? window.innerHeight : 800 });

  // Отслеживание изменения размера окна
  useEffect(() => {
    const handleResize = () => {
      setWindowSize({ width: window.innerWidth, height: window.innerHeight });
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // Адаптивные размеры в зависимости от размера экрана
  const getAdaptiveSize = () => {
    const isMobile = windowSize.width < 768;
    const isTablet = windowSize.width >= 768 && windowSize.width < 1024;
    
    if (isMobile) {
      return { width: windowSize.width, height: windowSize.height };
    } else if (isTablet) {
      return { width: Math.min(900, windowSize.width - 40), height: Math.min(700, windowSize.height - 40) };
    } else {
      return { width: Math.min(1200, windowSize.width - 40), height: Math.min(800, windowSize.height - 40) };
    }
  };

  // Обновляем размер при изменении размера окна
  useEffect(() => {
    if (isOpen && modalState === 'normal') {
      const adaptiveSize = getAdaptiveSize();
      setSize(adaptiveSize);
      const centerX = Math.max(0, (windowSize.width - adaptiveSize.width) / 2);
      const centerY = Math.max(0, (windowSize.height - adaptiveSize.height) / 2);
      setPosition({ x: centerX, y: centerY });
    }
  }, [windowSize, isOpen, modalState]);

  // Обработка перетаскивания
  const handleMouseDown = useCallback((e: React.MouseEvent) => {
    const isMobile = windowSize.width < 768;
    if (modalState === 'minimized' || modalState === 'maximized' || isMobile) return;
    if (e.target !== headerRef.current && !headerRef.current?.contains(e.target as Node)) return;
    
    setIsDragging(true);
    setDragStart({
      x: e.clientX - position.x,
      y: e.clientY - position.y,
    });
  }, [modalState, position, windowSize.width]);

  useEffect(() => {
    if (!isDragging) return;

    const handleMouseMove = (e: MouseEvent) => {
      setPosition({
        x: e.clientX - dragStart.x,
        y: Math.max(0, e.clientY - dragStart.y),
      });
    };

    const handleMouseUp = () => {
      setIsDragging(false);
    };

    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);

    return () => {
      window.removeEventListener('mousemove', handleMouseMove);
      window.removeEventListener('mouseup', handleMouseUp);
    };
  }, [isDragging, dragStart]);

  // Обработка изменения размера
  const handleResizeMouseDown = useCallback((e: React.MouseEvent) => {
    const isMobile = windowSize.width < 768;
    if (modalState === 'minimized' || modalState === 'maximized' || isMobile) return;
    e.stopPropagation();
    setIsResizing(true);
    setResizeStart({
      x: e.clientX,
      y: e.clientY,
      width: size.width,
      height: size.height,
    });
  }, [modalState, size, windowSize.width]);

  useEffect(() => {
    if (!isResizing) return;

    const handleMouseMove = (e: MouseEvent) => {
      const deltaX = e.clientX - resizeStart.x;
      const deltaY = e.clientY - resizeStart.y;
      
      setSize({
        width: Math.max(600, resizeStart.width + deltaX),
        height: Math.max(400, resizeStart.height + deltaY),
      });
    };

    const handleMouseUp = () => {
      setIsResizing(false);
    };

    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);

    return () => {
      window.removeEventListener('mousemove', handleMouseMove);
      window.removeEventListener('mouseup', handleMouseUp);
    };
  }, [isResizing, resizeStart]);

  const handleMinimize = () => {
    setModalState('minimized');
  };

  const handleMaximize = () => {
    if (modalState === 'maximized') {
      setModalState('normal');
    } else {
      setModalState('maximized');
    }
  };

  const handleClose = () => {
    setModalState('normal');
    onClose();
  };

  if (!isOpen) return null;

  const isMobile = windowSize.width < 768;
  const isTablet = windowSize.width >= 768 && windowSize.width < 1024;

  const modalStyles: React.CSSProperties = {
    position: 'fixed',
    zIndex: 10000,
    ...(modalState === 'maximized' || isMobile
      ? {
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          width: '100vw',
          height: '100vh',
          maxWidth: '100vw',
          maxHeight: '100vh',
        }
      : modalState === 'minimized'
      ? {
          top: 'auto',
          bottom: 20,
          right: 20,
          width: 300,
          height: 50,
        }
      : {
          left: `${position.x}px`,
          top: `${position.y}px`,
          width: `${size.width}px`,
          height: `${size.height}px`,
          maxWidth: `${windowSize.width - 40}px`,
          maxHeight: `${windowSize.height - 40}px`,
        }),
  };

  return createPortal(
    <div className="support-modal-overlay" onClick={handleClose}>
      <div
        ref={modalRef}
        className={`support-modal ${modalState}`}
        style={modalStyles}
        onClick={(e) => e.stopPropagation()}
      >
        <div
          ref={headerRef}
          className="support-modal-header"
          onMouseDown={handleMouseDown}
        >
          <div className="support-modal-header-title">
            <span>Поддержка</span>
          </div>
          <div className="support-modal-header-actions">
            {!isMobile && (
              <button
                type="button"
                className="support-modal-button support-modal-button-minimize"
                onClick={handleMinimize}
                title="Свернуть"
              >
                <Remove />
              </button>
            )}
            {!isMobile && (
              <button
                type="button"
                className="support-modal-button support-modal-button-maximize"
                onClick={handleMaximize}
                title={modalState === 'maximized' ? 'Восстановить' : 'Развернуть'}
              >
                {modalState === 'maximized' ? <FullscreenExit /> : <Fullscreen />}
              </button>
            )}
            <button
              type="button"
              className="support-modal-button support-modal-button-close"
              onClick={handleClose}
              title="Закрыть"
            >
              <Close />
            </button>
          </div>
        </div>
        <div className="support-modal-content">
          {modalState !== 'minimized' && (
            isLoading ? (
              <SupportSkeleton />
            ) : (
              <SupportClient initialData={initialData ? { tickets: initialData.tickets, user: initialData.user } : undefined} />
            )
          )}
          {modalState === 'minimized' && (
            <div className="support-modal-minimized-content">
              <span>Поддержка свернута</span>
              <button onClick={() => setModalState('normal')}>
                Развернуть
              </button>
            </div>
          )}
        </div>
        {modalState === 'normal' && (
          <div
            className="support-modal-resize-handle"
            onMouseDown={handleResizeMouseDown}
          />
        )}
      </div>
    </div>,
    document.body
  );
}

