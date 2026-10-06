<script setup lang="ts">
import { t } from '../i18n'
import { useBuilderStore } from '../store'

// 使い方の画面: ページの組み立て方・ブロックの置き方・大きさの変え方・端末ごとの見た目・保存と公開・キーボードの操作。
// ツールバーの「使い方」で開き、初めてエディタを開いたときは自動で開く(BuilderApp)。
// 公開・版の履歴を出さないエディタ(インストーラー)では、その説明も出さない
const store = useBuilderStore()

function close(): void {
  store.state.helpOpen = false
}

const SHORTCUTS: { keys: string, label: string }[] = [
  { keys: 'Ctrl + S', label: t('下書き保存') },
  { keys: 'Ctrl + Z', label: t('元に戻す') },
  { keys: 'Ctrl + Shift + Z / Ctrl + Y', label: t('やり直す') },
  { keys: 'Ctrl + C / X / V', label: t('選んだブロックのコピー・切り取り・貼り付け') },
  { keys: 'Delete', label: t('選んだブロックを削除') },
  { keys: 'Esc', label: t('ひとつ外側のブロックを選ぶ') },
]
</script>

<template>
  <div class="builder-dialog-backdrop" @click.self="close">
    <div class="builder-dialog builder-help" role="dialog" aria-modal="true" :aria-label="t('ページビルダーの使い方')">
      <header class="builder-dialog-header">
        <h2 class="h6 mb-0">{{ t('ページビルダーの使い方') }}</h2>
        <button type="button" class="btn-close" :aria-label="t('閉じる')" @click="close" />
      </header>

      <div class="builder-dialog-body small">
        <section>
          <h3><i class="bi bi-diagram-3" /> {{ t('ページの組み立て') }}</h3>
          <p>{{ t('ページは「セクション」を縦に並べて作ります。セクションの中にコンテナ・行を置き、行の中にカラムを並べて、見出し・テキスト・画像などのブロックを入れます。') }}</p>
          <p class="mb-0">{{ t('迷ったら、ツールバーの「テンプレート」から近いページを選んで、中身を書き換えるのが早道です。') }}</p>
        </section>

        <section>
          <h3><i class="bi bi-plus-square" /> {{ t('ブロックを置く') }}</h3>
          <ul>
            <li>{{ t('左のパレットのブロックを、中央の画面へドラッグします。青い線が置く位置です。') }}</li>
            <li>{{ t('パレットのブロックをクリックすると、選んでいるブロックの近くに追加します。') }}</li>
          </ul>
        </section>

        <section>
          <h3><i class="bi bi-cursor" /> {{ t('選ぶ・動かす・編集する') }}</h3>
          <ul>
            <li>{{ t('ブロックをクリックして選ぶと、右のパネルで内容・スタイル・表示を編集できます。') }}</li>
            <li>{{ t('選んだブロックの上の名前の部分をドラッグすると、別の場所へ移せます。名前の横のボタンで、前後への移動・複製・コピー・削除ができます。') }}</li>
            <li>{{ t('入れ子が深くて選びにくいときは、左の「ツリー」タブで選んだり、ドラッグで並べ替えたりできます。') }}</li>
          </ul>
        </section>

        <section>
          <h3><i class="bi bi-arrows-angle-expand" /> {{ t('大きさを変える') }}</h3>
          <ul>
            <li>{{ t('下の端の丸いつまみをドラッグすると、セクションの高さ(最小の高さ)・スペーサーの高さを変えられます。') }}</li>
            <li>{{ t('右の端のつまみをドラッグすると、画像・コンテナなどの幅を変えられます。カラムの幅は 12 分割の目盛りに合わせて変わります。') }}</li>
            <li>{{ t('上下の緑の帯をドラッグすると、内側の余白を変えられます。') }}</li>
          </ul>
        </section>

        <section>
          <h3><i class="bi bi-phone" /> {{ t('端末ごとの見た目') }}</h3>
          <ul>
            <li>{{ t('ツールバーのデスクトップ・タブレット・スマートフォンで、画面の幅を切り替えて確かめます。') }}</li>
            <li>{{ t('タブレット・スマートフォンを選んでいるときに変えたスタイル・大きさは、その端末だけに効きます(空欄は大きい画面の値を引き継ぎます)。') }}</li>
            <li>{{ t('右のパネルの「表示」タブで、端末ごとに隠したり、表示する期間を決めたりできます。') }}</li>
          </ul>
        </section>

        <section>
          <h3><i class="bi bi-cloud-check" /> {{ t('保存と公開') }}</h3>
          <ul>
            <li>{{ t('変更は、手を止めて 2 秒たつと自動で下書きに保存されます。') }}</li>
            <li v-if="store.features.publish">{{ t('「プレビュー」で公開側の見た目を確かめ、「公開」を押すと公開側のサイトに出ます。公開するまで、公開側は前の内容のままです。') }}</li>
            <li v-if="store.features.versions">{{ t('「版の履歴」から、前に公開した内容を下書きに読み込めます。') }}</li>
          </ul>
        </section>

        <section>
          <h3><i class="bi bi-keyboard" /> {{ t('キーボードの操作') }}</h3>
          <table class="table table-sm mb-0">
            <tbody>
              <tr v-for="shortcut in SHORTCUTS" :key="shortcut.keys">
                <td class="text-nowrap pe-3" style="width: 1%"><kbd>{{ shortcut.keys }}</kbd></td>
                <td>{{ shortcut.label }}</td>
              </tr>
            </tbody>
          </table>
          <p class="text-secondary mt-2 mb-0">{{ t('入力欄の中では、元に戻す・コピーなどは入力欄の操作になります。Mac では Ctrl の代わりに Command を使います。') }}</p>
        </section>

        <div class="text-end mt-3">
          <button type="button" class="btn btn-sm btn-primary" @click="close">{{ t('閉じる') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
