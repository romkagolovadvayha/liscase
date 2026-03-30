<?php
namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\components\settings\Settings;
use common\helpers\SettingsCacheHelper;
use common\models\box\BoxImage;
use Yii;
use yii\base\BaseObject;
use yii\filters\VerbFilter;
use common\models\site\SiteSetting;
use yii\web\UploadedFile;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

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
                ],
            ],
        ];
    }

    /**
     * Группы категорий настроек для вкладок в шапке (категория => заголовок).
     * @return array[]
     */
    protected function getSettingsCategoryGroups(): array
    {
        return [
            [
                'site' => Yii::t('common', 'Настройки сайта'),
                'social' => Yii::t('common', 'Социальные сети'),
                'section' => Yii::t('common', 'Разделы сайта'),
                'banSystem' => Yii::t('common', 'Бан система'),
                'metrics' => Yii::t('common', 'Счетчики'),
            ],
            [
                'design' => Yii::t('common', 'Дизайн'),
                'colors' => Yii::t('common', 'Настройки темы'),
            ],
            [
                'tinkoffpay' => Yii::t('common', 'Тинькофф'),
                'trc20' => Yii::t('common', 'TRC20'),
                'ton' => Yii::t('common', 'TON COIN'),
                'skinpay' => Yii::t('common', 'Оплата скинами'),
                'telegrampay' => Yii::t('common', 'Оплата через TG'),
                'funpay' => Yii::t('common', 'FunPay'),
                'personal_info_ip' => Yii::t('common', 'Информация о вас'),
            ],
            [
                'skindrops' => Yii::t('common', 'Раздача скинов'),
                'rusttm' => Yii::t('common', 'Rust.Tm'),
                'custom-skins' => Yii::t('common', 'Кастомные скины'),
            ],
            [
                'tgbot' => Yii::t('common', 'Персональный бот'),
                'tgbotRedFlag' => Yii::t('common', 'Важные оповещения'),
                'tgbotReport' => Yii::t('common', 'Телеграм канал для репортов'),
                'tgbotPaymentReport' => Yii::t('common', 'Финансовые отчеты'),
                'tgbotPayments' => Yii::t('common', 'Оповещения о платежах'),
                'tgbotAlert' => Yii::t('common', 'Прочие оповещения'),
                'tgbotSupportAlert' => Yii::t('common', 'Поддержка, оповещения'),
            ],
        ];
    }

    /**
     * Найти группу, в которую входит категория, и вернуть список категорий этой группы.
     * @param string $category
     * @return array [category => title, ...]
     */
    protected function getTabsForCategory(string $category): array
    {
        if ($category === 'bots') {
            $category = 'tgbot';
        }
        if ($category === 'payments') {
            $category = 'tinkoffpay';
        }
        foreach ($this->getSettingsCategoryGroups() as $group) {
            if (isset($group[$category])) {
                return $group;
            }
        }
        return [$category => $category];
    }

    // Страница отображения настроек
    public function actionIndex($category = null)
    {
        if ($category === null || $category === '') {
            $category = 'site';
        }
        // Раньше bots и payments объединяли несколько категорий; переназначаем на первую в группе
        if ($category === 'bots') {
            $category = 'tgbot';
        }
        if ($category === 'payments') {
            $category = 'tinkoffpay';
        }
        $tabs = $this->getTabsForCategory($category);
        $baseButtonClass = 'px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5';
        $headerActions = [];
        foreach ($tabs as $cat => $title) {
            $isActive = (string)$cat === (string)$category;
            $headerActions[] = [
                'label' => Html::encode($title),
                'url' => ['index', 'category' => $cat],
                'class' => $isActive
                    ? 'bg-[hsl(0_0%_35%_/_1)] text-white ' . $baseButtonClass
                    : 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white ' . $baseButtonClass,
            ];
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = $headerActions;

        $pageTitle = $tabs[$category] ?? Yii::t('common', 'Настройки');
        $this->view->title = $pageTitle;

        return $this->render('pages/default', [
            'category' => $category,
            'pageTitle' => $pageTitle,
        ]);
    }

    // Страница отображения настроек
    public function actionForm($category, $itemsFlexClass = null)
    {
        $settings = SiteSetting::find()
                               ->indexBy('id')
                               ->all();

        if (Yii::$app->request->isPost) {
            $postSettings = Yii::$app->request->post('settings');
            if (!empty($postSettings)) {
                foreach ($postSettings as $id => $value) {
                    if (!empty($settings[$id])) {
                        /** @var SiteSetting $setting */
                        $setting = $settings[$id];
                        if ($setting->type === 'password' && (string) $value === '') {
                            continue;
                        }
                        $setting->value = $value;
                        $setting->save();
                    }
                }
            }
            if (!empty($_FILES) && !empty($_FILES['settings'])) {
                foreach ($_FILES['settings']['tmp_name'] as $id => $tmpName) {
                    if (!empty($settings[$id]) && !empty($tmpName)) {
                        /** @var SiteSetting $setting */
                        $setting        = $settings[$id];
                        if ($setting->type === 'image') {
                            $setting->value = $this->_loadImage($tmpName, $setting->category, $_FILES['settings']['name'][$id], $setting->code, $setting->value);
                        }
                        if ($setting->type === 'video') {
                            $setting->value = $this->_loadVideo($tmpName, $setting->category, $_FILES['settings']['name'][$id], $setting->code, $setting->value);
                        }
                        $setting->save();
                    }
                }
            }
            if (in_array($category, ['colors', 'design'])) {
                Yii::$app->settings->genColors();
            }
            Yii::$app->settings->getSettings(true);
            Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
            Yii::$app->cache->delete('Settings_getSettings');
            
            // Сбрасываем кэш API настроек (все возможные комбинации категорий)
            $this->clearApiSettingsCache();
            
            // Сбрасываем кэш реферальной системы, если изменялись настройки рефералов
            $referralSettings = ['referral_percent', 'referral_bonus', 'referral_skin', 'referral_minSum', 'referral_maxSum', 'section_referral'];
            $hasReferralChanges = false;
            foreach ($referralSettings as $settingCode) {
                if (isset($postSettings) && is_array($postSettings)) {
                    foreach ($postSettings as $id => $value) {
                        if (!empty($settings[$id]) && $settings[$id]->code === $settingCode) {
                            $hasReferralChanges = true;
                            break 2;
                        }
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

        return $this->render('pages/form', [
            'category' => $category,
            'setting_items_class' => $itemsFlexClass,
        ]);
    }

    private function _loadImage($tmpName, $category, $name, $code, $oldFile) {
        if (empty($tmpName)) {
            return null;
        }
        $exp = explode('.', $name);
        $exp = $exp[count($exp) - 1];
        if (!in_array($exp, ['svg', 'png', 'jpg', 'ico', 'webp'])) {
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
            $oldS3Key = 'uploads' . $oldFile;
            $s3Api->deleteFile($oldS3Key);
        }
        
        return $fileUrl;
    }

    private function _loadVideo($tmpName, $category, $name, $code, $oldFile) {
        if (empty($tmpName)) {
            return null;
        }
        $exp = explode('.', $name);
        $exp = $exp[count($exp) - 1];
        if (!in_array($exp, ['webm'])) {
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
            $oldS3Key = 'uploads' . $oldFile;
            $s3Api->deleteFile($oldS3Key);
        }
        
        return $fileUrl;
    }

    // Страница добавления новой настройки
    public function actionCreate()
    {
        $model = new SiteSetting();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // Сбрасываем кэш API настроек
            $this->clearApiSettingsCache();
            
            Yii::$app->session->setFlash('success', 'Настройка добавлена');
            return $this->redirect(['index?category=' . $model->category]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    // Страница добавления новой настройки
    public function actionUpdate($id)
    {
        $model = SiteSetting::findOne($id);
        $previousValue = $model->value;

        if ($model->load(Yii::$app->request->post())) {
            if ($model->type === 'password' && (string) $model->value === '') {
                $model->value = $previousValue;
            }
            if ($model->save()) {
                // Сбрасываем кэш API настроек
                $this->clearApiSettingsCache();

                Yii::$app->session->setFlash('success', 'Настройка сохранена');
                return $this->redirect(['index?category=' . $model->category]);
            }
        }

        return $this->render('create', [
            'model' => $model,
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