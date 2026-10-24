<?php

namespace Tests\Unit;

use App\Http\Controllers\BranchWorkspaceController;
use App\Services\{BranchPeriod, BranchReportService, NirabFinanceAccess, OperationalAccess};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Facade};
use Modules\ProviderManagement\Entities\Provider;
use Modules\UserManagement\Entities\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Disposable in-memory fixtures only. No application boot, migrations or external connections. */
class BranchWorkspaceTest extends TestCase
{
    private Container $container;
    private BranchReportService $reports;
    private Manager $database;

    protected function setUp(): void
    {
        $this->container = new class extends Container {
            public function abort($code, $message = '', array $headers = []): never { throw new HttpException($code, $message, null, $headers); }
            public function getLocale(): string { return 'en'; }
        };
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new Repository(['app' => ['timezone' => 'UTC'], 'nirab' => ['branch_mode' => true], 'nirab_execution' => ['settlement_timezone' => 'Asia/Riyadh'], 'nirab_branch' => ['late_minutes' => 15]]));
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $this->container->instance('translator', $translator);
        $this->container->instance('validator', new \Illuminate\Validation\Factory($translator, $this->container));
        $this->container->instance(OperationalAccess::class, new OperationalAccess());
        \Illuminate\Http\Request::macro('validate', function (array $rules) { return app('validator')->make($this->all(), $rules)->validate(); });
        $this->database = new Manager($this->container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->container->instance('db', $this->database->getDatabaseManager());
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values));
        foreach (['bookings', 'booking_repeats'] as $table) {
            Manager::schema()->create($table, function (Blueprint $t) use ($table): void {
                $t->string('id')->primary();
                foreach (['provider_id', 'serviceman_id', 'customer_id', 'zone_id', 'service_address_id', 'booking_id'] as $c) $t->string($c)->nullable();
                $t->string('booking_status')->default('pending'); $t->string('payment_method')->default('cash_after_service');
                $t->integer('readable_id')->default(100); $t->integer('is_repeated')->default(0); $t->integer('is_paid')->default(0); $t->integer('is_warranty')->default(0);
                $t->decimal('total_booking_amount', 12, 3)->default(100); $t->integer('actual_duration_seconds')->nullable();
                $t->dateTime('arrived_at')->nullable(); $t->dateTime('service_schedule'); $t->timestamps();
            });
        }
        Manager::schema()->create('booking_assignments', function (Blueprint $t): void { $t->string('booking_id'); $t->string('status'); });
        Manager::schema()->create('transactions', function (Blueprint $t): void { $t->increments('id'); $t->string('booking_id'); $t->string('booking_repeat_id')->nullable(); $t->string('trx_type'); $t->decimal('credit'); $t->decimal('debit')->default(0); $t->timestamps(); });
        Manager::schema()->create('booking_partial_payments', function (Blueprint $t): void { $t->string('booking_id'); $t->string('paid_with'); $t->decimal('paid_amount'); });
        Manager::schema()->create('payment_collections', function (Blueprint $t): void {
            $t->string('id')->primary(); $t->string('booking_id'); $t->string('booking_repeat_id')->nullable(); $t->string('provider_id'); $t->decimal('amount'); $t->string('status');
            $t->string('submitted_by')->nullable(); $t->string('branch_verified_by')->nullable();
        });
        foreach (['nirab_operational_scopes', 'nirab_finance_scopes'] as $table) Manager::schema()->create($table, function (Blueprint $t): void {
            $t->string('id'); $t->string('user_id'); $t->text('permissions'); $t->text('branch_ids'); $t->boolean('all_branches')->default(false);
        });
        Manager::schema()->create('odoo_invoices', function (Blueprint $t): void { $t->string('id')->primary(); $t->string('branch_id'); $t->string('status')->default('posted'); });
        $this->reports = new BranchReportService(new OperationalAccess());
        $this->container->instance(NirabFinanceAccess::class, new NirabFinanceAccess());
        Carbon::setTestNow('2026-10-08 09:00:00'); CarbonImmutable::setTestNow('2026-10-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); CarbonImmutable::setTestNow();
        DB::disconnect(); Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
    }

    private function actor(string $type = 'provider-admin', string $branch = 'a', string $id = 'user'): User
    {
        $user = new User(); $user->forceFill(['id' => $id, 'user_type' => $type, 'is_active' => 1, 'provider_id' => $branch]);
        $provider = new Provider(); $provider->forceFill(['id' => $branch]); $user->setRelation('provider', $provider);
        return $user;
    }
    private function booking(string $id, array $values = []): void
    {
        DB::table('bookings')->insert($values + ['id' => $id, 'provider_id' => 'a', 'service_schedule' => '2026-10-08 08:00:00', 'created_at' => '2026-10-01 00:00:00']);
    }
    private function filters(array $extra = []): array { return $extra + BranchPeriod::dates('this_month') + ['branch_id' => 'a']; }
    private function grant(string $table, array $permissions, array $branches = ['a'], bool $all = false, string $user = 'user'): void
    {
        DB::table($table)->insert(['id' => 'scope', 'user_id' => $user, 'permissions' => json_encode($permissions), 'branch_ids' => json_encode($branches), 'all_branches' => $all]);
    }

    public function testTenPendingBookingsHaveZeroCompletionAndEqualPanelSummaries(): void
    {
        for ($i = 0; $i < 10; $i++) $this->booking('pending-' . $i);
        $admin = $this->reports->summary($this->actor('super-admin'), $this->filters());
        $manager = $this->reports->summary($this->actor(), $this->filters());
        $this->assertSame($admin, $manager); $this->assertSame(0.0, $manager['completion_rate']); $this->assertSame(10, $manager['queues']['pending']);
    }

    public function testEveryExecutionStateAndDrilldownUseTheSamePopulation(): void
    {
        foreach (BranchReportService::STATUSES as $status) $this->booking($status, ['booking_status' => $status]);
        $filters = $this->filters(['queue' => 'overdue']);
        $summary = $this->reports->summary($this->actor(), $filters);
        foreach ($summary['states'] as $status => $count) $this->assertSame((int) $count, $this->reports->subjects($this->actor(), $this->filters(['status' => $status]))->count());
        foreach ($summary['queues'] as $queue => $count) $this->assertSame($count, $this->reports->subjects($this->actor(), $this->filters(['queue' => $queue]))->count());
    }

    public function testRecurringParentExcludedAndWarrantyAndPrepaidRemainExplicit(): void
    {
        $this->booking('parent', ['is_repeated' => 1]);
        DB::table('booking_repeats')->insert(['id' => 'visit', 'booking_id' => 'parent', 'provider_id' => 'a', 'service_schedule' => '2026-10-08 08:00:00']);
        $this->booking('warranty', ['is_warranty' => 1]); $this->booking('prepaid', ['payment_method' => 'prepaid_visit']);
        $this->assertSame(3, $this->reports->subjects($this->actor(), $this->filters())->count());
        $rows = $this->reports->balances($this->actor(), $this->filters())->get()->keyBy('subject_id');
        $this->assertEquals(0, $rows['warranty']->outstanding); $this->assertEquals(0, $rows['prepaid']->outstanding);
        $this->assertArrayNotHasKey('parent', $rows->all());
    }

    public function testCentralAndOtherBranchRecordsCannotBeForgedIntoManagerScope(): void
    {
        $this->booking('own'); $this->booking('other', ['provider_id' => 'b']); $this->booking('central', ['provider_id' => null]);
        $this->assertSame(0, $this->reports->subjects($this->actor(), $this->filters(['branch_id' => 'b']))->count());
        $this->assertSame(1, $this->reports->subjects($this->actor(), $this->filters(['branch_id' => null]))->count());
        $this->assertSame(3, $this->reports->subjects($this->actor('super-admin'), $this->filters(['branch_id' => null]))->count());
    }

    public function testLimitedAdminGetsOnlyDelegatedBranches(): void
    {
        $this->grant('nirab_operational_scopes', ['analytics.read']);
        $this->booking('a'); $this->booking('b', ['provider_id' => 'b']);
        $this->assertSame(1, $this->reports->subjects($this->actor('admin-employee'), $this->filters(['branch_id' => null]))->count());
        $this->assertFalse((new OperationalAccess())->allows($this->actor('admin-employee'), 'dispatch.manage', 'a'));
    }

    public function testMonthBoundariesIncludeYearAndLocalMidnight(): void
    {
        $period = BranchPeriod::dates('this_month');
        $this->assertSame('2026-09-30 21:00:00', $period['start']);
        $this->assertSame('2026-10-31 21:00:00', $period['end']);
        $this->booking('old', ['service_schedule' => '2025-10-08 08:00:00']);
        $this->booking('boundary', ['service_schedule' => $period['start']]);
        $this->booking('outside', ['service_schedule' => $period['end']]);
        $this->assertSame(['boundary'], $this->reports->subjects($this->actor(), $this->filters())->pluck('subject_id')->all());
        CarbonImmutable::setTestNow('2026-01-10 00:00:00');
        $this->assertSame('2025-12-01', BranchPeriod::dates('last_month')['from']);
        $this->assertSame('2025-12-31', BranchPeriod::dates('last_month')['to']);
    }

    public function testPartialPaymentIsNotCountedTwiceAndUnapprovedCashDoesNotSettleDebt(): void
    {
        $this->booking('digital', ['payment_method' => 'tap']); $this->booking('cash');
        foreach (['digital', 'cash'] as $id) {
            DB::table('transactions')->insert(['booking_id' => $id, 'trx_type' => TRX_TYPE['booking_amount'], 'credit' => 30]);
            DB::table('booking_partial_payments')->insert(['booking_id' => $id, 'paid_with' => 'wallet_payment', 'paid_amount' => 30]);
        }
        DB::table('payment_collections')->insert(['id' => 'c', 'booking_id' => 'cash', 'provider_id' => 'a', 'amount' => 70, 'status' => 'branch_verified']);
        $rows = $this->reports->balances($this->actor(), $this->filters())->get()->keyBy('subject_id');
        $this->assertEquals(30, $rows['digital']->allocated); $this->assertEquals(70, $rows['digital']->outstanding);
        $this->assertEquals(70, $rows['cash']->outstanding);
        DB::table('payment_collections')->where('id', 'c')->update(['status' => 'ready_for_accounting_sync']);
        $cash = $this->reports->balances($this->actor(), $this->filters())->where('subject_id', 'cash')->first();
        $this->assertEquals(100, $cash->allocated); $this->assertEquals(0, $cash->outstanding);
    }

    public function testCanceledDebtIsZeroAndPaidFlagAloneIsNotPaymentEvidence(): void
    {
        $this->booking('canceled', ['booking_status' => 'canceled']); $this->booking('flag', ['is_paid' => 1, 'payment_method' => 'tap']);
        $rows = $this->reports->balances($this->actor(), $this->filters())->get()->keyBy('subject_id');
        $this->assertEquals(0, $rows['canceled']->outstanding); $this->assertEquals(100, $rows['flag']->outstanding);
    }

    public function testSensitiveFinancialDelegationIsExplicitAndBranchBound(): void
    {
        $access = new NirabFinanceAccess(); $actor = $this->actor();
        $this->assertFalse($access->allows($actor, 'accountant_approve', 'a'));
        $this->grant('nirab_finance_scopes', ['accountant_approve', 'custody_adjust', 'refund', 'accounting_retry'], ['a','b'], true);
        foreach (['accountant_approve', 'custody_adjust', 'refund', 'accounting_retry'] as $permission) {
            $this->assertTrue($access->allows($actor, $permission, 'a')); $this->assertFalse($access->allows($actor, $permission, 'b'));
        }
        $this->assertFalse($access->allows($actor, 'payment_review'));
    }

    public function testFinanceOnlyEmployeeCanQueryBalancesWithoutAnalyticsPermission(): void
    {
        $this->grant('nirab_finance_scopes', ['view_cash']); $this->booking('a'); $this->booking('b', ['provider_id' => 'b']);
        $this->assertSame(1, $this->reports->balances($this->actor('admin-employee'), $this->filters(), 'view_cash')->count());
    }

    public function testAccountantCannotApproveOwnBranchVerification(): void
    {
        $this->grant('nirab_finance_scopes', ['accountant_approve']);
        DB::table('payment_collections')->insert(['id' => 'collection', 'booking_id' => 'booking', 'provider_id' => 'a', 'amount' => 10, 'status' => 'branch_verified', 'submitted_by' => 'technician', 'branch_verified_by' => 'user']);
        $cash = new \Modules\BookingModule\Services\TechnicianCashService(new NirabFinanceAccess(), new \App\Services\AuditService());
        $this->expectException(HttpException::class);
        $cash->review('collection', $this->actor(), 'accountant', true, 'Approval');
    }

    public function testCsvNeutralizesSpreadsheetFormulas(): void
    {
        $this->assertSame("'=HYPERLINK(1)", BranchWorkspaceController::csvCell('=HYPERLINK(1)'));
        $this->assertSame("'  @SUM(1)", BranchWorkspaceController::csvCell('  @SUM(1)'));
        $this->assertSame('مرجع ١', BranchWorkspaceController::csvCell('مرجع ١'));
    }

    public function testInvoicePdfCannotReadAnotherBranchEvenWithItsKnownId(): void
    {
        DB::table('odoo_invoices')->insert(['id' => 'foreign-invoice', 'branch_id' => 'b']);
        $request = \Illuminate\Http\Request::create('/provider/branch-workspace/invoices/foreign-invoice/pdf', 'GET', ['branch_id' => 'a']);
        $request->setUserResolver(fn () => $this->actor());
        $controller = new \App\Http\Controllers\BranchFinanceController(new NirabFinanceAccess());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->pdf($request, 'foreign-invoice');
    }

    public function testManagerCannotRetryAnotherBranchAccountingEvent(): void
    {
        $this->grant('nirab_finance_scopes', ['accounting_retry']);
        $request = \Illuminate\Http\Request::create('/retry', 'POST', ['branch_id' => 'b', 'reason' => 'Retry']);
        $request->setUserResolver(fn () => $this->actor());
        $controller = new \App\Http\Controllers\BranchFinanceController(new NirabFinanceAccess());
        $service = $this->createMock(\Modules\OdooIntegration\Services\OutboxService::class);
        $service->expects($this->never())->method('retry');
        $this->expectException(HttpException::class);
        $controller->retry($request, 'foreign-event', $service);
    }

    public function testDelegatedAccountantMayOnlySetPaidThroughApprovedCollectionFlow(): void
    {
        $this->grant('nirab_finance_scopes', ['accountant_approve']);
        $request = \Illuminate\Http\Request::create('/accountant'); $request->setUserResolver(fn () => $this->actor()); $this->container->instance('request', $request);
        $this->container->make('config')->set('nirab_execution.enabled', true);
        $cash = $this->createMock(\Modules\BookingModule\Services\TechnicianCashService::class);
        $cash->method('manualMethod')->willReturn(true); $cash->method('approvedAmount')->willReturn(100.0); $cash->method('dueAmount')->willReturn(100.0);
        $this->container->instance(\Modules\BookingModule\Services\TechnicianCashService::class, $cash);
        $booking = new \Modules\BookingModule\Entities\Booking();
        $booking->forceFill(['id' => 'booking', 'provider_id' => 'a', 'booking_status' => 'completed', 'is_paid' => 0, 'payment_method' => 'cash']); $booking->syncOriginal(); $booking->exists = true;
        $booking->is_paid = 1; $booking->nirabAccountingApproval = true;
        $execution = new \Modules\BookingModule\Services\BookingExecutionService(new NirabFinanceAccess(), new \App\Services\AuditService());
        $execution->guard($booking);
        $this->assertSame(1, $booking->is_paid);
    }

    public function testAllWorkspaceRoutesUseSessionAndBranchGuardsAndExistingActions(): void
    {
        $events = new \Illuminate\Events\Dispatcher($this->container);
        $router = new \Illuminate\Routing\Router($events, $this->container);
        \Illuminate\Support\Facades\Route::swap($router);
        $router->middleware('web')->group(fn () => require dirname(__DIR__, 2) . '/routes/branch-workspace.php');
        $this->assertGreaterThan(60, count($router->getRoutes()));
        foreach ($router->getRoutes() as $route) {
            $this->assertContains('web', $route->middleware());
            $this->assertContains(\App\Http\Middleware\BranchWorkspaceContext::class, $route->middleware());
            [$controller, $method] = explode('@', $route->getActionName());
            $this->assertTrue(method_exists($controller, $method), $route->uri());
        }
    }

    public function testDirectPaidEditRemainsForbiddenEvenForDelegatedManager(): void
    {
        $this->grant('nirab_finance_scopes', ['accountant_approve']);
        $request = \Illuminate\Http\Request::create('/direct'); $request->setUserResolver(fn () => $this->actor()); $this->container->instance('request', $request);
        $this->container->make('config')->set('nirab_execution.enabled', true);
        $booking = new \Modules\BookingModule\Entities\Booking();
        $booking->forceFill(['id' => 'booking', 'provider_id' => 'a', 'booking_status' => 'completed', 'is_paid' => 0]); $booking->syncOriginal(); $booking->exists = true; $booking->is_paid = 1;
        $execution = new \Modules\BookingModule\Services\BookingExecutionService(new NirabFinanceAccess(), new \App\Services\AuditService());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $execution->guard($booking);
    }

    public function testRepeatedCustodyAdjustmentHasOneLedgerMovement(): void
    {
        Manager::schema()->create('servicemen', function (Blueprint $t): void { $t->string('id')->primary(); $t->string('provider_id'); $t->softDeletes(); });
        Manager::schema()->create('technician_custody_entries', function (Blueprint $t): void {
            $t->string('id')->primary(); foreach (['technician_id','provider_id','type','source_type','source_id','actor_id','reason'] as $key) $t->string($key)->nullable();
            $t->decimal('amount'); $t->dateTime('occurred_at'); $t->timestamps();
        });
        DB::table('servicemen')->insert(['id' => 'tech', 'provider_id' => 'a']);
        $this->grant('nirab_finance_scopes', ['custody_adjust']);
        $this->container->make('config')->set('nirab_execution.technician_cash_custody_limit', 3000);
        $audit = $this->createMock(\App\Services\AuditService::class);
        $audit->expects($this->once())->method('record')->willReturn(new \App\Models\AuditLog());
        \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher($this->container));
        \Modules\BookingModule\Entities\TechnicianCustodyEntry::clearBootedModels();
        try {
            $cash = new \Modules\BookingModule\Services\TechnicianCashService(new NirabFinanceAccess(), $audit);
            $first = $cash->adjust('tech', $this->actor(), 20, 'Correction', 'request-key');
            $second = $cash->adjust('tech', $this->actor(), 20, 'Correction', 'request-key');
            $this->assertSame((string) $first->id, (string) $second->id); $this->assertSame(1, DB::table('technician_custody_entries')->count()); $this->assertEquals(20, $cash->balance('tech'));
        } finally { \Illuminate\Database\Eloquent\Model::unsetEventDispatcher(); }
    }

    public function testPaymentMovementsUsePaymentDateAndStillEnforceBranch(): void
    {
        $this->booking('past', ['service_schedule' => '2026-08-01 00:00:00']);
        $this->booking('other', ['provider_id' => 'b']);
        foreach (['past', 'other'] as $id) DB::table('transactions')->insert(['booking_id' => $id, 'trx_type' => TRX_TYPE['booking_amount'], 'credit' => 25, 'created_at' => '2026-10-08 08:00:00']);
        $this->assertSame(['past'], $this->reports->paymentLedger($this->actor(), $this->filters())->pluck('subject_id')->all());
        $this->assertSame(0, $this->reports->subjects($this->actor(), $this->filters())->count());
    }

    private function navigationRoutes(\Illuminate\Http\Request $request): void
    {
        $router = new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($this->container), $this->container);
        foreach (['admin', 'provider'] as $panel) {
            $router->get('/' . $panel . '/branch-workspace', fn () => null)->name($panel . '.branch.index');
            $router->get('/' . $panel . '/booking/details/{id}', fn () => null)->name($panel . '.booking.details');
            $router->get('/' . $panel . '/booking/repeat-single-details/{id}', fn () => null)->name($panel . '.booking.repeat_single_details');
        }
        $router->getRoutes()->refreshNameLookups();
        $this->container->instance('url', new \Illuminate\Routing\UrlGenerator($router->getRoutes(), $request));
        $this->container->alias('url', \Illuminate\Contracts\Routing\UrlGenerator::class);
        $this->container->instance('request', $request);
    }

    private function renderFragment(string $source, array $data = []): string
    {
        $files = new \Illuminate\Filesystem\Filesystem();
        $compiler = new \Illuminate\View\Compilers\BladeCompiler($files, sys_get_temp_dir());
        $path = tempnam(sys_get_temp_dir(), 'nirab-supplement-');
        file_put_contents($path, $compiler->compileString($source));
        try { return (new \Illuminate\View\Engines\PhpEngine($files))->get($path, $data); }
        finally { unlink($path); }
    }

    public function testActualBookingAndFinanceLinksSetDefaultVisitPageAndRoundTripAllFilters(): void
    {
        $values = ['branch_id' => 'a', 'tab' => 'finance', 'period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-08',
            'status' => 'completed', 'queue' => 'completed_unpaid', 'payment' => 'unpaid', 'technician_id' => 'tech', 'service_id' => 'service',
            'search' => 'مرجع & رقم 42', 'page' => '3', 'collection_page' => '2', 'entry_page' => '4'];
        $request = \Illuminate\Http\Request::create('/admin/branch-workspace', 'GET', $values);
        $this->navigationRoutes($request);
        foreach (['bookings', 'finance'] as $template) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/resources/views/branch/' . $template . '.blade.php');
            preg_match('/href="(' . preg_quote("{{ route(\$prefix.'.booking.'", '/') . '.*?\}\})"/', $source, $match);
            $this->assertNotEmpty($match, $template);
            foreach (['admin', 'provider'] as $panel) foreach ([null, 'visit-id'] as $repeat) {
                $url = $this->renderFragment($match[1], ['prefix' => $panel, 'row' => (object) ['subject_id' => 'visit-id', 'booking_repeat_id' => $repeat], 'filters' => $values]);
                parse_str(parse_url(html_entity_decode($url), PHP_URL_QUERY), $query);
                $this->assertSame('details', $query['web_page']);
                $detail = \Illuminate\Http\Request::create('/' . $panel . '/booking/details/visit-id', 'GET', $query);
                $this->assertEquals($values, \App\Services\BranchWorkspaceNavigation::returnParameters($detail));
                $statusUrl = $detail->fullUrlWithQuery(['web_page' => 'status']);
                parse_str(parse_url($statusUrl, PHP_URL_QUERY), $statusQuery);
                $this->assertSame('status', $statusQuery['web_page']);
                $this->assertSame($query['workspace'], $statusQuery['workspace']);
                $this->navigationRoutes($detail);
                $return = $this->renderFragment(file_get_contents(dirname(__DIR__, 2) . '/resources/views/branch/return.blade.php'), ['workspacePanel' => $panel]);
                preg_match('/href="([^"]+)"/', $return, $returnMatch);
                parse_str(parse_url(html_entity_decode($returnMatch[1]), PHP_URL_QUERY), $returned);
                $this->assertEquals($values, $returned);
                $this->navigationRoutes($request);
            }
        }
    }

    public function testReturnStateCannotChangeBranchOrInjectRedirectAndLegacyDatesStillWork(): void
    {
        $request = \Illuminate\Http\Request::create('/detail', 'GET', ['branch_id' => 'a', 'workspace' => ['tab' => 'reports', 'branch_id' => 'b', 'redirect' => 'https://example.invalid', 'search' => ['unexpected']], 'workspace_from' => '2026-10-01', 'workspace_to' => '2026-10-08']);
        $this->assertSame(['tab' => 'reports', 'from' => '2026-10-01', 'to' => '2026-10-08', 'branch_id' => 'a'], \App\Services\BranchWorkspaceNavigation::returnParameters($request));
    }

    public function testNonOwnerEmployeeResolvesAssignedProviderAndKeepsOperationalAndFinancialBounds(): void
    {
        Manager::schema()->create('providers', function (Blueprint $t): void { $t->string('id')->primary(); $t->string('user_id'); $t->string('company_name'); $t->softDeletes(); });
        DB::table('providers')->insert(['id' => 'a', 'user_id' => 'branch-owner', 'company_name' => 'Branch A']);
        $employee = new User(); $employee->forceFill(['id' => 'user', 'user_type' => 'provider-employee', 'provider_id' => 'a', 'is_active' => 1]);
        $this->assertNull($employee->provider);
        $this->grant('nirab_operational_scopes', ['analytics.read', 'dispatch.manage'], ['a', 'b'], true);
        $this->grant('nirab_finance_scopes', ['view_cash'], ['a', 'b'], true);
        $access = new OperationalAccess();
        $this->assertSame('a', $access->provider($employee)->id);
        $this->assertSame('Branch A', $access->provider($employee)->company_name);
        $this->assertNull($employee->provider, 'The owner relation must stay empty');
        $this->assertTrue($access->allows($employee, 'dispatch.manage', 'a'));
        $this->assertFalse($access->allows($employee, 'dispatch.manage', 'b'));
        $this->booking('own'); $this->booking('other', ['provider_id' => 'b']);
        $this->assertSame(['own'], $this->reports->subjects($employee, $this->filters())->pluck('subject_id')->all());
        $this->assertSame(0, $this->reports->balances($employee, $this->filters(['branch_id' => 'b']), 'view_cash')->count());
        $request = \Illuminate\Http\Request::create('/provider/branch-workspace', 'GET', ['branch_id' => 'a']); $request->setUserResolver(fn () => $employee);
        $this->navigationRoutes($request);
        $context = (new BranchWorkspaceController($access, new NirabFinanceAccess(), $this->reports))->context($request);
        $this->assertSame('a', $context['branchId']); $this->assertContains('bookings', $context['tabs']);
        $html = $this->renderFragment("{{ app(\\App\\Services\\OperationalAccess::class)->provider(request()->user())->company_name }}");
        $this->assertSame('Branch A', $html);
    }

    public function testEmployeeCannotSelectAnotherBranchThroughWorkspaceMiddleware(): void
    {
        $employee = new User(); $employee->forceFill(['id' => 'user', 'user_type' => 'provider-employee', 'provider_id' => '11111111-1111-4111-8111-111111111111', 'is_active' => 1]);
        $request = \Illuminate\Http\Request::create('/provider/branch-workspace', 'GET', ['branch_id' => '22222222-2222-4222-8222-222222222222']); $request->setUserResolver(fn () => $employee);
        \Illuminate\Http\Request::macro('validate', function (array $rules) { return app('validator')->make($this->all(), $rules)->validate(); });
        $this->expectException(HttpException::class);
        (new \App\Http\Middleware\BranchWorkspaceContext())->handle($request, fn () => $this->fail('Foreign branch reached controller'));
    }

    public function testNonOwnerEmployeeCanUseRealProviderScheduleActionThroughWorkspace(): void
    {
        Manager::schema()->create('providers', function (Blueprint $t): void { $t->string('id')->primary(); $t->string('user_id'); $t->softDeletes(); });
        DB::table('providers')->insert(['id' => 'a', 'user_id' => 'branch-owner']);
        $employee = new User(); $employee->forceFill(['id' => 'user', 'user_type' => 'provider-employee', 'provider_id' => 'a', 'is_active' => 1]);
        $this->assertNull($employee->provider);
        $this->grant('nirab_operational_scopes', ['dispatch.manage']);
        $this->booking('own', ['service_schedule' => '2026-10-09 08:00:00']);
        $request = \Illuminate\Http\Request::create('/provider/branch-workspace/bookings/booking/own/schedule', 'POST', ['branch_id' => 'a', 'reason' => 'Keep confirmed appointment', 'service_schedule' => '2026-10-09T11:00']);
        $request->setUserResolver(fn () => $employee); $this->container->instance('request', $request);
        $legacy = (new \ReflectionClass(\Modules\BookingModule\Http\Controllers\Web\Provider\BookingController::class))->newInstanceWithoutConstructor();
        foreach (['booking' => new \Modules\BookingModule\Entities\Booking(), 'bookingRepeat' => new \Modules\BookingModule\Entities\BookingRepeat(), 'bookingScheduleHistory' => new \Modules\BookingModule\Entities\BookingScheduleHistory()] as $property => $value) {
            (new \ReflectionProperty($legacy, $property))->setValue($legacy, $value);
        }
        $this->container->instance(get_class($legacy), $legacy);
        $responseFactory = $this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responseFactory->method('json')->willReturnCallback(fn ($data, $status = 200) => new \Illuminate\Http\JsonResponse($data, $status));
        $this->container->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responseFactory);
        $audit = $this->createMock(\App\Services\AuditService::class); $audit->expects($this->once())->method('record')->willReturn(new \App\Models\AuditLog());
        $this->container->instance(\App\Services\AuditService::class, $audit);
        $response = (new BranchWorkspaceController(new OperationalAccess(), new NirabFinanceAccess(), $this->reports))->bookingAction($request, 'booking', 'own', 'schedule');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(NO_CHANGES_FOUND['response_code'], $response->getData(true)['response_code']);
        $this->assertNull($employee->provider);
        $this->assertSame('a', DB::table('bookings')->where('id', 'own')->value('provider_id'));
    }

    public function testVisitDetailPageDefaultsAndRejectsUnknownPages(): void
    {
        foreach ([[], ['web_page' => ''], ['web_page' => 'details'], ['web_page' => 'status']] as $values) {
            $request = \Illuminate\Http\Request::create('/admin/booking/repeat-single-details/visit', 'GET', $values);
            $this->assertSame(($values['web_page'] ?? '') ?: 'details', \App\Services\BranchWorkspaceNavigation::detailPage($request));
        }
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Services\BranchWorkspaceNavigation::detailPage(\Illuminate\Http\Request::create('/visit', 'GET', ['web_page' => 'unknown']));
    }
}
