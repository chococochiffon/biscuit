<?php

namespace App\Installer;

/**
 * インストーラーの段(Stepper の並び順)。前の段をすべて終えないと次の段へ進めない(InstallerManager::canEnter())。
 * 完了(ロック)は段ではなく InstallerManager::lock() で表す。
 */
enum InstallerStep: string
{
    case Requirements = 'requirements';
    case Database = 'database';
    case Application = 'application';
    case Site = 'site';
    case Mail = 'mail';
    case Administrator = 'administrator';
    case Design = 'design';
    case Finalize = 'finalize';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Requirements => __('環境の確認'),
            self::Database => __('データベース'),
            self::Application => __('アプリケーション'),
            self::Site => __('サイト'),
            self::Mail => __('メール'),
            self::Administrator => __('管理者'),
            self::Design => __('デザイン'),
            self::Finalize => __('完了'),
        };
    }

    /**
     * 段のルート名(installer.段)。
     */
    public function routeName(): string
    {
        return 'installer.'.$this->value;
    }

    /**
     * 終えたあとに戻って入力を直せる段か。サイト・メール・デザインはいつでも直せる。
     * データベースは、アプリケーションの段(DB を作る)を終えるまでだけ直せる(DB を作ったあとに接続先を変えると、作った DB に届かなくなる)。
     * 環境の確認・アプリケーション・管理者(2 人目を作らない)は戻れない。完了は段を終える前に必ず入る。
     */
    public function isEditable(InstallationState $state): bool
    {
        return match ($this) {
            self::Site, self::Mail, self::Design, self::Finalize => true,
            self::Database => ! $state->isCompleted(self::Application),
            default => false,
        };
    }

    /**
     * この段より前の段(並び順)。
     *
     * @return list<self>
     */
    public function previous(): array
    {
        return array_slice(self::cases(), 0, array_search($this, self::cases(), true));
    }
}
