import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(request: NextRequest) {
  try {
    // Получаем только родительские категории (где blog_category_id IS NULL)
    const parentCategories = await query(`
      SELECT 
        id,
        name,
        link_name
      FROM blog_category
      WHERE status = 1 AND blog_category_id IS NULL
      ORDER BY created_at DESC
    `);

    // Для каждой родительской категории получаем дочерние
    const categories = await Promise.all(
      (parentCategories as any[]).map(async (parent) => {
        const children = await query(`
          SELECT 
            id,
            name,
            link_name,
            blog_category_id as parent_id
          FROM blog_category
          WHERE status = 1 AND blog_category_id = ?
          ORDER BY created_at DESC
        `, [parent.id]);

        // Формируем URL для категории
        const url = `/posts/${parent.link_name}`;

        return {
          id: parent.id,
          name: parent.name,
          linkName: parent.link_name,
          url,
          children: (children as any[]).map((child) => {
            // URL для дочерней категории: /posts/{parentLinkName}/{childLinkName}
            const childUrl = `/posts/${parent.link_name}/${child.link_name}`;
            return {
              id: child.id,
              name: child.name,
              linkName: child.link_name,
              parentId: child.parent_id,
              url: childUrl,
            };
          }),
        };
      })
    );

    return NextResponse.json({
      success: true,
      data: categories,
    });
  } catch (error: any) {
    console.error('Error fetching blog categories:', error);
    return NextResponse.json(
      {
        success: false,
        message: error.message || 'Ошибка при загрузке категорий',
      },
      { status: 500 }
    );
  }
}




