import type { PublicData, UserInfo } from "./types";

export const fallbackData: PublicData = {
  servers: [
    { id: 1, tag: "max3", name: "ПРОСТОЙ #1 [MAX3]", monitoring_name: "MAX3", status: 1, players: 3, max: 100, ip: "195.18.27.175", port: 35200, text_ip: "s1.prostoj.store", nextWipe: "2026-08-21 16:00:00", wipeType: "Каждые две недели" },
    { id: 2, tag: "classicx2", name: "ПРОСТОЙ #2 [X2]", monitoring_name: "X2", status: 1, players: 2, max: 400, ip: "195.18.27.53", port: 35000, text_ip: "s2.prostoj.store", nextWipe: "2026-08-14 16:00:00", wipeType: "Еженедельно" },
    { id: 6, tag: "pve", name: "ПРОСТОЙ #4 PVE [Мирный]", monitoring_name: "PVE", status: 1, players: 19, max: 50, ip: "185.207.214.198", port: 35700, text_ip: "s4.prostoj.store", nextWipe: "2026-08-21 16:00:00", wipeType: "Каждые две недели" },
    { id: 7, tag: "x10", name: "ПРОСТОЙ #5 [X10]", monitoring_name: "X10", status: 1, players: 33, max: 200, ip: "195.18.27.53", port: 35100, text_ip: "s5.prostoj.store", nextWipe: "2026-08-21 16:00:00", wipeType: "Каждые две недели" },
    { id: 9, tag: "classic14x2", name: "ПРОСТОЙ #6 [X2]", monitoring_name: "X2", status: 1, players: 20, max: 250, ip: "195.18.27.175", port: 35100, text_ip: "s6.prostoj.store", nextWipe: "2026-08-21 16:00:00", wipeType: "Каждые две недели" },
  ],
  stats: { users: 301205, online: 77, count: 5 },
  posts: [
    { id: 1519, title: "Обновление Rust Built Different — новая модель игрока, M16A2 и броня", description: "Большой обзор главного обновления Rust.", image: "https://storage.prostoj.store/blog/1519_11f78820dd9921e0480d5a63ab438bec.png", views: 863, url: "/posts", createdAt: "2026-06-05 13:29:12" },
    { id: 1518, title: "Rust: обновление Май 2026 — новые верстаки и DLC", views: 3150, url: "/posts", createdAt: "2026-05-08 12:00:00" },
    { id: 1517, title: "Новый Twitch Drops Апрель — Round 49", views: 2185, url: "/posts", createdAt: "2026-04-24 12:00:00" },
    { id: 1516, title: "Rust обновление 02.04 — оружие, строительство и технологии", views: 1650, url: "/posts", createdAt: "2026-04-02 12:00:00" },
    { id: 1515, title: "EAC TPM verification failed — как исправить", views: 2095, url: "/posts", createdAt: "2026-03-16 10:33:51" },
  ],
  products: [
    { id: 972, name: "VIP", image: "https://storage.prostoj.store/uploads/drop150/972_1_856c8fdf972a2ac8ec5af6f9b7a64ead.png", price: 399, priceReal: 399 },
    { id: 958, name: "Набор рейдера", image: "https://storage.prostoj.store/uploads/drop150/958_1_07350b3fb4ec5c805b2d7ec781fea70a.png", price: 499, priceReal: 499 },
    { id: 982, name: "Набор пирата", image: "https://storage.prostoj.store/uploads/drop150/982_1_fe1e39223853bc1ea12da1e2957b6ae7.png", price: 299, priceReal: 299 },
    { id: 960, name: "Набор киллер", image: "https://storage.prostoj.store/uploads/drop150/960_1_5406383335d63592e0c8aa7ff9e6e266.png", price: 255, priceReal: 255 },
    { id: 1037, name: "Набор пехотинца", image: "https://storage.prostoj.store/uploads/drop150/1037_1_eba736c6507879d303b345ef21120506.png", price: 339, priceReal: 339 },
    { id: 1027, name: "Набор подрывника", image: "https://storage.prostoj.store/uploads/drop150/1027_1_496df5eb7ed8ccfd211cab054560578c.png", price: 299, priceReal: 299 },
  ],
  wipeEvents: [
    { id: 80, event_type: "map_wipe", event_type_label: "Вайп карты", calendar_title: "ПРОСТОЙ #2 [X2]", event_at: "2026-08-14 16:00:00", start: "2026-08-14T16:00:00", server: { index_with_hash: "#2", short_name: "X2", tag: "classicx2" } },
    { id: 77, event_type: "map_wipe", event_type_label: "Вайп карты", calendar_title: "ПРОСТОЙ #5 [X10]", event_at: "2026-08-21 16:00:00", start: "2026-08-21T16:00:00", server: { index_with_hash: "#5", short_name: "X10", tag: "x10" } },
    { id: 79, event_type: "global_wipe", event_type_label: "Глобальный вайп", calendar_title: "ПРОСТОЙ #2 [X2]", event_at: "2026-08-21 16:00:00", start: "2026-08-21T16:00:00", server: { index_with_hash: "#2", short_name: "X2", tag: "classicx2" } },
  ],
};

export const demoUser: UserInfo = { username: "A_tonna3kg", avatar: "/avatar-default.png", balance: 1240, serverTag: "#5 X10" };
