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
use App\Services\Marketing\{ConsentService, ConversionRecorder, PlatformConnector, WebEventOrigin};
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
function verifyActivation(bool $ok, string $label): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
$config->set('marketing.public_scopes', ['landing','customer_web','customer_android','customer_ios']);
$config->set('marketing.providers', ['ga4','meta','tiktok','snap']);
$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$container->instance('validator', new Illuminate\Validation\Factory($translator, $container));
Illuminate\Http\Request::macro('validate', function ($rules) { return Illuminate\Support\Facades\Validator::make($this->all(), $rules)->validate(); });
$routes = new Illuminate\Routing\RouteCollection();
$routes->add((new Illuminate\Routing\Route(['GET'], 'admin/marketing-integrations', fn () => null))->name('admin.marketing.index'));
$container->instance('redirect', new Illuminate\Routing\Redirector(new Illuminate\Routing\UrlGenerator($routes, Illuminate\Http\Request::create('https://example.test'))));
$controller = new App\Http\Controllers\MarketingIntegrationsController();
function saveGa4(string $scope, string $id, bool $expected): void {
    global $controller;
    $request = Illuminate\Http\Request::create('/', 'PUT', ['environment'=>'staging','scope'=>$scope,'provider'=>'ga4','enabled'=>true,'identifier'=>$id]);
    $request->setUserResolver(fn () => (object) ['user_type'=>'super-admin','id'=>'test-owner']);
    try { $controller->update($request); $accepted = true; } catch (Illuminate\Validation\ValidationException) { $accepted = false; }
    verifyActivation($accepted === $expected, 'F-01 '.$scope.' identity validation');
}
saveGa4('customer_android','',true);
verifyActivation(json_decode(DB::table('marketing_integrations')->where('scope','customer_android')->value('public_ids'),true) === [], 'No fake Web ID required for Native');
saveGa4('customer_android','1:12345:android:abcdef',true);
verifyActivation(isset(json_decode(DB::table('marketing_integrations')->where('scope','customer_android')->value('public_ids'),true)['firebase_app_id']), 'Native identity stored under its own key');
saveGa4('customer_ios','1:12345:ios:abcdef',true);
saveGa4('customer_android','G-ABCDE123',false);
saveGa4('customer_ios','1:12345:android:abcdef',false);
saveGa4('customer_web','',false);
saveGa4('customer_web','G-ABCDE123',true);
saveGa4('customer_web','1:12345:android:abcdef',false);
$config->set('marketing.web_origins.staging.customer_web','https://app.example.test/');
$config->set('marketing.web_origins.staging.landing','https://www.example.test');
$config->set('marketing.web_origins.production.customer_web','https://prod.example.test');
$origin = new WebEventOrigin();
verifyActivation($origin->forScope('customer_web') === 'https://app.example.test/', 'Staging Web origin');
verifyActivation($origin->forScope('landing') === 'https://www.example.test/', 'Landing origin isolated');
$config->set('marketing.environment','production');
verifyActivation($origin->forScope('customer_web') === 'https://prod.example.test/', 'Production origin isolated');
$config->set('marketing.environment','staging');
foreach (['https://app.example.test/private','https://app.example.test/?email=private','https://app.example.test/#private','https://user:pass@app.example.test','http://app.example.test',''] as $bad) {
    $config->set('marketing.web_origins.staging.customer_web',$bad);
    verifyActivation($origin->forScope('customer_web') === null, 'Reject unsafe or unconfigured origin');
}
$config->set('marketing.web_origins.staging.customer_web','https://app.example.test/');
$config->set('app.url','https://api.example.test');
$grant = (new ConsentService())->save(['scope'=>'landing','analytics'=>true,'advertising'=>true,'attribution'=>['fbclid'=>'fake-click','ttclid'=>'fake-click']], null);
$consent = (new ConsentService())->find($grant['token']);
foreach (['meta','tiktok'] as $platform) {
    $config->set('marketing.server.'.$platform,['enabled'=>true,'token'=>'test-only-token','test_code'=>'test-only-code','version'=>'v22.0']);
    DB::table('marketing_integrations')->insert(['environment'=>'staging','scope'=>'customer_web','provider'=>$platform,'enabled'=>true,'public_ids'=>json_encode(['pixel_id'=>'123456789'])]);
}
$http = new Illuminate\Http\Client\Factory();
$http->preventStrayRequests();
$http->fake(['graph.facebook.com/*'=>Illuminate\Http\Client\Factory::response(['events_received'=>1]),'business-api.tiktok.com/*'=>Illuminate\Http\Client\Factory::response(['code'=>0])]);
$container->instance(Illuminate\Http\Client\Factory::class,$http);
$event=(object)['id'=>'test-event','event_name'=>'purchase','occurred_at'=>now()->toDateTimeString(),'payload'=>json_encode(['value'=>115,'currency'=>'SAR','transaction_id'=>'booking:test'])];
$connector = new PlatformConnector();
verifyActivation($connector->send('meta',$event,$consent)==='sent','Mock Meta acceptance');
verifyActivation($connector->send('tiktok',$event,$consent)==='sent','Mock TikTok acceptance');
$recorded=$http->recorded();
verifyActivation($recorded[0][0]['data'][0]['event_source_url']==='https://app.example.test/','Meta uses Customer Web, including Landing-granted receipt');
verifyActivation($recorded[1][0]['data'][0]['page']['url']==='https://app.example.test/','TikTok uses Customer Web');
$config->set('marketing.web_origins.staging.customer_web','');
verifyActivation($connector->send('meta',$event,$consent)==='source_origin_unconfigured','Missing Web origin fails closed');
verifyActivation($http->recorded()->count()===2,'No additional HTTP request without approved origin');
$migration->down();
echo "PASS: {$checks} owner-activation assertions; memory-only database, mocked HTTP, no live account calls.\n";
