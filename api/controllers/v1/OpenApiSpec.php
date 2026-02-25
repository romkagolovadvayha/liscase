<?php

namespace api\controllers\v1;

use OpenApi\Annotations as OA;

/**
 * OpenAPI спецификация для Liscase API
 *
 * @OA\OpenApi(
 *     openapi="3.0.0"
 * )
 * @OA\Info(
 *     version="1.0.0",
 *     title="Liscase API",
 *     description="API для работы с игровым сервером Liscase. Все методы требуют JWT авторизации, кроме публичных эндпоинтов."
 * )
 * @OA\Server(
 *     url="http://api.test.prostoj.store",
 *     description="Development Server"
 * )
 * @OA\Server(
 *     url="https://api.prostoj.store",
 *     description="Production Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="JWT токен авторизации. Добавьте 'Bearer ' перед токеном в заголовке Authorization"
 * )
 * @OA\Tag(
 *     name="Auth",
 *     description="Авторизация и аутентификация пользователей"
 * )
 * @OA\Tag(
 *     name="User",
 *     description="Управление профилем пользователя и балансами"
 * )
 * @OA\Tag(
 *     name="Servers",
 *     description="Информация о серверах (публичный доступ)"
 * )
 * @OA\Tag(
 *     name="Stats",
 *     description="Статистика серверов и игроков"
 * )
 * @OA\Tag(
 *     name="Tasks",
 *     description="Задания и награды (TasksV2)"
 * )
 * @OA\Tag(
 *     name="Payment",
 *     description="Платежи и пополнение баланса"
 * )
 * @OA\Tag(
 *     name="Support",
 *     description="Поддержка (тикеты и сообщения)"
 * )
 * @OA\Tag(
 *     name="Skins",
 *     description="Каталог скинов и покупка"
 * )
 * @OA\Tag(
 *     name="Settings",
 *     description="Публичные настройки сайта"
 * )
 */
class OpenApiSpec
{
}






























