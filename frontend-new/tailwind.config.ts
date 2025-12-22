import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        // Добавьте цвета из старого проекта
        primary: {
          DEFAULT: '#your-primary-color',
          dark: '#your-primary-dark',
        },
      },
    },
  },
  plugins: [],
};

export default config;











