<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AdministratorSessionController;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 管理画面と API の書き込み系のルート(POST・PUT・PATCH・DELETE)が、監査ログを記録しているかを確認する(記録漏れを防ぐ)。
 * 各ルートのアクションのソースに AuditLogger の呼び出しがあることを見る(共通トレイトの saveReorder() など、
 * アクションから呼ぶ同じコントローラーのメソッドの中で記録していてもよい)。動作は AuditLogRecordingTest で確認する。
 */
class AuditLogCoverageTest extends TestCase
{
    /**
     * 監査ログをアクションの中で記録しないルートのコントローラー(ログイン・ログアウトは認証イベントのリスナーが記録する)。
     *
     * @var list<class-string>
     */
    private const EXCLUDED_CONTROLLERS = [AdministratorSessionController::class];

    public function test_every_admin_and_api_write_route_records_an_audit_log(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => (str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'api/'))
                && array_diff($route->methods(), ['GET', 'HEAD']) !== []
                && str_contains($route->getActionName(), '@'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            [$controller, $method] = explode('@', $route->getActionName());

            if (in_array($controller, self::EXCLUDED_CONTROLLERS, true)) {
                continue;
            }

            $this->assertTrue($this->recordsAuditLog($controller, $method), "{$route->getActionName()}({$route->uri()})が監査ログを記録していません。");
        }
    }

    /**
     * メソッドのソースに AuditLogger の呼び出しがあるか。なければ、その中で呼んでいる同じクラスのメソッド($this->xxx())も 1 段だけ見る。
     *
     * @param  class-string  $controller
     */
    private function recordsAuditLog(string $controller, string $method, bool $followCalls = true): bool
    {
        $source = $this->methodSource(new ReflectionMethod($controller, $method));

        if (str_contains($source, 'AuditLogger::')) {
            return true;
        }

        if (! $followCalls || ! preg_match_all('/\$this->(\w+)\(/', $source, $matches)) {
            return false;
        }

        return collect($matches[1])
            ->unique()
            ->filter(fn (string $called) => method_exists($controller, $called))
            ->contains(fn (string $called) => $this->recordsAuditLog($controller, $called, followCalls: false));
    }

    private function methodSource(ReflectionMethod $reflection): string
    {
        return implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }
}
