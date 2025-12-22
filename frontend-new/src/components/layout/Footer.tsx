'use client';

import React from 'react';
import Link from 'next/link';
import moment from 'moment';

interface FooterLink {
  label: string;
  href: string;
}

interface SocialLink {
  name: 'telegram' | 'vk' | 'discord';
  url: string;
}

interface FooterProps {
  logo?: string;
  email?: string;
  socialLinks?: SocialLink[];
  menuLinks?: {
    title: string;
    items: FooterLink[];
  }[];
  inn?: string;
  ipName?: string;
  domain?: string;
}

export default function Footer({
  logo = '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg',
  email = 'support@example.com',
  socialLinks = [
    { name: 'telegram', url: 'https://t.me/channel' },
    { name: 'vk', url: 'https://vk.com/group' },
    { name: 'discord', url: 'https://discord.gg/server' },
  ],
  menuLinks = [
    {
      title: 'Главная',
      items: [
        { label: 'Главная страница', href: '/' },
        { label: 'Статистика', href: '/servers' },
        { label: 'Новости', href: '/posts' },
      ],
    },
    {
      title: 'Информация',
      items: [
        { label: 'Серверы', href: '/servers' },
        { label: 'Правила', href: '/rules' },
        { label: 'Пользовательское соглашение', href: '/site/agreement' },
        { label: 'Политика конфиденциальности', href: '/site/privacy' },
      ],
    },
  ],
  inn = '180600035048',
  ipName = 'ИП УСКОВ АРТЕМ ОЛЕГОВИЧ',
  domain = 'prostoj.store',
}: FooterProps) {
  return (
    <footer className="footer">
      <div className="footer__container container mx-auto">
        <div className="footer__content">
          {/* Слева: Логотип, Email, Соц. сети */}
          <div className="footer__left">
            <Link href="/" className="footer__logo">
              <img src={logo} alt="Logo" />
            </Link>
            {email && (
              <a href={`mailto:${email}`} className="footer__email">
                {email}
              </a>
            )}
            {socialLinks && socialLinks.length > 0 && (
              <div className="footer__social">
                {socialLinks.map((social, index) => (
                  <a
                    key={index}
                    href={social.url}
                    target="_blank"
                    rel="nofollow"
                    className="footer__social-link"
                    aria-label={social.name}
                  >
                    <span className={`icons icons_32px icons_32px_${social.name}`}></span>
                  </a>
                ))}
              </div>
            )}
          </div>

          {/* Посередине: Меню в несколько колонок */}
          {menuLinks && menuLinks.length > 0 && (
            <div className="footer__menu">
              {menuLinks.map((group, index) => (
                <div key={index} className="footer__menu-column">
                  <h3 className="footer__menu-title">{group.title}</h3>
                  <ul className="footer__menu-list">
                    {group.items.map((link, linkIndex) => (
                      <li key={linkIndex}>
                        <Link href={link.href} className="footer__menu-link">
                          {link.label}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          )}

          {/* Справа: Юридическая информация */}
          <div className="footer__right">
            <p className="footer__legal-text">
              Размещенная на настоящем сайте информация носит исключительно информационный характер и ни при каких условиях не является публичной офертой, определяемой положениями ч. 2 ст. 437 Гражданского кодекса Российской Федерации.
            </p>
            {inn && (
              <p className="footer__legal-info">
                ИНН {inn}
              </p>
            )}
            {ipName && (
              <p className="footer__legal-info">
                {ipName}
              </p>
            )}
          </div>
        </div>

        {/* Копирайт */}
        <div className="footer__bottom">
          <p className="footer__copyright">© {moment().year()} {domain}</p>
        </div>
      </div>
    </footer>
  );
}

