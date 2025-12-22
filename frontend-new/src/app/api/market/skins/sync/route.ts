import { NextResponse } from 'next/server';
import { getDbConnection, query } from '@/lib/db';

type GameType = 'rust' | 'cs2';

interface SyncResult {
  inserted: number;
  updated: number;
  skipped: number;
  total: number;
}

// Маршрут для синхронизации скинов с rust.tm и cs2.tm
// Доступен только для админов (нужно добавить проверку)
export async function POST(request: Request) {
  try {
    const conn = await getDbConnection();
    
    try {
      await conn.beginTransaction();

      // Процент накрутки (можно вынести в настройки)
      const MARKUP_PERCENT = 30;

      // Синхронизируем Rust скины
      const rustResult = await syncGameSkins(conn, 'rust', 'https://rust.tm/api/v2/prices/class_instance/RUB.json', 'https://cdn.rust.tm/item/', MARKUP_PERCENT);
      
      // Синхронизируем CS2 скины (используем market.csgo.com API)
      const cs2Result = await syncGameSkins(conn, 'cs2', 'https://market.csgo.com/api/v2/prices/class_instance/RUB.json', 'https://cdn2.csgo.com/item/', MARKUP_PERCENT);

      await conn.commit();

      const totalStats = {
        inserted: rustResult.inserted + cs2Result.inserted,
        updated: rustResult.updated + cs2Result.updated,
        skipped: rustResult.skipped + cs2Result.skipped,
        total: rustResult.total + cs2Result.total,
      };

      return NextResponse.json({
        success: true,
        message: 'Синхронизация завершена',
        stats: totalStats,
        byGame: {
          rust: rustResult,
          cs2: cs2Result,
        },
      });
    } catch (error: any) {
      await conn.rollback();
      throw error;
    } finally {
      conn.release();
    }
  } catch (error: any) {
    console.error('Error syncing skins:', error);
    return NextResponse.json(
      { success: false, message: error.message || 'Ошибка при синхронизации' },
      { status: 500 }
    );
  }
}

async function syncGameSkins(
  conn: any,
  gameType: GameType,
  apiUrl: string,
  cdnBaseUrl: string,
  markupPercent: number
): Promise<SyncResult> {
  const result: SyncResult = {
    inserted: 0,
    updated: 0,
    skipped: 0,
    total: 0,
  };

  try {
    // Получаем данные с API
    const response = await fetch(apiUrl);
    if (!response.ok) {
      console.error(`Failed to fetch ${gameType} prices: ${response.statusText}`);
      return result;
    }

    const data = await response.json();
    if (!data.success || !data.items) {
      console.error(`Invalid response from ${gameType} API`);
      return result;
    }

    const items = data.items;
    result.total = Object.keys(items).length;

    // Минимальная цена 15 рублей, максимальная цена 10000 рублей
    const MIN_PRICE = 15; // в рублях
    const MAX_PRICE = 10000; // в рублях

    for (const [id, item] of Object.entries(items)) {
      const skin = item as any;

      // Пропускаем скины вне диапазона цен
      const skinPrice = parseFloat(String(skin.price || 0));
      if (skinPrice < MIN_PRICE || skinPrice > MAX_PRICE) {
        result.skipped++;
        continue;
      }

      // Парсинг class_id и instance_id из id (формат: "class_id_instance_id")
      const [classId, instanceId = '0'] = String(id).split('_');

      // Обработка названий
      const title = skin.market_hash_name?.split(' | ').pop() || skin.market_hash_name || '';
      const titleRu = skin.ru_name?.split(' | ').pop()?.replace(` (${skin.ru_quality})`, '') || null;

      // Категория
      let category = skin.market_hash_name || '';
      const isStatTrak = category.includes('StatTrak™');
      if (isStatTrak) {
        category = category.replace('StatTrak™ ', '');
      }
      category = category.split('|')[0].trim();

      // URL изображений
      const encodedName = encodeURIComponent(skin.market_hash_name || '').replace(/\+/g, '%20');
      const imageUrl = `${cdnBaseUrl}${encodedName}/100.png`;
      const image300Url = `${cdnBaseUrl}${encodedName}/300.png`;

      // Расчет нашей цены с накруткой (в копейках)
      // API возвращает цену в рублях, конвертируем в копейки
      const priceKopecks = Math.ceil(parseFloat(String(skin.price || 0)) * 100); // цена в копейках
      const ourPriceKopecks = Math.ceil(priceKopecks * (1 + markupPercent / 100));

      // Сначала проверяем по class_id+instance_id (точное совпадение)
      const [existingById] = await conn.execute(`
        SELECT id, price, our_price FROM market_skins 
        WHERE class_id = ? AND instance_id = ? AND game_type = ?
      `, [classId, instanceId, gameType]) as any[];

      if (existingById && existingById.length > 0) {
        const existingPrice = existingById[0].price;
        const existingId = existingById[0].id;
        // Если новый дешевле - обновляем, иначе пропускаем (одинаково для Rust и CS2)
        if (priceKopecks < existingPrice) {
          await conn.execute(`
            UPDATE market_skins SET
              market_hash_name = ?,
              name = ?,
              ru_name = ?,
              category = ?,
              ru_quality = ?,
              text_color = ?,
              bg_color = ?,
              price = ?,
              our_price = ?,
              markup_percent = ?,
              avg_price = ?,
              popularity_7d = ?,
              image_url = ?,
              image300_url = ?,
              is_stat_trak = ?,
              last_synced_at = NOW(),
              updated_at = NOW()
            WHERE id = ?
          `, [
            skin.market_hash_name,
            title,
            titleRu,
            category,
            skin.ru_quality || null,
            skin.text_color || null,
            skin.bg_color || null,
            priceKopecks,
            ourPriceKopecks,
            markupPercent,
            skin.avg_price ? Math.ceil(parseFloat(String(skin.avg_price)) * 100) : null,
            skin.popularity_7d || 0,
            imageUrl,
            image300Url,
            isStatTrak ? 1 : 0,
            existingId,
          ]);
          result.updated++;
        } else {
          // Пропускаем дубликат с более высокой ценой
          result.skipped++;
        }
      } else {
        // Если не найдено по ID, проверяем по названию
        const [existingByName] = await conn.execute(`
          SELECT id, price, our_price, class_id, instance_id FROM market_skins 
          WHERE market_hash_name = ? AND game_type = ?
        `, [skin.market_hash_name, gameType]) as any[];

        if (existingByName && existingByName.length > 0) {
          const existingPrice = existingByName[0].price;
          const existingId = existingByName[0].id;
          
          // Если новый дешевле - обновляем/заменяем, иначе пропускаем
          if (priceKopecks < existingPrice) {
            // Проверяем, есть ли уже запись с такими же class_id+instance_id (кроме найденной по названию)
            const [conflictCheck] = await conn.execute(`
              SELECT id FROM market_skins 
              WHERE class_id = ? AND instance_id = ? AND game_type = ? AND id != ?
            `, [classId, instanceId, gameType, existingId]) as any[];

            if (conflictCheck && conflictCheck.length > 0) {
              // Если есть конфликт - удаляем запись, найденную по названию (она будет заменена другой)
              await conn.execute(`DELETE FROM market_skins WHERE id = ?`, [existingId]);
              
              // Обновляем конфликтующую запись с правильными данными
              const conflictId = conflictCheck[0].id;
              await conn.execute(`
                UPDATE market_skins SET
                  market_hash_name = ?,
                  name = ?,
                  ru_name = ?,
                  category = ?,
                  ru_quality = ?,
                  text_color = ?,
                  bg_color = ?,
                  price = ?,
                  our_price = ?,
                  markup_percent = ?,
                  avg_price = ?,
                  popularity_7d = ?,
                  image_url = ?,
                  image300_url = ?,
                  is_stat_trak = ?,
                  last_synced_at = NOW(),
                  updated_at = NOW()
                WHERE id = ?
              `, [
                skin.market_hash_name,
                title,
                titleRu,
                category,
                skin.ru_quality || null,
                skin.text_color || null,
                skin.bg_color || null,
                priceKopecks,
                ourPriceKopecks,
                markupPercent,
                skin.avg_price ? Math.ceil(parseFloat(String(skin.avg_price)) * 100) : null,
                skin.popularity_7d || 0,
                imageUrl,
                image300Url,
                isStatTrak ? 1 : 0,
                conflictId,
              ]);
              result.updated++;
            } else {
              // Нет конфликта - обновляем найденную запись, устанавливая правильные class_id и instance_id
              await conn.execute(`
                UPDATE market_skins SET
                  class_id = ?,
                  instance_id = ?,
                  market_hash_name = ?,
                  name = ?,
                  ru_name = ?,
                  category = ?,
                  ru_quality = ?,
                  text_color = ?,
                  bg_color = ?,
                  price = ?,
                  our_price = ?,
                  markup_percent = ?,
                  avg_price = ?,
                  popularity_7d = ?,
                  image_url = ?,
                  image300_url = ?,
                  is_stat_trak = ?,
                  last_synced_at = NOW(),
                  updated_at = NOW()
                WHERE id = ?
              `, [
                classId,
                instanceId,
                skin.market_hash_name,
                title,
                titleRu,
                category,
                skin.ru_quality || null,
                skin.text_color || null,
                skin.bg_color || null,
                priceKopecks,
                ourPriceKopecks,
                markupPercent,
                skin.avg_price ? Math.ceil(parseFloat(String(skin.avg_price)) * 100) : null,
                skin.popularity_7d || 0,
                imageUrl,
                image300Url,
                isStatTrak ? 1 : 0,
                existingId,
              ]);
              result.updated++;
            }
          } else {
            // Пропускаем дубликат с более высокой ценой
            result.skipped++;
          }
        } else {
          // Если не найдено ни по ID, ни по названию - вставляем новую запись
          await conn.execute(`
            INSERT INTO market_skins (
              class_id, instance_id, game_type, market_hash_name, name, ru_name,
              category, ru_quality, text_color, bg_color,
              price, our_price, markup_percent, avg_price, popularity_7d,
              image_url, image300_url, is_stat_trak, status,
              created_at, updated_at, last_synced_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
          `, [
            classId,
            instanceId,
            gameType,
            skin.market_hash_name,
            title,
            titleRu,
            category,
            skin.ru_quality || null,
            skin.text_color || null,
            skin.bg_color || null,
            priceKopecks,
            ourPriceKopecks,
            markupPercent,
            skin.avg_price ? Math.ceil(parseFloat(String(skin.avg_price)) * 100) : null,
            skin.popularity_7d || 0,
            imageUrl,
            image300Url,
            isStatTrak ? 1 : 0,
          ]);
          result.inserted++;
        }
      }
    }
  } catch (error: any) {
    console.error(`Error syncing ${gameType} skins:`, error);
    // Продолжаем работу, даже если одна игра не синхронизировалась
  }

  return result;
}

