<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AdministratorSessionController;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 管理画面の書き込み系のルート(POST・PUT・PATCH・DELETE)が、監査ログを記録しているかを確認する(記録漏れを防ぐ)。
 * 各ルートのアクションのソースに AuditLogger の呼び出しがあることを見る。動作は AuditLogRecordingTest で確認する。
 */
class AuditLogCoverageTest extends TestCase
{
    /**
     * 監査ログをアクションの中で記録しないルートのコントローラー(ログイン・ログアウトは認証イベントのリスナーが記録する)。
     *
     * @var list<class-string>
     */
    private const EXCLUDED_CONTROLLERS = [AdministratorSessionController::class];

    public function test_every_admin_write_route_records_an_audit_log(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'admin')
                && array_diff($route->methods(), ['GET', 'HEAD']) !== []
                && str_contains($route->getActionName(), '@'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            [$controller, $method] = explode('@', $route->getActionName());

            if (in_array($controller, self::EXCLUDED_CONTROLLERS, true)) {
                continue;
            }

            $reflection = new ReflectionMethod($controller, $method);
            $source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

            $this->assertStringContainsString('AuditLogger::', $source, "{$route->getActionName()}({$route->uri()})が監査ログを記録していません。");
        }
    }
}
