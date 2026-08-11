"use client";

import { LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { demoUser, fallbackData } from "../lib/fallbacks";
import type { BlogPost, Locale, ProductItem, PublicData, ServerItem, UserInfo, ViewMode, WipeEvent } from "../lib/types";
import { AppHeader } from "./Header";
import { GuestHome } from "./GuestHome";
import { PlayerHome } from "./PlayerHome";

const API = "https://api.prostoj.store/v1";

async function getJson<T>(url: string, locale: Locale, token?: string): Promise<T> {
  const response = await fetch(url, { headers: { Accept: "application/json", "Accept-Language": locale === "ru" ? "ru-RU" : "en-US", ...(token ? { Authorization: `Bearer ${token}` } : {}) } });
  if (!response.ok) throw new Error(`${response.status}`);
  return response.json() as Promise<T>;
}

function AppFooter({ locale, loading }: { locale: Locale; loading: boolean }) {
  return <footer className="app-footer"><span>© 2026 prostoj.store</span><div><span className="system-state">{loading ? <LoaderCircle size={13} className="spin" /> : <i />}{loading ? (locale === "ru" ? "Обновляем данные" : "Refreshing data") : (locale === "ru" ? "Все системы работают" : "All systems operational")}</span><a href="https://prostoj.store/rules">{locale === "ru" ? "Правила" : "Rules"}</a><a href="https://prostoj.store/privacy-policy">{locale === "ru" ? "Конфиденциальность" : "Privacy"}</a><a href="https://prostoj.store/support">{locale === "ru" ? "Поддержка" : "Support"}</a></div></footer>;
}

export function HomeApp() {
  const [locale, setLocale] = useState<Locale>("ru");
  const [mode, setMode] = useState<ViewMode>("guest");
  const [data, setData] = useState<PublicData>(fallbackData);
  const [user, setUser] = useState<UserInfo>(demoUser);
  const [loading, setLoading] = useState(true);
  const [showDevSwitch, setShowDevSwitch] = useState(false);

  useEffect(() => {
    document.documentElement.lang = locale;
    let active = true;
    setLoading(true);
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const endDay = new Date(year, now.getMonth() + 1, 0).getDate();
    Promise.allSettled([
      getJson<{ success: boolean; data: { servers: ServerItem[]; projectStats?: { users: string | number; online: string | number; count: string | number } } }>(`${API}/servers`, locale),
      getJson<{ success: boolean; data: { posts: BlogPost[] } }>(`${API}/blog?limit=5&page=1&sort=created_at&order=desc`, locale),
      getJson<{ success: boolean; data: ProductItem[] }>(`${API}/products?limit=6&offset=0`, locale),
      getJson<{ success: boolean; data: { events: WipeEvent[] } }>(`${API}/wipe-calendar?start=${year}-${month}-01&end=${year}-${month}-${String(endDay).padStart(2, "0")}`, locale),
    ]).then(results => {
      if (!active) return;
      const next = { ...fallbackData };
      const servers = results[0].status === "fulfilled" ? results[0].value.data : null;
      if (servers?.servers?.length) next.servers = servers.servers;
      if (servers?.projectStats) next.stats = { users: Number(servers.projectStats.users), online: Number(servers.projectStats.online), count: Number(servers.projectStats.count) };
      if (results[1].status === "fulfilled" && results[1].value.data.posts?.length) next.posts = results[1].value.data.posts.slice(0, 5);
      if (results[2].status === "fulfilled" && results[2].value.data?.length) next.products = results[2].value.data.slice(0, 6);
      if (results[3].status === "fulfilled" && results[3].value.data.events?.length) next.wipeEvents = results[3].value.data.events;
      setData(next);
      setLoading(false);
    });
    return () => { active = false; };
  }, [locale]);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const forced = params.get("view");
    setShowDevSwitch(process.env.NODE_ENV === "development" || forced === "guest" || forced === "auth");
    if (forced === "auth") { setMode("auth"); setUser(demoUser); return; }
    if (forced === "guest") { setMode("guest"); return; }
    const token = localStorage.getItem("access_token");
    if (!token) return;
    getJson<{ success: boolean; data: { username?: string; avatar?: string; server?: { monitoring_name?: string; name?: string }; balances?: { personal?: { balance?: number | string } } } }>(`${API}/auth/me?expand=balance`, locale, token).then(result => {
      if (!result.success) return;
      setUser({ username: result.data.username || demoUser.username, avatar: result.data.avatar || demoUser.avatar, balance: Number(result.data.balances?.personal?.balance ?? 0), serverTag: result.data.server?.monitoring_name ? `# ${result.data.server.monitoring_name}` : demoUser.serverTag });
      setMode("auth");
    }).catch(() => setMode("guest"));
  }, [locale]);

  function startSteamAuth() {
    const oauth = new URL("https://api.prostoj.store/v1/auth/oauth");
    oauth.searchParams.set("redirect_uri", window.location.origin);
    window.location.href = oauth.toString();
  }

  function switchPreview(next: ViewMode) {
    setMode(next);
    const url = new URL(window.location.href);
    url.searchParams.set("view", next);
    window.history.replaceState({}, "", url);
  }

  return <div className="app-shell"><AppHeader locale={locale} onLocale={setLocale} mode={mode} user={user} onSteam={startSteamAuth} />{mode === "guest" ? <GuestHome data={data} locale={locale} onSteam={startSteamAuth} /> : <PlayerHome data={data} locale={locale} user={user} />}<AppFooter locale={locale} loading={loading} />{showDevSwitch && <div className="view-switch" aria-label="Preview mode"><button className={mode === "guest" ? "is-active" : ""} onClick={() => switchPreview("guest")}>Гость</button><button className={mode === "auth" ? "is-active" : ""} onClick={() => switchPreview("auth")}>Игрок</button></div>}</div>;
}
