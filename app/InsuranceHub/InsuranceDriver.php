<?php

namespace App\InsuranceHub;

use App\InsuranceProviderCredential;
use RuntimeException;

class InsuranceDriver
{
    /** @var InterfaceInsurance[] */
    private static $instances = [];

    /**
     * Entry point duy nhất — chỉ cần providerCode.
     *
     * Convention: BAO_MINH → App\InsuranceHub\Providers\BaoMinh\Insurance
     *             BAO_VIET → App\InsuranceHub\Providers\BaoViet\Insurance
     *             PTI      → App\InsuranceHub\Providers\Pti\Insurance
     *
     * Thêm provider mới: tạo folder + class đúng tên, InsuranceDriver không cần sửa.
     */
    public static function for(string $providerCode): InterfaceInsurance
    {
        $key = $providerCode;

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $credential = InsuranceProviderCredential::where('ProviderCode', $key)
            ->where('IsActive', 1)
            ->first();

        if (!$credential) {
            throw new RuntimeException("Không tìm thấy credential cho provider [{$providerCode}].");
        }

        $class    = self::resolveProviderClass($key);
        $provider = new $class($credential);

        self::$instances[$key] = $provider;

        return $provider;
    }

    /** Xóa cache (dùng trong tests). */
    public static function flush(): void
    {
        self::$instances = [];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function resolveProviderClass(string $providerCode): string
    {
        $pascal = implode('', array_map(
            function ($word) { return $word; },
            explode('_', $providerCode)
        ));

        $class = "App\\InsuranceHub\\Providers\\{$pascal}\\Insurance";
        if (!class_exists($class)) {
            throw new RuntimeException(
                "Provider không tồn tại cho [{$providerCode}]. Tạo class: {$class}"
            );
        }

        return $class;
    }
}
