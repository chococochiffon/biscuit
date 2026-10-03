<?php

namespace App\Installer;

use App\Models\SiteSetting;

/**
 * サイトの段: サイト名・説明をサイト設定に、公開側・管理画面の URL・言語・タイムゾーンを .env とサイト設定に書く。
 * URL を変えたときは、install.sh がインストールの完了のあとに公開側(chococo)のコンテナを新しい URL で起動し直す。
 */
class SiteInstaller
{
    public function __construct(private EnvironmentWriter $environment, private InstallationState $state) {}

    /**
     * @param  array{site_title: string, description: string|null, locale: string, timezone: string, front_url: string, admin_url: string}  $values
     */
    public function configure(array $values): void
    {
        $frontUrl = rtrim($values['front_url'], '/');
        $adminUrl = rtrim($values['admin_url'], '/');

        $setting = SiteSetting::current() ?? new SiteSetting([
            'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH,
            'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH,
        ]);
        $setting->fill([
            'site_title' => $values['site_title'],
            'description' => $values['description'],
            'front_url' => $frontUrl,
            'api_url' => $adminUrl.'/api',
        ])->save();

        $this->environment->set([
            'APP_URL' => $adminUrl,
            'FRONT_URL' => $frontUrl,
            'APP_LOCALE' => $values['locale'],
            'APP_TIMEZONE' => $values['timezone'],
        ]);

        $this->state->markCompleted(InstallerStep::Site);
        InstallerLog::info('サイトの設定を保存しました。', ['front_url' => $frontUrl, 'admin_url' => $adminUrl, 'locale' => $values['locale'], 'timezone' => $values['timezone']]);
    }
}
