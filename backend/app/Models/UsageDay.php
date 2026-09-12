<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person's presence on one client platform on one day (doc 08's switch-trigger instrumentation).
 *
 * @property int $id
 * @property string $user_id
 * @property Carbon $day
 * @property string $platform
 * @property Carbon $first_seen_at
 */
final class UsageDay extends Model
{
    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_WEB_MOBILE = 'web_mobile';

    public const PLATFORM_WEB_DESKTOP = 'web_desktop';

    public const PLATFORMS = [self::PLATFORM_ANDROID, self::PLATFORM_IOS, self::PLATFORM_WEB_MOBILE, self::PLATFORM_WEB_DESKTOP];

    public $timestamps = false;

    protected $fillable = ['user_id', 'day', 'platform', 'first_seen_at'];

    protected function casts(): array
    {
        return ['day' => 'date', 'first_seen_at' => 'datetime'];
    }
}
