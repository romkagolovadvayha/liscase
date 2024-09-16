<?php

$this->title = Yii::t('common', 'Репорты');

/** @var \common\models\servers\Servers $servers */
$servers = \common\models\servers\Servers::find()
                                         ->cache(30)
                                         ->all();
$users = [];
foreach ($servers as $server) {
    $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
    $userCheckings = \common\models\user\UserChecking::find()
        ->select([
            'steam_id' => 'u.steam_id',
            'checking_at' => 'uc.created_at',
        ])
        ->alias('uc')
        ->joinWith(['user u'])
        ->andWhere(['>=', 'uc.created_at', $server->wipe])
        ->orderBy(['uc.created_at' => SORT_DESC])
        ->indexBy('steam_id')
        ->asArray()
        ->all();

    /** @var \common\models\statistics\Reports[] $reportsData */
    $reportsData = \common\models\statistics\Reports::find()
                                                ->alias('r')
                                                ->joinWith(['user u'])
                                                ->andWhere(['r.wipe' => $wipeDate])
                                                ->andWhere(['r.server_tag' => $server->tag])
                                                ->andWhere(['u.status' => \common\models\user\User::STATUS_ACTIVE])
                                                ->andWhere('u.unbanned_at IS NULL')
                                                ->all();
    foreach ($reportsData as $report) {
        if (!empty($userCheckings[$report->recepient_steam_id]) && $report->created_at < $userCheckings[$report->recepient_steam_id]['checking_at']) {
            continue;
        }
        if (empty($users[$report->recepient_steam_id])) {
            $users[$report->recepient_steam_id] = [
                'count' => 1,
                'servers' => [$server->tag],
                'steam_id' => $report->user->steam_id,
                'username' => $report->user->username,
                'user_id' => $report->user->id,
            ];
        } else {
            $users[$report->recepient_steam_id]['count'] += 1;
            if (!in_array($server->tag, $users[$report->recepient_steam_id]['servers'])) {
                $users[$report->recepient_steam_id]['servers'][] = $server->tag;
            }
        }
    }
}

$checkingProvider = new \yii\data\ArrayDataProvider([
                                                        'allModels' => $users,
                                                        'totalCount' => count($users),
                                                        'sort' => [
                                                            'attributes' => ['count'],
                                                            'defaultOrder' => ['count' => SORT_DESC],
                                                        ],
                                                        'pagination' => [
                                                            'pageSize' => 30,
                                                        ],
                                                    ]);

?>


<?=\frontend\widgets\Alert::widget()?>
<div class="row">
    <div class="col-md-12">
        <div class="mt-4">
            <h3>Репорты</h3>
            <?= \kartik\grid\GridView::widget([
                                                  'dataProvider' => $checkingProvider,
                                                  'layout'       => "{items} {pager}",
                                                  'columns'      => [
                                                      [
                                                          'attribute' => 'username',
                                                          'label'     => Yii::t('common', "Имя"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return "<a href=\"/user/profile?userId={$model['user_id']}\">{$model['username']}</a>";
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'steam_id',
                                                          'label'     => Yii::t('common', "Steam ID"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                              return "{$model['steam_id']}";
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'count',
                                                          'label'     => Yii::t('common', "Кол-во репортов"),
                                                          'format'    => 'raw',
                                                          'options'   => ['width' => '150'],
                                                          'value'          => function ($model) {
                                                              return $model['count'];
                                                          },
                                                      ],
                                                      [
                                                          'attribute' => 'servers',
                                                          'options'   => ['width' => '100'],
                                                          'label'     => Yii::t('common', "Сервер"),
                                                          'format'    => 'raw',
                                                          'value'          => function ($model) {
                                                                $text = "";
                                                                foreach ($model['servers'] as $server) {
                                                                    $text .= "<div>$server</div>";
                                                                }
                                                              return $text;
                                                          },
                                                      ],
                                                  ],
                                              ]);
            ?>
        </div>
    </div>
</div>
