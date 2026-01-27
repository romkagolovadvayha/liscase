# Установка зависимостей

После клонирования репозитория выполните:

```bash
npm install
```

Это установит все необходимые зависимости, включая:
- Next.js 14
- React 18
- Material UI Icons (@mui/icons-material)
- TypeScript
- Tailwind CSS
- SASS

## Material Icons

Проект использует Material Icons от MUI вместо Font Awesome. Все иконки импортируются из `@mui/icons-material`.

Пример использования:
```tsx
import { ArrowForwardRounded, CalendarTodayRounded } from '@mui/icons-material';

<ArrowForwardRounded fontSize="small" />
<CalendarTodayRounded fontSize="medium" />
```

Доступные размеры: `'inherit' | 'small' | 'medium' | 'large'`



















