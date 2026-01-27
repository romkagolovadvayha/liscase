import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import React from 'react';
import '@/styles/globals.scss';
import { ConfigProvider } from 'antd';
import FontAwesomeProvider from '@/components/providers/FontAwesomeProvider';
import QueryProvider from '@/providers/QueryProvider';
import Header from '@/components/layout/Header';
import FooterClient from '@/components/layout/FooterClient';
import LeftMenu from '@/components/layout/LeftMenu';
import SidebarClient from '@/components/sidebar/SidebarClient';
import TooltipProvider from '@/components/providers/TooltipProvider';
import ToastProvider from '@/components/providers/ToastProvider';
import { TableOfContentsProvider } from '@/contexts/TableOfContentsContext';
import { SupportProvider } from '@/components/support/SupportProvider';
import { NotificationWebSocketProvider } from '@/providers/NotificationWebSocketProvider';
import { UserProvider } from '@/providers/UserProvider';
import { HomepageDataProvider } from '@/providers/HomepageDataProvider';
import LoaderWrapper from '@/components/layout/LoaderWrapper';
import SettingsCSSVariablesProvider from '@/components/providers/SettingsCSSVariablesProvider';
import { getSettingsServer } from '@/lib/services/settingsServer';
import { getLogo } from '@/lib/utils/settingsImage';

const inter = Inter({ subsets: ['latin', 'cyrillic'] });

export const metadata: Metadata = {
  title: 'Liscase',
  description: 'Rust server statistics and community platform',
};

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // Получаем настройки на сервере
  const settings = await getSettingsServer();
  const cdnUrl = settings?.site?.cdnUrl as string | null | undefined;
  const logo = getLogo(settings, cdnUrl);

  return (
    <html lang="ru">
      <head>
        {/* CSRF Token для API запросов */}
        <meta name="csrf-token" content={process.env.CSRF_TOKEN || ''} />
      </head>
      <body className={inter.className}>
        <ConfigProvider>
          <QueryProvider>
            <SettingsCSSVariablesProvider>
              <UserProvider>
                <HomepageDataProvider>
                  <FontAwesomeProvider>
                  <TooltipProvider>
                    <ToastProvider>
                      <TableOfContentsProvider>
                        <NotificationWebSocketProvider>
                          <SupportProvider>
                          <LoaderWrapper>
                          <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
                            <Header logo={logo} />
                            <div className="main-layout">
                              <LeftMenu />
                              <div className="main-layout__content">
                                <main>{children}</main>
                              </div>
                              {/* Sidebar загружает данные на клиенте */}
                              <aside className="main-layout__sidebar">
                                <SidebarClient />
                              </aside>
                            </div>
                            <FooterClient />
                          </div>
                          </LoaderWrapper>
                          </SupportProvider>
                        </NotificationWebSocketProvider>
                      </TableOfContentsProvider>
                    </ToastProvider>
                  </TooltipProvider>
                </FontAwesomeProvider>
                </HomepageDataProvider>
              </UserProvider>
            </SettingsCSSVariablesProvider>
          </QueryProvider>
        </ConfigProvider>
      </body>
    </html>
  );
}

