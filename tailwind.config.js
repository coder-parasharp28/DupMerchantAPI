module.exports = {
  purge: [
    './storage/framework/views/*.php',
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  content: ["./src/**/*.{html,js}"],
  theme: {
    extend: {
      colors: {
        red: {
          400: '#EC004E'
        },
        blue: {
          400: '#00FBFF'
        },
        yellow: {
          400: '#ffd600'
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    require('tailwindcss-font-inter')()
  ],
  variants: {
    extend: {
      opacity: ['disabled'],
    }
  }
}
