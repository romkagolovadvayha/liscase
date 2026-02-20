<?php

use yii\helpers\Html;

?>

<!-- Filters Panel -->
<aside class="admin-filters-content bg-[hsl(0_0%_20.4%_/_1)] border-l border-[hsl(0_0%_15.3%_/_1)] h-full overflow-y-auto scrollbar-thin">
    <div class="p-4">
        <!-- Search in Filters -->
        <div class="mb-4">
            <div class="relative">
                <input 
                    type="search" 
                    class="ds-input w-full pl-10" 
                    placeholder="Поиск..." 
                    id="filters-search"
                >
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
            </div>
        </div>

        <!-- Фильтры Section -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">
                Фильтры
            </h3>
            
            <div class="space-y-3">
                <!-- Только с сервера -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Только с сервера
                    </label>
                    <div class="flex items-center gap-2">
                        <select class="ds-select text-sm min-w-[80px] py-1.5 pr-8 pl-2">
                            <option>Все</option>
                            <option>Сервер 1</option>
                            <option>Сервер 2</option>
                        </select>
                        <label class="ds-switch">
                            <input type="checkbox">
                            <span class="ds-switch__slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Только онлайн игроки -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Только онлайн игроки
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox">
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>

                <!-- Только игроков с VPN -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Только игроков с VPN
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox">
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>

                <!-- Только с игнором жалоб -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Только с игнором жалоб
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox">
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>

                <!-- Только с заметками -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Только с заметками
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox">
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Внешний вид Section -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">
                Внешний вид
            </h3>
            
            <div class="space-y-3">
                <!-- Показывать тип подключения -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Показывать тип подключения
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox">
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>

                <!-- Выделять IP c VPN/Proxy -->
                <div class="flex items-center justify-between gap-3">
                    <label class="text-sm text-gray-400 flex-1">
                        Выделять IP c VPN/Proxy
                    </label>
                    <label class="ds-switch">
                        <input type="checkbox" checked>
                        <span class="ds-switch__slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide">
                Быстрые действия
            </h3>
            
            <div class="space-y-2">
                <button class="ds-btn ds-btn--primary ds-btn--sm w-full justify-start">
                    <i class="fas fa-filter"></i>
                    <span>Применить фильтры</span>
                </button>
                <button class="ds-btn ds-btn--secondary ds-btn--sm w-full justify-start">
                    <i class="fas fa-redo"></i>
                    <span>Сбросить</span>
                </button>
            </div>
        </div>
    </div>
</aside>

