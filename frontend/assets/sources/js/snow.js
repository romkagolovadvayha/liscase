/**
 * Новогодняя анимация снега
 */
(function() {
    'use strict';
    
    // Проверяем, нужно ли показывать снег (можно добавить проверку даты)
    const isNewYearPeriod = () => {
        const now = new Date();
        const month = now.getMonth(); // 0-11
        const day = now.getDate();
        // Показываем снег с 1 декабря по 15 января
        return (month === 11 && day >= 1) || (month === 0 && day <= 15);
    };
    
    // Если не новогодний период, не запускаем
    // if (!isNewYearPeriod()) {
    //     return;
    // }
    
    // Создаем контейнер для снега
    const createSnowContainer = () => {
        const container = document.createElement('div');
        container.className = 'snow-container';
        document.body.appendChild(container);
        return container;
    };
    
    // Создаем снежинку
    const createSnowflake = (container) => {
        const snowflake = document.createElement('div');
        snowflake.className = 'snowflake';
        
        // Случайная позиция по горизонтали
        const left = Math.random() * 100;
        snowflake.style.left = left + '%';
        
        // Снежинка появляется в верхней трети экрана (0-33% высоты)
        const startY = -(Math.random() * (window.innerHeight * 0.33));
        snowflake.style.setProperty('--start-y', startY + 'px');
        snowflake.style.top = '0px';
        
        // Без задержки - сразу в движении
        snowflake.style.animationDelay = '0s';
        
        // Случайная длительность падения (от 12 до 22.5 секунд) - в 3 раза медленнее
        const duration = 12 + Math.random() * 10.5;
        snowflake.style.animationDuration = duration + 's';
        
        // Случайный дрейф (боковое движение)
        const drift = (Math.random() - 0.5) * 200;
        snowflake.style.setProperty('--snow-drift', drift + 'px');
        
        // Случайный размер
        const size = 0.5 + Math.random() * 1.5;
        snowflake.style.fontSize = size + 'em';
        
        // Начальная прозрачность всегда 1 (полностью видимая)
        // Эффект таяния будет в CSS анимации
        snowflake.style.opacity = '1';
        
        container.appendChild(snowflake);
        
        // Удаляем снежинку после завершения анимации
        setTimeout(() => {
            if (snowflake.parentNode) {
                snowflake.parentNode.removeChild(snowflake);
            }
        }, duration * 1000);
    };
    
    // Инициализация
    const init = () => {
        // Проверяем, не на мобильном устройстве (для производительности)
        if (window.innerWidth <= 480) {
            return;
        }
        
        const container = createSnowContainer();
        
        // Создаем начальные снежинки (уменьшенное количество)
        const snowflakeCount = Math.min(20, Math.floor(window.innerWidth / 40));
        for (let i = 0; i < snowflakeCount; i++) {
            setTimeout(() => {
                createSnowflake(container);
            }, i * 300);
        }
        
        // Продолжаем создавать новые снежинки (реже)
        const createInterval = setInterval(() => {
            if (container.parentNode) {
                createSnowflake(container);
            } else {
                clearInterval(createInterval);
            }
        }, 1000); // Увеличено с 500 до 1000 мс
        
        // Очистка при размонтировании (если используется в SPA)
        window.addEventListener('beforeunload', () => {
            clearInterval(createInterval);
        });
    };
    
    // Запускаем после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

