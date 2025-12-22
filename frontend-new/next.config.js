/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  
  // Поддержка SCSS
  sassOptions: {
    includePaths: ['./src/styles'],
  },
  
  // Переменные окружения
  env: {
    API_BASE_URL: process.env.API_BASE_URL || 'http://api.test.prostoj.store',
    // NODE_ENV автоматически устанавливается Next.js, не нужно указывать здесь
  },
  
  // Rewrites для проксирования статики /uploads/*
  // Проксирует запросы на старый фронтенд, чтобы использовать файлы из frontend/web/uploads
  async rewrites() {
    const rewrites = [];
    
    // Проксирование статики /uploads/* на старый фронтенд
    // URL старого фронтенда (можно настроить через переменную окружения OLD_FRONTEND_URL)
    // По умолчанию: http://localhost (для локальной разработки)
    const oldFrontendUrl = process.env.OLD_FRONTEND_URL || 'http://localhost';
    
    rewrites.push({
      source: '/uploads/:path*',
      destination: `${oldFrontendUrl}/uploads/:path*`,
    });
    
    return rewrites;
  },
  
  
  // Headers для CORS и безопасности
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          {
            key: 'X-Content-Type-Options',
            value: 'nosniff',
          },
          {
            key: 'X-Frame-Options',
            value: 'DENY',
          },
          {
            key: 'X-XSS-Protection',
            value: '1; mode=block',
          },
        ],
      },
    ];
  },
  
  // Оптимизация изображений
  images: {
    domains: [
      'localhost',
      // Добавьте домены для изображений
    ],
    formats: ['image/webp', 'image/avif'],
  },
};

module.exports = nextConfig;

