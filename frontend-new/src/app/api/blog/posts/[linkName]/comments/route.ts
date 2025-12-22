import { NextRequest, NextResponse } from 'next/server';
import { query, insert, execute } from '@/lib/db';
import { cookies } from 'next/headers';

export const dynamic = 'force-dynamic';

// Получение комментариев для поста
// Используем [linkName] для совместимости с другими роутами
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ linkName: string }> }
) {
  try {
    const { linkName } = await params;
    // linkName может быть либо ID поста (число), либо link_name поста (строка)
    // Сначала пытаемся как число
    let blogIdNum = parseInt(linkName);
    
    // Если не число, ищем по link_name
    if (isNaN(blogIdNum)) {
      const [blog] = await query<{ id: number }>(`
        SELECT id FROM blog WHERE link_name = ? AND status = 1 LIMIT 1
      `, [linkName]);
      
      if (!blog) {
        return NextResponse.json({
          success: false,
          message: 'Пост не найден',
        }, { status: 404 });
      }
      
      blogIdNum = blog.id;
    }
    
    if (isNaN(blogIdNum)) {
      return NextResponse.json({
        success: false,
        message: 'Неверный ID поста',
      }, { status: 400 });
    }

    // Получаем текущего пользователя (если авторизован) для проверки лайков
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;
    let userId: number | null = null;
    
    if (authToken) {
      const [user] = await query<{ id: number }>(`
        SELECT id FROM user WHERE auth_key = ? AND status = 1 LIMIT 1
      `, [authToken]);
      userId = user?.id || null;
    }

    // Получаем комментарии для блога
    // entity = hash('crc32', 'common\models\blog\Blog') = '22977e76' (hex)
    // Используем известное значение сразу, как в старой версии
    const entityHash = '22977e76';
    
    // Для отладки: проверим, есть ли комментарии для этого поста
    console.log('Fetching comments for blogId:', blogIdNum, 'entity:', entityHash);
    
    // Проверяем, существует ли пост
    const [blogCheck] = await query<{ id: number }>(`
      SELECT id FROM blog WHERE id = ? AND status = 1 LIMIT 1
    `, [blogIdNum]);
    
    if (!blogCheck) {
      console.log('Blog post not found:', blogIdNum);
      return NextResponse.json({
        success: false,
        message: 'Пост не найден',
      }, { status: 404 });
    }
    
    // Получаем все комментарии с информацией о пользователях и лайках
    // entityHash уже определен как '22977e76'
    // ВАЖНО: порядок параметров должен соответствовать порядку ? в запросе
    // Сначала параметры для подзапроса is_liked (если userId есть), потом для WHERE
    let queryParams: any[] = [];
    let isLikedSubquery = '0 as is_liked';
    
    if (userId) {
      isLikedSubquery = '(SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id AND cl.user_id = ?) as is_liked';
      queryParams.push(userId); // Первый параметр для подзапроса is_liked
    }
    
    queryParams.push(blogIdNum); // Для WHERE c.entityId = ?
    queryParams.push(entityHash); // Для WHERE c.entity = ?
    
    // Проверяем, сколько комментариев найдется без JOIN для отладки
    const [countCheck] = await query<{ cnt: number }>(`
      SELECT COUNT(*) as cnt 
      FROM comment c
      WHERE c.entityId = ? 
        AND c.entity = ?
        AND c.status = 1
    `, [blogIdNum, entityHash]);
    
    console.log('Comments count for blogId', blogIdNum, ':', countCheck?.cnt || 0);
    
    // Проверяем кодировку колонки content (для отладки)
    const [columnInfo] = await query<any>(`
      SHOW FULL COLUMNS FROM comment WHERE Field = 'content'
    `);
    if (columnInfo) {
      console.log('Comment content column charset:', columnInfo.Collation);
    }
    
    const comments = await query<any>(`
      SELECT 
        c.id,
        c.entity,
        c.entityId,
        c.content,
        c.parentId,
        c.level,
        c.createdBy as user_id,
        c.createdAt,
        c.updatedAt,
        c.status,
        u.username,
        u.steam_id,
        up.avatar,
        (SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id) as likes_count,
        ${isLikedSubquery}
      FROM comment c
      LEFT JOIN user u ON c.createdBy = u.id
      LEFT JOIN user_profile up ON u.id = up.user_id
      WHERE c.entityId = ? 
        AND c.entity = ?
        AND c.status = 1
      ORDER BY c.createdAt ASC
    `, queryParams);
    
    console.log('Fetched comments:', comments.length);
    
    // Получаем настройки CDN для аватаров
    const baseUrl = process.env.CDN_URL || '';

    // Форматируем комментарии в древовидную структуру
    const formatComment = (comment: any) => {
      let avatar: string = 'https://storage.prostoj.store/uploads/site/design/86e6c084c19ad0c4c824c8e985b3bc8c.png'; // Fallback по умолчанию
      
      if (comment.avatar && typeof comment.avatar === 'string' && comment.avatar.trim()) {
        const avatarPath = comment.avatar.trim();
        console.log('Raw avatar path for comment', comment.id, ':', avatarPath);
        
        if (avatarPath.startsWith('http://') || avatarPath.startsWith('https://')) {
          avatar = avatarPath;
        } else {
          // Убираем начальный слеш если есть, чтобы избежать двойного слэша
          const cleanPath = avatarPath.startsWith('/') ? avatarPath.slice(1) : avatarPath;
          // Убираем завершающий слэш из baseUrl если есть
          const normalizedBaseUrl = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
          avatar = `${normalizedBaseUrl}/${cleanPath}`;
          console.log('Formatted avatar URL for comment', comment.id, ':', avatar);
        }
      } else {
        console.log('Using fallback avatar for comment', comment.id);
      }
      
      console.log('Final avatar for comment', comment.id, ':', avatar);

      // Логируем контент для отладки эмодзи
      if (comment.id && comment.content) {
        const hasEmoji = /[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/u.test(comment.content);
        if (hasEmoji) {
          console.log('Comment', comment.id, 'has emoji. Content:', comment.content);
        }
      }
      
      return {
        id: comment.id,
        content: comment.content || '',
        parentId: comment.parentId,
        level: comment.level,
        userId: comment.user_id,
        username: comment.username,
        steamId: comment.steam_id,
        avatar,
        createdAt: comment.createdAt,
        updatedAt: comment.updatedAt,
        likesCount: parseInt(comment.likes_count) || 0,
        isLiked: Boolean(comment.is_liked && parseInt(comment.is_liked) > 0),
        replies: [] as any[],
      };
    };

    // Если комментарии не найдены, попробуем найти без фильтра по entity (на случай если entity хранится по-другому)
    let finalComments = comments;
    
    if (comments.length === 0) {
      console.log('No comments found with entity filter, trying without entity filter...');
      const commentsWithoutEntity = await query<any>(`
        SELECT 
          c.id,
          c.entity,
          c.entityId,
          c.content,
          c.parentId,
          c.level,
          c.createdBy as user_id,
          c.createdAt,
          c.updatedAt,
          c.status,
          u.username,
          u.steam_id,
          up.avatar,
          (SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id) as likes_count,
          ${userId ? `(SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id AND cl.user_id = ?) as is_liked` : `0 as is_liked`}
        FROM comment c
        LEFT JOIN user u ON c.createdBy = u.id
        LEFT JOIN user_profile up ON u.id = up.user_id
        WHERE c.entityId = ? 
          AND c.status = 1
        ORDER BY c.createdAt ASC
        LIMIT 10
      `, userId ? [userId, blogIdNum] : [blogIdNum]);
      
      if (commentsWithoutEntity.length > 0) {
        console.log('Found comments without entity filter:', commentsWithoutEntity.length);
        console.log('First comment entity value:', commentsWithoutEntity[0].entity);
        // Если нашли комментарии, используем их entity
        const foundEntity = commentsWithoutEntity[0].entity?.toString() || '';
        if (foundEntity && foundEntity !== entityHash) {
          console.log('Using found entity:', foundEntity, 'instead of', entityHash);
          // Перезапрашиваем с правильным entity
          const queryParams2: any[] = [];
          if (userId) {
            queryParams2.push(userId);
          }
          queryParams2.push(blogIdNum);
          queryParams2.push(foundEntity);
          
          finalComments = await query<any>(`
            SELECT 
              c.id,
              c.entity,
              c.entityId,
              c.content,
              c.parentId,
              c.level,
              c.createdBy as user_id,
              c.createdAt,
              c.updatedAt,
              c.status,
              u.username,
              u.steam_id,
              up.avatar,
              (SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id) as likes_count,
              ${userId ? `(SELECT COUNT(*) FROM comment_like cl WHERE cl.comment_id = c.id AND cl.user_id = ?) as is_liked` : `0 as is_liked`}
            FROM comment c
            LEFT JOIN user u ON c.createdBy = u.id
            LEFT JOIN user_profile up ON u.id = up.user_id
            WHERE c.entityId = ? 
              AND c.entity = ?
              AND c.status = 1
            ORDER BY c.createdAt ASC
          `, queryParams2);
        }
      }
    }

    // Строим дерево комментариев
    const commentsMap = new Map<number, any>();
    const rootComments: any[] = [];

    // Сначала создаем все комментарии
    finalComments.forEach((comment: any) => {
      const formatted = formatComment(comment);
      commentsMap.set(comment.id, formatted);
    });

    // Затем связываем детей с родителями
    finalComments.forEach((comment: any) => {
      const formatted = commentsMap.get(comment.id)!;
      if (comment.parentId) {
        const parent = commentsMap.get(comment.parentId);
        if (parent) {
          parent.replies.push(formatted);
        } else {
          // Если родитель не найден, добавляем как корневой
          rootComments.push(formatted);
        }
      } else {
        rootComments.push(formatted);
      }
    });

    return NextResponse.json({
      success: true,
      data: rootComments,
    });
  } catch (error: any) {
    console.error('Error fetching comments:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при загрузке комментариев',
    }, { status: 500 });
  }
}

// Создание нового комментария или ответа
export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ linkName: string }> }
) {
  try {
    const { linkName } = await params;
    console.log('POST /api/blog/posts/[linkName]/comments - linkName:', linkName);
    
    // linkName может быть либо ID поста (число), либо link_name поста (строка)
    // Сначала пытаемся как число
    let blogIdNum = parseInt(linkName);
    
    // Если не число, ищем по link_name
    if (isNaN(blogIdNum)) {
      const [blog] = await query<{ id: number }>(`
        SELECT id FROM blog WHERE link_name = ? AND status = 1 LIMIT 1
      `, [linkName]);
      
      if (!blog) {
        console.log('Blog not found by link_name:', linkName);
        return NextResponse.json({
          success: false,
          message: 'Пост не найден',
        }, { status: 404 });
      }
      
      blogIdNum = blog.id;
    }
    
    if (isNaN(blogIdNum)) {
      console.log('Invalid blogIdNum:', linkName);
      return NextResponse.json({
        success: false,
        message: 'Неверный ID поста',
      }, { status: 400 });
    }

    console.log('POST - Using blogIdNum:', blogIdNum);

    // Проверка авторизации
    const cookieStore = await cookies();
    const authToken = cookieStore.get('auth_token')?.value;

    if (!authToken) {
      return NextResponse.json({
        success: false,
        message: 'Необходима авторизация',
      }, { status: 401 });
    }

    // Получаем пользователя
    const [user] = await query<{ id: number }>(`
      SELECT id FROM user WHERE auth_key = ? AND status = 1 LIMIT 1
    `, [authToken]);

    if (!user) {
      return NextResponse.json({
        success: false,
        message: 'Пользователь не найден',
      }, { status: 401 });
    }

    const body = await request.json();
    const { content, parentId } = body;

    // Валидация
    if (!content || typeof content !== 'string' || content.trim().length < 2) {
      return NextResponse.json({
        success: false,
        message: 'Комментарий должен содержать минимум 2 символа',
      }, { status: 400 });
    }

    if (content.trim().length > 5000) {
      return NextResponse.json({
        success: false,
        message: 'Комментарий слишком длинный (максимум 5000 символов)',
      }, { status: 400 });
    }

    // Получаем entity hash из существующих комментариев к блогу
    // Если комментариев к этому посту еще нет, получаем из любого комментария к блогу
    const [existingEntity] = await query<{ entity: string }>(`
      SELECT DISTINCT entity FROM comment WHERE entityId = ? LIMIT 1
    `, [blogIdNum]);
    
    let entityHash = existingEntity?.entity;
    
    // Если комментариев к этому посту еще нет, получаем из любого комментария к блогу
    // Исправляем запрос для MariaDB - убираем LIMIT из подзапроса
    if (!entityHash) {
      // Сначала получаем один ID блога
      const [blogRow] = await query<{ id: number }>(`
        SELECT id FROM blog WHERE status = 1 LIMIT 1
      `);
      
      if (blogRow) {
        const [anyEntity] = await query<{ entity: string }>(`
          SELECT DISTINCT entity FROM comment 
          WHERE entityId = ?
          LIMIT 1
        `, [blogRow.id]);
        entityHash = anyEntity?.entity || '';
      }
    }
    
    // Если все еще нет entity, используем известное значение '22977e76'
    // Это hash('crc32', 'common\models\blog\Blog') в hex формате
    if (!entityHash) {
      entityHash = '22977e76';
      console.log('Using default entityHash:', entityHash);
    } else {
      console.log('Using entityHash from DB:', entityHash);
    }

    // Определяем уровень вложенности
    let level = 1;
    if (parentId) {
      const parentCommentId = parseInt(parentId);
      if (!isNaN(parentCommentId)) {
        const [parentComment] = await query<{ level: number }>(`
          SELECT level FROM comment WHERE id = ? AND entityId = ? AND entity = ? LIMIT 1
        `, [parentCommentId, blogIdNum, entityHash]);
        
        if (parentComment) {
          level = parentComment.level + 1;
        }
      }
    }

    // Получаем URL поста для сохранения в комментарии
    const [blogPost] = await query<{ link_name: string }>(`
      SELECT link_name FROM blog WHERE id = ? LIMIT 1
    `, [blogIdNum]);
    
    const blogUrl = blogPost ? `/posts/post-${blogPost.link_name}` : '';

    // Логируем контент для отладки эмодзи
    console.log('Saving comment with content:', content);
    console.log('Content length:', content.length);
    console.log('Content bytes:', Buffer.from(content, 'utf8').toString('hex'));
    
    const trimmedContent = content.trim();
    
    // Создаем комментарий
    const commentId = await insert(`
      INSERT INTO comment (entity, entityId, content, parentId, level, createdBy, updatedBy, url, status, createdAt, updatedAt)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [
      entityHash,
      blogIdNum,
      trimmedContent,
      parentId ? parseInt(parentId) : null,
      level,
      user.id,
      user.id,
      blogUrl,
      1, // status = 1 (active)
      Math.floor(Date.now() / 1000),
      Math.floor(Date.now() / 1000),
    ]);
    
    // Проверяем, что сохранилось
    const [savedComment] = await query<any>(`
      SELECT content FROM comment WHERE id = ? LIMIT 1
    `, [commentId]);
    
    if (savedComment) {
      console.log('Saved comment content from DB:', savedComment.content);
      console.log('Saved content bytes:', Buffer.from(savedComment.content, 'utf8').toString('hex'));
    }

    return NextResponse.json({
      success: true,
      data: {
        id: commentId,
        message: 'Комментарий успешно добавлен',
      },
    });
  } catch (error: any) {
    console.error('Error creating comment:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при создании комментария',
    }, { status: 500 });
  }
}

