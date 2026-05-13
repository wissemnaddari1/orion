/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#0FAF7A',
          light: '#0FAF7A',
          dark: '#008F6A',
          50: '#E6F7F2',
          100: '#CCEFE5',
          200: '#99DFCB',
          300: '#66CFB1',
          400: '#33BF97',
          500: '#0FAF7A',
          600: '#0C8C62',
          700: '#09694A',
          800: '#064631',
          900: '#032319',
        },
        secondary: {
          DEFAULT: '#3F3F3F',
          light: '#5A5A5A',
          dark: '#2A2A2A',
        },
        accent: {
          DEFAULT: '#FFF1A8',
          dark: '#E6D997',
        },
        background: '#FFFFFF',
        surface: '#FFFFFF',
      },
      backgroundImage: {
        'gradient-primary': 'linear-gradient(135deg, #0FAF7A 0%, #008F6A 100%)',
        'gradient-primary-hover': 'linear-gradient(135deg, #0d9a6d 0%, #007a5a 100%)',
        'glass': 'linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%)',
        'glass-dark': 'linear-gradient(135deg, rgba(63,63,63,0.9) 0%, rgba(42,42,42,0.95) 100%)',
      },
      boxShadow: {
        'soft': '0 4px 14px 0 rgba(0, 0, 0, 0.08)',
        'soft-lg': '0 10px 40px -10px rgba(0, 0, 0, 0.12)',
        'card': '0 2px 12px rgba(0, 0, 0, 0.06)',
        'card-hover': '0 8px 30px rgba(0, 0, 0, 0.12)',
        'glass': '0 8px 32px rgba(0, 0, 0, 0.08)',
      },
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
        '3xl': '2rem',
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      transitionDuration: {
        '250': '250ms',
      },
    },
  },
  plugins: [],
}
