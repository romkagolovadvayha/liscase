<?php
namespace backend\controllers;

use common\components\helpers\Role;
use common\components\settings\Settings;
use common\models\box\BoxImage;
use Yii;
use yii\base\BaseObject;
use yii\filters\VerbFilter;
use yii\web\Controller;
use common\models\site\SiteSetting;
use yii\web\UploadedFile;
use yii\helpers\ArrayHelper;

class SettingsController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
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

    // Страница отображения настроек
    public function actionIndex($category)
    {
        $template = 'pages/' . $category;
        if (!file_exists(\Yii::getAlias('@backend/views/settings/pages/' . $category . '.php'))) {
            $template = 'pages/default';
        }
        return $this->render($template, [
            'category' => $category
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
            if (in_array($category, ['colors', 'design', 'clans'])) {
                Yii::$app->settings->genColors();
            }
            Yii::$app->settings->getSettings(true);
            Yii::$app->session->setFlash('success', 'Настройки успешно сохранены!');
            Yii::$app->cache->delete('Settings_getSettings');

            $cur = (string)(Yii::$app->settings->get('site_version') ?: '0');
            if (function_exists('bcadd')) {
                $new = bcadd($cur, '0.00001', 5);           // 5 знаков после запятой
            } else {
                // fallback, если bcmath не установлен
                $new = number_format(((float)$cur + 0.00001), 5, '.', '');
            }
            Yii::$app->settings->set('site_version', $new);

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
        $uploadDir = Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/site/{$category}/{$fileName}.{$exp}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
        file_put_contents($filePath, file_get_contents($tmpName));
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
        $uploadDir = Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/site/{$category}/{$fileName}.{$exp}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
        file_put_contents($filePath, file_get_contents($tmpName));
        return $fileUrl;
    }

    // Страница добавления новой настройки
    public function actionCreate()
    {
        $model = new SiteSetting();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
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

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Настройка сохранена');
            return $this->redirect(['index?category=' . $model->category]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }
}