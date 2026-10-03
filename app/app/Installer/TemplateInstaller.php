<?php

namespace App\Installer;

use App\Enums\AuditAction;
use App\Enums\BuilderPageType;
use App\Models\Administrator;
use App\Models\PageBuilder;
use App\Models\SiteSetting;
use App\Support\AuditLogger;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Database\Seeders\LayoutSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * デザインの段: デフォルトテンプレート(installDefault())か、インストーラーのビルダーで作ったトップ(installBuilder())でサイトの見た目を作る。デフォルトの画面も Blade に書かず、ユーザーがビルダーで作るのと同じ
 * ビルダーの JSON(resources/installer/templates/default.json)にし、インストールのあとにビルダーでそのまま直せるようにする。
 * - ヘッダー(サイト名・ナビメニュー)・フッター(コピーライト・SNS リンク)は、レイアウト管理の初期値(LayoutSeeder)
 * - トップは Hero(サイト名・説明)と新着記事を、トップのビルダーの公開済みの内容にし、サイト設定でトップにビルダーを使う
 * テンプレートの {{site_title}}・{{site_description}}・{{latest_articles}} は差し込み、ブロックの ID は振り直す。
 */
class TemplateInstaller
{
    public const DEFAULT_TEMPLATE = 'installer/templates/default.json';

    public function __construct(private BuilderValidator $validator, private InstallationState $state) {}

    public function installDefault(): void
    {
        $setting = SiteSetting::current();
        $content = $this->defaultContent((string) $setting?->site_title, (string) $setting?->description);
        $errors = $this->validator->errors($content);

        if ($errors !== []) {
            throw new RuntimeException('Invalid default template: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $builder = PageBuilder::top() ?? PageBuilder::newEmpty(BuilderPageType::Top);
        $builder->draft_content = $this->validator->normalize($content);
        $this->publishTop($builder, null);
        InstallerLog::info('デフォルトテンプレートでトップとレイアウトを作りました。');
    }

    /**
     * インストーラーのビルダーで作ったトップの下書きを、検証し直してから公開する。
     * 下書きがない・空・検証で引っかかるときは InstallerStepException(デザインの段に戻して理由を出す)。
     */
    public function installBuilder(Administrator $administrator): void
    {
        $builder = PageBuilder::top();
        $draft = $builder ? (new SchemaMigrator)->migrate($builder->draft_content) : null;

        if ($builder === null || ($draft['children'] ?? []) === []) {
            throw new InstallerStepException('design', __('ビルダーでトップにブロックを置いてから、もう一度お試しください。'));
        }

        $errors = $this->validator->errors($draft);

        if ($errors !== []) {
            throw new InstallerStepException('design', __('ビルダーの内容を公開できません: :message', ['message' => $errors[0]['message']]));
        }

        $builder->draft_content = $this->validator->normalize($draft);
        $builder->schema_version = SchemaMigrator::CURRENT_VERSION;
        $this->publishTop($builder, $administrator);
        InstallerLog::info('ビルダーで作ったトップを公開しました。', ['nodes' => iterator_count(BuilderContent::nodes($builder->published_content))]);
    }

    /**
     * レイアウトの初期値(ヘッダー・フッター)を入れ、トップの下書きを公開してトップにビルダーを使い、デザインの段を済みにする。
     */
    private function publishTop(PageBuilder $builder, ?Administrator $administrator): void
    {
        Artisan::call('db:seed', ['--class' => LayoutSeeder::class, '--force' => true]);

        DB::transaction(function () use ($builder, $administrator) {
            $builder->publish();
            $builder->save();
            $version = $builder->recordVersion($administrator?->id);
            SiteSetting::current()?->update(['top_use_builder' => true]);

            if ($administrator !== null) {
                AuditLogger::record(AuditAction::Published, $builder, BuilderPageType::Top->label(), metadata: ['version' => $version->id], actor: $administrator);
            }
        });

        $this->state->markCompleted(InstallerStep::Design);
    }

    /**
     * デフォルトテンプレートに、サイト名・説明を差し込んだ内容(説明が空なら説明の段落は外す)。
     *
     * @return array<string, mixed>
     */
    public function defaultContent(string $siteTitle, string $description): array
    {
        $template = json_decode((string) file_get_contents(resource_path(self::DEFAULT_TEMPLATE)), true);
        $replacements = [
            '{{site_title}}' => $siteTitle !== '' ? $siteTitle : 'Biscuit',
            '{{site_description}}' => e($description),
            '{{latest_articles}}' => __('新着記事'),
        ];

        $prepare = function (array $nodes) use (&$prepare, $replacements, $description): array {
            $prepared = [];

            foreach ($nodes as $node) {
                if ($description === '' && str_contains(json_encode($node['props'] ?? []), '{{site_description}}')) {
                    continue;
                }

                $node['id'] = BuilderContent::newId($node['type']);
                $node['props'] = array_map(fn (mixed $value) => is_string($value) ? strtr($value, $replacements) : $value, $node['props'] ?? []);

                if (isset($node['children'])) {
                    $node['children'] = $prepare($node['children']);
                }

                $prepared[] = $node;
            }

            return $prepared;
        };

        return ['version' => $template['version'], 'children' => $prepare($template['children'])];
    }
}
