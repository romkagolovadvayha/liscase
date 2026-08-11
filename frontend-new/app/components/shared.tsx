"use client";

import type { ReactNode } from "react";
import { CalendarDays, ChevronRight, CircleDot, Copy, ExternalLink, Newspaper, Server, ShoppingBag } from "lucide-react";
import type { BlogPost, Locale, ProductItem, ProjectStats, ServerItem, WipeEvent } from "../lib/types";

const SITE = "https://prostoj.store";

export function Panel({ icon, title, action, className = "", children }: { icon?: ReactNode; title: string; action?: ReactNode; className?: string; children: ReactNode }) {
  return <section className={`ui-panel ${className}`}><header className="ui-panel__header"><div className="ui-panel__title">{icon}<span>{title}</span></div>{action}</header>{children}</section>;
}

function serverTitle(server: ServerItem) {
  const index = server.name.match(/#\d+/)?.[0] ?? "";
  return `${index} ${server.monitoring_name ?? server.tag.toUpperCase()}`.trim();
}

function wipeLabel(value?: string | null, locale: Locale = "ru") {
  if (!value) return locale === "ru" ? "Дата уточняется" : "Date pending";
  const date = new Date(value.replace(" ", "T") + "+03:00");
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale === "ru" ? "ru-RU" : "en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }).format(date);
}

export function ServersGrid({ servers, online, locale }: { servers: ServerItem[]; online: number; locale: Locale }) {
  return <Panel icon={<Server size={17} />} title={locale === "ru" ? "Наши серверы" : "Our servers"} action={<span className="live-label"><i />{online} {locale === "ru" ? "онлайн" : "online"}</span>} className="servers-panel"><div className="server-grid">{servers.slice(0, 5).map((server) => { const total = server.players + (server.joined ?? 0) + (server.queued ?? 0); const percent = server.max ? Math.min(100, total / server.max * 100) : 0; return <a key={server.id} className="server-card" href={`${SITE}/servers/${server.tag}`}><div className="server-card__top"><strong>{serverTitle(server)}</strong><span>{total} / {server.max}</span></div><div className="progress"><i style={{ width: `${percent}%` }} /></div><div className="server-card__bottom"><span>{locale === "ru" ? "Вайп" : "Wipe"}: {wipeLabel(server.nextWipe, locale)}</span><ChevronRight size={14} /></div></a>; })}</div></Panel>;
}

export function ServersRail({ servers, online, locale }: { servers: ServerItem[]; online: number; locale: Locale }) {
  return <Panel icon={<Server size={17} />} title={locale === "ru" ? "Серверы" : "Servers"} action={<span className="live-label"><i />{online}</span>} className="servers-rail"><div className="server-rows">{servers.slice(0, 5).map((server, index) => { const total = server.players + (server.joined ?? 0) + (server.queued ?? 0); return <a key={server.id} href={`${SITE}/servers/${server.tag}`} className={`server-row ${index === 3 ? "server-row--active" : ""}`}><span className="status-dot" /><div className="server-row__main"><strong>{serverTitle(server)}</strong><div className="progress"><i style={{ width: `${Math.min(100, total / server.max * 100)}%` }} /></div></div><span>{total}/{server.max}</span></a>; })}</div></Panel>;
}

export function NewsPanel({ posts, locale, compact = false }: { posts: BlogPost[]; locale: Locale; compact?: boolean }) {
  const items = posts.slice(0, 5); const featured = items[0];
  if (compact) return <Panel icon={<Newspaper size={17} />} title={locale === "ru" ? "Новости" : "News"} action={<a className="panel-link" href={`${SITE}/posts`}>{locale === "ru" ? "Все" : "All"}</a>} className="news-rail"><div className="news-rows">{items.map(post => <a key={post.id} href={`${SITE}${post.url ?? "/posts"}`} className="news-row"><strong>{post.title}</strong><span>{new Intl.NumberFormat("ru-RU").format(post.views ?? 0)} {locale === "ru" ? "просмотров" : "views"}</span></a>)}</div></Panel>;
  return <Panel icon={<Newspaper size={17} />} title={locale === "ru" ? "Последние новости" : "Latest news"} action={<a className="panel-link" href={`${SITE}/posts`}>{locale === "ru" ? "Все новости" : "All news"}</a>} className="news-panel"><div className="news-layout">{featured && <a href={`${SITE}${featured.url ?? "/posts"}`} className="news-feature"><img src={featured.image ?? featured.image_100 ?? ""} alt="" /><div className="news-feature__shade" /><div className="news-feature__copy"><span>{new Intl.NumberFormat("ru-RU").format(featured.views ?? 0)} {locale === "ru" ? "просмотров" : "views"}</span><h2>{featured.title}</h2><p>{featured.description}</p></div></a>}<div className="news-list">{items.slice(1).map(post => <a key={post.id} href={`${SITE}${post.url ?? "/posts"}`}><span>{post.title}</span><small>{new Intl.NumberFormat("ru-RU").format(post.views ?? 0)}</small><ChevronRight size={15} /></a>)}</div></div></Panel>;
}

export function ProductsPanel({ products, locale, compact = false }: { products: ProductItem[]; locale: Locale; compact?: boolean }) {
  return <Panel icon={<ShoppingBag size={17} />} title={compact ? (locale === "ru" ? "Для вашего сервера" : "For your server") : (locale === "ru" ? "Популярное в магазине" : "Popular in store")} action={<a className="panel-link" href={`${SITE}/#store`}>{locale === "ru" ? "Магазин" : "Store"}</a>} className={`products-panel ${compact ? "products-panel--compact" : ""}`}><div className="products-grid">{products.slice(0, 6).map(product => <a href={`${SITE}/#store`} className="product-card" key={product.id}><div className="product-card__image"><img src={product.image} alt={product.name} /></div><div className="product-card__copy"><strong>{product.name}</strong><span>{Math.ceil(product.priceReal || product.price || 0).toLocaleString("ru-RU")} ₽</span></div>{product.discount ? <em>−{product.discount}%</em> : null}</a>)}</div></Panel>;
}

export function WipeCalendar({ events, locale }: { events: WipeEvent[]; locale: Locale }) {
  const year = 2026, month = 7; const start = new Date(Date.UTC(year, month, 1)); const days = new Date(Date.UTC(year, month + 1, 0)).getUTCDate(); const offset = (start.getUTCDay() + 6) % 7; const eventMap = new Map<number, WipeEvent[]>(); events.forEach(event => { const date = new Date(event.start); if (date.getUTCFullYear() === year && date.getUTCMonth() === month) { const day = date.getUTCDate(); eventMap.set(day, [...(eventMap.get(day) ?? []), event]); } });
  const weekdays = locale === "ru" ? ["ПН","ВТ","СР","ЧТ","ПТ","СБ","ВС"] : ["MO","TU","WE","TH","FR","SA","SU"];
  return <Panel icon={<CalendarDays size={17} />} title={locale === "ru" ? "Календарь вайпов" : "Wipe calendar"} action={<span className="panel-meta">{locale === "ru" ? "Август 2026" : "August 2026"}</span>} className="calendar-panel"><div className="calendar"><div className="calendar__week">{weekdays.map(day => <span key={day}>{day}</span>)}</div><div className="calendar__days">{Array.from({ length: offset }).map((_, i) => <span key={`e${i}`} />)}{Array.from({ length: days }, (_, i) => i + 1).map(day => { const dayEvents = eventMap.get(day) ?? []; const type = dayEvents.some(x => x.event_type === "global_wipe") ? "global" : dayEvents.some(x => x.event_type === "game_update") ? "update" : dayEvents.length ? "map" : ""; return <button key={day} className={`calendar-day ${type ? `calendar-day--${type}` : ""} ${day === 10 ? "calendar-day--today" : ""}`} title={dayEvents.map(x => `${x.event_type_label}: ${x.calendar_title}`).join("\n")}>{day}{dayEvents.length > 1 && <b>{dayEvents.length}</b>}</button>; })}</div><div className="calendar__legend"><span><i className="legend-map" />{locale === "ru" ? "Карта" : "Map"}</span><span><i className="legend-global" />{locale === "ru" ? "Глобальный" : "Global"}</span><span><i className="legend-update" />{locale === "ru" ? "Обновление" : "Update"}</span></div></div></Panel>;
}

export function ProjectStatsCard({ stats, locale }: { stats: ProjectStats; locale: Locale }) {
  const labels = locale === "ru" ? ["онлайн", "серверов", "игроков"] : ["online", "servers", "players"];
  return <Panel icon={<CircleDot size={17} />} title={locale === "ru" ? "Сейчас в проекте" : "Project now"} action={<span className="status-dot" />} className="stats-panel"><div className="stats-grid"><div><strong>{stats.online}</strong><span>{labels[0]}</span></div><div><strong>{stats.count}</strong><span>{labels[1]}</span></div><div><strong>{Intl.NumberFormat("ru-RU", { notation: "compact" }).format(stats.users)}</strong><span>{labels[2]}</span></div></div><div className="stats-actions"><button onClick={() => navigator.clipboard?.writeText("connect s5.prostoj.store:35100")}><Copy size={14} />{locale === "ru" ? "Скопировать IP" : "Copy IP"}</button><a href={`${SITE}/support`}><ExternalLink size={14} />{locale === "ru" ? "Помощь" : "Support"}</a></div></Panel>;
}
