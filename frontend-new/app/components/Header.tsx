"use client";

import { Bell, ChevronDown, Menu, Plus, UserRound, X } from "lucide-react";
import { useState } from "react";
import type { Locale, UserInfo, ViewMode } from "../lib/types";

const SITE = "https://prostoj.store";

const nav = [
  ["Новости", "News", "/posts"],
  ["Календарь вайпов", "Wipe calendar", "/wipe-calendar"],
  ["Поддержка", "Support", "/support"],
  ["Статистика", "Statistics", "/stats"],
  ["Кланы", "Clans", "/clans"],
  ["Турниры", "Tournaments", "/tournaments"],
] as const;

export function AppHeader({ locale, onLocale, mode, user, onSteam }: { locale: Locale; onLocale: (locale: Locale) => void; mode: ViewMode; user: UserInfo; onSteam: () => void }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  return <header className="app-header"><div className="app-header__inner"><div className="app-header__left"><a className="brand" href={SITE} aria-label="PROSTOJ"><img src="/logo.svg" alt="PROSTOJ" /></a><nav className="desktop-nav" aria-label={locale === "ru" ? "Основное меню" : "Main navigation"}>{nav.map(item => <a key={item[2]} href={`${SITE}${item[2]}`}>{locale === "ru" ? item[0] : item[1]}</a>)}</nav></div><div className="app-header__right"><div className="socials"><a href="https://t.me/rust_prostoj" aria-label="Telegram"><img src="/icons/telegram.svg" alt="" /></a><a href="https://discord.gg/prostoj" aria-label="Discord"><img src="/icons/discord.svg" alt="" /></a><a href="https://vk.com/prostoj_rust" aria-label="VKontakte"><img src="/icons/vk.svg" alt="" /></a></div><div className="locale-switch" role="group" aria-label={locale === "ru" ? "Язык" : "Language"}><button className={locale === "ru" ? "is-active" : ""} onClick={() => onLocale("ru")} aria-label="Русский"><img src="/flags/RU.svg" alt="" /></button><button className={locale === "en" ? "is-active" : ""} onClick={() => onLocale("en")} aria-label="English"><img src="/flags/GB.svg" alt="" /></button></div>{mode === "guest" ? <button className="steam-button" onClick={onSteam}><img src="/icons/steam.svg" alt="" />{locale === "ru" ? "Войти через Steam" : "Sign in with Steam"}</button> : <><button className="balance-button"><span>{user.balance.toLocaleString("ru-RU")} ₽</span><i /><Plus size={16} /></button><button className="notice-button" aria-label={locale === "ru" ? "Уведомления" : "Notifications"}><Bell size={17} /><i /></button><div className="profile-menu"><button className="profile-button" onClick={() => setProfileOpen(!profileOpen)} aria-expanded={profileOpen}><img src={user.avatar} alt="" /><span><strong>{user.username}</strong><small>{user.serverTag}</small></span><ChevronDown size={15} /></button>{profileOpen && <div className="profile-popover"><a href={`${SITE}/profile`}><UserRound size={16} />{locale === "ru" ? "Профиль" : "Profile"}</a><a href={`${SITE}/user/payment`}><Plus size={16} />{locale === "ru" ? "Пополнить баланс" : "Add funds"}</a></div>}</div></>}<button className="mobile-menu-button" onClick={() => setMobileOpen(!mobileOpen)} aria-label={locale === "ru" ? "Открыть меню" : "Open menu"}>{mobileOpen ? <X /> : <Menu />}</button></div></div>{mobileOpen && <nav className="mobile-nav">{nav.map(item => <a key={item[2]} href={`${SITE}${item[2]}`}>{locale === "ru" ? item[0] : item[1]}</a>)}</nav>}</header>;
}
