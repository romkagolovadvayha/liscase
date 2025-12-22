import { NextRequest, NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET(
  request: NextRequest,
  { params }: { params: { linkName: string } }
) {
  try {
    const { linkName } = params;

    // Получаем тег сервера
    const [tag] = await query<any>(`
      SELECT 
        id,
        name,
        title,
        link_name,
        short_description,
        description,
        color,
        sort,
        status
      FROM servers_tags
      WHERE link_name = ? AND status = 1
      LIMIT 1
    `, [linkName]);

    if (!tag) {
      return NextResponse.json(
        { success: false, message: 'Tag not found' },
        { status: 404 }
      );
    }

    // Получаем серверы с этим тегом
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
      INNER JOIN servers_tags_relation str ON s.id = str.server_id
      WHERE str.tag_id = ? AND s.status IN (1, 2, 0)
      ORDER BY s.sort ASC, s.id ASC
    `, [tag.id]);

    // Получаем все теги для каждого сервера
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
    serverTags.forEach((tagItem: any) => {
      if (!tagsByServer.has(tagItem.server_id)) {
        tagsByServer.set(tagItem.server_id, []);
      }
      tagsByServer.get(tagItem.server_id)!.push({
        id: tagItem.tag_id,
        name: tagItem.name,
        link_name: tagItem.link_name,
        color: tagItem.color,
        title: tagItem.title,
        short_description: tagItem.short_description,
      });
    });

    // Форматируем данные
    const formattedServers = servers.map((server: any) => {
      const tags = tagsByServer.get(server.id) || [];
      
      let statusText = 'Выключен';
      if (server.status === 1) {
        statusText = 'Онлайн';
      } else if (server.status === 2) {
        statusText = 'Скоро откроется';
      }

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
      data: {
        tag: {
          id: tag.id,
          name: tag.name,
          title: tag.title,
          link_name: tag.link_name,
          short_description: tag.short_description,
          description: tag.description,
          color: tag.color,
        },
        servers: formattedServers,
      },
    });
  } catch (error: any) {
    console.error('Error fetching server tag:', error);
    return NextResponse.json(
      { success: false, message: error.message },
      { status: 500 }
    );
  }
}










