import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import '@/styles/globals.scss';
import { ConfigProvider } from 'antd';
import FontAwesomeProvider from '@/components/providers/FontAwesomeProvider';
import QueryProvider from '@/providers/QueryProvider';
import Header from '@/components/layout/Header';
import Footer from '@/components/layout/Footer';
import Sidebar from '@/components/sidebar/Sidebar';
import TooltipProvider from '@/components/providers/TooltipProvider';
import ToastProvider from '@/components/providers/ToastProvider';
import { TableOfContentsProvider } from '@/contexts/TableOfContentsContext';
import { query } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';
import { cookies } from 'next/headers';

const inter = Inter({ subsets: ['latin', 'cyrillic'] });

export const metadata: Metadata = {
  title: 'Liscase',
  description: 'Rust server statistics and community platform',
};

async function getSidebarData() {
  try {
    // Получаем серверы (как в старой версии)
    const servers = await query<any>(`
      SELECT 
        id,
        name,
        tag,
        ip,
        port,
        players,
        joined,
        queued,
        max,
        status,
        wipe_type,
        next_wipe,
        description,
        monitoring_name,
        monitoring_description
      FROM servers
      WHERE status IN (1, 2)
      ORDER BY sort ASC, id ASC
    `);

    // Получаем общее количество онлайн игроков
    const [onlineStats] = await query<{ total_online: number }>(`
      SELECT COALESCE(SUM(players), 0) as total_online
      FROM servers
      WHERE status = 1
    `);

    // Форматируем данные серверов для компонента (как в старой версии)
    const formattedServers = servers.map((server: any) => {
      let nextWipeTimestamp = 0;
      if (server.next_wipe) {
        const wipeDate = new Date(server.next_wipe);
        if (!isNaN(wipeDate.getTime())) {
          nextWipeTimestamp = Math.floor(wipeDate.getTime() / 1000);
        }
      }

      // Вычисляем проценты заполненности (как в методе monitoring())
      const totalPlayers = (server.players || 0) + (server.joined || 0);
      const max = server.max || 1;
      let percentPlayers = 0;
      let percentJoined = 0;
      let percentPlayersAbsolute = 0;
      let percentJoinedAbsolute = 0;

      if (totalPlayers > 0) {
        percentPlayers = Math.ceil((100 / max) * (server.players || 0));
        percentJoined = Math.ceil((100 / max) * (server.joined || 0));
        const percentAbsoluteCount = 100 / (percentPlayers + percentJoined || 1);
        percentPlayersAbsolute = Math.ceil(percentAbsoluteCount * percentPlayers);
        percentJoinedAbsolute = Math.ceil(percentAbsoluteCount * percentJoined);
      }

      return {
        id: server.id,
        tag: server.tag || '',
        name: server.monitoring_name || server.name || '',
        description: server.monitoring_description || server.description || server.name || '',
        status: server.status || 0,
        players: server.players || 0,
        max: server.max || 0,
        joined: server.joined || 0,
        queued: server.queued || 0,
        ip: server.ip || '',
        port: server.port || 0,
        nextWipe: nextWipeTimestamp,
        wipeType: getWipeTypeName(server.wipe_type),
        // Данные для прогресс-бара (как в методе monitoring())
        monitoring: {
          percentPlayers,
          percentJoined,
          percentQueued: Math.ceil((100 / max) * (server.queued || 0)),
          percentPlayersAbsolute,
          percentJoinedAbsolute,
          percentQueuedAbsolute: Math.ceil((100 / (percentPlayers + percentJoined || 1)) * Math.ceil((100 / max) * (server.queued || 0))),
        },
      };
    });

    return {
      servers: formattedServers,
      projectStats: {
        online: onlineStats?.total_online || 0,
      },
    };
  } catch (error) {
    console.error('Error fetching sidebar data:', error);
    return {
      servers: [],
      projectStats: {
        online: 0,
      },
    };
  }
}

function getWipeTypeName(wipeType: number | null): string {
  if (!wipeType) return 'Не указано';
  
  const wipeTypes: Record<number, string> = {
    7: 'Еженедельно',
    14: 'Каждые две недели',
    30: 'Раз в месяц',
  };
  return wipeTypes[wipeType] || 'Не указано';
}

async function getCurrentUser() {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    console.log('Reading cookie:', {
      hasToken: !!authToken,
      tokenPreview: authToken ? authToken.substring(0, 10) + '...' : 'none',
    });

    if (!authToken) {
      console.log('No auth token found in cookies');
      return null;
    }

    // Ищем пользователя по auth_key
    const [user] = await query<{
      id: number;
      username: string;
      steam_id: string;
      balance: number | null;
      avatar: string | null;
    }>(`
      SELECT 
        u.id,
        u.username,
        u.steam_id,
        ub.balance,
        up.avatar
      FROM user u
      LEFT JOIN user_profile up ON u.id = up.user_id
      LEFT JOIN user_balance ub ON u.id = ub.user_id AND ub.type = 1
      WHERE u.auth_key = ? AND u.status = 1
      LIMIT 1
    `, [authToken]);

    if (!user) {
      return null;
    }

    // Получаем активный VIP
    const [activeVip] = await query<{
      expires_at: string;
    }>(`
      SELECT expires_at
      FROM user_vip
      WHERE user_id = ? AND expires_at > NOW()
      ORDER BY expires_at DESC
      LIMIT 1
    `, [user.id]);

    // Форматируем аватар
    const avatar = user.avatar || '/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png';

    return {
      id: user.id,
      username: user.username,
      steamId: user.steam_id,
      balance: user.balance || 0,
      avatar: avatar,
      activeVip: activeVip ? {
        expires_at: activeVip.expires_at,
        timestamp: Math.floor(new Date(activeVip.expires_at).getTime() / 1000),
      } : null,
    };
  } catch (error) {
    console.error('Error fetching current user:', error);
    return null;
  }
}

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const sidebarData = await getSidebarData();
  const settings = await getSettings();
  const currentUser = await getCurrentUser();

  // Извлекаем нужные настройки для Header и Footer
  const headerSettings = {
    logo: (settings.design_logo as string) || '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg',
  };

  const footerSettings = {
    logo: (settings.design_logo as string) || '/uploads/site/design/0554f1c40e29411f9422851a1918153c.svg',
    email: (settings.site_email as string) || 'support@example.com',
    domain: (settings.site_domain as string) || 'prostoj.store',
    inn: (settings.personal_info_ip_inn as string) || '180600035048',
    ipName: (settings.personal_info_ip_fio as string) || 'ИП УСКОВ АРТЕМ ОЛЕГОВИЧ',
    socialTelegram: (settings.social_telegram_channel as string) || '',
    socialVk: (settings.social_vk as string) || '',
    socialDiscord: (settings.social_discord as string) || '',
  };

  return (
    <html lang="ru">
      <head>
        {/* CSRF Token для API запросов */}
        <meta name="csrf-token" content={process.env.CSRF_TOKEN || ''} />
      </head>
      <body className={inter.className}>
        <ConfigProvider>
          <QueryProvider>
            <FontAwesomeProvider>
              <TooltipProvider>
                <ToastProvider>
                  <TableOfContentsProvider>
                    <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
                      <Header
                        isGuest={!currentUser}
                        logo={headerSettings.logo}
                        balance={currentUser?.balance}
                        avatar={currentUser?.avatar}
                        username={currentUser?.username}
                        steamId={currentUser?.steamId}
                        activeVip={currentUser?.activeVip}
                      />
                      <div className="main-layout">
                        <div className="main-layout__content">
                          <main>{children}</main>
                        </div>
                        <aside className="main-layout__sidebar main-layout__sidebar--hide-on-profile">
                          <Sidebar
                            servers={sidebarData.servers}
                            projectStats={sidebarData.projectStats}
                          />
                        </aside>
                      </div>
                      <Footer
                        logo={footerSettings.logo}
                        email={footerSettings.email}
                        domain={footerSettings.domain}
                        inn={footerSettings.inn}
                        ipName={footerSettings.ipName}
                        socialLinks={[
                          ...(footerSettings.socialTelegram ? [{ name: 'telegram' as const, url: footerSettings.socialTelegram }] : []),
                          ...(footerSettings.socialVk ? [{ name: 'vk' as const, url: footerSettings.socialVk }] : []),
                          ...(footerSettings.socialDiscord ? [{ name: 'discord' as const, url: footerSettings.socialDiscord }] : []),
                        ]}
                      />
                    </div>
                  </TableOfContentsProvider>
                </ToastProvider>
              </TooltipProvider>
            </FontAwesomeProvider>
          </QueryProvider>
        </ConfigProvider>
      </body>
    </html>
  );
}

