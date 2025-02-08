<script setup lang="ts">

</script>

<template>
  <div class="container">
    <div class="row py-5">
      <div class="col-lg-12">
        <div class="text-center mx-1 px-1 mb-4 mp-4">
          <h2 class="fw-bold font-monospace">
            <span id="faq" class="underline">FAQs</span>
          </h2>
          <p>聞いてみたいこと</p>
        </div>
      </div>
      <div class="col-lg-12">
        <div class="accordion accordion-flush" id="accordionFlushExample">
          <div class="accordion-item shadow p-2 m-3 aos-init aos-animate rounded" data-aos="fade-up" data-aos-delay="200">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed font-weight-bold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                <i class="bi bi-question-circle question-icon"></i><span class="m-1 p-1">結局このサイトってなんなの？</span>
              </button>
            </h2>
            <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
              <div class="accordion-body">
                <i class="bi bi-check2 answer-icon"></i><span class="m-1 p-1">ポートフォリオみたいなものだとおもって！</span>
              </div>
            </div>
          </div>
          <div class="accordion-item shadow p-2 m-3 aos-init aos-animate rounded" data-aos="fade-up" data-aos-delay="200">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                <i class="bi bi-question-circle question-icon"></i><span class="m-1 p-1">ブログでやれば？</span>
              </button>
            </h2>
            <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
              <div class="accordion-body">
                <i class="bi bi-check2 answer-icon"></i><span class="m-1 p-1">やだ！</span>
              </div>
            </div>
          </div>
          <div class="accordion-item shadow p-2 m-3 aos-init aos-animate rounded" data-aos="fade-up" data-aos-delay="200">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                <i class="bi bi-question-circle question-icon"></i><span class="m-1 p-1">もっと詳しい DEMO がみたいんだけど？</span>
              </button>
            </h2>
            <div id="flush-collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
              <div class="accordion-body">
                <i class="bi bi-check2 answer-icon"></i><span class="m-1 p-1"> X (@chococo_chiffon) に DM くれ！</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

</style>