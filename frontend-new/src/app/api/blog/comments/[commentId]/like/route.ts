import { NextRequest, NextResponse } from 'next/server';
import { query, insert, execute } from '@/lib/db';
import { cookies } from 'next/headers';

export const dynamic = 'force-dynamic';

// Поставить или убрать лайк с комментария
export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ commentId: string }> }
) {
  try {
    const { commentId } = await params;
    const commentIdNum = parseInt(commentId);
    
    if (isNaN(commentIdNum)) {
      return NextResponse.json({
        success: false,
        message: 'Неверный ID комментария',
      }, { status: 400 });
    }

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

    // Проверяем существование комментария
    const [comment] = await query<{ id: number }>(`
      SELECT id FROM comment WHERE id = ? AND status = 1 LIMIT 1
    `, [commentIdNum]);

    if (!comment) {
      return NextResponse.json({
        success: false,
        message: 'Комментарий не найден',
      }, { status: 404 });
    }

    // Проверяем, есть ли уже лайк
    const [existingLike] = await query<{ id: number }>(`
      SELECT id FROM comment_like 
      WHERE comment_id = ? AND user_id = ? 
      LIMIT 1
    `, [commentIdNum, user.id]);

    if (existingLike) {
      // Убираем лайк
      await execute(`
        DELETE FROM comment_like 
        WHERE comment_id = ? AND user_id = ?
      `, [commentIdNum, user.id]);

      // Получаем новое количество лайков
      const [likesData] = await query<{ count: number }>(`
        SELECT COUNT(*) as count FROM comment_like WHERE comment_id = ?
      `, [commentIdNum]);

      return NextResponse.json({
        success: true,
        data: {
          isLiked: false,
          likesCount: likesData?.count || 0,
        },
      });
    } else {
      // Ставим лайк
      const now = Math.floor(Date.now() / 1000);
      await insert(`
        INSERT INTO comment_like (comment_id, user_id, created_at)
        VALUES (?, ?, ?)
      `, [commentIdNum, user.id, now]);

      // Получаем новое количество лайков
      const [likesData] = await query<{ count: number }>(`
        SELECT COUNT(*) as count FROM comment_like WHERE comment_id = ?
      `, [commentIdNum]);

      return NextResponse.json({
        success: true,
        data: {
          isLiked: true,
          likesCount: likesData?.count || 0,
        },
      });
    }
  } catch (error: any) {
    console.error('Error toggling like:', error);
    return NextResponse.json({
      success: false,
      message: error.message || 'Ошибка при изменении лайка',
    }, { status: 500 });
  }
}




