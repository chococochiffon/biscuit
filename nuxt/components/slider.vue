<script setup lang="ts">
  const route = useRoute();
</script>

<template>
  <section v-if="route.path === '/'" class="header-image d-flex align-items-center">
    <div class="container aos-init aos-animate f-edging-text" data-aos="zoom-out" data-aos-delay="100">
      <h2>Chococo Chiffon</h2>
      <p>たのしいふらふらライフ――</p>
      <div class="d-flex">
        <a href="#about" class="btn-get-started">Get Started</a>
      </div>
    </div>
  </section>
</template>

<style scoped>
.header-image {
  width: 100%;
  height: 80vh;
  background: url(/image/header/ffxiv_20220617_010514_782.png) center center;
  background-size: cover;
  position: relative;
}
.btn-get-started {
  color: #ff6800;
  text-decoration: none;
}
</style>