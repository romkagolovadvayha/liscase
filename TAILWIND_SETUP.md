# Tailwind CSS Setup

Tailwind CSS подключен через npm и интегрирован в проект.

## Установка зависимостей

```bash
npm install
```

## Компиляция Tailwind CSS

### Однократная компиляция (production)
```bash
npm run build:tailwind
```

### Режим разработки с автопересборкой
```bash
npm run watch:tailwind
```

## Структура файлов

- `tailwind.config.js` - конфигурация Tailwind CSS
- `postcss.config.js` - конфигурация PostCSS
- `backend/assets/sources/scss/tailwind.css` - входной файл с директивами Tailwind
- `backend/assets/sources/scss/tailwind.min.css` - скомпилированный CSS (генерируется автоматически)

## Использование

Tailwind CSS автоматически подключен через `AppAsset.php`. Используйте классы Tailwind в PHP views:

```php
<div class="flex items-center justify-between p-4 bg-gray-800">
    <h1 class="text-white text-xl font-semibold">Заголовок</h1>
    <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        Кнопка
    </button>
</div>
```

## Кастомные цвета

В `tailwind.config.js` определены цвета дизайн-системы:
- `ds-bg-primary`, `ds-bg-secondary`, `ds-bg-tertiary`
- `ds-text-primary`, `ds-text-secondary`, `ds-text-muted`
- `ds-primary`, `ds-success`, `ds-danger`, `ds-warning`, `ds-info`

Использование:
```html
<div class="bg-ds-bg-primary text-ds-text-primary">
    Контент
</div>
```

## Важно

После изменения классов Tailwind в PHP файлах необходимо пересобрать CSS:
```bash
npm run build:tailwind
```

Или используйте watch режим для автоматической пересборки:
```bash
npm run watch:tailwind
```
