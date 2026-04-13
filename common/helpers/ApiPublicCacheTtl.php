<?php

namespace common\helpers;

/**
 * Единый TTL (секунды) для публичных ответов API (списки, виджеты, настройки для фронта).
 * Согласован с ISR Next.js (~2 мин): API чуть «короче», чтобы при промахе Next данные на PHP уже могли обновиться.
 */
final class ApiPublicCacheTtl
{
    public const SECONDS = 60;
}
