# SCSS Compilation System

## Описание

Система компиляции SCSS в CSS для шаблонов дизайна. Поддерживает два компилятора:
1. **scssphp** (PHP-библиотека) - основной, быстрый
2. **dart-sass** (CLI) - фолбэк, если scssphp не справился

## Использование

### Консольные команды

#### Компиляция для одного шаблона

```bash
# Компиляция без указания шаблона (default)
php yii scss/compile

# Компиляция для конкретного шаблона по ID
php yii scss/compile 1

# Альтернативный синтаксис с опцией
php yii scss/compile --templateId=1
php yii scss/compile -t 1
```

#### Список доступных шаблонов

```bash
php yii scss/list
```

Вывод:
```
📋 Список доступных шаблонов:

   [ID: 1] Default
   [ID: 2] Dark Theme
   [ID: 3] Light Theme

Использование:
   php yii scss/compile [template_id]
```

#### Массовая компиляция

```bash
# Компилировать SCSS для всех шаблонов
php yii scss/compile-all
```

Вывод:
```
🎨 Массовая компиляция SCSS для всех шаблонов...

Шаблон [1] Default: ✅ OK
Шаблон [2] Dark Theme: ✅ OK
Шаблон [3] Light Theme: ❌ FAIL
   Ошибка: SCSS compile failed...

📊 Результат:
   Успешно: 2
   Ошибок: 1
```

### Использование в коде

#### В контроллере или компоненте

```php
use common\models\template\Template;

// Компиляция для шаблона с ID=1
$result = Template::compileDesignScss(1);

// Компиляция без указания шаблона
$result = Template::compileDesignScss();

// Проверка результата
if ($result['success']) {
    echo "✅ " . $result['message'];
    echo "\nКомпилятор: " . $result['compiler']; // 'scssphp' или 'sass'
} else {
    echo "❌ Ошибка: " . $result['message'];
}
```

#### Формат ответа

```php
[
    'success' => bool,           // true если компиляция успешна
    'message' => string,         // Сообщение о результате
    'compiler' => string|null,   // 'scssphp', 'sass' или null при ошибке
]
```

## Docker

### Выполнение в Docker контейнере

```bash
# Компиляция SCSS внутри PHP контейнера
docker-compose exec php php yii scss/compile

# Компиляция для конкретного шаблона
docker-compose exec php php yii scss/compile 1

# Список шаблонов
docker-compose exec php php yii scss/list

# Массовая компиляция
docker-compose exec php php yii scss/compile-all
```

## Конфигурация

### Константы в модели Template

```php
// Путь к выходному CSS файлу
const SCSS_OUTPUT = '@frontend/web/css/styles.css';

// Путь к входному SCSS файлу
const SCSS_ENTRY = '@frontend/assets/sources/css/design/styles.scss';

// Ключ root для поиска в БД
const SCSS_ENTRY_ROOT_KEY = 'frontend_sources_css';

// Относительный путь для поиска в БД
const SCSS_ENTRY_REL_PATH = 'design/styles.scss';
```

### Пути импортов

Компилятор автоматически добавляет следующие пути для `@import`:
- `@frontend/assets/sources/css/design`
- `@frontend/assets/sources/css`

## Архитектура

### Процесс компиляции

1. **Подготовка выходной директории**
   - Создание, если не существует
   - Проверка прав на запись

2. **Получение исходного SCSS**
   - Сначала ищет в БД (таблица `template_file`)
   - Если не найдено - читает из файловой системы

3. **Компиляция через scssphp**
   - Попытка компиляции PHP-библиотекой
   - Compressed output style
   - Автоматические пути импортов

4. **Фолбэк на dart-sass CLI**
   - Если scssphp не справился
   - Попытка компиляции как `.scss`
   - При неудаче и источнике из БД - попытка как `.sass`

5. **Атомарная запись результата**
   - Сначала во временный файл
   - Затем переименование/копирование

### Структура кода

```
common/models/template/Template.php
├── compileDesignScss()      - Основной метод компиляции
├── getRootPath()             - Получение абсолютного пути
└── safeFindOverride()        - Поиск override в БД

console/controllers/ScssController.php
├── actionCompile()           - Компиляция одного шаблона
├── actionList()              - Список шаблонов
└── actionCompileAll()        - Массовая компиляция

backend/controllers/TemplateController.php
└── compileDesignScss()       - Делегирует в Template::compileDesignScss()
```

## Требования

### PHP Extensions
- **scssphp/scssphp** - основной компилятор (Composer пакет)

### Optional (для CLI фолбэка)
- **dart-sass** - установленный глобально
  ```bash
  npm install -g sass
  ```

## Troubleshooting

### Ошибка "Output dir is not writable"

Проверьте права доступа к директории:
```bash
chmod -R 777 frontend/web/css
chown -R www-data:www-data frontend/web/css
```

В Docker:
```bash
docker-compose exec php chmod -R 777 /app/frontend/web/css
docker-compose exec php chown -R www-data:www-data /app/frontend/web/css
```

### Ошибка "SCSS entry not found"

Убедитесь, что файл существует:
```bash
ls -la frontend/assets/sources/css/design/styles.scss
```

### Компиляция завершается с ошибкой

1. Проверьте синтаксис SCSS
2. Убедитесь, что все импорты существуют
3. Проверьте логи:
   ```bash
   docker-compose logs php
   ```

## Примеры использования

### Автоматическая компиляция при деплое

```bash
#!/bin/bash
# deploy.sh

echo "📦 Deploying..."
git pull origin main
docker-compose exec php composer install
docker-compose exec php php yii migrate --interactive=0

echo "🎨 Compiling SCSS..."
docker-compose exec php php yii scss/compile-all

echo "✅ Deploy complete!"
```

### Компиляция через cron

```cron
# Компиляция каждый час
0 * * * * cd /app && docker-compose exec -T php php yii scss/compile-all >> /var/log/scss-compile.log 2>&1
```

### Интеграция с CI/CD

```yaml
# .github/workflows/deploy.yml
- name: Compile SCSS
  run: |
    docker-compose exec -T php php yii scss/compile-all
    if [ $? -ne 0 ]; then
      echo "SCSS compilation failed!"
      exit 1
    fi
```

## История изменений

### v1.0.0 (2025-10-20)
- ✅ Вынесен метод компиляции в модель Template
- ✅ Создана консольная команда ScssController
- ✅ Поддержка scssphp и dart-sass
- ✅ Массовая компиляция для всех шаблонов
- ✅ Подробные сообщения об ошибках

