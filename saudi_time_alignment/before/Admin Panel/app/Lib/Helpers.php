<?php

use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\BookingModule\Entities\SubscriptionSubscriberBooking;
use Modules\BusinessSettingsModule\Entities\NotificationSetup;
use Modules\BusinessSettingsModule\Entities\PackageSubscriber;
use Modules\BusinessSettingsModule\Entities\SettingsTutorials;
use Modules\PaymentModule\Entities\Bonus;
use Modules\PaymentModule\Entities\Setting;
use Modules\ProviderManagement\Entities\Provider;
use Modules\UserManagement\Entities\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\UploadedFile;

if (!function_exists('nirab_branch_mode')) {
    /**
     * Whether providers are operating as internal NIRAB branches.
     */
    function nirab_branch_mode(): bool
    {
        return (bool) config('nirab.branch_mode', true);
    }
}

if (!function_exists('translate')) {
    function translate($key, array $replace = [], ?string $locale = null)
    {
        try {
            $key = (string)$key;
            $local = $locale ?? app()->getLocale();
            $langArray = load_translation_messages($local);
            $defaultValue = humanize_translation_key($key);

            if (!array_key_exists($key, $langArray)) {
                $langArray[$key] = $defaultValue;
                save_translation_messages($local, $langArray);
            }

            $translated = apply_translation_replacements($langArray[$key] ?? $defaultValue, $replace);

            return apply_nirab_branch_terminology($translated, $local);
        } catch (\Throwable $exception) {
            return apply_nirab_branch_terminology(
                apply_translation_replacements((string)$key, $replace),
                $locale ?? app()->getLocale()
            );
        }
    }
}

if (!function_exists('apply_nirab_branch_terminology')) {
    function apply_nirab_branch_terminology(string $value, string $locale): string
    {
        if (!nirab_branch_mode()) {
            return $value;
        }

        if (str_starts_with(strtolower($locale), 'ar')) {
            return str_replace(
                ['مقدمي الخدمات', 'مقدمو الخدمات', 'مقدم الخدمة', 'مزودي الخدمات', 'مزودو الخدمات', 'مزود الخدمة', 'المزودين', 'المزودون', 'المزود', 'مزودين', 'مزودون', 'مزود'],
                ['فروع نيراب', 'فروع نيراب', 'فرع نيراب', 'فروع نيراب', 'فروع نيراب', 'فرع نيراب', 'الفروع', 'الفروع', 'الفرع', 'فروع', 'فروع', 'فرع'],
                $value
            );
        }

        return str_ireplace(
            ['Service Providers', 'Service Provider', 'Providers', 'Provider'],
            ['NIRAB Branches', 'NIRAB Branch', 'Branches', 'Branch'],
            $value
        );
    }
}

if (!function_exists('translation_file_path')) {
    function translation_file_path(string $locale): string
    {
        return base_path('resources/lang/' . $locale . '/lang.php');
    }
}

if (!function_exists('load_translation_messages')) {
    function load_translation_messages(string $locale): array
    {
        $cache = &$GLOBALS['translation_messages_cache'];

        if (!is_array($cache)) {
            $cache = [];
        }

        if (!array_key_exists($locale, $cache)) {
            $filePath = translation_file_path($locale);
            $cache[$locale] = file_exists($filePath) ? include($filePath) : [];
        }

        return $cache[$locale];
    }
}

if (!function_exists('save_translation_messages')) {
    function save_translation_messages(string $locale, array $messages): void
    {
        $filePath = translation_file_path($locale);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }

        $content = "<?php return " . var_export($messages, true) . ";";
        file_put_contents($filePath, $content);

        $cache = &$GLOBALS['translation_messages_cache'];
        if (!is_array($cache)) {
            $cache = [];
        }

        $cache[$locale] = $messages;
    }
}

if (!function_exists('normalize_translation_text')) {
    function normalize_translation_text(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', $text)));
    }
}

if (!function_exists('humanize_translation_key')) {
    function humanize_translation_key(string $key): string
    {
        $text = normalize_translation_text($key);

        if ($text === '') {
            return '';
        }

        return Str::ucfirst($text);
    }
}

if (!function_exists('apply_translation_replacements')) {
    function apply_translation_replacements(string $text, array $replace = []): string
    {
        foreach ($replace as $key => $value) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string)$value;
            } elseif (is_array($value)) {
                $value = implode(',', $value);
            }

            $value = (string)$value;
            $placeholder = ':' . $key;

            $text = str_replace(
                [$placeholder, Str::upper($placeholder), Str::ucfirst($placeholder)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $text
            );
        }

        return $text;
    }
}

if (!function_exists('bs_data')) {
    function bs_data($settings, $key, $required = 0)
    {
        try {
            if (env('APP_ENV') == 'local' || env('APP_ENV') == 'live' || $required) {
                $config = $settings->where('key_name', $key)->first()->live_values;
            } else {
                $config = null;
            }

        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('bs_data_text')) {
    function bs_data_text($settings, $key, $required = 0)
    {
        try {
            if (env('APP_ENV') == 'local' || env('APP_ENV') == 'live' || $required) {
                $config = $settings->where('key', $key)->first()->value;
            } else {
                $config = null;
            }

        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('get_business_time_format')) {
    function get_business_time_format(): string
    {
        try {
            return (string)((business_config('time_format', 'business_information'))->live_values ?? '24');
        } catch (\Throwable $exception) {
            return '24';
        }
    }
}

if (!function_exists('format_time_by_business_settings')) {
    function format_time_by_business_settings($dateTime, string $dateFormat = '', string $separator = ' '): string
    {
        if (empty($dateFormat) && empty($dateTime)) {
            return '';
        }

        try {
            $dateTime = $dateTime instanceof Carbon ? $dateTime : Carbon::parse($dateTime);
            $timeFormat = get_business_time_format() === '12' ? 'g:i A' : 'H:i';

            if ($dateFormat === '') {
                return $dateTime->format($timeFormat);
            }

            return $dateTime->format($dateFormat) . $separator . $dateTime->format($timeFormat);
        } catch (\Throwable $exception) {
            return '';
        }
    }
}

if (!function_exists('error_processor')) {
    function error_processor($validator)
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => translate($error[0])];
        }
        return $errors;
    }
}

if (!function_exists('get_path')) {
    function get_path($type)
    {
        if ($type == 'public') {
            return url('/') . '/public';
        }

        return url('/');
    }
}

if (!function_exists('response_formatter')) {
    function response_formatter($constant, $content = null, $errors = []): array
    {
        $constant = [
            'response_code' => $constant['response_code'],
            'message' => translate($constant['message']),
        ];
        $constant['content'] = $content;
        $constant['errors'] = $errors;

        return $constant;
    }
}

if (!function_exists('getDisk')) {
    function getDisk()
    {
        $storageType = business_config('storage_connection_type', 'storage_settings');
        return isset($storageType) ? ($storageType->live_values == 's3' ? 's3' : 'public') : 'public';
    }
}

if (!function_exists('upload_validation_exception')) {
    function upload_validation_exception(string $message): never
    {
        $translatedMessage = translate($message);

        if (request()->expectsJson() || request()->is('api/*')) {
            throw new HttpResponseException(
                response()->json(
                    response_formatter(DEFAULT_400, null, [
                        ['error_code' => 'storage', 'message' => $translatedMessage],
                    ]),
                    400
                )
            );
        }

        Toastr::error($translatedMessage);

        throw ValidationException::withMessages([
            'storage' => [$translatedMessage],
        ]);
    }
}

if (!function_exists('file_uploader')) {
    function file_uploader(string $dir, string $format, array|object|null $image = null, ?string $old_image = null)
    {
        if ($image == null) {
            return $old_image ?? 'def.png';
        }

        $disk = getDisk();
        $dir = trim($dir, '/') . '/';
        $format = strtolower($format);

        $diskErrorMessage = $disk === 's3'
            ? 'File upload failed. Please check S3 credentials and bucket configuration.'
            : 'File upload failed. Please try again.';

        $storeFile = function (string $path, string $contents) use ($disk, $diskErrorMessage): void {
            try {
                if ($disk == 's3') {
                    $s3Disk = app('dynamic.s3');
                    if (!$s3Disk) {
                        throw new \Exception('S3 not configured');
                    }
                    $result = $s3Disk->put($path, $contents);
                } else {
                    $result = Storage::disk($disk)->put($path, $contents);
                }

                if (!$result) {
                    throw new \Exception('Upload failed');
                }
            } catch (\Throwable $exception) {
                upload_validation_exception($diskErrorMessage);
            }
        };

        $deleteOldFile = function () use ($disk, $dir, $old_image): void {
            if (!$old_image || in_array($old_image, ['def.png', 'default.png'], true)) {
                return;
            }

            $path = $dir . $old_image;
            $localDisk = Storage::disk('public');

            try {
                $s3Disk = app('dynamic.s3');
                if ($s3Disk) {
                    $s3Disk->delete($path);
                }
            } catch (\Throwable $e) {
                // S3 unreachable/misconfigured — fall through to local cleanup.
            }

            try {
                if (!$localDisk->delete($path) && $localDisk->exists($path)) {
                    throw new \Exception('Delete failed');
                }
            } catch (\Throwable $e) {
                upload_validation_exception('Failed to delete');
            }
        };

        $sourcePath = $image instanceof UploadedFile ? $image->getRealPath() : $image;
        $mimeType = $image instanceof UploadedFile
            ? $image->getMimeType()
            : (is_string($sourcePath) ? @mime_content_type($sourcePath) : null);

        if (!$mimeType || !str_starts_with($mimeType, 'image/')) {
            $imageName = now()->toDateString() . "-" . uniqid() . "." . $format;
            $contents = @file_get_contents($sourcePath);

            if ($contents === false) {
                upload_validation_exception('The selected file could not be read.');
            }

            $storeFile($dir . $imageName, $contents);
            $deleteOldFile();

            return $imageName;
        }

        $info = @getimagesize($sourcePath);
        if (!$info || empty($info['mime'])) {
            upload_validation_exception('The selected image could not be processed.');
        }

        $mime = strtolower($info['mime']);

        $format = match ($mime) {
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => $format,
        };

        $imageName = now()->toDateString() . "-" . uniqid() . "." . $format;

        if ($mime === 'image/gif' || ($mime === 'image/webp' && $format === 'webp')) {
            $contents = @file_get_contents($sourcePath);

            if ($contents === false) {
                upload_validation_exception('The selected image could not be processed.');
            }

            $storeFile($dir . $imageName, $contents);
            $deleteOldFile();

            return $imageName;
        }

        $gdImage = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png'  => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default      => null
        };

        if (!$gdImage) {
            upload_validation_exception('The selected image format is not supported.');
        }

        if (function_exists('imageistruecolor') && function_exists('imagepalettetotruecolor') && !imageistruecolor($gdImage)) {
            imagepalettetotruecolor($gdImage);
        }

        if (in_array($mime, ['image/png', 'image/webp'])) {
            imagealphablending($gdImage, false);
            imagesavealpha($gdImage, true);
        }

        $maxSize = 2500;
        $w = imagesx($gdImage);
        $h = imagesy($gdImage);

        if ($w > $maxSize || $h > $maxSize) {
            $ratio = min($maxSize / $w, $maxSize / $h);
            $nw = (int)($w * $ratio);
            $nh = (int)($h * $ratio);

            $temp = imagecreatetruecolor($nw, $nh);
            imagealphablending($temp, false);
            imagesavealpha($temp, true);

            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
                imagefill($temp, 0, 0, $transparent);
            }

            imagecopyresampled($temp, $gdImage, 0, 0, 0, 0, $nw, $nh, $w, $h);

            imagedestroy($gdImage);
            $gdImage = $temp;
        }

        ob_start();
        $saved = match ($format) {
            'jpg', 'jpeg' => imagejpeg($gdImage, null, 85),
            'png'         => imagepng($gdImage, null, -1),
            'webp'        => imagewebp($gdImage, null, 78),
            default       => false,
        };
        $imageContents = $saved ? ob_get_contents() : false;
        ob_end_clean();

        imagedestroy($gdImage);

        if (!$saved || $imageContents === false) {
            upload_validation_exception(
                $disk === 's3'
                    ? 'Image upload failed. Please check S3 credentials and bucket configuration.'
                    : 'Image upload failed. Please try again.'
            );
        }

        $storeFile($dir . $imageName, $imageContents);
        $deleteOldFile();

        return $imageName;
    }

}

if (!function_exists('file_remover')) {
    function file_remover(string $dir, $image): bool
    {
        if (empty($image)) {
            return true;
        }
        if (is_array($image)) {
            foreach ($image as $img) {
                if (!file_remover($dir, $img)) {
                    return false;
                }
            }
            return true;
        }

        $path = $dir . $image;
        $localDisk = Storage::disk('public');

        try {
            $s3Disk = app('dynamic.s3');
            if ($s3Disk) {
                $s3Disk->delete($path);
            }
        } catch (\Throwable $e) {
            // S3 unreachable/misconfigured — fall through to local cleanup.
        }

        try {
            if (!$localDisk->delete($path) && $localDisk->exists($path)) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('divnum')) {
    function divnum($numerator, $denominator)
    {
        return $denominator == 0 ? 0 : ($numerator / $denominator);
    }
}

if (!function_exists('access_checker')) {
    function access_checker($module)
    {
        return true;
        if (auth()->user()->user_type == 'super-admin') {
            return true;
        } elseif (auth()->user()->roles->count() > 0) {
            $modules = auth()->user()->roles[0]->modules;
            if (in_array($module, $modules)) {
                return true;
            } else {
                return false;
            }
        }
    }
}

if (!function_exists('exc_handler')) {
    function exc_handler($data)
    {
        try {
            $response = $data;
        } catch (Exception $exception) {
            $response = translate('not_available');
        }
        return $response;
    }
}

if (!function_exists('get_add_money_bonus')) {
    function get_add_money_bonus($amount)
    {
        $bonuses = Bonus::where('is_active', 1)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('minimum_add_amount', '<=', $amount)
            ->get();

        $bonuses = $bonuses->where('minimum_add_amount', $bonuses->max('minimum_add_amount'));

        foreach ($bonuses as $key => $item) {
            $item->applied_bonus_amount = $item->bonus_amount_type == 'percent' ? ($amount * $item->bonus_amount) / 100 : $item->bonus_amount;

            if ($item->bonus_amount_type == 'percent' && $item->applied_bonus_amount > $item->maximum_bonus_amount) {
                $item->applied_bonus_amount = $item->maximum_bonus_amount;
            }
        }

        return $bonuses->max('applied_bonus_amount') ?? 0;
    }
}

if (!function_exists('get_distance')) {
    function get_distance(array $originCoordinates, array $destinationCoordinates, $unit = 'K'): float
    {
        $lat1 = (float)$originCoordinates[0];
        $lat2 = (float)$destinationCoordinates[0];
        $lon1 = (float)$originCoordinates[1];
        $lon2 = (float)$destinationCoordinates[1];

        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        } else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            $unit = strtoupper($unit);
            if ($unit == "K") {
                return ($miles * 1.609344);
            } else if ($unit == "N") {
                return ($miles * 0.8684);
            } else {
                return $miles;
            }
        }
    }
}

if (!function_exists('provider_warning_amount_calculate')) {
    function provider_warning_amount_calculate($payable, $receivable): bool|string
    {
        if ($payable > $receivable) {
            $limit_amount = (business_config('max_cash_in_hand_limit_provider', 'provider_config'))->live_values ?? 0;
            $amount = $payable - $receivable;

            $percentage_80 = 0.8 * $limit_amount;
            $percentage_100 = $limit_amount;

            $warningType = '';

            if ($amount >= $percentage_80) {
                $warningType = '80_percent';
            }

            if ($amount >= $percentage_100) {
                $warningType = '100_percent';
            }
            return $warningType;
        }
        return false;
    }
}

if (!function_exists('remove_invalid_charcaters')) {
    function remove_invalid_charcaters($str): array|string
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', $str);
    }
}

if (!function_exists('text_variable_data_format')) {
    function text_variable_data_format($title, $booking_id, ?string $type = null, array|object|string|null $data = null, ?string $bookingType = null): array|string
    {
        $replaceMap = [
            '{{providerName}}' => '',
            '{{scheduleTime}}' => '',
            '{{userName}}' => '',
            '{{zoneName}}' => '',
            '{{serviceManName}}' => '',
        ];

        if ($type == 'booking' || $type == 'offline-payment') {
            $booking = null;

            if ($bookingType == 'repeat') {
                $booking = BookingRepeat::find($booking_id) ?? Booking::find($booking_id);
            } else {
                $booking = Booking::find($booking_id);
            }

            if (!$booking) {
                return $title;
            }

            $replaceMap['{{providerName}}'] = $booking?->provider?->company_name ?? '';
            $replaceMap['{{bookingId}}'] = $booking->readable_id;
            $replaceMap['{{scheduleTime}}'] = $booking->service_schedule;

            if ($bookingType == 'repeat') {
                if ($booking->booking) {
                    $replaceMap['{{userName}}'] = $booking->booking->customer ? $booking->booking->customer->first_name . ' ' . $booking->booking->customer->last_name : '';
                    $replaceMap['{{zoneName}}'] = $booking->booking->zone?->name ?? '';
                } else {
                    $replaceMap['{{userName}}'] = $booking->customer?->first_name . ' ' . $booking->customer?->last_name;
                    $replaceMap['{{zoneName}}'] = $booking->zone?->name;
                }
            } else {
                $replaceMap['{{userName}}'] = $booking->customer?->first_name . ' ' . $booking->customer?->last_name;
                $replaceMap['{{zoneName}}'] = $booking->zone?->name;
            }

            $replaceMap['{{serviceManName}}'] = $booking?->serviceman?->user?->first_name . ' ' . $booking?->serviceman?->user?->last_name;

        } else {
            if (is_array($data) && !empty($data)) {
                $replaceMap['{{providerName}}'] = $data['provider_name'] ?? '';
                $replaceMap['{{scheduleTime}}'] = $data['schedule_time'] ?? '';
                $replaceMap['{{userName}}'] = $data['user_name'] ?? '';
                $replaceMap['{{zoneName}}'] = $data['zone_name'] ?? '';
                $replaceMap['{{serviceManName}}'] = $data['service_man_name'] ?? '';
            }
        }

        $formattedTitle = str_replace(array_keys($replaceMap), array_values($replaceMap), $title);

        return ($formattedTitle === $title) ? $title : $formattedTitle;
    }
}

if (!function_exists('config_settingss')) {
    function config_settingss($key, $settings_type)
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return null;
        }

        return (isset($config)) ? $config : null;
    }
}

if (!function_exists('onErrorImage')) {
    function onErrorImage($data, $src, $error_src ,$path)
    {
        if(isset($data) && strlen($data) >1 && Storage::disk('public')->exists($path.$data)){
            return $src;
        }
        return $error_src;
    }
}

if (!function_exists('getSuperAdminId')) {
    function getSuperAdminId()
    {
        return User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
    }
}

if (!function_exists('getServiceFee')) {
    function getServiceFee()
    {
        $additionalCharge = 0;
        if ((business_config('booking_additional_charge', 'booking_setup'))?->live_values) {
            $additionalCharge = (business_config('additional_charge_fee_amount', 'booking_setup'))?->live_values;
        }
        return $additionalCharge;
    }
}

if (!function_exists('formatSubscriptionPackage')) {
    function formatSubscriptionPackage($subscriptionPackage, $features)
    {
        $featureList = [];
        foreach ($features as $feature) {
            $featureExists = $subscriptionPackage->subscriptionPackageFeature->contains(function ($value) use ($feature) {
                return $value->feature == $feature['key'];
            });
            if ($featureExists) {
                $featureList[] = $feature['value'];
            }
        }

        $bookingLimit = 'Unlimited Bookings';
        $categoryLimit = 'Unlimited Service Sub Categories';

        foreach ($subscriptionPackage->subscriptionPackageLimit as $limit) {
            if ($limit->key === 'booking' && $limit->is_limited) {
                $bookingLimit = $limit->limit_count . ' Booking Limit';
            }
            if ($limit->key === 'category' && $limit->is_limited) {
                $categoryLimit = $limit->limit_count . ' Sub Category Limit';
            }
        }

        $featureList[] = $bookingLimit;
        $featureList[] = $categoryLimit;

        $subscriptionPackage['feature_list'] = $featureList;

        unset($subscriptionPackage->subscriptionPackageFeature);
        unset($subscriptionPackage->subscriptionPackageLimit);

        return $subscriptionPackage;
    }
}

if (!function_exists('subscriptionFeatureList')) {
    function subscriptionFeatureList($subscription, $features): array
    {
        $categoryCount = 0;
        $bookingCount = 0;

        $featureList = [];
        $limitFeature = [
            'booking' => 'Unlimited',
            'category' => 'Unlimited'
        ];
        $limitLeft = [
            'booking' => 0,
            'category' => 0
        ];

        foreach ($features as $feature) {
            $featureExists = $subscription->subscriptionPackageFeature->contains(function ($value) use ($feature) {
                return $value->feature == $feature['key'];
            });
            if ($featureExists) {
                $featureList[] = $feature['key'];
            }
        }

        $featureList[] = 'booking';
        $featureList[] = 'category';

        foreach ($subscription->subscriptionPackageLimit as $limit) {
            if ($limit->key === 'booking' && $limit->is_limited) {
                $limitFeature['booking'] = $limit->limit_count;
                $limitLeft['booking'] = $limit->limit_count - $bookingCount;
            }
            if ($limit->key === 'category' && $limit->is_limited) {
                $limitFeature['category'] = $limit->limit_count;
                $limitLeft['category'] = $limit->limit_count - $categoryCount;
            }
        }

        $subscription->feature_list = $featureList;
        $subscription->feature_limit = $limitFeature;

        unset($subscription->subscriptionPackageFeature);
        unset($subscription->subscriptionPackageLimit);

        return $subscription->toArray();
    }
}



if (!function_exists('packageSubscriber')) {
    function packageSubscriber($packageSubscriber, $features)
    {
        $providerId = $packageSubscriber->provider_id;
        $packageSubscriber['total_amount'] = $packageSubscriber?->logs->where('provider_id', $providerId)->sum('package_price');
        $packageSubscriber['number_of_uses'] = $packageSubscriber?->logs->where('provider_id', $providerId)->count();
        $packageSubscriber['description'] = $packageSubscriber?->package->description;

        $featureList = [];
        foreach ($features as $feature) {
            $featureExists = $packageSubscriber->feature->contains(function ($value) use ($feature) {
                return $value->feature == $feature['key'];
            });
            if ($featureExists) {
                $featureList[] = $feature['value'];
            }
        }
        $bookingLimit = 'Unlimited Bookings';
        $categoryLimit = 'Unlimited Service Categories';

        foreach ($packageSubscriber->limits as $limit) {
            if ($limit->key === 'booking' && $limit->is_limited) {
                $bookingLimit = $limit->limit_count . ' Booking Limit';
            }
            if ($limit->key === 'category' && $limit->is_limited) {
                $categoryLimit = $limit->limit_count . ' Category Limit';
            }
        }

        $featureList[] = $bookingLimit;
        $featureList[] = $categoryLimit;

        $packageSubscriber['feature_list'] = $featureList;

        unset($packageSubscriber->feature);
        unset($packageSubscriber->limits);
        unset($packageSubscriber->logs);
        unset($packageSubscriber->package);

        return $packageSubscriber;
    }
}

if (!function_exists('apiPackageSubscriber')) {
    function apiPackageSubscriber($packageSubscriber, $features)
    {
        $categoryCount = 0;
        $bookingCount = 0;

        $startDate = $packageSubscriber?->package_start_date;
        $endDate = $packageSubscriber?->package_end_date;
        $providerId = $packageSubscriber?->provider_id;
        $providerUserId = $packageSubscriber?->provider->user_id;

        $packageSubscriber['total_amount'] = $packageSubscriber?->logs->sum('package_price');
        $packageSubscriber['number_of_uses'] = $packageSubscriber?->logs->count();
        $packageSubscriber['description'] = $packageSubscriber?->package->description;
        $packageSubscriber['is_paid'] = $packageSubscriber?->payment?->where('id', $packageSubscriber->payment_id)->value('is_paid');

        if ($startDate && $endDate) {
            $bookingCount = SubscriptionSubscriberBooking::where('provider_id', $providerId)
                ->where('package_subscriber_log_id', $packageSubscriber?->package_subscriber_log_id)
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $startDate = Carbon::parse($startDate)->startOfDay();
                    $endDate = Carbon::parse($endDate)->endOfDay();
                    return $query->whereBetween('updated_at', [$startDate, $endDate]);
                })
                ->count();

            $categoryCount = 0; // Historical marketplace entitlement, no direct-service dependency.
        }

        $featureList = [];
        $limitFeature = [
            'booking' => 'Unlimited',
            'category' => 'Unlimited'
        ];
        $limitLeft = [
            'booking' => 0,
            'category' => 0
        ];

        foreach ($features as $feature) {
            $featureExists = $packageSubscriber->feature->contains(function ($value) use ($feature) {
                return $value->feature == $feature['key'];
            });
            if ($featureExists) {
                $featureList[] = $feature['key'];
            }
        }

        $featureList[] = 'booking';
        $featureList[] = 'category';

        foreach ($packageSubscriber->limits->where('provider_id', $providerId) as $limit) {
            if ($limit->key === 'booking' && $limit->is_limited) {
                $limitFeature['booking'] = $limit->limit_count;
                $limitLeft['booking'] = $limit->limit_count - $bookingCount;
            }
            if ($limit->key === 'category' && $limit->is_limited) {
                $limitFeature['category'] = $limit->limit_count;
                $limitLeft['category'] = $limit->limit_count - $categoryCount;
            }
        }

        $packageSubscriber['feature_list'] = $featureList;
        $packageSubscriber['feature_limit'] = $limitFeature;
        $packageSubscriber['feature_limit_left'] = $limitLeft;

        unset($packageSubscriber->feature);
        unset($packageSubscriber->limits);
        unset($packageSubscriber->logs);
        unset($packageSubscriber->package);
        unset($packageSubscriber->payment);

        return $packageSubscriber;
    }
}

if (!function_exists('saveSingleImageDataToStorage')) {
    function saveSingleImageDataToStorage($model, $modelColumn, $storageType)
    {
        \Modules\BusinessSettingsModule\Entities\Storage::updateOrCreate(
            [
                'model' => get_class($model),
                'model_id' => $model->id,
                'model_column' => $modelColumn
            ],
            [
                'storage_type' => $storageType,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
        return true;
    }
}

if (!function_exists('saveBusinessImageDataToStorage')) {
    function saveBusinessImageDataToStorage($model, $modelColumn, $storageType)
    {
        \Modules\BusinessSettingsModule\Entities\Storage::updateOrCreate(
            [
                'model' => get_class($model),
                'model_column' => $modelColumn
            ],
            [
                'model_id' => $model->id,
                'storage_type' => $storageType,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
        return true;
    }
}

if (!function_exists('getSingleImageFullPath')) {
    function getSingleImageFullPath($imagePath, array|object|null $s3Storage = null, ?string $defaultPath = null, ?bool $page = null)
    {
        try {
            if ($s3Storage && $s3Storage->storage_type === 's3') {
                $disk = app('dynamic.s3');
                return $disk?->url($imagePath);
            }

            $publicDisk = Storage::disk('public');
            if ($publicDisk->exists($imagePath)) {
                return asset('storage/app/public/' . $imagePath);
            }
        } catch (\Exception $exception) {
            \Log::error('Image path error: ' . $exception->getMessage());
        }

        if (request()->is('api/*')) {
            return $page ? $defaultPath : null;
        }

        return $defaultPath;
    }
}

if (!function_exists('getIdentityImageFullPath')) {
    function getIdentityImageFullPath($identityImages, $path, ?string $defaultPath = null)
    {
        $identityImageFullPath = [];

        foreach ($identityImages as $identityImage) {
            $identityImage = is_array($identityImage) ? $identityImage : ['image' => $identityImage, 'storage' => 'public'];
            $imagePath = $path . $identityImage['image'];
            $fullPath = $defaultPath;
            try {
                if ($identityImage['storage'] == 's3') {
                    $disk = app('dynamic.s3');
                    $fullPath = $disk?->url($imagePath);
                }
            }catch(\Exception $exception){
                //
            }

            if ($identityImage['storage'] == 'public' && Storage::disk('public')->exists($imagePath)) {
                $fullPath = asset('storage/app/public/' . $imagePath);
            }

            if (request()->is('api/*') && $fullPath == $defaultPath) {
                continue;
            }else{
                $identityImageFullPath[] = $fullPath;
            }
        }

        return $identityImageFullPath;
    }
}

if (!function_exists('getBusinessSettingsImageFullPath')) {
    function getBusinessSettingsImageFullPath($key, $settingType, $path, ?string $defaultPath = null)
    {
        $image = \Modules\BusinessSettingsModule\Entities\BusinessSettings::with('storage')->where(['key_name' => $key, 'settings_type' => $settingType])->first();
        if (!$image) {
            if (request()->is('api/*')) {
                return null;
            }
            return asset($defaultPath);
        }

        $imagePath = $path . $image->live_values;
        $s3Storage = $image->storage;

        try {
            if ($s3Storage && $s3Storage->storage_type == 's3') {
                $disk = app('dynamic.s3');
                return $disk?->url($imagePath);
            }
        }catch(\Exception $exception){
            //
        }

        if (Storage::disk('public')->exists($imagePath)) {
            return asset('storage/app/public/' . $imagePath);
        } else {
            if (request()->is('api/*')) {
                return null;
            }
            return asset($defaultPath);
        }
    }
}
if (!function_exists('getDataSettingsImageFullPath')) {
    function getDataSettingsImageFullPath($key, $settingType, $path, ?string $defaultPath = null)
    {
        $image = \Modules\BusinessSettingsModule\Entities\DataSetting::with('storage')->where(['key' => $key, 'type' => $settingType])->first();
        if (!$image) {
            if (request()->is('api/*')) {
                return null;
            }
            return asset($defaultPath);
        }

        $imagePath = $path . $image->value;
        $s3Storage = $image->storage;

        try {
            if ($s3Storage && $s3Storage->storage_type == 's3') {
                $disk = app('dynamic.s3');
                return $disk?->url($imagePath);
            }
        }catch(\Exception $exception){
            //
        }

        if (Storage::disk('public')->exists($imagePath)) {
            return asset('storage/app/public/' . $imagePath);
        } else {
            if (request()->is('api/*')) {
                return null;
            }
            return asset($defaultPath);
        }
    }
}

if (!function_exists('getPaymentGatewayImageFullPath')) {
    function getPaymentGatewayImageFullPath($key, $settingsType, ?string $defaultPath = null)
    {
        $addonSettings = \Modules\PaymentModule\Entities\Setting::where('key_name', $key)->where('settings_type', $settingsType)->first();
        if (!$addonSettings) {
            if (request()->is('api/*')) {
                return null;
            }
            return asset($defaultPath);
        }
        $additionalData = $addonSettings['additional_data'] != null ? json_decode($addonSettings['additional_data']) : null;

        if(!$additionalData)
        {
            return asset($defaultPath);
        }

        if ($additionalData){
            if (!$additionalData->gateway_image){
                return asset($defaultPath);
            }
        }

        $path = 'payment_modules/gateway_image/';
        $imagePath = $path . ($additionalData ? $additionalData->gateway_image : '');

        $additionalData = [
            'gateway_title' => $additionalData->gateway_title?? null,
            'gateway_image' => $additionalData->gateway_image?? null,
            'storage' => $additionalData->storage ?? 'public'
        ];

        try {
            if ($additionalData['storage'] == 's3') {
                $disk = app('dynamic.s3');
                return $disk?->url($imagePath);
            }
        }catch(\Exception $exception){
            //
        }

        if ($additionalData['storage'] == 'public' && Storage::disk('public')->exists($imagePath)) {
            return asset('storage/app/public/' . $imagePath);
        }

        if (request()->is('api/*')) {
            return null;
        }

        return asset($defaultPath);
    }
}


if (!function_exists('nextBookingEligibility')) {
    function nextBookingEligibility($providerId): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        $now = \Carbon\Carbon::now()->subDay();
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();
        $packageSubscriberLogId = $packageSubscriber?->package_subscriber_log_id;
        $providerUserId = $packageSubscriber?->provider?->user_id;
        $isPaid = $packageSubscriber?->payment?->where('id', $packageSubscriber?->payment_id)->value('is_paid');

        if ($packageSubscriber && $packageSubscriber->payment_id != null) {
            if ($isPaid){
                if ($packageSubscriber->is_canceled){
                    return false;
                }
                foreach ($packageSubscriber->limits->where('provider_id', $providerId) as $limit) {
                    if ($limit->key === 'booking') {
                        if ($limit->is_limited) {
                            $limitLeft = $limit->limit_count;

                            $startDate = $packageSubscriber->package_start_date;
                            $endDate = $packageSubscriber->package_end_date;

                            if ($startDate && $endDate) {
                                if($now > $endDate){
                                    return false;
                                }

//                                $bookingCount = SubscriptionSubscriberBooking::where('provider_id', $providerId)
//                                    ->whereBetween('updated_at', [$startDate, $endDate])
//                                    ->count();

                                $bookingCount = SubscriptionSubscriberBooking::where('provider_id', $providerId)->where('package_subscriber_log_id',$packageSubscriberLogId)
                                    ->whereBetween(DB::raw('DATE(updated_at)'), [date('Y-m-d', strtotime($startDate)), date('Y-m-d', strtotime($endDate))])
                                    ->count();

                                $leftBookingCount = $limitLeft - $bookingCount;
                                if ($leftBookingCount > 0) {
                                    return true;
                                }
                            }
                        } else {
                            return true;
                        }
                    }
                }
            }
            return false;
        }
        return true;
    }
}

if (!function_exists('scheduleBookingEligibility')) {
    function scheduleBookingEligibility($providerId): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        $now = \Carbon\Carbon::now();
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();

        if ($packageSubscriber) {
            if ($packageSubscriber->payment_id) {

                if ($packageSubscriber->is_canceled){
                    return false;
                }

                $startDate = $packageSubscriber->package_start_date;
                $endDate = $packageSubscriber->package_end_date;

                if ($startDate && $endDate) {
                    if ($now > $endDate) {
                        return false;
                    }

                    $featureExists = $packageSubscriber->feature->contains(function ($value) {
                        return $value->feature === 'schedule_service';
                    });

                    if ($featureExists) {
                        return true;
                    }
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('chatEligibility')) {
    function chatEligibility($providerId): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        $now = \Carbon\Carbon::now();
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();

        if ($packageSubscriber) {
            if ($packageSubscriber->payment_id) {

                if ($packageSubscriber->is_canceled){
                    return false;
                }

                $startDate = $packageSubscriber->package_start_date;
                $endDate = $packageSubscriber->package_end_date;

                if ($startDate && $endDate) {
                    if ($now > $endDate) {
                        return false;
                    }

                    $featureExists = $packageSubscriber->feature->contains(function ($value) {
                        return $value->feature === 'chat';
                    });

                    if ($featureExists) {
                        return true;
                    }
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('advertisementsEligibility')) {
    function advertisementsEligibility($providerId): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        $now = \Carbon\Carbon::now();
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();

        if ($packageSubscriber) {
            if ($packageSubscriber->payment_id) {

                if ($packageSubscriber->is_canceled){
                    return false;
                }

                $startDate = $packageSubscriber->package_start_date;
                $endDate = $packageSubscriber->package_end_date;

                if ($startDate && $endDate) {
                    if ($now > $endDate) {
                        return false;
                    }

                    $featureExists = $packageSubscriber->feature->contains(function ($value) {
                        return $value->feature === 'advertisement';
                    });

                    if ($featureExists) {
                        return true;
                    }
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('mobileAppCheck')) {
    function mobileAppCheck($user, $module): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        if ($user) {
            $provider = Provider::where('user_id', $user->id)->first();
            if ($provider) {

                $providerId = $provider->id;
                $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->with('feature')->first();
                if ($packageSubscriber) {
                    $featureKeys = $packageSubscriber->feature->pluck('feature')->toArray();
                    if (in_array($module, $featureKeys) ) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}

if (!function_exists('sendDeviceNotificationPermission')) {
    function sendDeviceNotificationPermission($providerId): bool
    {
        if (nirab_branch_mode()) {
            return true;
        }

        $providerSubscription = PackageSubscriber::where('provider_id', $providerId)->first();
        $endDate = optional($providerSubscription)->package_end_date;
        $canceled = optional($providerSubscription)->is_canceled;
        $packageEndDate = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        $currentDate = Carbon::now()->subDay();
        $isPackageEnded = $packageEndDate ? $currentDate->diffInDays($packageEndDate, false) : null;
        $scheduleBookingEligibility = nextBookingEligibility($providerId);

        if ($providerSubscription) {
            if ($isPackageEnded > 0 && !$canceled && $scheduleBookingEligibility) {
                return true;
            }else{
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('isNotificationActive')) {
   function isNotificationActive(?string $providerId, string $key, string $type, string $userType): ?bool
   {
        $notificationSetup = NotificationSetup::where('key', $key)->where('user_type', $userType)->get();

        foreach ($notificationSetup as $setup) {
            $adminSettings = json_decode($setup->value);
            $providerSettings = null;

            if ($providerId) {
                $providerSettings = $setup->providerNotifications()->where('provider_id', $providerId)->first();
                $providerSettings = $providerSettings ? json_decode($providerSettings->value) : null;
            }

            $settingValue = $providerSettings->$type ?? $adminSettings->$type;

            if (is_null($settingValue)) {
                return false;
            }

            return (bool) $settingValue;
        }

        return false;
    }
}

if (!function_exists('checkCurrency')) {
   function checkCurrency($data, ?string $type = null)
   {
       $digitalPayment = business_config('digital_payment', 'service_setup')->live_values;
       $publishedStatus = 0;

       try {
           $full_data = include('Modules/Gateways/Addon/info.php');
           $publishedStatus = $full_data['is_published'] == 1 ? 1 : 0;
       } catch (\Exception $exception) {
       }

       if($digitalPayment){
           if ($type === null && $data === 'SAR' && app(\Modules\PaymentModule\Services\GatewaySettings::class)->publicMethods() !== []) {
               return true;
           }
           if($type === null) {
               if ($publishedStatus == 1) {
                   $methods = DB::table('addon_settings')->where('is_active', 1)->where('settings_type', 'payment_config')->get();
                   $env = env('APP_ENV') == 'live' ? 'live' : 'test';
                   $credentials = $env . '_values';

               } else {
                   $methods = DB::table('addon_settings')->where('is_active', 1)->whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago'])->get();
                   $env = env('APP_ENV') == 'live' ? 'live' : 'test';
                   $credentials = $env . '_values';

               }

               $getData = [];
               foreach ($methods as $method) {
                   $credentialsData = json_decode($method->$credentials);
                   $additional_data = json_decode($method->additional_data);
                   if ($credentialsData?->status == 1) {
                       $getData[] = [
                           'gateway' => $method->key_name,
                           'gateway_title' => $additional_data?->gateway_title,
                           'gateway_image' => $additional_data?->gateway_image
                       ];
                   }
               }

               if (is_array($getData)) {
                   foreach ($getData as $payment_gateway) {
                       $supportedCurrencies = getPaymentGatewaySupportedCurrencies($payment_gateway['gateway']);
                       if (!empty($supportedCurrencies) && array_key_exists($data, $supportedCurrencies)) {
                           return true;
                       }
                   }
               }
           }
           elseif($type == 'payment_gateway'){
               $currency = business_config('currency_code', 'business_information')->live_values;
               if(!empty(getPaymentGatewaySupportedCurrencies($data)) && array_key_exists($currency, getPaymentGatewaySupportedCurrencies($data))){
                   return  $data;
               }
           }
       }

       return false;
    }
}

if (!function_exists('getPaymentGatewaySupportedCurrencies')) {
   function getPaymentGatewaySupportedCurrencies(?string $key = null): array
   {
       if (in_array($key, ['tap', 'tabby', 'tamara'], true)) {
           return ['SAR' => 'Saudi Riyal'];
       }
       $paymentGateway = [
           "amazon_pay" => [
               "USD" => "United States Dollar",
               "GBP" => "Pound Sterling",
               "EUR" => "Euro",
               "JPY" => "Japanese Yen",
               "AUD" => "Australian Dollar",
               "NZD" => "New Zealand Dollar",
               "CAD" => "Canadian Dollar"
           ],
           "bkash" => [
               "BDT" => "Bangladeshi Taka"
           ],
           "cashfree" => [
               "INR" => "Indian Rupee"
           ],
           "ccavenue" => [
               "INR" => "Indian Rupee"
           ],
           "ccavenue" => [
               "INR" => "Indian Rupee"
           ],
           "esewa" => [
               "NPR" => "Nepalese Rupee"
           ],
           "fatoorah" => [
               "KWD" => "Kuwaiti Dinar",
               "SAR" => "Saudi Riyal"
           ],
           "flutterwave" => [
               "NGN" => "Nigerian Naira",
               "GHS" => "Ghanaian Cedi",
               "KES" => "Kenyan Shilling",
               "ZAR" => "South African Rand",
               "USD" => "United States Dollar",
               "EUR" => "Euro",
               "GBP" => "Pound Sterling",
               "XAF" => "Central African CFA Franc"
           ],
           "foloosi" => [
               "AED" => "United Arab Emirates Dirham"
           ],
           "hubtel" => [
               "GHS" => "Ghanaian Cedi"
           ],
           "hyper_pay" => [
               "AED" => "United Arab Emirates Dirham",
               "SAR" => "Saudi Riyal",
               "EGP" => "Egyptian Pound",
               "BHD" => "Bahraini Dinar",
               "KWD" => "Kuwaiti Dinar",
               "OMR" => "Omani Rial",
               "QAR" => "Qatari Riyal",
               "USD" => "United States Dollar"
           ],
           "instamojo" => [
               "INR" => "Indian Rupee"
           ],
           "iyzi_pay" => [
               "TRY" => "Turkish Lira"
           ],
           "liqpay" => [
               "UAH" => "Ukrainian Hryvnia",
               "USD" => "United States Dollar",
               "EUR" => "Euro"
           ],
           "maxicash" => [
               "PHP" => "Philippine Peso"
           ],
           "mercadopago" => [
               "ARS" => "Argentine Peso",
               "BRL" => "Brazilian Real",
               "CLP" => "Chilean Peso",
               "COP" => "Colombian Peso",
               "MXN" => "Mexican Peso",
               "PEN" => "Peruvian Sol",
               "UYU" => "Uruguayan Peso",
               "USD" => "United States Dollar"
           ],
           "momo" => [
               "VND" => "Vietnamese Dong"
           ],
           "moncash" => [
               "HTG" => "Haitian Gourde"
           ],
           "payfast" => [
               "ZAR" => "South African Rand"
           ],
           "paymob_accept" => [
               "EGP" => "Egyptian Pound"
           ],
           "paypal" => [
               "AUD" => "Australian Dollar",
               "BRL" => "Brazilian Real",
               "CAD" => "Canadian Dollar",
               "CZK" => "Czech Koruna",
               "DKK" => "Danish Krone",
               "EUR" => "Euro",
               "HKD" => "Hong Kong Dollar",
               "HUF" => "Hungarian Forint",
               "INR" => "Indian Rupee",
               "ILS" => "Israeli New Shekel",
               "JPY" => "Japanese Yen",
               "MYR" => "Malaysian Ringgit",
               "MXN" => "Mexican Peso",
               "TWD" => "New Taiwan Dollar",
               "NZD" => "New Zealand Dollar",
               "NOK" => "Norwegian Krone",
               "PHP" => "Philippine Peso",
               "PLN" => "Polish Zloty",
               "GBP" => "Pound Sterling",
               "RUB" => "Russian Ruble",
               "SGD" => "Singapore Dollar",
               "SEK" => "Swedish Krona",
               "CHF" => "Swiss Franc",
               "THB" => "Thai Baht",
               "TRY" => "Turkish Lira",
               "USD" => "United States Dollar"
           ],
           "paystack" => [
               "NGN" => "Nigerian Naira",
               "KES" => "Kenyan Shilling"
           ],
           "paytabs" => [
               "AED" => "United Arab Emirates Dirham",
               "SAR" => "Saudi Riyal",
               "BHD" => "Bahraini Dinar",
               "KWD" => "Kuwaiti Dinar",
               "OMR" => "Omani Rial",
               "QAR" => "Qatari Riyal",
               "EGP" => "Egyptian Pound",
               "USD" => "United States Dollar"
           ],
           "paytm" => [
               "INR" => "Indian Rupee"
           ],
           "phonepe" => [
               "INR" => "Indian Rupee"
           ],
           "pvit" => [
               "NGN" => "Nigerian Naira"
           ],
           "razor_pay" => [
               "INR" => "Indian Rupee"
           ],
           "senang_pay" => [
               "MYR" => "Malaysian Ringgit"
           ],
           "sixcash" => [
               "BDT" => "Bangladeshi Taka"
           ],
           "ssl_commerz" => [
               "BDT" => "Bangladeshi Taka",
               "USD" => "United States Dollar",
           ],
           "stripe" => [
               "USD" => "United States Dollar",
               "AUD" => "Australian Dollar",
               "CAD" => "Canadian Dollar",
               "EUR" => "Euro",
               "GBP" => "Pound Sterling",
               "JPY" => "Japanese Yen",
               "NZD" => "New Zealand Dollar",
               "CHF" => "Swiss Franc",
               "DKK" => "Danish Krone",
               "NOK" => "Norwegian Krone",
               "SEK" => "Swedish Krona",
               "SGD" => "Singapore Dollar",
               "HKD" => "Hong Kong Dollar",
               "MXN" => "Mexican Peso",
           ],
           "swish" => [
               "SEK" => "Swedish Krona"
           ],
           "tap" => [
               "AED" => "United Arab Emirates Dirham",
               "SAR" => "Saudi Riyal",
               "BHD" => "Bahraini Dinar",
               "KWD" => "Kuwaiti Dinar",
               "OMR" => "Omani Rial",
               "QAR" => "Qatari Riyal"
           ],
           "thawani" => [
               "OMR" => "Omani Rial"
           ],
           "viva_wallet" => [
               "EUR" => "Euro"
           ],
           "worldpay" => [
               "GBP" => "Pound Sterling",
               "USD" => "United States Dollar",
               "EUR" => "Euro",
               "JPY" => "Japanese Yen"
           ],
           "xendit" => [
               "IDR" => "Indonesian Rupiah",
               "PHP" => "Philippine Peso",
               "VND" => "Vietnamese Dong",
               "THB" => "Thai Baht",
               "MYR" => "Malaysian Ringgit",
               "SGD" => "Singapore Dollar"
           ],
       ];

       if ($key) {
           return array_key_exists($key,$paymentGateway) ?  $paymentGateway[$key] : [];
       }
       return $paymentGateway;
    }
}

if (!function_exists('getProviderSettings')) {
    function getProviderSettings($providerId, $key, $type)
    {
        $setting = \Modules\ProviderManagement\Entities\ProviderSetting::where([
            'key_name'      => $key,
            'provider_id'   => $providerId,
            'settings_type' => $type,
        ])->first();

        if ($setting) {
            $decoded = json_decode($setting->live_values, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return [];

    }
}

if (!function_exists('checkActiveSMSGatewayCount')) {
    function checkActiveSMSGatewayCount()
    {
        $dataValues = Setting::where('settings_type', 'sms_config')->get();
        $count = 0;
        foreach ($dataValues as $gateway) {
            $status = $gateway?->live_values['status'] ?? 0;
            if ($status == 1) {
                $count = 1;
            }
        }

        $firebaseOtpConfig = business_config('firebase_otp_verification', 'third_party');
        $firebaseOtpStatus = (int)$firebaseOtpConfig?->live_values['status'] ?? null;

        if ($firebaseOtpStatus == 1) {
            $count = 1;
        }

         return (((login_setup('phone_verification'))->value ?? 0 ) == 1 && $count == 1 ? 1 : 0);

    }
}

if (!function_exists('readableUploadMaxFileSize')) {
    function readableUploadMaxFileSize($fileType)
    {
        $uploadMaxFileSize = uploadMaxFileSize($fileType);

        return convertToReadableSize($uploadMaxFileSize);

    }
}

if (!function_exists('uploadMaxFileSizeInKB')) {
    function uploadMaxFileSizeInKB($fileType = 'image')
    {
        $uploadMaxFileSize = uploadMaxFileSize($fileType);
        $uploadMaxFileSize = $uploadMaxFileSize / 1024;

        return $uploadMaxFileSize;

    }
}

if (!function_exists('convertToReadableSize')) {
    function convertToReadableSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824) . 'GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024) . 'KB';
        } else {
            return $bytes . 'B';
        }
    }
}

if (!function_exists('uploadMaxFileSize')) {
    function uploadMaxFileSize($fileType) {

        $phpLimit = convertToBytes(ini_get('upload_max_filesize'));

        if (env('APP_ENV') === 'demo') {
            $appLimit = convertToBytes( '1M');
        }else{
            $appLimit = convertToBytes($fileType === 'image' ? '20M' : '50M');
        }

        return min($phpLimit, $appLimit);
    }
}

if (!function_exists('convertToBytes')) {
    function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;

        switch ($last) {
            case 'g':
                $num *= 1024;
            case 'm':
                $num *= 1024;
            case 'k':
                $num *= 1024;
        }

        return $num;
    }
}


if (!function_exists('getSetupGuideSteps')) {
    function getSetupGuideSteps($panel = 'admin_panel', $user = null, $platform = 'web'): array
    {
        $steps = [];

        if ($panel === 'admin_panel') {
            $steps = [
                'business_information' => [
                    'key'   => 'business_information',
                    'title' => translate('setup_business_info'),
                    'route' => route('admin.business-settings.get-business-information', ['web_page' => 'business_setup']),
                    'order' => 1,
                ],
                'business_plan' => [
                    'key'   => 'business_plan',
                    'title' => translate('setup_business_plan'),
                    'route' => route('admin.business-settings.get-business-information', ['web_page' => 'business_plan']),
                    'order' => 2,
                ],
                'google_map_configuration' => [
                    'key'   => 'google_map_configuration',
                    'title' => translate('setup_google_map_configuration'),
                    'route' => route('admin.configuration.third-party', 'map-api'),
                    'order' => 3,
                ],
                'email_configuration' => [
                    'key'   => 'email_configuration',
                    'title' => translate('setup_email_configuration'),
                    'route' => route('admin.configuration.third-party', 'email-config'),
                    'order' => 4,
                ],
                'notification_configuration' => [
                    'key'   => 'notification_configuration',
                    'title' => translate('setup_notification_configuration'),
                    'route' => route('admin.configuration.third-party', 'firebase-configuration'),
                    'order' => 5,
                ],
                'login_option' => [
                    'key'   => 'login_option',
                    'title' => translate('explore_login_option'),
                    'route' => route('admin.business-settings.login.setup'),
                    'order' => 6,
                ],
                'digital_payment' => [
                    'key'   => 'digital_payment',
                    'title' => translate('explore_digital_payment'),
                    'route' => route('admin.configuration.third-party', [
                        'webPage' => 'payment_config',
                        'type' => 'digital_payment'
                    ]),
                    'order' => 7,
                ],
            ];

            $stepSections = [
                'business_information' => 'business',
                'business_plan' => 'business',
                'google_map_configuration' => 'configuration',
                'email_configuration' => 'configuration',
                'notification_configuration' => 'firebase',
                'login_option' => 'login_setup',
                'digital_payment' => 'payment_method',
            ];

            $allowedSections = $user?->module_access
                ?->pluck('section_name')
                ->unique()
                ->toArray() ?? [];

            if ($user && $user->user_type !== 'super-admin' && !empty($allowedSections)) {
                $steps = array_filter($steps, function ($step, $key) use ($stepSections, $allowedSections) {
                    $section = $stepSections[$key] ?? null;
                    return $section && in_array($section, $allowedSections, true);
                }, ARRAY_FILTER_USE_BOTH);
            }

            uasort($steps, fn($a, $b) => $a['order'] <=> $b['order']);

            // apply checked status
            $options = $user ? getSetupGuidelineTutorialOptions(user: $user, platform:  $platform, userType:  'admin') : [];
            foreach ($steps as $key => &$step) {
                $step['checked'] = !empty($options[$key]) && (int)$options[$key] === 1;
            }

            // calculate completion percentage
            $totalSteps = count($steps);
            $completedSteps = collect($steps)->where('checked', true)->count();
            $percentage = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;
            $rotation = $percentage * 3.6; // 0–360

            $isGuidelineDataExist = SettingsTutorials::where([
                'user_id'  => auth()->id(),
                'platform' => 'web',
            ])->first();

            $isFirstTimeGuide = is_null($isGuidelineDataExist);

            return [
                'steps' => $steps,
                'percentage' => $percentage,
                'rotation' => $rotation,
                'isFirstTimeGuide' => $isFirstTimeGuide,
            ];
        }

        if ($panel === 'provider_panel') {
            $steps = [
                'business_information' => [
                    'key'   => 'business_information',
                    'title' => translate('setup_business_info'),
                    'route' => route('provider.business-settings.get-business-information', ['web_page' => 'businessinfos']),
                    'order' => 1,
                ],
                'business_plan' => [
                    'key'   => 'business_plan',
                    'title' => translate('explore_business_plan'),
                    'route' => route('provider.subscription-package.details'),
                    'order' => 2,
                ],
                'subscribe_services' => [
                    'key'   => 'subscribe_services',
                    'title' => translate('subscribe_a_services'),
                    'route' => route('provider.service.available'),
                    'order' => 3,
                ],
                'payment_information' => [
                    'key'   => 'payment_information',
                    'title' => translate('payment_information'),
                    'route' => route('provider.settings.payment-information.index'),
                    'order' => 4,
                ],
                'service_availability' => [
                    'key'   => 'service_availability',
                    'title' => translate('setup_service_availability'),
                    'route' => route('provider.business-settings.get-business-information', ['web_page' => 'service_availability']),
                    'order' => 5,
                ],
            ];

            // apply checked status
            $options = $user ? getSetupGuidelineTutorialOptions(user: $user, platform: $platform, userType: 'provider' ) : [];
            foreach ($steps as $key => &$step) {
                $step['checked'] = !empty($options[$key]) && (int)$options[$key] === 1;
            }

            // calculate completion percentage
            $totalSteps = count($steps);
            $completedSteps = collect($steps)->where('checked', true)->count();
            $percentage = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;
            $rotation = $percentage * 3.6; // 0–360

            $isGuidelineDataExist = SettingsTutorials::where([
                'user_id'  => auth()->id(),
                'platform' => 'web',
            ])->first();

            $isFirstTimeGuide = is_null($isGuidelineDataExist);

            return [
                'steps' => $steps,
                'percentage' => $percentage,
                'rotation' => $rotation,
                'isFirstTimeGuide' => $isFirstTimeGuide,
            ];
        }

        return [
            'steps' => $steps,
            'percentage' => 0,
            'rotation' => 0,
            'isFirstTimeGuide' => false,
        ];
    }
}

if (!function_exists('getSetupGuidelineTutorialOptions')) {
    function getSetupGuidelineTutorialOptions($user, string $platform = 'web', $userType = 'admin'): array
    {
        if ($userType == 'admin'){
            $defaults = [
                'business_information'       => 0,
                'business_plan'              => 0,
                'google_map_configuration'   => 0,
                'email_configuration'        => 0,
                'notification_configuration' => 0,
                'login_option'               => 0,
                'digital_payment'            => 0,
            ];
        }else{
            $defaults = [
                'business_information' => 0,
                'business_plan'        => 0,
                'subscribe_services'   => 0,
                'payment_information'  => 0,
                'service_availability' => 0,
            ];
        }


        if (!$user) {
            return $defaults;
        }

        $tutorial = $user->getTutorialByPlatform($platform);

        if ($tutorial && is_array($tutorial->options)) {
            return array_replace($defaults, $tutorial->options);
        }

        return $defaults;
    }
}


if (!function_exists('updateSetupGuidelineTutorialsOptions')) {
    function updateSetupGuidelineTutorialsOptions($userId, $option, $platform = 'web'): void
    {
        $tutorial = SettingsTutorials::firstOrNew([
            'user_id'  => $userId,
            'platform' => $platform,
        ]);

        $options = is_array($tutorial->options) ? $tutorial->options : [];

        if (isset($options[$option]) && $options[$option] == 1) {
            return;
        }

        $options[$option] = 1;

        $tutorial->options = $options;
        $tutorial->save();
    }
}

if (!function_exists('setupGuidelineRouteModify')) {
    function setupGuidelineRouteModify(string $url): string
    {
        $parsed = parse_url($url);

        // Existing query params
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        // Add / override from_guide
        $query['from_guide'] = 1;

        // Build base URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';

        return $scheme
            . $host
            . $port
            . $path
            . '?' . http_build_query($query);
    }
}

if (!function_exists('mergeCountryCodeToRequest')) {
    function mergeCountryCodeToRequest(object|array $request): void
    {
        $countryCode = business_config('country_code', 'business_information')->live_values ?? 'US';

        if (!$request->has('country_code')) {
            $request->merge(['country_code' => $countryCode]);
        }
    }
}
if (!function_exists('discountTooltip')) {
    function discountTooltip($admin, $provider): string
    {
        return translate('Discount shared — Admin: :adminDiscount, Provider: :providerDiscount', ['adminDiscount' => with_currency_symbol($admin), 'providerDiscount' => with_currency_symbol($provider)]);
    }
}

if (!function_exists('applied_filter_count')) {
    function applied_filter_count(array $keys, array $ignored = []): int
    {
        $count = 0;
        foreach ($keys as $key) {
            $value = request($key);
            if ($value === null || $value === '') {
                continue;
            }
            if (array_key_exists($key, $ignored) && $value == $ignored[$key]) {
                continue;
            }
            $count++;
        }
        return $count;
    }
}

if (!function_exists('coupon_types')) {
    function coupon_types(): array
    {
        $types = COUPON_TYPES;

        $proMemberEnabled = (bool)business_config('is_pro_member_feature_enabled', 'customer_config')?->live_values;
        $couponBenefitActive = (int)(business_config('pro_coupon', 'customer_subscription')?->live_values['status'] ?? 0) === 1;

        if ($proMemberEnabled && $couponBenefitActive) {
            $types['pro_member'] = 'Pro Member';
        }

        return $types;
    }
}

if (!function_exists('nirab_booking_filter_statuses')) {
    function nirab_booking_filter_statuses(string $status): array
    {
        if ($status === 'accepted' && nirab_branch_mode()) return ['accepted', 'assigned'];
        return $status === 'ongoing' && nirab_branch_mode()
            ? ['on_the_way', 'arrived', 'ongoing', 'work_finished']
            : [$status];
    }
}
