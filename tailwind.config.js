/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./backend/views/**/*.php",
    "./backend/widgets/**/*.php",
    "./common/widgets/**/*.php",
    "./backend/assets/sources/js/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        // Dark theme colors
        'ds-bg-primary': 'hsl(0 0% 10% / 1)',
        'ds-bg-secondary': 'hsl(0 0% 15.3% / 1)',
        'ds-bg-tertiary': 'hsl(0 0% 20.4% / 1)',
        'ds-bg-hover': 'hsl(0 0% 25% / 1)',
        'ds-text-primary': 'hsl(0 0% 94.9% / 1)',
        'ds-text-secondary': 'hsl(0 0% 70% / 1)',
        'ds-text-muted': 'hsl(0 0% 55.3% / 1)',
        'ds-border': 'hsl(0 0% 15.3% / 1)',
        // Accent colors
        'ds-primary': 'hsl(200 70% 50% / 1)',
        'ds-success': 'hsl(140 60% 40% / 1)',
        'ds-danger': 'hsl(0 70% 50% / 1)',
        'ds-warning': 'hsl(40 80% 60% / 1)',
        'ds-info': 'hsl(240 60% 60% / 1)',
      },
      spacing: {
        'ds-1': '0.25rem',
        'ds-2': '0.5rem',
        'ds-3': '0.75rem',
        'ds-4': '1rem',
        'ds-6': '1.5rem',
        'ds-8': '2rem',
      },
      borderRadius: {
        'ds-sm': '0.25rem',
        'ds-md': '0.375rem',
        'ds-lg': '0.5rem',
        'ds-xl': '0.75rem',
      },
    },
  },
  plugins: [],
}
