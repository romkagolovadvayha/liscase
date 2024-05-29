<?php

/** @var \common\models\servers\Servers[] $servers */
$servers = \common\models\servers\Servers::find()
    ->andWhere(['!=', 'db_host', ''])
    ->cache(30)
    ->all();

?>

<?php foreach ($servers as $server): ?>
<div class="boxBody widget_live_body" id="<?=$server->tag?>_live_body">
<?=$this->render('@frontend/views/widgets/_live_stats', [
        'dbHost' => $server->db_host,
        'dbName' => $server->db_name,
        'dbUser' => $server->db_user,
        'dbPassword' => $server->db_password,
        'server' => $server->tag,
    ])?>
<a href="/stats?server=<?=$server->tag?>" class="footer_button_stats"><i class="fa-solid fa-chart-pie"></i> Вся статистика</a>
</div>
<?php endforeach; ?>