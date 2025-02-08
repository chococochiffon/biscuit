<script setup lang="ts">
</script>

<template>
  <div class="container">
    <div class="row py-5">
      <div class="col-lg-12">
        <div class="text-center mx-1 px-1 mb-4 mp-4">
          <h2 class="fw-bold font-monospace">
            <span id="about" class="underline">About</span>
          </h2>
          <p>このサイトについて</p>
        </div>
      </div>
      <div class="col-lg-12 shadow aos-init aos-animate" data-aos="fade-up" data-aos-delay="100">
        <div class="row">
          <div class="col-lg-5">
            <div class="blurBg">
            </div>
          </div>
          <div class="col-lg-7 my-4 my-4">
            <h4 class="text-center mb-2 pb-2 font-monospace">ふらふらとしていたい</h4>
            <p class="m-2 p-2 lh-base">
              このサイトをふくめたプロダクトの発表のためにサイトをつくったよ。<br />
              フリーランスとしてお仕事を請け負っていく中で、自分のできることを書いておくと便利なんだ。
            </p>
            <p class="m-2 p-2 lh-base">
              ほかにも動画編集もやってみたくて、ゲームのプレイ動画や Github で公開しているプログラムのつかいかたも動画にするよ。<br>
              お仕事として動画編集もうけてみたいんだよね～。
            </p>
            <p class="m-2 p-2 lh-base">
              いままで以上に、ふわふわいきていくためにサイトをつくったってわけ！
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.blurBg{
  width: 100%;
  height: 34vh;
  text-align: center;
  font-size: 32px;
  line-height: 350px;
  background: url(/image/header/CB168143-5E03-40DD-B985-70CE1FD2747C-1482-000000B42DD8D937.jpeg) no-repeat center;
  background-size: cover;
  position: relative;
  z-index: 0;
  overflow: hidden;
}
.blurBg:before{
  content: '';
  background: inherit;
  -webkit-filter: blur(5px);
  -moz-filter: blur(5px);
  -o-filter: blur(5px);
  -ms-filter: blur(5px);
  filter: blur(5px);
  position: absolute;
  top: -5px;
  left: -5px;
  right: -5px;
  bottom: -5px;
  z-index: -1;
}
</style>