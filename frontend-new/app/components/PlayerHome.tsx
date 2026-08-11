"use client";

import { CalendarDays, Crosshair, Map, MessageSquare, Minus, Navigation, Plus, Send, UserPlus, Users, Volume2 } from "lucide-react";
import { FormEvent, useState } from "react";
import type { Locale, PublicData, UserInfo, WipeEvent } from "../lib/types";
import { NewsPanel, Panel, ProductsPanel, ServersRail } from "./shared";

const SITE = "https://prostoj.store";

function MiniMap({ locale, online }: { locale: Locale; online: number }) {
  return <Panel icon={<Map size={17} />} title={locale === "ru" ? "Карта сервера #5 X10" : "#5 X10 server map"} action={<div className="map-head"><span className="server-pill">{online} {locale === "ru" ? "онлайн" : "online"}</span><small>H18 · 4250</small></div>} className="map-panel"><div className="mini-map"><div className="map-island map-island--one" /><div className="map-island map-island--two" /><span className="map-location map-location--launch">Launch Site</span><span className="map-location map-location--outpost">Outpost</span><span className="map-location map-location--market">Супермаркет</span><button className="map-marker map-marker--player" style={{ left: "48%", top: "55%" }} aria-label="Вы"><Navigation size={11} /></button><button className="map-marker map-marker--squad" style={{ left: "35%", top: "39%" }} aria-label="Dmitry_G">D</button><button className="map-marker map-marker--squad" style={{ left: "68%", top: "32%" }} aria-label="Katya_Play">K</button><button className="map-marker map-marker--wounded" style={{ left: "72%", top: "67%" }} aria-label="RustLord_99">R</button><div className="map-hint"><Crosshair size={14} />{locale === "ru" ? "Вы рядом с Супермаркетом" : "You are near Supermarket"}</div><div className="map-zoom"><button aria-label="Zoom in"><Plus size={16} /></button><button aria-label="Zoom out"><Minus size={16} /></button></div><button className="connect-button" onClick={() => navigator.clipboard?.writeText("connect s5.prostoj.store:35100")}><Navigation size={15} />{locale === "ru" ? "Подключиться" : "Connect"}</button></div></Panel>;
}

const squad = [
  { name: "A_tonna3kg", meta: "Вы", hp: 92, avatar: "/avatar-default.png", state: "online" },
  { name: "Dmitry_G", meta: "340 м", hp: 88, initial: "D", state: "online" },
  { name: "RustLord_99", meta: "ранен", hp: 18, initial: "R", state: "wounded" },
  { name: "Katya_Play", meta: "1.2 км", hp: 100, initial: "K", state: "online" },
];

function SquadPanel({ locale }: { locale: Locale }) {
  return <Panel icon={<Users size={17} />} title={locale === "ru" ? "Команда · 4/8" : "Squad · 4/8"} action={<button className="panel-link panel-link--button"><UserPlus size={14} />{locale === "ru" ? "Пригласить" : "Invite"}</button>} className="squad-panel"><div className="squad-list">{squad.map((member, index) => <div className={`squad-member ${index === 0 ? "squad-member--self" : ""}`} key={member.name}>{member.avatar ? <img src={member.avatar} alt="" /> : <span className="squad-avatar">{member.initial}</span>}<div className="squad-member__main"><div><strong>{member.name}</strong>{index === 1 && <Volume2 size={13} />}<span className={`squad-member__meta squad-member__meta--${member.state}`}>{member.meta}</span></div><div className="health"><i className={member.state === "wounded" ? "is-wounded" : ""} style={{ width: `${member.hp}%` }} /></div></div></div>)}</div></Panel>;
}

type ChatMessage = { id: number; author: string; text: string; time: string; kind?: "system" | "moderator" | "self" };
const initialMessages: ChatMessage[] = [
  { id: 1, author: "Система", text: "Cargo Ship появится через 5 минут.", time: "14:22", kind: "system" },
  { id: 2, author: "Алексей", text: "Кто на сферу? Нужна помощь с лутом.", time: "14:20" },
  { id: 3, author: "Модератор", text: "Напоминание: завтра глобальный вайп.", time: "14:18", kind: "moderator" },
  { id: 4, author: "RustLord_99", text: "Я упал на J-14, поднимите!", time: "14:15" },
  { id: 5, author: "Вы", text: "Уже иду, держись.", time: "14:13", kind: "self" },
];

function ChatPanel({ locale }: { locale: Locale }) {
  const [channel, setChannel] = useState<"all" | "team">("all"); const [messages, setMessages] = useState(initialMessages); const [value, setValue] = useState("");
  function submit(event: FormEvent) { event.preventDefault(); const text = value.trim(); if (!text) return; setMessages([...messages.slice(-4), { id: Date.now(), author: locale === "ru" ? "Вы" : "You", text, time: new Date().toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit" }), kind: "self" }]); setValue(""); }
  return <Panel icon={<MessageSquare size={17} />} title={locale === "ru" ? "Чат" : "Chat"} action={<span className="live-label"><i />{locale === "ru" ? "онлайн" : "online"}</span>} className="chat-panel"><div className="chat-tabs"><button className={channel === "all" ? "is-active" : ""} onClick={() => setChannel("all")}>{locale === "ru" ? "Общий" : "Global"}</button><button className={channel === "team" ? "is-active" : ""} onClick={() => setChannel("team")}>{locale === "ru" ? "Команда" : "Team"}<b>2</b></button></div><div className="chat-messages">{messages.map(message => <div key={message.id} className={`chat-message chat-message--${message.kind ?? "player"}`}><div><strong>{message.author}</strong><time>{message.time}</time></div><p>{message.text}</p></div>)}</div><form className="chat-compose" onSubmit={submit}><input value={value} onChange={event => setValue(event.target.value)} placeholder={locale === "ru" ? "Сообщение..." : "Message..."} aria-label={locale === "ru" ? "Сообщение" : "Message"} /><button aria-label={locale === "ru" ? "Отправить" : "Send"}><Send size={16} /></button></form></Panel>;
}

function UpcomingWipes({ events, locale }: { events: WipeEvent[]; locale: Locale }) {
  const upcoming = events.filter(event => new Date(event.start) >= new Date("2026-08-10T00:00:00+03:00")).slice(0, 3);
  return <Panel icon={<CalendarDays size={17} />} title={locale === "ru" ? "Ближайшие вайпы" : "Upcoming wipes"} action={<a className="panel-link" href={`${SITE}/wipe-calendar`}>{locale === "ru" ? "Календарь" : "Calendar"}</a>} className="upcoming-panel"><div className="upcoming-list">{upcoming.map(event => <div key={event.id}><span><i className={`wipe-dot wipe-dot--${event.event_type}`} />{event.server ? `${event.server.index_with_hash} ${event.server.short_name}` : event.event_type_label}</span><strong>{new Intl.DateTimeFormat(locale === "ru" ? "ru-RU" : "en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }).format(new Date(event.start + "+03:00"))}</strong></div>)}</div></Panel>;
}

export function PlayerHome({ data, locale, user: _user }: { data: PublicData; locale: Locale; user: UserInfo }) {
  const active = data.servers.find(server => server.tag === "x10");
  return <main className="dashboard dashboard--player"><div className="player-left"><ServersRail servers={data.servers} online={data.stats.online} locale={locale} /><NewsPanel posts={data.posts} locale={locale} compact /></div><div className="player-center"><MiniMap locale={locale} online={active?.players ?? data.stats.online} /><ProductsPanel products={data.products} locale={locale} compact /></div><aside className="player-right"><SquadPanel locale={locale} /><ChatPanel locale={locale} /><UpcomingWipes events={data.wipeEvents} locale={locale} /></aside></main>;
}
