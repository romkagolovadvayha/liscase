<?php

namespace common\components\servers;

use common\models\servers\Servers;

/**
 * Разбор отображаемого имени сервера как на фронте (см. prostoj-frontend ServerDetailClient: #{index} {monitoring_name}).
 */
final class ServerDisplayNameParts
{
    /**
     * @return array{
     *   index: int,
     *   index_with_hash: string,
     *   short_name: string,
     *   tag: string
     * }
     */
    public static function fromServer(Servers $server): array
    {
        $index = (int) ($server->sort ?: $server->id ?: 1);
        $monitoringDesc = (string) ($server->monitoring_description ?: $server->description ?: '');
        if ($monitoringDesc !== '' && preg_match('/(?:#|№)\s*(\d+)/iu', $monitoringDesc, $m)) {
            $index = (int) $m[1];
        }

        $shortName = trim((string) ($server->monitoring_name ?? ''));
        if ($shortName === '') {
            $shortName = strtoupper((string) $server->tag);
        }

        return [
            'index' => $index,
            'index_with_hash' => '#' . $index,
            'short_name' => $shortName,
            'tag' => (string) $server->tag,
        ];
    }
}
