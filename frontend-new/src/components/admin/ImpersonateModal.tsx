'use client';

import React, { useState } from 'react';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import apiClient, { setTokens } from '@/lib/api/client';
import '@/styles/product-modal.scss';

interface ImpersonateModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export default function ImpersonateModal({
  isOpen,
  onClose,
  onSuccess,
}: ImpersonateModalProps) {
  const [steamId, setSteamId] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleImpersonate = async () => {
    if (!steamId.trim()) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await apiClient.post('/auth/impersonate', {
        steam_id: steamId.trim(),
      });

      if (response.data.success) {
        // Сохраняем новые токены
        if (response.data.data.token) {
          const refreshToken = response.data.data.refresh_token || response.data.data.refreshToken || '';
          setTokens(response.data.data.token, refreshToken);
        }
        
        // Закрываем модальное окно
        onClose();
        setSteamId('');
        
        // Вызываем callback успеха
        if (onSuccess) {
          onSuccess();
        }
        
        // Перезагружаем страницу для применения изменений
        window.location.reload();
      } else {
        setError(response.data.message || 'Не удалось войти под пользователем');
      }
    } catch (error: any) {
      console.error('Error impersonating user:', error);
      setError(error.response?.data?.message || error.message || 'Не удалось войти под пользователем');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading) {
      onClose();
      setSteamId('');
      setError(null);
    }
  };

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget && !loading) {
      handleClose();
    }
  };

  if (!isOpen) return null;

  return (
    <div className="product-modal-overlay" onClick={handleOverlayClick}>
      <div className="product-modal">
        {/* Снежинки для эффекта объема */}
        <span className="product-modal__snowflake product-modal__snowflake--1">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--2">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--3">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--4">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--5">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--6">❄</span>
        
        <button className="product-modal__close" onClick={handleClose} disabled={loading}>
          <Icon name="close" fontSize="small" />
        </button>

        <header className="product-modal__header">
          <div style={{ 
            marginBottom: '24px',
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
          }}>
            <div style={{ fontSize: '64px', color: 'var(--primary-colors-main)' }}>
              <Icon name="person" fontSize="large" />
            </div>
          </div>
          <h2 className="product-modal__title" style={{ textAlign: 'center' }}>
            Войти под пользователем
          </h2>
        </header>

        <div className="product-modal__content">
          <p style={{ 
            marginBottom: '24px',
            textAlign: 'center',
            color: 'var(--text-secondary)',
            fontSize: '16px',
            lineHeight: '1.5',
          }}>
            Введите Steam ID пользователя, под которым хотите войти
          </p>
          
          {error && (
            <div style={{
              padding: '12px 16px',
              marginBottom: '20px',
              backgroundColor: 'rgba(255, 77, 79, 0.1)',
              border: '1px solid rgba(255, 77, 79, 0.3)',
              borderRadius: '8px',
              color: 'var(--error-text, #ff4d4f)',
              fontSize: '14px',
              textAlign: 'center',
            }}>
              {error}
            </div>
          )}
          
          <div style={{ marginBottom: '24px' }}>
            <input
              type="text"
              value={steamId}
              onChange={(e) => setSteamId(e.target.value)}
              placeholder="76561198012345678"
              onKeyDown={(e) => {
                if (e.key === 'Enter' && steamId.trim() && !loading) {
                  handleImpersonate();
                }
              }}
              disabled={loading}
              autoFocus
              style={{
                width: '100%',
                padding: '12px 16px',
                borderRadius: '8px',
                border: '1px solid var(--border-color-default)',
                backgroundColor: 'var(--background-hover)',
                color: 'var(--text-primary)',
                fontSize: '16px',
                boxSizing: 'border-box',
                outline: 'none',
                transition: 'all 0.2s',
              }}
              onFocus={(e) => {
                e.target.style.borderColor = 'var(--primary-colors-main)';
                e.target.style.boxShadow = '0 0 0 2px rgba(235, 12, 53, 0.2)';
              }}
              onBlur={(e) => {
                e.target.style.borderColor = 'var(--border-color-default)';
                e.target.style.boxShadow = 'none';
              }}
            />
          </div>

          <footer className="product-modal__footer" style={{ paddingTop: '0' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <Button
                variant="primary"
                onClick={handleImpersonate}
                disabled={!steamId.trim() || loading}
                style={{
                  width: '100%',
                }}
              >
              {loading ? (
                <>
                  <span style={{ marginRight: '8px', display: 'inline-flex', alignItems: 'center' }}>
                    <Icon name="loading" fontSize="small" />
                  </span>
                  Вход...
                </>
              ) : (
                'Войти'
              )}
              </Button>
              <Button
                variant="secondary"
                onClick={handleClose}
                disabled={loading}
                style={{
                  width: '100%',
                }}
              >
                Отмена
              </Button>
            </div>
          </footer>
        </div>
      </div>
    </div>
  );
}
