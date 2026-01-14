'use client';

import React from 'react';
import { Modal, Button } from 'antd';
import { HeadsetMic } from '@mui/icons-material';
import { startSteamAuth } from '@/lib/api/auth';
import '@/styles/support.scss';

interface SupportAuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  onAuthSuccess: () => void;
}

export default function SupportAuthModal({
  isOpen,
  onClose,
  onAuthSuccess,
}: SupportAuthModalProps) {
  const handleSteamAuth = () => {
    startSteamAuth();
  };

  return (
    <Modal
      open={isOpen}
      onCancel={onClose}
      footer={null}
      centered
      width={500}
      className="support-auth-modal"
    >
      <div className="support-auth-modal-content">
        <div className="support-auth-modal-icon">
          <HeadsetMic style={{ fontSize: '64px', color: 'var(--primary-colors-primary)' }} />
        </div>
        <h2 className="support-auth-modal-title">
          Для доступа к поддержке необходимо авторизоваться
        </h2>
        <p className="support-auth-modal-description">
          Войдите через Steam, чтобы использовать систему поддержки и получать помощь от нашей команды.
        </p>
        <div className="support-auth-modal-actions">
          <Button
            type="primary"
            size="large"
            onClick={handleSteamAuth}
            className="support-auth-modal-button"
            style={{
              width: '100%',
              height: '48px',
              fontSize: '16px',
              fontWeight: 600,
            }}
          >
            Войти через Steam
          </Button>
          <Button
            type="text"
            onClick={onClose}
            className="support-auth-modal-cancel"
            style={{
              width: '100%',
              marginTop: '12px',
            }}
          >
            Отмена
          </Button>
        </div>
      </div>
    </Modal>
  );
}




