import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ linkName: string }> }
) {
  try {
    const { linkName: linkNameParam } = await params;
    // Убираем префикс "post-" если есть (для обратной совместимости)
    const linkName = linkNameParam.startsWith('post-') 
      ? linkNameParam.replace(/^post-/, '') 
      : linkNameParam;

    const rows = await query(
      `
      SELECT 
        b.id,
        b.name,
        b.description,
        b.content,
        b.link_name,
        b.views,
        b.created_at,
        b.keywords,
        b.blog_category_id,
        bc.name as category_name,
        bc.link_name as category_link_name,
        pc.name as parent_category_name,
        pc.link_name as parent_category_link_name,
        (SELECT GROUP_CONCAT(link ORDER BY id ASC) FROM blog_image WHERE blog_id = b.id) as images,
        (SELECT COUNT(*) FROM comment WHERE entityId = b.id AND entity = '22977e76' AND status = 1) as comments_count
      FROM blog b
      LEFT JOIN blog_category bc ON b.blog_category_id = bc.id
      LEFT JOIN blog_category pc ON bc.blog_category_id = pc.id
      WHERE b.status = 1 AND b.link_name = ?
      LIMIT 1
    `,
      [linkName]
    );

    const posts = rows as any[];

    if (posts.length === 0) {
      return NextResponse.json(
        {
          success: false,
          message: 'Новость не найдена',
        },
        { status: 404 }
      );
    }

    const row = posts[0];

    // Формируем URL (старый формат: /posts/{categoryLinkName}/{categoryLinkNameChild?}/post-{blogLinkName})
    let url = '';
    if (row.parent_category_link_name && row.category_link_name) {
      url = `/posts/${row.parent_category_link_name}/${row.category_link_name}/post-${row.link_name}`;
    } else if (row.category_link_name) {
      url = `/posts/${row.category_link_name}/post-${row.link_name}`;
    } else {
      url = `/posts/post-${row.link_name}`;
    }

    // Получаем S3 URL из переменных окружения или используем дефолтный адрес
    const s3Url = process.env.S3_URL || process.env.NEXT_PUBLIC_S3_URL || 'https://storage.prostoj.store';
    
    // Парсим изображения и форматируем их URL
    const images = row.images ? row.images.split(',') : [];
    const formatImageUrl = (link: string | null): string | null => {
      if (!link) return null;
      // Если это полный URL, используем как есть
      if (link.startsWith('http://') || link.startsWith('https://')) {
        return link;
      } else {
        // Используем S3 URL + /blog/ + link
        return `${s3Url}/blog/${link}`;
      }
    };

    const post = {
      id: row.id,
      title: row.name,
      description: row.description,
      content: row.content,
      keywords: row.keywords,
      image: formatImageUrl(images[0] || null),
      images: images.map(formatImageUrl).filter((img): img is string => img !== null),
      category: row.category_name,
      categoryLinkName: row.category_link_name,
      parentCategoryName: row.parent_category_name,
      parentCategoryLinkName: row.parent_category_link_name,
      date: row.created_at,
      views: row.views,
      commentsCount: row.comments_count || 0,
      url,
      link_name: row.link_name, // Добавляем link_name для использования в компоненте
    };

    return NextResponse.json({
      success: true,
      data: post,
    });
  } catch (error: any) {
    console.error('Error fetching blog post:', error);
    return NextResponse.json(
      {
        success: false,
        message: error.message || 'Ошибка при загрузке новости',
      },
      { status: 500 }
    );
  }
}

