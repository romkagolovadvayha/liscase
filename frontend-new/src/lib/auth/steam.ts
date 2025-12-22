import { query, getDbConnection } from '@/lib/db';
import bcrypt from 'bcryptjs';
import crypto from 'crypto';
import { getSettings } from '@/lib/services/settings';

/**
 * Генерирует случайную строку для auth_key
 */
function generateAuthKey(): string {
  return crypto.randomBytes(32).toString('hex');
}

/**
 * Генерирует хеш пароля
 */
async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 10);
}

/**
 * Генерирует уникальный реферальный код
 */
async function generateRefCode(): Promise<string> {
  let refCode: string;
  let exists = true;
  
  do {
    refCode = String(Date.now() * Math.floor(Math.random() * 10000000)).substring(0, 10);
    const [result] = await query<{ count: number }>(`
      SELECT COUNT(*) as count FROM user WHERE ref_code = ?
    `, [refCode]);
    exists = (result?.count || 0) > 0;
  } while (exists);
  
  return refCode;
}

/**
 * Генерирует уникальный socket_room
 */
async function generateSocketRoom(): Promise<string> {
  let socketRoom: string;
  let exists = true;
  
  do {
    const randomStr = crypto.randomBytes(10).toString('hex');
    socketRoom = crypto.createHash('md5').update(`${Date.now()}_${randomStr}`).digest('hex');
    const [result] = await query<{ count: number }>(`
      SELECT COUNT(*) as count FROM user WHERE socket_room = ?
    `, [socketRoom]);
    exists = (result?.count || 0) > 0;
  } while (exists);
  
  return socketRoom;
}

/**
 * Создает нового пользователя со всеми необходимыми записями
 */
export async function createSteamUser(steamId: string, username: string = steamId): Promise<number | null> {
  // Проверяем, что steam_id валидный (17 символов)
  if (steamId.length !== 17) {
    throw new Error('Invalid Steam ID');
  }

  // Проверяем, не существует ли уже пользователь с таким steam_id
  const [existingUser] = await query<{ id: number }>(`
    SELECT id FROM user WHERE steam_id = ?
  `, [steamId]);

  if (existingUser) {
    return existingUser.id;
  }

  const settings = await getSettings();
  const defaultAvatar = settings.design_avatar_default as string || '/uploads/site/design/default-avatar.png';
  const domain = settings.site_domain as string || 'prostoj.store';
  const avatarLink = `https://${domain}${defaultAvatar}`;

  // Генерируем необходимые значения
  const password = crypto.randomBytes(32).toString('hex');
  const passwordHash = await hashPassword(password);
  const authKey = generateAuthKey();
  const refCode = await generateRefCode();
  const socketRoom = await generateSocketRoom();
  const email = `${steamId}@steam.com`;

  const conn = await getDbConnection();
  
  try {
    // Начинаем транзакцию
    await conn.beginTransaction();

    // Создаем пользователя
    const [userResult] = await conn.execute(`
      INSERT INTO user (
        email,
        steam_id,
        username,
        password_hash,
        auth_key,
        ref_code,
        socket_room,
        status,
        auto,
        created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
    `, [email, steamId, username, passwordHash, authKey, refCode, socketRoom]) as any;

    const userId = userResult.insertId;

    // Обновляем user_id (в старой версии это делается после сохранения)
    await conn.execute(`UPDATE user SET user_id = ? WHERE id = ?`, [userId, userId]);

    // Создаем запись в auth
    await conn.execute(`
      INSERT INTO auth (user_id, source, source_id, created_at)
      VALUES (?, 'steam', ?, NOW())
    `, [userId, steamId]);

    // Создаем user_profile
    await conn.execute(`
      INSERT INTO user_profile (user_id, name, full_name, created_at)
      VALUES (?, ?, ?, NOW())
    `, [userId, username, username.trim()]);

    // Добавляем в user_tree (по умолчанию к пользователю с ID 509)
    await conn.execute(`
      INSERT INTO user_tree (user_id, parent_user_id, level, lft, rgt, created_at)
      VALUES (?, 509, 1, 1, 1, NOW())
    `, [userId]);

    // Коммитим транзакцию
    await conn.commit();

    return userId;
  } catch (error) {
    // Откатываем транзакцию при ошибке
    await conn.rollback();
    console.error('Error creating Steam user:', error);
    throw error;
  } finally {
    // Всегда освобождаем соединение
    conn.release();
  }
}

/**
 * Находит пользователя по Steam ID
 */
export async function findUserBySteamId(steamId: string): Promise<{ id: number; username: string; email: string; auth_key: string } | null> {
  const [user] = await query<{ id: number; username: string; email: string; auth_key: string }>(`
    SELECT id, username, email, auth_key
    FROM user
    WHERE steam_id = ? AND status = 1
  `, [steamId]);

  return user || null;
}

