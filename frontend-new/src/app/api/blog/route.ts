import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

const POSTS_PER_PAGE = 10;

// Кэширование на 10 минут (600 секунд)
export const revalidate = 600;

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const page = parseInt(searchParams.get('page') || '1', 10);
    const limit = parseInt(searchParams.get('limit') || POSTS_PER_PAGE.toString(), 10);
    const search = searchParams.get('search') || '';
    const categoryId = searchParams.get('category_id');
    const sort = searchParams.get('sort') || 'created_at';
    const order = searchParams.get('order') || 'desc';

    const offset = (page - 1) * limit;

    let sqlQuery = `
      SELECT 
        b.id,
        b.name,
        b.description,
        b.link_name,
        b.views,
        b.created_at,
        b.blog_category_id,
        bc.name as category_name,
        bc.link_name as category_link_name,
        bc.id as category_id,
        pc.link_name as parent_category_link_name,
        pc.id as parent_category_id,
        (SELECT link FROM blog_image WHERE blog_id = b.id ORDER BY id ASC LIMIT 1) as image_url,
        (SELECT COUNT(*) FROM comment WHERE entityId = b.id AND entity = '22977e76' AND status = 1) as comments_count
      FROM blog b
      LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
      LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
      WHERE b.status = 1
    `;

    const params: any[] = [];

    if (search) {
      sqlQuery += ` AND b.name LIKE ?`;
      params.push(`%${search}%`);
    }

    if (categoryId) {
      // Проверяем, является ли выбранная категория родительской или дочерней
      const categoryInfo = await query(`
        SELECT id, blog_category_id as parent_id 
        FROM blog_category 
        WHERE id = ? AND status = 1
        LIMIT 1
      `, [categoryId]);
      
      if ((categoryInfo as any[]).length > 0) {
        const catInfo = (categoryInfo as any[])[0];
        
        if (catInfo.parent_id === null) {
          // Это родительская категория - включаем все дочерние категории
          const childCategories = await query(`
            SELECT id FROM blog_category 
            WHERE blog_category_id = ? AND status = 1
          `, [categoryId]);
          
          const categoryIds = [categoryId];
          (childCategories as any[]).forEach((cat: any) => {
            categoryIds.push(cat.id);
          });
          
          const placeholders = categoryIds.map(() => '?').join(',');
          sqlQuery += ` AND b.blog_category_id IN (${placeholders})`;
          params.push(...categoryIds);
        } else {
          // Это дочерняя категория - показываем только записи этой категории
          sqlQuery += ` AND b.blog_category_id = ?`;
          params.push(categoryId);
        }
      }
    }

    // Подсчет общего количества
    // Создаем отдельный запрос для подсчета, убирая подзапросы и сортировку
    let countQuery = `
      SELECT COUNT(DISTINCT b.id) as total
      FROM blog b
      LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
      LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
      WHERE b.status = 1
    `;
    
    const countParams: any[] = [];
    
    if (search) {
      countQuery += ` AND b.name LIKE ?`;
      countParams.push(`%${search}%`);
    }
    
    if (categoryId) {
      // Проверяем, является ли выбранная категория родительской или дочерней
      const categoryInfo = await query(`
        SELECT id, blog_category_id as parent_id 
        FROM blog_category 
        WHERE id = ? AND status = 1
        LIMIT 1
      `, [categoryId]);
      
      if ((categoryInfo as any[]).length > 0) {
        const catInfo = (categoryInfo as any[])[0];
        
        if (catInfo.parent_id === null) {
          // Это родительская категория - включаем все дочерние категории
          const childCategories = await query(`
            SELECT id FROM blog_category 
            WHERE blog_category_id = ? AND status = 1
          `, [categoryId]);
          
          const categoryIds = [categoryId];
          (childCategories as any[]).forEach((cat: any) => {
            categoryIds.push(cat.id);
          });
          
          const placeholders = categoryIds.map(() => '?').join(',');
          countQuery += ` AND b.blog_category_id IN (${placeholders})`;
          countParams.push(...categoryIds);
        } else {
          // Это дочерняя категория - показываем только записи этой категории
          countQuery += ` AND b.blog_category_id = ?`;
          countParams.push(categoryId);
        }
      }
    }
    
    const countResult = await query(countQuery, countParams);
    const total = countResult[0]?.total || 0;

    // Сортировка
    const orderBy = order === 'asc' ? 'ASC' : 'DESC';
    const sortField = sort === 'views' ? 'b.views' : 'b.created_at';
    sqlQuery += ` ORDER BY ${sortField} ${orderBy}`;

    // Пагинация
    sqlQuery += ` LIMIT ? OFFSET ?`;
    params.push(limit, offset);

    const rows = await query(sqlQuery, params);

    // Получаем S3 URL из переменных окружения или используем дефолтный адрес
    const s3Url = process.env.S3_URL || process.env.NEXT_PUBLIC_S3_URL || 'https://storage.prostoj.store';
    
    // Формируем URL для каждого поста (старый формат: /posts/{categoryLinkName}/{categoryLinkNameChild?}/post-{blogLinkName})
    const posts = (rows as any[]).map((row) => {
      // Формируем URL как в старой версии
      let url = '';
      if (row.parent_category_link_name && row.category_link_name) {
        // Есть родительская категория и дочерняя
        url = `/posts/${row.parent_category_link_name}/${row.category_link_name}/post-${row.link_name}`;
      } else if (row.category_link_name) {
        // Только категория
        url = `/posts/${row.category_link_name}/post-${row.link_name}`;
      } else {
        // Нет категории (не должно быть, но на всякий случай)
        url = `/posts/post-${row.link_name}`;
      }
      
      // Форматируем URL изображения
      let imageUrl = null;
      if (row.image_url) {
        // Если это полный URL, используем как есть
        if (row.image_url.startsWith('http://') || row.image_url.startsWith('https://')) {
          imageUrl = row.image_url;
        } else {
          // Используем S3 URL + /blog/ + link
          imageUrl = `${s3Url}/blog/${row.image_url}`;
        }
      }

      return {
        id: row.id,
        title: row.name,
        description: row.description,
        image: imageUrl,
        category: row.category_name,
        date: row.created_at,
        views: row.views,
        commentsCount: parseInt(row.comments_count) || 0,
        url,
        link_name: row.link_name,
      };
    });

    const totalPages = Math.ceil(total / limit);

    const response = NextResponse.json({
      success: true,
      data: {
        posts,
        pagination: {
          page,
          limit,
          total,
          totalPages,
        },
      },
    });

    // Устанавливаем заголовки кэширования (10 минут = 600 секунд)
    response.headers.set('Cache-Control', 'public, s-maxage=600, stale-while-revalidate=1200');

    return response;
  } catch (error: any) {
    console.error('Error fetching blog posts:', error);
    return NextResponse.json(
      {
        success: false,
        message: error.message || 'Ошибка при загрузке новостей',
      },
      { status: 500 }
    );
  }
}

