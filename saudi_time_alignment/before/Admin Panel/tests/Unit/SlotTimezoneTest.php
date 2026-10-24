<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\{ArrayLoader, Translator};
use Illuminate\Validation\Factory;
use Mockery;
use Modules\BookingModule\Http\Controllers\Api\V1\Customer\DispatchController;
use Modules\BookingModule\Services\{AvailabilityService, BookingSchedule, BranchCheckoutService, TrackingService};
use Modules\ProviderManagement\Entities\Provider;
use Modules\ProviderManagement\Services\{BranchAssignmentService, BranchServiceAreaRepository};
use Modules\ServiceManagement\Services\{BranchServiceAvailabilityService, ServiceItemRequirements};
use PHPUnit\Framework\TestCase;

/** Real controller/check-out checks, fake capacity and model reads; no DB or app boot. */
class SlotTimezoneTest extends TestCase
{
    private const ZONE = '11111111-1111-4111-8111-111111111111';
    private const BRANCH = '22222222-2222-4222-8222-222222222222';
    private Container $container;
    private DispatchController $controller;
    private bool $reserved = false;
    private string $phpTimezone;
    private mixed $modelResolver;

    protected function setUp(): void
    {
        $this->phpTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Riyadh');
        Carbon::setTestNow(Carbon::parse('2026-10-14T12:00:00Z'));
        $this->container = new class extends Container {
            public function getLocale(): string { return 'en'; }
        };
        Container::setInstance($this->container);
        Facade::setFacadeApplication($this->container);
        Facade::clearResolvedInstances();
        $this->container->instance('config', new Repository(['app' => ['timezone' => 'UTC']]));
        $translator = new Translator(new ArrayLoader(), 'en');
        $this->container->instance('translator', $translator);
        $this->container->instance('validator', new Factory($translator, $this->container));
        $response = Mockery::mock(ResponseFactory::class);
        $response->shouldReceive('json')->andReturnUsing(fn ($data, $status) => new JsonResponse($data, $status));
        $this->container->instance(ResponseFactory::class, $response);

        $requirements = Mockery::mock(ServiceItemRequirements::class);
        $requirements->shouldReceive('evaluate')->andReturn(['service_ids' => ['service'], 'duration_minutes' => 60, 'required_technicians' => 1, 'execution_type' => 'single']);
        $this->container->instance(ServiceItemRequirements::class, $requirements);
        $area = Mockery::mock(BranchServiceAreaRepository::class);
        $area->shouldReceive('resolveAreaId')->andReturn('area');
        $this->container->instance(BranchServiceAreaRepository::class, $area);
        $services = Mockery::mock(BranchServiceAvailabilityService::class);
        $services->shouldReceive('isAvailable')->andReturnTrue();
        $this->container->instance(BranchServiceAvailabilityService::class, $services);

        $provider = new Provider();
        $provider->setRawAttributes(['id' => self::BRANCH]);
        $branches = Mockery::mock(BranchAssignmentService::class);
        $branches->shouldReceive('isEligible')->andReturnTrue();
        $branches->shouldReceive('resolve')->andReturnUsing(function ($zone, $ids, CarbonInterface $start, $lat, $lon, $id, $available = null) use ($provider) {
            return !$available || $available($provider) ? $provider : null;
        });
        $this->container->instance(BranchAssignmentService::class, $branches);
        $availability = Mockery::mock(AvailabilityService::class);
        $availability->shouldReceive('canFulfill')->andReturnUsing(fn ($branch, $ids, CarbonInterface $start) =>
            !$this->reserved && !$start->isPast() && $start->format('H:i:s') === '08:00:00');
        $this->container->instance(AvailabilityService::class, $availability);
        $this->controller = new DispatchController($branches, $availability, Mockery::mock(TrackingService::class));

        // Eloquent builds its normal query; only the provider SELECT is mocked.
        $this->modelResolver = Model::getConnectionResolver();
        $connection = Mockery::mock(Connection::class.'[select]', [null]);
        $connection->shouldReceive('select')->andReturn([(object) ['id' => self::BRANCH]]);
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->andReturn($connection);
        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->phpTimezone);
        if ($this->modelResolver) Model::setConnectionResolver($this->modelResolver);
        else Model::unsetConnectionResolver();
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
    }

    private function availability(?string $start = null, string $date = '2026-10-15'): array
    {
        $request = Request::create('/slots', 'GET', ['zone_id' => self::ZONE, 'items' => [['service_id' => 'service']], 'date' => $date, 'starts_at' => $start]);
        return $this->controller->availability($request)->getData(true)['content'];
    }

    private function checkoutAvailable(string $start): bool
    {
        $method = new \ReflectionMethod(BranchCheckoutService::class, 'visitAvailable');
        return $method->invoke(new BranchCheckoutService(),
            ['provider_id' => self::BRANCH, 'area_id' => 'area', 'service_ids' => ['service'], 'duration' => 60, 'required_technicians' => 1, 'execution_type' => 'single', 'branch_selection' => 'manual'],
            ['schedule' => BookingSchedule::forStorage($start)],
            ['zone_id' => self::ZONE, 'address' => ['lat' => 24.0, 'lon' => 46.0]]);
    }

    public function testListedSlotRevalidatesAndCheckoutUsesSameStoredInstant(): void
    {
        $listed = $this->availability()['slots'];
        self::assertCount(1, $listed);
        self::assertSame('2026-10-15T08:00:00+00:00', $listed[0]['starts_at']);
        foreach ([$listed[0]['starts_at'], '2026-10-15T08:00:00Z', '2026-10-15T08:00:00.000Z', '2026-10-15T11:00:00+03:00', '2026-10-15T04:00:00-04:00', '2026-10-14T23:00:00-09:00'] as $start) {
            self::assertTrue($this->availability($start)['requested_slot_available']);
            self::assertSame(self::BRANCH, $this->availability($start)['provider_id']);
            self::assertSame('2026-10-15 08:00:00', BookingSchedule::forStorage($start));
            self::assertTrue($this->checkoutAvailable($start));
        }
    }

    public function testLegacyWallTimeKeepsOperationalMeaningDespitePhpTimezone(): void
    {
        foreach (['2026-10-15T08:00:00.000', '2026-10-15 08:00:00'] as $start) {
            self::assertTrue($this->availability($start)['requested_slot_available']);
            self::assertTrue($this->checkoutAvailable($start));
            self::assertSame('2026-10-15T08:00:00+00:00', BookingSchedule::parse($start)->toIso8601String());
        }
        $this->container['config']->set('app.timezone', 'Asia/Riyadh');
        self::assertSame('2026-10-15T08:00:00+03:00', BookingSchedule::parse('2026-10-15 08:00:00')->toIso8601String());
        self::assertSame('2026-10-15 11:00:00', BookingSchedule::forStorage('2026-10-15T08:00:00Z'));
    }

    public function testReservedSlotDisappearsAndBothRechecksRejectIt(): void
    {
        $start = $this->availability()['slots'][0]['starts_at'];
        $this->reserved = true;
        self::assertSame([], $this->availability()['slots']);
        self::assertFalse($this->availability($start)['requested_slot_available']);
        self::assertFalse($this->checkoutAvailable($start));
    }

    public function testDifferentInstantOrOperationalDayIsNotAcceptedAsTheListedSlot(): void
    {
        self::assertFalse($this->availability('2026-10-15T08:00:00+03:00')['requested_slot_available']);
        self::assertFalse($this->checkoutAvailable('2026-10-15T08:00:00+03:00'));
        self::assertFalse($this->availability('2026-10-15T08:00:00Z', '2026-10-16')['requested_slot_available']);
    }

    public function testPastSlotIsRejectedInListRecheckAndCheckout(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15T09:00:00Z'));
        self::assertSame([], $this->availability()['slots']);
        self::assertFalse($this->availability('2026-10-15T08:00:00Z')['requested_slot_available']);
        self::assertFalse($this->checkoutAvailable('2026-10-15T11:00:00+03:00'));
    }
}
