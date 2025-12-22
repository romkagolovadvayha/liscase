import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET() {
  try {
    // Получаем список серверов с тегами
    const servers = await query<any>(`
      SELECT 
        s.id,
        s.name,
        s.monitoring_name,
        s.monitoring_description,
        s.tag,
        s.ip,
        s.port,
        s.players,
        s.joined,
        s.queued,
        s.max,
        s.team_limit,
        s.status,
        s.wipe,
        s.wipe_type,
        s.next_wipe,
        s.global_wipe,
        s.secret_map,
        s.map_id,
        s.map_list_id,
        s.sort
      FROM servers s
      WHERE s.status IN (1, 2, 0)
      ORDER BY s.sort ASC, s.id ASC
    `);

    // Получаем теги для каждого сервера
    const serverIds = servers.map((s: any) => s.id);
    let serverTags: any[] = [];
    
    if (serverIds.length > 0) {
      const placeholders = serverIds.map(() => '?').join(',');
      serverTags = await query<any>(`
        SELECT 
          str.server_id,
          st.id as tag_id,
          st.name,
          st.link_name,
          st.color,
          st.title,
          st.short_description
        FROM servers_tags_relation str
        INNER JOIN servers_tags st ON str.tag_id = st.id
        WHERE str.server_id IN (${placeholders}) AND st.status = 1
        ORDER BY st.sort ASC
      `, serverIds);
    }

    // Группируем теги по серверам
    const tagsByServer = new Map<number, any[]>();
    serverTags.forEach((tag: any) => {
      if (!tagsByServer.has(tag.server_id)) {
        tagsByServer.set(tag.server_id, []);
      }
      tagsByServer.get(tag.server_id)!.push({
        id: tag.tag_id,
        name: tag.name,
        link_name: tag.link_name,
        color: tag.color,
        title: tag.title,
        short_description: tag.short_description,
      });
    });

    // Форматируем данные
    const formattedServers = servers.map((server: any) => {
      const tags = tagsByServer.get(server.id) || [];
      
      // Определяем статус
      let statusText = 'Выключен';
      if (server.status === 1) {
        statusText = 'Онлайн';
      } else if (server.status === 2) {
        statusText = 'Скоро откроется';
      }

      // Вычисляем процент заполненности
      const totalPlayers = server.players + server.joined;
      const percentPlayers = server.max > 0 ? (totalPlayers / server.max) * 100 : 0;

      return {
        id: server.id,
        name: server.name,
        monitoring_name: server.monitoring_name,
        monitoring_description: server.monitoring_description,
        tag: server.tag,
        ip: server.ip,
        port: server.port,
        players: server.players,
        joined: server.joined,
        queued: server.queued,
        max: server.max,
        team_limit: server.team_limit,
        status: server.status,
        statusText,
        wipe: server.wipe,
        wipe_type: server.wipe_type,
        next_wipe: server.next_wipe,
        global_wipe: server.global_wipe,
        secret_map: server.secret_map === 1,
        map_id: server.map_id,
        map_list_id: server.map_list_id,
        tags,
        percentPlayers: Math.min(percentPlayers, 100),
        totalPlayers,
      };
    });

    return NextResponse.json({
      success: true,
      data: formattedServers,
    });
  } catch (error: any) {
    console.error('Error fetching servers:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}
