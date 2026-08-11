"use client";

import { ArrowRight, Gamepad2, ShieldCheck } from "lucide-react";
import type { Locale, PublicData } from "../lib/types";
import { NewsPanel, ProductsPanel, ProjectStatsCard, ServersGrid, WipeCalendar } from "./shared";

export function GuestHome({ data, locale, onSteam }: { data: PublicData; locale: Locale; onSteam: () => void }) {
  return <main className="dashboard dashboard--guest"><div className="guest-main"><ServersGrid servers={data.servers} online={data.stats.online} locale={locale} /><NewsPanel posts={data.posts} locale={locale} /><ProductsPanel products={data.products} locale={locale} /></div><aside className="guest-side"><WipeCalendar events={data.wipeEvents} locale={locale} /><section className="guest-cta ui-panel"><div className="guest-cta__orb" /><div className="guest-cta__content"><span className="eyebrow"><ShieldCheck size={15} />{locale === "ru" ? "Личный игровой центр" : "Personal game hub"}</span><h1>{locale === "ru" ? "Всё для игры — на одном экране" : "Everything you need on one screen"}</h1><p>{locale === "ru" ? "Войдите, чтобы открыть карту сервера, команду, чат и быстрые игровые действия." : "Sign in to unlock the server map, squad, chat and quick game actions."}</p><button className="cta-button" onClick={onSteam}><img src="/icons/steam.svg" alt="" />{locale === "ru" ? "Войти через Steam" : "Sign in with Steam"}<ArrowRight size={16} /></button></div><Gamepad2 className="guest-cta__watermark" /></section><ProjectStatsCard stats={data.stats} locale={locale} /></aside></main>;
}
