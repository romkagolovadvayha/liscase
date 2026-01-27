'use client';

import React from 'react';
import Link from 'next/link';
import { Storage, Link as LinkIcon, Person, BugReport } from '@mui/icons-material';
import { useSettings } from '@/hooks/useSettings';
import { getDefaultAvatar } from '@/lib/utils/settingsImage';

interface SupportUserInfoProps {
  ticketUser: {
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
  isOwn: boolean;
}

export default function SupportUserInfo({
  ticketUser,
  reports = [],
  isOwn,
}: SupportUserInfoProps) {
  const { data: settings } = useSettings();
  const defaultAvatar = getDefaultAvatar(settings);
  
  return (
    <div className="support-user-info">
      <div className="support-user-info-grid">
        <div className="support-user-info-item">
          <div className="support-user-info-item-icon">
            <Storage />
          </div>
          <div className="support-user-info-item-content">
            <div className="support-user-info-item-label">
              {isOwn ? 'Сервер на котором вы играете' : 'Сервер игрока'}
            </div>
            <div className="support-user-info-item-value">
              {ticketUser.server ? ticketUser.server.name : 'неизвестно'}
            </div>
          </div>
        </div>

        {ticketUser.trade_link && (
          <div className="support-user-info-item">
            <div className="support-user-info-item-icon">
              <LinkIcon />
            </div>
            <div className="support-user-info-item-content">
              <div className="support-user-info-item-label">
                {isOwn ? 'Ваша трейд ссылка' : 'Трейд ссылка игрока'}
              </div>
              <div className="support-user-info-item-value">
                <a 
                  href={ticketUser.trade_link} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="support-user-info-link"
                >
                  Открыть трейд ссылку
                </a>
              </div>
            </div>
          </div>
        )}

        {ticketUser.steam_id && (
          <div className="support-user-info-item">
            <div className="support-user-info-item-icon">
              <Person />
            </div>
            <div className="support-user-info-item-content">
              <div className="support-user-info-item-label">
                {isOwn ? 'Ваш Steam ID' : 'Steam ID'}
              </div>
              <div className="support-user-info-item-value">
                <a
                  href={`https://steamcommunity.com/profiles/${ticketUser.steam_id}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="support-user-info-link"
                >
                  {ticketUser.steam_id}
                </a>
              </div>
            </div>
          </div>
        )}

        <div className="support-user-info-item support-user-info-item--full">
          <div className="support-user-info-item-icon">
            <BugReport />
          </div>
          <div className="support-user-info-item-content">
            <div className="support-user-info-item-label">
              {isOwn ? 'Ваши последние жалобы на игроков' : 'Последние репорты игрока'}
            </div>
            {reports.length === 0 ? (
              <div className="support-user-info-empty">
                {isOwn
                  ? 'Вы не отправили ни одной жалобы на сервере! Чтобы отправить жалобу нажмите F7 в игре.'
                  : 'Игрок не отправил ни одного репорта!'}
              </div>
            ) : (
              <div className="support-message-item-reports">
                {reports.map((report) => (
                  <Link
                    key={report.id}
                    href={`/profile/${report.user.steam_id}`}
                    target="_blank"
                    rel="nofollow"
                    className="support-message-item-report-item"
                    title={`Причина: ${report.reason}`}
                  >
                    <div className="support-message-item-report-avatar">
                      <img
                        src={report.user.avatar || defaultAvatar}
                        width="50px"
                        alt={report.user.username}
                      />
                    </div>
                    <div className="support-message-item-report-content">
                      <div className="support-message-item-report-name">
                        {report.user.username}
                      </div>
                      <div className="support-message-item-report-steam-id">
                        {report.user.steam_id}
                      </div>
                      <div className="support-message-item-report-date">
                        {new Date(report.created_at).toLocaleString('ru-RU')}
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}


