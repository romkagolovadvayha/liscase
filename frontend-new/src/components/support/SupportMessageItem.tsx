'use client';

import React, { useState } from 'react';
import type { SupportMessage } from '@/types/support';
import Image from 'next/image';
import classNames from 'classnames';
import { InsertDriveFile, Done, DoneAll, Close, ZoomIn, Delete, Edit } from '@mui/icons-material';
import SupportUserInfo from './SupportUserInfo';
import SupportAlertReport from './SupportAlertReport';
import { useSettings } from '@/hooks/useSettings';
import { getDefaultAvatar } from '@/lib/utils/settingsImage';

interface SupportMessageItemProps {
  message: SupportMessage;
  isOwn: boolean;
  isAdmin?: boolean;
  onDelete?: (messageId: number) => void;
  onEdit?: (messageId: number, currentMessage: string) => void;
  ticketUser?: {
    id: number;
    username: string;
    steam_id?: string;
    server?: {
      id: number;
      name: string;
      tag: string;
    };
    trade_link?: string;
  };
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
  showAvatar?: boolean;
}

export default function SupportMessageItem({
  message,
  isOwn,
  isAdmin = false,
  onDelete,
  onEdit,
  ticketUser,
  reports = [],
  showAvatar = true,
}: SupportMessageItemProps) {
  const { data: settings } = useSettings();
  const defaultAvatar = getDefaultAvatar(settings);
  
  console.log('[SupportMessageItem] Message data:', {
    id: message.id,
    user_id: message.user_id,
    user: message.user,
    user_avatar: message.user?.avatar,
    showAvatar,
  });
  const [selectedImage, setSelectedImage] = useState<{ url: string; filename: string } | null>(null);
  const isSystem = message.user_id === null;
  const isUserInfo = message.message === '{USER_INFO}';
  const isAlertReport = message.message === '{ALERT_REPORT}';

  // Определяем класс для имени пользователя (модератор/админ)
  let usernameClass = '';
  if (message.user) {
    // TODO: Проверить роли пользователя через API или передать в props
    // Пока что используем простую проверку
    if (message.user.username === 'admin' || message.user.username === 'moderator') {
      usernameClass = 'moder';
    }
  }

  if (isSystem) {
    // Для системных сообщений используем структуру разделителя
    return (
      <div className="support-message-item support-message-item--system">
        <div className="support-message-item-system-content">
          <div className="support-message-item-system-text">
            {isUserInfo && ticketUser ? (
              <SupportUserInfo ticketUser={ticketUser} reports={reports} isOwn={isOwn} />
            ) : isAlertReport ? (
              <SupportAlertReport />
            ) : message.message ? (
              <div
                className="support-message-item-text"
                style={{ whiteSpace: 'pre-line' }}
                dangerouslySetInnerHTML={{ __html: message.message }}
              />
            ) : null}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      className={classNames('support-message-item', {
        'support-message-item--own': isOwn,
      })}
    >
      {message.user && showAvatar && (
        <div className="support-message-item-avatar">
          {(() => {
            const avatarUrl = message.user.avatar || defaultAvatar;
            console.log('[SupportMessageItem] Rendering avatar for user:', message.user.username, 'avatar:', avatarUrl);
            return (
              <Image
                src={avatarUrl}
                alt={message.user.username}
                width={36}
                height={36}
                className="support-message-item-avatar-img"
                unoptimized={avatarUrl.startsWith('http')}
              />
            );
          })()}
        </div>
      )}
      {message.user && !showAvatar && (
        <div className="support-message-item-avatar support-message-item-avatar--empty"></div>
      )}
      <div className="support-message-item-message">
        {message.user && showAvatar && (
          <div className={`support-message-item-username ${usernameClass}`}>
            {message.user.username}
          </div>
        )}
        <div className="support-message-item-content">
          {message.message ? (
            <div
              className="support-message-item-text"
              style={{ whiteSpace: 'pre-line' }}
              dangerouslySetInnerHTML={{ __html: message.message }}
            />
          ) : null}
        </div>
        {message.files && message.files.length > 0 && (
          <div className="support-message-item-files">
            {message.files.map((file) => {
              const s3Url = process.env.NEXT_PUBLIC_S3_URL || 'https://storage.prostoj.store';
              const fileUrl = `${s3Url}/support/${file.file}`;
              const isImage = file.mimetype?.startsWith('image/');
              const isVideo = file.mimetype?.startsWith('video/');

              return (
                <div key={file.id} className="support-message-item-files_item">
                  {isImage ? (
                    <div className="support-message-item-files_item_preview">
                      <div 
                        className="support-message-item-files_item_preview-img-wrapper"
                        onClick={() => setSelectedImage({ url: fileUrl, filename: file.filename })}
                      >
                        <img src={fileUrl} alt={file.filename} />
                        <div className="support-message-item-files_item_preview-overlay">
                          <ZoomIn />
                        </div>
                      </div>
                      <a href={fileUrl} target="_blank" rel="noopener noreferrer" className="support-message-item-files_item_button">
                        {file.filename}
                      </a>
                    </div>
                  ) : isVideo ? (
                    <div>
                      <video className="support-message-item-files_item_video" controls>
                        <source src={fileUrl} type={file.mimetype} />
                        Ваш браузер не поддерживает видео.
                      </video>
                      <a href={fileUrl} target="_blank" rel="noopener noreferrer" className="support-message-item-files_item_button">
                        {file.filename}
                      </a>
                    </div>
                  ) : (
                    <a href={fileUrl} target="_blank" rel="noopener noreferrer" className="support-message-item-files_item_button">
                      <InsertDriveFile /> {file.filename}
                    </a>
                  )}
                </div>
              );
            })}
          </div>
        )}
        {!isSystem && (
          <div className="support-message-item-footer">
            <span className="support-message-item-time">
              {new Date(message.created_at).toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit' 
              })}
            </span>
            <div className="support-message-item-footer-right">
              {isOwn && (
                <span className="support-message-item-status">
                  {message.isRead ? (
                    <DoneAll className="support-message-item-status-read" />
                  ) : (
                    <Done className="support-message-item-status-sent" />
                  )}
                </span>
              )}
              {isAdmin && (
                <>
                  {onEdit && message.user_id !== null && (() => {
                    // Проверяем, есть ли файлы
                    const hasFiles = message.files && message.files.length > 0;
                    // Проверяем, есть ли стикеры (в HTML есть тег <img class="support_sticker")
                    const hasStickers = message.message && message.message.includes('class="support_sticker"');
                    // Запрещаем редактирование, если есть файлы или стикеры
                    const canEdit = !hasFiles && !hasStickers;
                    
                    if (!canEdit) {
                      return null;
                    }
                    
                    return (
                      <button
                        type="button"
                        className="support-message-item-edit"
                        onClick={() => {
                          // Получаем текст сообщения без HTML
                          const tempDiv = document.createElement('div');
                          tempDiv.innerHTML = message.message || '';
                          const textContent = tempDiv.textContent || tempDiv.innerText || '';
                          onEdit(message.id, textContent);
                        }}
                        title="Редактировать сообщение"
                        aria-label="Редактировать сообщение"
                      >
                        <Edit />
                      </button>
                    );
                  })()}
                  {onDelete && (
                    <button
                      type="button"
                      className="support-message-item-delete"
                      onClick={() => {
                        if (window.confirm('Вы уверены, что хотите удалить это сообщение?')) {
                          onDelete(message.id);
                        }
                      }}
                      title="Удалить сообщение"
                      aria-label="Удалить сообщение"
                    >
                      <Delete />
                    </button>
                  )}
                </>
              )}
            </div>
          </div>
        )}
      </div>
      {selectedImage && (
        <div 
          className="support-image-lightbox"
          onClick={() => setSelectedImage(null)}
        >
          <div className="support-image-lightbox-overlay" />
          <div className="support-image-lightbox-content" onClick={(e) => e.stopPropagation()}>
            <button
              type="button"
              className="support-image-lightbox-close"
              onClick={() => setSelectedImage(null)}
              aria-label="Закрыть"
            >
              <Close />
            </button>
            <img src={selectedImage.url} alt={selectedImage.filename} />
            <div className="support-image-lightbox-filename">
              {selectedImage.filename}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

