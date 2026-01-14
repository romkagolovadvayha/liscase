'use client';

import React, { useState } from 'react';
import {
  FiberManualRecord as CircleIcon,
  EmojiEvents as CrownIcon,
  VisibilityOff as EyeSlashIcon,
  AccessTime as ClockIcon,
  ExpandMore as ChevronDownIcon,
} from '@mui/icons-material';
import type { TeamMember } from '@/types/profile';

interface TeamWidgetProps {
  teamMembers: TeamMember[];
}

export default function TeamWidget({ teamMembers }: TeamWidgetProps) {
  const [expanded, setExpanded] = useState(false);
  const [visibleCount, setVisibleCount] = useState(5);

  // Сортируем участников: онлайн -> офлайн -> скрытые
  const sortedMembers = React.useMemo(() => {
    const online: TeamMember[] = [];
    const offline: TeamMember[] = [];
    const hidden: TeamMember[] = [];

    teamMembers.forEach((member) => {
      if (member.is_hidden) {
        hidden.push(member);
      } else if (member.is_online) {
        online.push(member);
      } else {
        offline.push(member);
      }
    });

    return [...online, ...offline, ...hidden];
  }, [teamMembers]);

  const onlineCount = sortedMembers.filter((m) => m.is_online).length;
  const totalCount = sortedMembers.length;

  const handleShowMore = () => {
    if (expanded) {
      // Скрываем все после 5-го
      setExpanded(false);
      setVisibleCount(5);
    } else {
      // Показываем следующие 10 элементов
      const newCount = Math.min(visibleCount + 10, totalCount);
      setVisibleCount(newCount);
      if (newCount >= totalCount) {
        setExpanded(true);
      }
    }
  };

  if (!teamMembers || teamMembers.length === 0) {
    return null;
  }

  const remainingCount = totalCount - visibleCount;

  return (
    <section className="page-stats__block-without-hover stat-block team-widget">
      <div className="team-widget__header">
        <h4 className="stat-block__title">Команда</h4>
        <div className="team-widget__stats">
          <span className="team-widget__stat team-widget__stat--online">
            <CircleIcon sx={{ fontSize: 8 }} />
            {onlineCount}
          </span>
          <span className="team-widget__stat">{totalCount}</span>
        </div>
      </div>

      <ul className="team-widget__list" data-total={totalCount}>
        {sortedMembers.map((member, index) => (
          <li
            key={member.id}
            className={`team-member ${index >= visibleCount ? 'team-member--hidden' : ''}`}
            data-index={index + 1}
          >
            <div
              className={`team-member__avatar ${
                member.is_hidden
                  ? 'team-member__avatar--hidden'
                  : !member.is_online
                    ? 'team-member__avatar--offline'
                    : ''
              }`}
            >
              <img src={member.avatar} alt={member.username} loading="lazy" />
              {member.is_online && <span className="team-member__status"></span>}
            </div>

            <div className="team-member__content">
              <div className="team-member__info">
                <a href={member.link} rel="nofollow" className="team-member__name">
                  {member.username}
                </a>
                {member.is_leader && (
                  <span className="team-member__badge team-member__badge--leader">
                    <CrownIcon sx={{ fontSize: 9 }} />
                    лидер
                  </span>
                )}
              </div>

              <div className="team-member__status-text">
                {member.is_hidden ? (
                  <>
                    <EyeSlashIcon sx={{ fontSize: 10 }} />
                    <span>Скрыт</span>
                  </>
                ) : member.is_online ? (
                  <span>Онлайн</span>
                ) : (
                  <>
                    <ClockIcon sx={{ fontSize: 10 }} />
                    <span>
                      Был онлайн{' '}
                      {member.date_visit && (
                        <span className="wipe_timer" data-time={member.time_visit || undefined}>
                          {member.date_visit}
                        </span>
                      )}
                    </span>
                  </>
                )}
              </div>
            </div>
          </li>
        ))}
      </ul>

      {totalCount > 5 && (
        <button
          type="button"
          className="team-widget__show-more"
          onClick={handleShowMore}
          data-text-more={`Показать еще ${remainingCount}`}
          data-text-less="Скрыть"
        >
          <span className="team-widget__show-more-text">
            {expanded
              ? 'Скрыть'
              : `Показать еще ${remainingCount > 0 ? remainingCount : totalCount - 5}`}
          </span>
          <ChevronDownIcon
            sx={{
              fontSize: 11,
              transition: 'transform 0.25s ease-in-out',
              transform: expanded ? 'rotate(180deg)' : 'rotate(0deg)',
            }}
          />
        </button>
      )}
    </section>
  );
}

