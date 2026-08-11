import type { Metadata } from "next";
import { Roboto } from "next/font/google";
import "./globals.css";

const roboto = Roboto({
  variable: "--font-roboto",
  subsets: ["latin", "cyrillic"],
  weight: ["400", "500", "700"],
});

export const metadata: Metadata = {
  title: "PROSTOJ — игровые серверы Rust",
  description: "Серверы, магазин, новости и личный игровой центр PROSTOJ.",
  icons: { icon: "/logo.svg", shortcut: "/logo.svg" },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru">
      <body className={roboto.variable}>{children}</body>
    </html>
  );
}
