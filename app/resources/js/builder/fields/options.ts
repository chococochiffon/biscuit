import { t } from '../i18n'

// 項目の入力欄(PropField)と、Canvas の操作バー(QuickBar)で共通に使う選択肢の表示名・リンク先の形

// 選択肢の表示名(値そのものを出すと分かりにくいもの)
export const OPTION_LABELS: Record<string, string> = {
  _self: t('同じタブで開く'),
  _blank: t('新しいタブで開く'),
  'primary': t('塗りつぶし(メイン)'),
  'secondary': t('塗りつぶし(サブ)'),
  'outline-primary': t('枠線(メイン)'),
  'outline-secondary': t('枠線(サブ)'),
  'link': t('リンク'),
  'newest': t('新しい順'),
  'oldest': t('古い順'),
  'card': t('カード'),
  'list': t('リスト'),
  'site': t('サイトのナビメニューと同じ'),
  'pages': t('固定ページ(リンクリストに表示するもの)'),
  'horizontal': t('横に並べる'),
  'vertical': t('縦に並べる'),
  'links': t('リンク'),
  'pills': t('ピル'),
  'underline': t('下線'),
  'slash': t('スラッシュ( / )'),
  'chevron': t('山かっこ( › )'),
  'arrow': t('矢印( → )'),
  '16x9': '16:9',
  '4x3': '4:3',
  '1x1': '1:1',
  '21x9': '21:9',
  'start': t('左揃え'),
  'center': t('中央揃え'),
  'end': t('右揃え'),
}

// リンク先として受け付ける形(biscuit の BuilderValidator と同じ)
export const URL_PATTERN = /^(?:https?:\/\/[^\s\\]+|mailto:[^\s\\]+|tel:[0-9+\-() ]+|\/(?!\/)[^\s\\]*|#[^\s\\]*)$/i
