<?php

use yii\helpers\Html;

$this->title = 'Макет - Дизайн-система';
$this->params['breadcrumbs'][] = ['label' => 'Дизайн-система', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Макет';

?>

<div class="design-system-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <!-- Структура страницы -->
        <section class="mb-5">
            <h2 class="mb-4">Структура страницы</h2>
            <div class="ds-card">
                <pre class="ds-bg--secondary ds-p-4 ds-rounded-lg" style="overflow-x: auto;"><code>&lt;div class="wrapper"&gt;
    &lt;aside class="main-sidebar"&gt;
        &lt;!-- Sidebar --&gt;
    &lt;/aside&gt;
    
    &lt;div class="content-wrapper"&gt;
        &lt;nav class="main-header"&gt;
            &lt;!-- Navbar --&gt;
        &lt;/nav&gt;
        
        &lt;div class="content"&gt;
            &lt;div class="content-header"&gt;
                &lt;!-- Заголовок страницы --&gt;
            &lt;/div&gt;
            
            &lt;div class="content"&gt;
                &lt;!-- Основной контент --&gt;
            &lt;/div&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
            </div>
        </section>

        <!-- Content Header -->
        <section class="mb-5">
            <h2 class="mb-4">Content Header</h2>
            <div class="content-header">
                <h1>Заголовок страницы</h1>
            </div>
        </section>
    </div>
</div>
