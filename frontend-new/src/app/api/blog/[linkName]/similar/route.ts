import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ linkName: string }> }
) {
  try {
    const { linkName } = await params;
    const limit = 8;

    // Получаем текущий пост для определения заголовка и категории
    const currentPost = await query(
      `
      SELECT 
        b.id,
        b.name,
        b.blog_category_id,
        bc.blog_category_id as parent_category_id
      FROM blog b
      LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
      WHERE b.status = 1 AND b.link_name = ?
      LIMIT 1
    `,
      [linkName]
    );

    const posts = currentPost as any[];

    if (posts.length === 0) {
      return NextResponse.json({
        success: false,
        message: 'Пост не найден',
      });
    }

    const post = posts[0];
    
    // Токенизация: чистим, нормализуем, выкидываем стоп-слова и коротыши (как в старой версии)
    const tokenize = (text: string): string[] => {
      // Декодируем HTML сущности
      text = text.replace(/&[#\w]+;/g, ' ').replace(/<[^>]*>/g, ' ');
      // Приводим к нижнему регистру
      text = text.toLowerCase();
      // Заменяем ё на е
      text = text.replace(/ё/g, 'е');
      // Оставляем только буквы, цифры, дефис и пробелы
      text = text.replace(/[^\p{L}\p{N}\s\-]+/gu, ' ');
      // Множественные пробелы в один
      text = text.replace(/\s+/g, ' ').trim();
      
      // Разбиваем на слова и фильтруем
      const words = text.split(' ')
        .map(w => w.trim())
        .filter(w => w.length >= 3); // минимум 3 символа
      
      // Стоп-слова (ru + общие) - как в старой версии
      const stopWords = [
        'без','близ','в','во','вместо','вне','для','до','за','и','из','изо','под','к','ко','кроме',
        'между','на','над','о','об','обо','от','ото','перед','передо','пред','предо','по','подо',
        'при','про','ради','с','со','сквозь','среди','у','через','но','или',
        // частые мусорные
        'это','как','так','чтоб','чтобы','быть','есть','будет','тут','там','тема','про','по','надо'
      ];
      
      // Убираем стоп-слова и дубликаты
      const uniqueWords = [...new Set(words.filter(w => !stopWords.includes(w)))];
      
      return uniqueWords.slice(0, 8); // Берем первые 8 слов, как в старой версии
    };

    // Токенизируем заголовок текущего поста
    const tokens = tokenize(post.name);
    
    console.log('Post title:', post.name);
    console.log('Tokens:', tokens);
    
    // Если токены не найдены, используем простой fallback - последние записи
    if (tokens.length === 0) {
      console.log('No tokens found, using simple fallback');
      // Пропускаем дальше к fallback логике
    }

    let nameConditions = '';
    let descConditions = '';
    
    // Если есть токены, формируем условия поиска
    if (tokens.length > 0) {
      // Формируем условие для поиска - упрощаем: ищем посты где хотя бы один токен встречается в названии
      // Используем OR для всех токенов в названии (более гибкий поиск)
      nameConditions = tokens.map(() => `b.name LIKE ?`).join(' OR ');
      
      // Также ищем по описанию для большей релевантности
      descConditions = tokens.map(() => `b.description LIKE ?`).join(' OR ');
    }

    // Формируем запрос для похожих записей
    // Сначала ищем в той же категории, если не найдем достаточно - ищем везде
    let sqlQuery = `
      SELECT 
        b.id,
        b.name,
        b.description,
        b.content,
        b.link_name,
        b.views,
        b.created_at,
        b.blog_category_id,
        bc.name as category_name,
        bc.link_name as category_link_name,
        pc.link_name as parent_category_link_name,
        (SELECT link FROM blog_image WHERE blog_id = b.id ORDER BY id ASC LIMIT 1) as image_url
      FROM blog b
      LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
      LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
      WHERE b.status = 1 AND b.id != ?
      ${tokens.length > 0 ? `AND (${nameConditions} OR ${descConditions})` : ''}
    `;

    const paramsArray: any[] = [post.id];
    
    // Добавляем параметры для поиска по названию и описанию (только если есть токены)
    if (tokens.length > 0) {
      tokens.forEach((token: string) => {
        paramsArray.push(`%${token}%`);
      });
      
      tokens.forEach((token: string) => {
        paramsArray.push(`%${token}%`);
      });
    }

    // Параметры для поиска (без категории пока)
    const baseParams = [...paramsArray];
    
    // Если есть категория, сначала пытаемся найти в той же категории
    if (post.blog_category_id) {
      sqlQuery += ` AND b.blog_category_id = ?`;
      paramsArray.push(post.blog_category_id);
    }

    sqlQuery += ` ORDER BY b.created_at DESC LIMIT ?`;
    paramsArray.push(limit * 3); // Получаем больше записей для подсчета релевантности

    console.log('SQL Query (with category):', sqlQuery);
    console.log('Params count:', paramsArray.length);
    
    let similarPosts = await query(sqlQuery, paramsArray);
    
    console.log('Similar posts found in category:', similarPosts.length);
    
    // Если в категории нашли меньше 5 записей И есть токены, ищем везде (как fallback)
    if ((similarPosts as any[]).length < limit && post.blog_category_id && tokens.length > 0) {
      console.log('Not enough posts in category, searching everywhere...');
      
      // Создаем запрос без фильтра по категории
      let fallbackQuery = `
        SELECT 
          b.id,
          b.name,
          b.description,
          b.content,
          b.link_name,
          b.views,
          b.created_at,
          b.blog_category_id,
          bc.name as category_name,
          bc.link_name as category_link_name,
          pc.link_name as parent_category_link_name,
          (SELECT link FROM blog_image WHERE blog_id = b.id ORDER BY id ASC LIMIT 1) as image_url
        FROM blog b
        LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
        LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
        WHERE b.status = 1 AND b.id != ?
          AND (${nameConditions} OR ${descConditions})
        ORDER BY b.created_at DESC LIMIT ?
      `;
      
      const fallbackParams = [
        post.id,
        ...tokens.map((token: string) => `%${token}%`), // Используем все токены для поиска по названию
        ...tokens.map((token: string) => `%${token}%`), // Используем все токены для поиска по описанию
        limit * 3
      ];
      
      const fallbackPosts = await query(fallbackQuery, fallbackParams);
      console.log('Fallback posts found:', (fallbackPosts as any[]).length);
      
      // Объединяем результаты, убирая дубликаты
      const existingIds = new Set((similarPosts as any[]).map((p: any) => p.id));
      const newPosts = (fallbackPosts as any[]).filter((p: any) => !existingIds.has(p.id));
      similarPosts = [...(similarPosts as any[]), ...newPosts] as any;
      
      console.log('Total similar posts after fallback:', (similarPosts as any[]).length);
    }

    // Подсчитываем релевантность только если есть токены
    let topSimilarPosts;
    if (tokens.length > 0) {
      const postsWithMatches = (similarPosts as any[]).map((row) => {
        const searchText = `${row.name} ${row.description || ''} ${row.content || ''}`.toLowerCase().replace(/ё/g, 'е');
        
        // Подсчитываем количество совпадений токенов
        const matches = tokens.filter((token: string) => {
          const tokenLower = token.toLowerCase();
          return searchText.includes(tokenLower);
        }).length;
        
        return {
          ...row,
          relevanceScore: matches,
        };
      });

      // Сортируем по релевантности (количество совпадений), затем по дате
      topSimilarPosts = postsWithMatches
        .sort((a, b) => {
          if (b.relevanceScore !== a.relevanceScore) {
            return b.relevanceScore - a.relevanceScore;
          }
          return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        })
        .slice(0, limit);
    } else {
      // Если токенов нет, просто берем первые записи
      topSimilarPosts = (similarPosts as any[]).slice(0, limit);
    }

    const s3Url = process.env.S3_URL || 'https://storage.prostoj.store';

    const formatImageUrl = (link: string | null): string | null => {
      if (!link) return null;
      if (link.startsWith('http://') || link.startsWith('https://')) {
        return link;
      } else {
        return `${s3Url}/blog/${link}`;
      }
    };

    const formatPostUrl = (row: any): string => {
      if (row.parent_category_link_name && row.category_link_name) {
        return `/posts/${row.parent_category_link_name}/${row.category_link_name}/post-${row.link_name}`;
      } else if (row.category_link_name) {
        return `/posts/${row.category_link_name}/post-${row.link_name}`;
      } else {
        return `/posts/post-${row.link_name}`;
      }
    };

    const formattedPosts = topSimilarPosts.map((row) => ({
      id: row.id,
      title: row.name,
      description: row.description,
      image: formatImageUrl(row.image_url),
      category: row.category_name,
      date: row.created_at,
      url: formatPostUrl(row),
    }));

    console.log('Final similar posts count:', formattedPosts.length);
    console.log('Formatted posts:', formattedPosts.map(p => ({ id: p.id, title: p.title })));
    
    // Если все еще нет результатов, делаем простой fallback - берем последние записи из категории или вообще последние
    if (formattedPosts.length === 0) {
      console.log('No similar posts found by tokens, using simple fallback...');
      
      let fallbackQuery = `
        SELECT 
          b.id,
          b.name,
          b.description,
          b.content,
          b.link_name,
          b.views,
          b.created_at,
          b.blog_category_id,
          bc.name as category_name,
          bc.link_name as category_link_name,
          pc.link_name as parent_category_link_name,
          (SELECT link FROM blog_image WHERE blog_id = b.id ORDER BY id ASC LIMIT 1) as image_url
        FROM blog b
        LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
        LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
        WHERE b.status = 1 AND b.id != ?
      `;
      
      const fallbackParams: any[] = [post.id];
      
      // Если есть категория, берем из той же категории
      if (post.blog_category_id) {
        fallbackQuery += ` AND b.blog_category_id = ?`;
        fallbackParams.push(post.blog_category_id);
      }
      
      fallbackQuery += ` ORDER BY b.created_at DESC LIMIT ?`;
      fallbackParams.push(limit);
      
      const fallbackPosts = await query(fallbackQuery, fallbackParams);
      console.log('Fallback posts found (simple):', (fallbackPosts as any[]).length);
      
      const s3Url = process.env.S3_URL || 'https://storage.prostoj.store';
      
      const formatImageUrl = (link: string | null): string | null => {
        if (!link) return null;
        if (link.startsWith('http://') || link.startsWith('https://')) {
          return link;
        } else {
          return `${s3Url}/blog/${link}`;
        }
      };
      
      const formatPostUrl = (row: any): string => {
        if (row.parent_category_link_name && row.category_link_name) {
          return `/posts/${row.parent_category_link_name}/${row.category_link_name}/post-${row.link_name}`;
        } else if (row.category_link_name) {
          return `/posts/${row.category_link_name}/post-${row.link_name}`;
        } else {
          return `/posts/post-${row.link_name}`;
        }
      };
      
      const simpleFallbackPosts = (fallbackPosts as any[]).map((row) => ({
        id: row.id,
        title: row.name,
        description: row.description,
        image: formatImageUrl(row.image_url),
        category: row.category_name,
        date: row.created_at,
        url: formatPostUrl(row),
      }));
      
      return NextResponse.json({
        success: true,
        data: simpleFallbackPosts,
      });
    }
    
    return NextResponse.json({
      success: true,
      data: formattedPosts,
    });
  } catch (error: any) {
    console.error('Error fetching similar posts:', error);
    return NextResponse.json(
      {
        success: false,
        message: error.message || 'Ошибка при загрузке похожих записей',
      },
      { status: 500 }
    );
  }
}

