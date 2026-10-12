<?php
// Uses the REAL controller source files with minimal fake framework boundaries.
// Does not require Composer, a database, network, or a running Laravel app.
// This verifies that the source calls the expected auth/validation branches;
// full HTTP and MySQL tests remain separate requirements.
namespace Illuminate\Routing { class Controller {} }
namespace Illuminate\Http {
    class JsonResponse { public function __construct(public $data = [], public $status = 200) {} }
    class Request implements \ArrayAccess {
        public function __construct(public array $values = []) {}
        public function input($name, $fallback = null) {return $this->values[$name] ?? $fallback;}
        public function all() {return $this->values;}
        public function has($name) {return array_key_exists($name, $this->values);}
        public function filled($name) {return !empty($this->values[$name]);}
        public function offsetExists($offset): bool {return array_key_exists($offset, $this->values);}
        public function offsetGet($offset): mixed {return $this->values[$offset] ?? null;}
        public function offsetSet($offset, $value): void {$this->values[$offset] = $value;}
        public function offsetUnset($offset): void {unset($this->values[$offset]);}
        public function validate(array $rules) {return $this->values;}
    }
}
namespace Modules\CartModule\Traits { trait CartTrait {} trait AddedToCartTrait {} }
namespace Modules\BookingModule\Http\Traits { trait BookingTrait {} trait RebookingTrait {} }
namespace Modules\CustomerModule\Traits { trait CustomerAddressTrait {} }
namespace Modules\CartModule\Entities { class Cart {} }
namespace Modules\BookingModule\Entities {class Booking {} class BookingStatusHistory {} class BookingRepeat {} }
namespace Modules\PromotionManagement\Entities { class Coupon {} }
namespace Modules\ProviderManagement\Entities {class Provider {} }
namespace Modules\ServiceManagement\Entities {class Service {} class Variation {} }
namespace Modules\ServiceManagement\Services {class PricingEngine {} }
namespace Modules\UserManagement\Entities {class User {} class Guest {} }
namespace Modules\PaymentModule\Entities {class OfflinePayment {} }
namespace Modules\PaymentModule\Services {
    class PaymentPrincipal {
        public static string $issuedSubject = 'b68d1f9b-08f2-4595-8183-5a81be7e50a0';
        public static bool $valid = true;
        public function resolve(\Illuminate\Http\Request $request): array {
            if (!self::$valid) throw new \RuntimeException('401 guest token invalid', 401);
            return ['id' => self::$issuedSubject, 'is_guest' => true];
        }
    }
}
namespace Illuminate\Support\Facades {
    class Config {public static function get(string $x) {return 'zone-a';}}
    class Validator {
        public static array $captured = [];
        public static function make(array $data, array $rules) { self::$captured = $rules; return new class {public function fails(){return true;} }; }
    }
}
namespace {
    define('PAYMENT_METHODS', [['key' => 'cash_after_service']]);
    define('DEFAULT_400', 'bad-request');
    $GLOBALS['loginUser'] = null;
    function auth($guard = null): object {return new class {public function user() {return $GLOBALS['loginUser'];}};}
    function app($name) {return new $name;}
    function abort_unless($condition, $status, $message = ''): void {
        if (!$condition) throw new \RuntimeException("$status $message", $status);
    }
    function business_config($key, $group): object {return (object) ['live_values' => 0];}
    function response_formatter($code, $body=null, $errors=null): array {return ['code'=>$code, 'content'=>$body, 'errors'=>$errors];}
    function error_processor($validator): array {return [];}
    function response(): object {return new class {public function json($body, $status = 200) {return new \Illuminate\Http\JsonResponse($body, $status);}};}
    function runTest(string $description, callable $action): void {
        global $passed, $failed; try {$action(); ++$passed; echo "PASS $description\n";}
        catch (\Throwable $e) {++$failed; echo "FAIL $description :: {$e->getMessage()}\n";}
    }
    function ensure($condition, $message) {if (!$condition) throw new \RuntimeException($message);}
    function expectStatus(int $status, callable $action): void {
        try {$action(); throw new \RuntimeException("Expected status $status but operation succeeded");}
        catch (\RuntimeException $e) {if ($e->getCode() !== $status) throw $e;}
    }
    $passed=0; $failed=0;
    $root = getenv('NIRAB_SOURCE_ROOT') ?: dirname(__DIR__, 2) . '/patched';
    require_once $root . '/01-NIRAB-BACKEND-SOURCE/Modules/CartModule/Http/Controllers/Api/V1/Customer/CartController.php';
    require_once $root . '/01-NIRAB-BACKEND-SOURCE/Modules/BookingModule/Http/Controllers/Api/V1/Customer/BookingController.php';
    $guest = \Modules\PaymentModule\Services\PaymentPrincipal::$issuedSubject;
    $request = new \Illuminate\Http\Request(['guest_id' => $guest, 'service_location' => 'customer']);
    $makeCart = fn ($req) => new \Modules\CartModule\Http\Controllers\Api\V1\Customer\CartController(
        new \Modules\CartModule\Entities\Cart, new \Modules\ServiceManagement\Entities\Service,
        new \Modules\ServiceManagement\Entities\Variation, new \Modules\UserManagement\Entities\User,
        new \Modules\ProviderManagement\Entities\Provider, new \Modules\UserManagement\Entities\Guest,
        $req, new \Modules\BookingModule\Entities\Booking, new \Modules\PromotionManagement\Entities\Coupon,
        new \Modules\ServiceManagement\Services\PricingEngine);
    runTest('Cart recognizes authenticated, matching guest session', function () use ($request, $makeCart) {
        \Modules\PaymentModule\Services\PaymentPrincipal::$valid = true;
        $cart = $makeCart($request);
        $p = new \ReflectionProperty($cart, 'customerUserId');
        ensure($p->getValue($cart) === $request->input('guest_id'), 'Wrong guest cart owner');
    });
    runTest('Cart refuses guessed or mismatched guest UUID', function () use ($makeCart) {
        \Modules\PaymentModule\Services\PaymentPrincipal::$valid = true;
        expectStatus(401, fn () => $makeCart(new \Illuminate\Http\Request(['guest_id' => '8a430ff9-8a42-49b5-9811-3f3bdc41e506'])));
    });
    runTest('Cart refuses invalid guest payment token', function () use ($request, $makeCart) {
        \Modules\PaymentModule\Services\PaymentPrincipal::$valid = false;
        expectStatus(401, fn () => $makeCart($request));
        \Modules\PaymentModule\Services\PaymentPrincipal::$valid = true;
    });
    runTest('Logged in customer cart uses own user id', function () use ($makeCart) {
        $GLOBALS['loginUser'] = (object) ['id' => 'customer-123'];
        $cart = $makeCart(new \Illuminate\Http\Request(['guest_id'=>'other']));
        $p = new \ReflectionProperty($cart, 'customerUserId');
        ensure($p->getValue($cart) === 'customer-123', 'Customer authentication not respected');
        $GLOBALS['loginUser'] = null;
    });
    $makeBooking = function ($isLoggedIn, $id) {
        $class = new \ReflectionClass(\Modules\BookingModule\Http\Controllers\Api\V1\Customer\BookingController::class);
        $instance = $class->newInstanceWithoutConstructor();
        $prop = $class->getProperty('isCustomerLoggedIn');$prop->setValue($instance, $isLoggedIn);
        $prop = $class->getProperty('customerUserId');$prop->setValue($instance, $id);
        return $instance;
    };
    runTest('Guest checkout refuses a different guest session', function () use ($makeBooking, $request) {
        $controller=$makeBooking(false,'bad-guest-id');
        $method = new \ReflectionMethod($controller, 'assertGuestBookingPrincipal');
        expectStatus(401, fn () => $method->invoke($controller, $request));
    });
    runTest('Guest checkout accepts the issued guest session', function () use ($makeBooking, $request, $guest) {
        $controller=$makeBooking(false,$guest);
        (new \ReflectionMethod($controller, 'assertGuestBookingPrincipal'))->invoke($controller, $request);
    });
    runTest('Booking validator attaches provider location restriction to correct field', function () use ($makeBooking) {
        $controller = $makeBooking(true,'customer-123');
        $controller->placeRequest(new \Illuminate\Http\Request([
            'service_location'=>'customer', 'service_address_id'=>null,
            'service_address'=>'"valid JSON but wrong type"',
        ]));
        $rules=\Illuminate\Support\Facades\Validator::$captured;
        ensure(isset($rules['service_location']) && is_array($rules['service_location']), 'service_location must have callback rule array');
        ensure(count(array_filter(array_keys($rules),'is_int')) === 0, 'Unexpected numeric validation rule');
        $locationRules=$rules['service_location'];
        $hasCallable=count(array_filter($locationRules,'is_callable'))>0;
        ensure($hasCallable, 'Provider-location callback is not attached');
    });
    runTest('Malformed address JSON scalar yields validation error, not TypeError', function () use ($makeBooking) {
        $controller=$makeBooking(true,'customer-123');
        $controller->placeRequest(new \Illuminate\Http\Request([
            'service_location'=>'customer','service_address_id'=>null, 'service_address'=>'"not-an-object"',
        ]));
        $rules=\Illuminate\Support\Facades\Validator::$captured;
        ensure(is_array($rules['service_address']), 'Missing service_address callback');
        $cb=end($rules['service_address']);$errors=[];
        $cb('service_address','"not-an-object"',function($message)use(&$errors){$errors[]=$message;});
        ensure(count($errors) === 1, 'Expected exactly one address validation error');
    });
    runTest('Address JSON with missing keys reports validation errors instead of warnings', function () use ($makeBooking) {
        $controller=$makeBooking(true,'customer-123');
        $controller->placeRequest(new \Illuminate\Http\Request([
            'service_location'=>'customer','service_address_id'=>null, 'service_address'=>'{"unrelated":true}',
        ]));
        $rules=\Illuminate\Support\Facades\Validator::$captured;
        $cb=end($rules['service_address']); $errors=[];
        set_error_handler(function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
        try { $cb('service_address','{"unrelated":true}',function($message)use(&$errors){$errors[]=$message;}); }
        finally {restore_error_handler();}
        ensure(count($errors)===6, 'All six required address properties must receive validation errors');
    });
    runTest('JSON address arrays are rejected instead of indexing missing keys', function () use ($makeBooking) {
        $controller=$makeBooking(true,'customer-123');
        $controller->placeRequest(new \Illuminate\Http\Request([
            'service_location'=>'customer','service_address_id'=>null, 'service_address'=>'[]',
        ]));
        $rules=\Illuminate\Support\Facades\Validator::$captured;
        $cb=end($rules['service_address']); $errors=[];
        set_error_handler(function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
        try { $cb('service_address','[]',function($message)use(&$errors){$errors[]=$message;}); }
        finally {restore_error_handler();}
        ensure(count($errors)===1, 'JSON array must produce one shape error');
    });
    echo "ISOLATED TEST TOTAL: passed=$passed failed=$failed\n";
    exit($failed ? 1 : 0);
}
