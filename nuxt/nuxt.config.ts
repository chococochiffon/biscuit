// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  app: {
    head: {
      title: 'Chococo Chiffon',
      charset: 'utf-8',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'たのしいふらふらライフをあなたに――。おどろきのお気楽さ！' },
        { property: 'og:url', content: 'https://chococo-chiffon.com/' },
        { property: 'og:type', content: 'article' },
        { property: 'og:title', content: 'Chococo Chiffon' },
        { property: 'og:description', content: 'たのしいふらふらライフをあなたに――。おどろきのお気楽さ！' },
        { property: 'og:site_name', content: 'Chococo Chiffon' },
        { property: 'og:image', content: 'https://chococo-chiffon.com/image/og/chococo.jpg' },
        { property: 'twitter:card', content: 'summary_large_image' },
      ],
      // script: [
      //   { src: 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js' },
      //   { src: '/js/scroll.js' }
      // ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: "" },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic&display=swap' },
        { rel: 'stylesheet', href: 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' },
      ]
    }
  },

  modules: [
    'usebootstrap',
    'nuxt-aos',
  ],

  usebootstrap: {
    scss: true,
    bootstrap: {
      prefix: ``
    },
    html: {
      prefix: `B`
    },
  },

  css: [
    "bootstrap/scss/bootstrap.scss",
    "@/assets/styles/main.scss",
  ],

  devtools: { enabled: false },
  compatibilityDate: '2025-02-02'
})