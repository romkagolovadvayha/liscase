# Список страниц для обновления

Осталось обновить следующие страницы, убрав все использования @/lib/db, cookies(), headers():

1. support/page.tsx - есть SupportClient
2. support/[id]/page.tsx - есть SupportTicketClient  
3. tasks/page.tsx - есть TasksClient
4. worked/page.tsx
5. blocked/page.tsx
6. custom-skins/page.tsx
7. raid-table/page.tsx
8. wipe-calendar/page.tsx
9. skindrops/page.tsx
10. radio/page.tsx
11. maps-v2/page.tsx
12. media/page.tsx
13. payment/result/page.tsx

Все эти страницы должны быть конвертированы в простые обертки над Client компонентами без server-side кода.




