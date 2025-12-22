import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { query, execute } from '@/lib/db';
import { getSettings } from '@/lib/services/settings';

export const dynamic = 'force-dynamic';

/**
 * Получение профиля пользователя
 */
export async function GET() {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      }, { status: 401 });
    }

    // Получаем данные пользователя и профиля
    const [userData] = await query<{
      user_id: number;
      username: string;
      steam_id: string;
      raid_notify: number;
      ban_notify: number;
      telegram_chat_id: string | null;
      discord_id: string | null;
      trade_link: string | null;
      youtube_link: string | null;
      twitch_link: string | null;
      vk_link: string | null;
      telegram_link: string | null;
      is_hide_online: number;
      is_hide_team: number;
    }>(`
      SELECT 
        u.id as user_id,
        u.username,
        u.steam_id,
        u.raid_notify,
        u.ban_notify,
        u.telegram_chat_id,
        u.discord_id,
        up.trade_link,
        up.youtube_link,
        up.twitch_link,
        up.vk_link,
        up.telegram_link,
        COALESCE(up.is_hide_online, 0) as is_hide_online,
        COALESCE(up.is_hide_team, 0) as is_hide_team
      FROM user u
      LEFT JOIN user_profile up ON u.id = up.user_id
      WHERE u.auth_key = ? AND u.status = 1
      LIMIT 1
    `, [authToken]);

    if (!userData) {
      return NextResponse.json({
        success: false,
        isGuest: true,
      }, { status: 401 });
    }

    // Проверяем наличие VIP
    const [vipData] = await query<{ count: number }>(`
      SELECT COUNT(*) as count
      FROM user_vip
      WHERE user_id = ? AND expires_at > NOW()
    `, [userData.user_id]);

    const hasVip = (vipData?.count || 0) > 0;

    // Получаем VIP товар для кнопки "Купить VIP"
    const [vipDrop] = await query<{ id: number; name: string }>(`
      SELECT id, name
      FROM \`drop\`
      WHERE drop_type = 5
        AND market_status = 1
        AND status = 1
      ORDER BY sort ASC
      LIMIT 1
    `);

    // Получаем настройки для Telegram бота
    const settings = await getSettings();
    const telegramBotUsername = (settings.tgbot_login as string) || '';

    return NextResponse.json({
      success: true,
      profile: {
        raid_notify: Boolean(userData.raid_notify),
        ban_notify: Boolean(userData.ban_notify),
        trade_link: userData.trade_link || '',
        telegram_chat_id: userData.telegram_chat_id,
        discord_id: userData.discord_id,
        youtube_link: userData.youtube_link || '',
        twitch_link: userData.twitch_link || '',
        vk_link: userData.vk_link || '',
        telegram_link: userData.telegram_link || '',
        is_hide_online: Boolean(userData.is_hide_online),
        is_hide_team: Boolean(userData.is_hide_team),
        hasVip,
        vipDrop: vipDrop ? { id: vipDrop.id, name: vipDrop.name } : null,
        telegramBotUsername,
      },
    });
  } catch (error: any) {
    console.error('Error fetching profile:', error);
    return NextResponse.json({
      success: false,
      message: error.message,
    }, { status: 500 });
  }
}

/**
 * Обновление профиля пользователя
 */
export async function POST(request: Request) {
  try {
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        message: 'Не авторизован',
      }, { status: 401 });
    }

    const body = await request.json();
    const {
      raid_notify,
      ban_notify,
      trade_link,
      youtube_link,
      twitch_link,
      vk_link,
      telegram_link,
      is_hide_online,
      is_hide_team,
      telegram_disabled,
      discord_disabled,
    } = body;

    // Получаем ID пользователя
    const [user] = await query<{ id: number; telegram_chat_id: string | null; discord_id: string | null }>(`
      SELECT id, telegram_chat_id, discord_id
      FROM user
      WHERE auth_key = ? AND status = 1
      LIMIT 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json({
        success: false,
        message: 'Пользователь не найден',
      }, { status: 401 });
    }

    // Проверяем наличие VIP для настроек приватности
    const [vipData] = await query<{ count: number }>(`
      SELECT COUNT(*) as count
      FROM user_vip
      WHERE user_id = ? AND expires_at > NOW()
    `, [user.id]);

    const hasVip = (vipData?.count || 0) > 0;

    // Валидация trade_link
    if (trade_link && trade_link.trim() !== '') {
      if (!trade_link.includes('steamcommunity.com')) {
        return NextResponse.json({
          success: false,
          message: 'Ссылка на обмен указана неверно!',
          errors: { trade_link: 'Ссылка на обмен указана неверно!' },
        }, { status: 400 });
      }
    }

    // Обновляем user_profile
    const updateProfileFields: string[] = [];
    const updateProfileValues: any[] = [];

    if (trade_link !== undefined) {
      updateProfileFields.push('trade_link = ?');
      updateProfileValues.push(trade_link && trade_link.trim() !== '' ? trade_link.trim() : null);
    }

    if (youtube_link !== undefined) {
      updateProfileFields.push('youtube_link = ?');
      updateProfileValues.push(youtube_link && youtube_link.trim() !== '' ? youtube_link.trim() : null);
    }

    if (twitch_link !== undefined) {
      updateProfileFields.push('twitch_link = ?');
      updateProfileValues.push(twitch_link && twitch_link.trim() !== '' ? twitch_link.trim() : null);
    }

    if (vk_link !== undefined) {
      updateProfileFields.push('vk_link = ?');
      updateProfileValues.push(vk_link && vk_link.trim() !== '' ? vk_link.trim() : null);
    }

    if (telegram_link !== undefined) {
      updateProfileFields.push('telegram_link = ?');
      updateProfileValues.push(telegram_link && telegram_link.trim() !== '' ? telegram_link.trim() : null);
    }

    // Настройки приватности (только для VIP)
    if (hasVip) {
      if (is_hide_online !== undefined) {
        updateProfileFields.push('is_hide_online = ?');
        updateProfileValues.push(Boolean(is_hide_online) ? 1 : 0);
      }
      if (is_hide_team !== undefined) {
        updateProfileFields.push('is_hide_team = ?');
        updateProfileValues.push(Boolean(is_hide_team) ? 1 : 0);
      }
    } else {
      // Если нет VIP, сбрасываем флаги
      if (is_hide_online !== undefined) {
        updateProfileFields.push('is_hide_online = 0');
      }
      if (is_hide_team !== undefined) {
        updateProfileFields.push('is_hide_team = 0');
      }
    }

    if (updateProfileFields.length > 0) {
      updateProfileValues.push(user.id);
      await execute(`
        UPDATE user_profile
        SET ${updateProfileFields.join(', ')}
        WHERE user_id = ?
      `, updateProfileValues);
    }

    // Обновляем user
    const updateUserFields: string[] = [];
    const updateUserValues: any[] = [];

    if (raid_notify !== undefined) {
      updateUserFields.push('raid_notify = ?');
      updateUserValues.push(Boolean(raid_notify) ? 1 : 0);
    }

    if (ban_notify !== undefined) {
      updateUserFields.push('ban_notify = ?');
      updateUserValues.push(Boolean(ban_notify) ? 1 : 0);
    }

    if (telegram_disabled) {
      updateUserFields.push('telegram_chat_id = NULL');
    }

    if (discord_disabled) {
      updateUserFields.push('discord_id = NULL');
    }

    if (updateUserFields.length > 0) {
      updateUserValues.push(user.id);
      await execute(`
        UPDATE user
        SET ${updateUserFields.join(', ')}
        WHERE id = ?
      `, updateUserValues);
    }

    return NextResponse.json({
      success: true,
      message: 'Профиль успешно обновлен',
    });
  } catch (error: any) {
    console.error('Error updating profile:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при обновлении профиля',
    }, { status: 500 });
  }
}

