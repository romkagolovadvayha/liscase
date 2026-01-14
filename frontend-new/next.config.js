/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  
  // Отключаем ESLint во время сборки (можно включить обратно после исправления конфига)
  eslint: {
    // ВАЖНО: В продакшене лучше включить обратно после настройки ESLint
    ignoreDuringBuilds: true,
  },
  
  // Отключаем проверку типов во время сборки (можно включить обратно)
  typescript: {
    // ВАЖНО: В продакшене лучше включить обратно
    ignoreBuildErrors: false,
  },
  
  // Поддержка SCSS
  sassOptions: {
    includePaths: ['./src/styles'],
  },
  
  // Переменные окружения
  env: {
    NEXT_PUBLIC_API_BASE_URL: process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store',
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
      'avatars.steamstatic.com',
      'steamcdn-a.akamaihd.net',
      'cdn.steamstatic.com',
      'prostoj.store',
      'test.prostoj.store',
      'api.test.prostoj.store',
      'prostoj.storeuploads', // Временное решение для некорректных URL (должно быть исправлено на бэкенде)
      // Добавьте другие домены для изображений при необходимости
    ],
    remotePatterns: [
      {
        protocol: 'https',
        hostname: '**.steamstatic.com',
      },
      {
        protocol: 'https',
        hostname: '**.steamcdn-a.akamaihd.net',
      },
      {
        protocol: 'https',
        hostname: '**.cdn.steamstatic.com',
      },
      {
        protocol: 'https',
        hostname: 'prostoj.store',
      },
      {
        protocol: 'http',
        hostname: 'prostoj.store',
      },
      {
        protocol: 'https',
        hostname: 'test.prostoj.store',
      },
      {
        protocol: 'http',
        hostname: 'test.prostoj.store',
      },
      {
        protocol: 'https',
        hostname: 'api.test.prostoj.store',
      },
      {
        protocol: 'http',
        hostname: 'api.test.prostoj.store',
      },
      {
        protocol: 'https',
        hostname: 'prostoj.storeuploads',
      },
      {
        protocol: 'http',
        hostname: 'prostoj.storeuploads',
      },
    ],
    formats: ['image/webp', 'image/avif'],
  },
};

module.exports = nextConfig;

