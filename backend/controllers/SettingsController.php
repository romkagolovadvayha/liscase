<?php
namespace backend\controllers;

use backend\components\BackendController;
use backend\components\SettingsCatalog;
use common\components\helpers\Role;
use common\helpers\SettingsCacheHelper;
use Yii;
use yii\filters\VerbFilter;
use common\models\site\SiteSetting;
use yii\web\NotFoundHttpException;

class SettingsController extends BackendController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'form' => ['POST'],
                    'register-max-webhook' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex($category = null)
    {
        $navigation = SettingsCatalog::navigation();
        $category = SettingsCatalog::normalizeCategory($category);
        $categoryMeta = SettingsCatalog::findCategory($navigation, $category);
        if ($categoryMeta === null) {
            $category = SettingsCatalog::firstCategory($navigation);
            $categoryMeta = SettingsCatalog::findCategory($navigation, $category)
                ?? SettingsCatalog::category($category);
        }

        $settings = SiteSetting::find()
            ->andWhere(['category' => $category])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fa-solid fa-plus" aria-hidden="true"></i><span>Добавить параметр</span>',
                'url' => ['/settings/create', 'category' => $category],
                'encode' => false,
                'class' => 'ds-btn ds-btn--primary ds-btn--sm',
            ],
        ];

        $this->view->title = 'Настройки · ' . $categoryMeta['label'];

        return $this->render('pages/default', [
            'category' => $category,
            'categoryMeta' => $categoryMeta,
            'navigation' => $navigation,
            'settings' => $settings,
            'totalCount' => SettingsCatalog::totalCount($navigation),
        ]);
    }

    public function actionForm($category, $itemsFlexClass = null)
    {
        $category = SettingsCatalog::normalizeCategory($category);
        $settings = SiteSetting::find()
            ->andWhere(['category' => $category])
            ->indexBy('id')
            ->all();

        if (Yii::$app->request->isPost) {
            $postSettings = Yii::$app->request->post('settings', []);
            if (!is_array($postSettings)) {
                $postSettings = [];
            }
            $saveFailed = false;
            foreach ($postSettings as $id => $value) {
                if (!isset($settings[$id]) || is_array($value)) {
                    continue;
                }
                /** @var SiteSetting $setting */
                $setting = $settings[$id];
                if (SettingsCatalog::isSensitive($setting) && (string) $value === '') {
                    continue;
                }
                $setting->value = (string) $value;
                if (!$setting->save()) {
                    $saveFailed = true;
                    Yii::warning(['setting' => $setting->id, 'errors' => $setting->errors], __METHOD__);
                }
            }
            if (!empty($_FILES['settings']['tmp_name']) && is_array($_FILES['settings']['tmp_name'])) {
                foreach ($_FILES['settings']['tmp_name'] as $id => $tmpName) {
                    if (isset($settings[$id]) && !empty($tmpName)) {
                        /** @var SiteSetting $setting */
                        $setting = $settings[$id];
                        $uploadedValue = null;
                        if ($setting->type === 'image') {
                            $uploadedValue = $this->_loadImage($tmpName, $setting->category, $_FILES['settings']['name'][$id] ?? '', $setting->code, $setting->value);
                        } elseif ($setting->type === 'video') {
                            $uploadedValue = $this->_loadVideo($tmpName, $setting->category, $_FILES['settings']['name'][$id] ?? '', $setting->code, $setting->value);
                        }
                        if ($uploadedValue !== null) {
                            $setting->value = $uploadedValue;
                            $saveFailed = !$setting->save() || $saveFailed;
                        }
                    }
                }
            }
            if (in_array($category, ['colors', 'design'])) {
                Yii::$app->settings->genColors();
            }
            Yii::$app->settings->getSettings(true);
            Yii::$app->session->setFlash(
                $saveFailed ? 'warning' : 'success',
                $saveFailed ? 'Часть параметров не удалось сохранить. Проверьте журнал ошибок.' : 'Настройки сохранены.'
            );
            Yii::$app->cache->delete('Settings_getSettings');

            if ($category === 'maxSupport') {
                try {
                    $webhook = Yii::$app->maxSupportBot->ensureSupportWebhook();
                    if (($webhook['status'] ?? '') === 'created') {
                        Yii::$app->session->setFlash('success', 'Настройки сохранены, webhook MAX зарегистрирован.');
                    } elseif (($webhook['status'] ?? '') === 'updated') {
                        Yii::$app->session->setFlash('success', 'Настройки сохранены, webhook MAX обновлён.');
                    }
                } catch (\Throwable $e) {
                    Yii::warning('MAX webhook registration failed: ' . $e->getMessage(), __METHOD__);
                    Yii::$app->session->setFlash(
                        'warning',
                        'Настройки сохранены, но webhook MAX не зарегистрирован: ' . $e->getMessage()
                    );
                }
            }
            
            // Сбрасываем кэш API настроек (все возможные комбинации категорий)
            $this->clearApiSettingsCache();
            
            // Сбрасываем кэш реферальной системы, если изменялись настройки рефералов
            $referralSettings = ['referral_percent', 'referral_bonus', 'referral_skin', 'referral_minSum', 'referral_maxSum', 'section_referral'];
            $hasReferralChanges = false;
            foreach ($referralSettings as $settingCode) {
                foreach ($postSettings as $id => $value) {
                    if (isset($settings[$id]) && $settings[$id]->code === $settingCode) {
                        $hasReferralChanges = true;
                        break 2;
                    }
                }
            }
            if ($hasReferralChanges) {
                Yii::$app->cache->delete('api_referral_settings');
            }

            $cur = (string)(Yii::$app->settings->get('site_version') ?: '0');
            if (function_exists('bcadd')) {
                $new = bcadd($cur, '0.00001', 5);           // 5 знаков после запятой
            } else {
                // fallback, если bcmath не установлен
                $new = number_format(((float)$cur + 0.00001), 5, '.', '');
            }
            Yii::$app->settings->set('site_version', $new);

            return $this->redirect(['index', 'category' => $category]);
        }

        return $this->redirect(['index', 'category' => $category]);
    }

    /**
     * Принудительно зарегистрировать или обновить webhook MAX из сохранённых настроек.
     */
    public function actionRegisterMaxWebhook()
    {
        try {
            $webhook = Yii::$app->maxSupportBot->ensureSupportWebhook();
            $status = (string)($webhook['status'] ?? '');
            if ($status === 'disabled') {
                Yii::$app->session->setFlash(
                    'warning',
                    'Интеграция MAX выключена. Включите её и сохраните настройки.'
                );
            } elseif ($status === 'created') {
                Yii::$app->session->setFlash('success', 'Webhook MAX зарегистрирован.');
            } else {
                Yii::$app->session->setFlash('success', 'Webhook MAX обновлён.');
            }
        } catch (\Throwable $e) {
            Yii::warning('Manual MAX webhook registration failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('danger', 'Не удалось зарегистрировать webhook MAX: ' . $e->getMessage());
        }

        return $this->redirect(['index', 'category' => 'maxSupport']);
    }

    private function _loadImage($tmpName, $category, $name, $code, $oldFile) {
        if (empty($tmpName)) {
            return null;
        }
        $exp = explode('.', $name);
        $exp = strtolower($exp[count($exp) - 1]);
        if (!in_array($exp, ['svg', 'png', 'jpg', 'jpeg', 'ico', 'webp'], true)) {
            Yii::$app->session->setFlash('danger', 'Разрешенно загружать только изображения в формате SVG, PNG, JPG, ICO, WEBP!');
            return null;
        }
        $fileName = md5(time() . $code);
        $fileUrl = "/uploads/site/{$category}/{$fileName}.{$exp}";
        
        // Определяем MIME-тип
        $contentType = 'image/' . ($exp === 'jpg' ? 'jpeg' : ($exp === 'svg' ? 'svg+xml' : ($exp === 'ico' ? 'x-icon' : $exp)));
        
        // Загружаем в S3
        $s3Api = Yii::$app->s3Api;
        $s3Key = 'uploads/site/' . $category . '/' . $fileName . '.' . $exp;
        $fileContent = file_get_contents($tmpName);
        $s3Result = $s3Api->putFile($s3Key, $fileContent, $contentType);
        
        if ($s3Result === false) {
            Yii::$app->session->setFlash('danger', 'Ошибка загрузки изображения в S3');
            return null;
        }
        
        // Удаляем старое изображение из S3, если оно было
        if (!empty($oldFile) && strpos($oldFile, '/uploads/') === 0) {
            $oldS3Key = ltrim($oldFile, '/');
            $s3Api->deleteFile($oldS3Key);
        }
        
        return $fileUrl;
    }

    private function _loadVideo($tmpName, $category, $name, $code, $oldFile) {
        if (empty($tmpName)) {
            return null;
        }
        $exp = explode('.', $name);
        $exp = strtolower($exp[count($exp) - 1]);
        if (!in_array($exp, ['webm'], true)) {
            Yii::$app->session->setFlash('danger', 'Разрешенно загружать только видео в формате WEBM!');
            return null;
        }
        $fileName = md5(time() . $code);
        $fileUrl = "/uploads/site/{$category}/{$fileName}.{$exp}";
        
        // Определяем MIME-тип
        $contentType = 'video/webm';
        
        // Загружаем в S3
        $s3Api = Yii::$app->s3Api;
        $s3Key = 'uploads/site/' . $category . '/' . $fileName . '.' . $exp;
        $fileContent = file_get_contents($tmpName);
        $s3Result = $s3Api->putFile($s3Key, $fileContent, $contentType);
        
        if ($s3Result === false) {
            Yii::$app->session->setFlash('danger', 'Ошибка загрузки видео в S3');
            return null;
        }
        
        // Удаляем старое видео из S3, если оно было
        if (!empty($oldFile) && strpos($oldFile, '/uploads/') === 0) {
            $oldS3Key = ltrim($oldFile, '/');
            $s3Api->deleteFile($oldS3Key);
        }
        
        return $fileUrl;
    }

    public function actionCreate($category = null)
    {
        $model = new SiteSetting();
        if (!Yii::$app->request->isPost && $category !== null) {
            $model->category = SettingsCatalog::normalizeCategory($category);
        }

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->clearApiSettingsCache();
            Yii::$app->settings->getSettings(true);
            Yii::$app->session->setFlash('success', 'Настройка добавлена');
            return $this->redirect(['index', 'category' => $model->category]);
        }

        $isSensitive = SettingsCatalog::isSensitive($model);
        if ($isSensitive) {
            $model->value = '';
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->title = 'Новый параметр';

        return $this->render('create', [
            'model' => $model,
            'isUpdate' => false,
            'hasStoredSecret' => false,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = SiteSetting::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Параметр настройки не найден.');
        }
        $previousValue = $model->value;
        $hadStoredSecret = SettingsCatalog::isSensitive($model) && (string) $previousValue !== '';

        if ($model->load(Yii::$app->request->post())) {
            if (SettingsCatalog::isSensitive($model) && (string) $model->value === '') {
                $model->value = $previousValue;
            }
            if ($model->save()) {
                $this->clearApiSettingsCache();
                Yii::$app->settings->getSettings(true);
                Yii::$app->session->setFlash('success', 'Настройка сохранена');
                return $this->redirect(['index', 'category' => $model->category]);
            }
        }

        if (SettingsCatalog::isSensitive($model)) {
            $model->value = '';
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->title = 'Редактирование параметра';

        return $this->render('create', [
            'model' => $model,
            'isUpdate' => true,
            'hasStoredSecret' => $hadStoredSecret,
        ]);
    }

    /**
     * Очистка кэша API настроек (ключи из SettingsCacheHelper).
     */
    protected function clearApiSettingsCache(): void
    {
        SettingsCacheHelper::clearApiSettingsCache();
    }
}
