<?php
// Isolated in-memory SQLite fixture; never boots Laravel or reads project .env.
require __DIR__ . '/../../Admin Panel/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use App\Models\MarketingConsent;
use App\Services\Marketing\{ConsentService, ConversionRecorder, PlatformConnector};
use App\Jobs\DeliverMarketingEvent;

$container = new Container();
Container::setInstance($container);
$config = new Repository(['marketing' => ['enabled' => true, 'environment' => 'staging', 'policy_version' => 'test-v1', 'consent_days' => 180, 'retention_days' => 90],
    'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]], 'app' => ['key' => 'test-only-key']]);
$container->instance('config', $config);
$container->instance('events', new Dispatcher($container));
$container->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
$capsule = new Manager($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setEventDispatcher($container['events']);
$capsule->setAsGlobal(); $capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$container->bind('db.schema', fn () => $capsule->schema());
$container->singleton('cache', fn () => new Illuminate\Cache\CacheManager($container));
Facade::setFacadeApplication($container);

$migration = require __DIR__ . '/../../Admin Panel/database/migrations/2026_11_05_000001_create_marketing_tracking_tables.php';
$migration->up(); // Only the :memory: connection above exists.
$checks = 0;
function verify(bool $ok, string $name): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($name); }
$service = new ConsentService();
$grant = $service->save(['scope' => 'customer_web', 'analytics' => true, 'advertising' => true,
    'attribution' => ['utm_source' => 'test', 'fbclid' => 'test-click', 'email' => 'blocked@example.test', 'utm_campaign' => 'bad@example.test']], null);
$consent = $service->find($grant['token']);
verify(strlen($grant['token']) === 64, 'Opaque receipt');
verify($consent->token_hash === hash('sha256', $grant['token']), 'Hash-only receipt');
verify($consent->allows('advertising'), 'Purpose grant');
verify($consent->attribution === ['utm_source' => 'test', 'fbclid' => 'test-click'], 'Attribution allowlist');
verify(!str_contains(DB::table('marketing_consents')->value('attribution'), 'test-click'), 'Attribution encrypted at rest');
verify(!isset($consent->toArray()['token_hash']), 'Hash not serialized');
$config->set('marketing.environment', 'production');
verify($service->find($grant['token']) === null, 'Environment-scoped receipt lookup');
verify(!$consent->allows('advertising'), 'Staging receipt cannot activate production');
$config->set('marketing.environment', 'staging');
$config->set('marketing.policy_version', 'test-v2');
verify(!$consent->allows('analytics'), 'Old policy cannot grant collection');
$config->set('marketing.policy_version', 'test-v1');
$config->set('marketing.enabled', false);
verify($service->find($grant['token']) === null, 'Master disable blocks receipt access');
$config->set('marketing.enabled', true);
$recorder = new ConversionRecorder();
$record = new ReflectionMethod($recorder, 'event');
$record->invoke($recorder, 'purchase', 'before-consent', $consent->id, ['value' => 10, 'currency' => 'SAR'], now()->subDay());
verify(DB::table('marketing_conversion_events')->count() === 0, 'No historical event before consent');
$record->invoke($recorder, 'purchase', 'purchase:booking:test', $consent->id, ['transaction_id' => 'booking:test', 'value' => 115, 'currency' => 'SAR']);
$record->invoke($recorder, 'purchase', 'purchase:booking:test', $consent->id, ['transaction_id' => 'booking:test', 'value' => 115, 'currency' => 'SAR']);
verify(DB::table('marketing_conversion_events')->count() === 1, 'Semantic replay dedup');
verify(DB::table('marketing_outbox')->count() === 3, 'Independent provider outbox rows');
$connector = new class extends PlatformConnector {
    public int $calls = 0; public bool $fail = false;
    public function send(string $platform, object $event, MarketingConsent $consent): string { ++$this->calls; if ($this->fail) throw new RuntimeException('isolated test'); return 'sent'; }
};
$row = DB::table('marketing_outbox')->where('platform', 'meta')->first();
$job = new DeliverMarketingEvent($row->id); $job->handle($connector); $job->handle($connector);
verify($connector->calls === 1, 'Sent job replay does not resend');
verify(DB::table('marketing_outbox')->find($row->id)->status === 'sent', 'Remote mock acceptance changes status');
$retry = DB::table('marketing_outbox')->where('platform', 'tiktok')->first();
$connector->fail = true;
for ($i = 0; $i < 6; ++$i) {
    DB::table('marketing_outbox')->where('id', $retry->id)->update(['available_at' => now()->subSecond()]);
    (new DeliverMarketingEvent($retry->id))->handle($connector);
}
verify(DB::table('marketing_outbox')->find($retry->id)->status === 'dead_letter', 'Bounded retries');
verify(DB::table('marketing_outbox')->find($retry->id)->event_id === $retry->event_id, 'Retry preserves event UUID');
$calls = $connector->calls;
$deny = $service->save(['scope' => 'customer_web', 'analytics' => false, 'advertising' => false], $grant['token']);
verify(!$consent->fresh()->allows('advertising'), 'Withdrawal invalidates receipt');
verify($consent->fresh()->attribution === null, 'Withdrawal erases attribution');
verify(DB::table('marketing_outbox')->where('platform', 'snap')->value('status') === 'failed', 'Withdrawal cancels pending');
(new DeliverMarketingEvent(DB::table('marketing_outbox')->where('platform', 'snap')->value('id')))->handle($connector);
verify($connector->calls === $calls, 'Withdrawal makes no outbound call');
$record->invoke($recorder, 'purchase', 'denied-sale', $service->find($deny['token'])->id, ['value' => 10, 'currency' => 'SAR']);
verify(DB::table('marketing_conversion_events')->count() === 1, 'Denied purpose cannot record sale');
$migration->down();
verify(!$capsule->schema()->hasTable('marketing_consents'), 'Fixture rollback');
echo "PASS: {$checks} isolated pipeline assertions; SQLite memory only, fake connector, no .env/network/services.\n";
