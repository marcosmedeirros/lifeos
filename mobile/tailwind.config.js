/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        base: '#050915',
        card: '#0b1020',
        accent: '#63c5ff',
        muted: '#9fb1d1'
      },
      boxShadow: {
        card: '0 18px 50px rgba(0,0,0,0.45)'
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif']
      }
    }
  },
  plugins: [],
};
