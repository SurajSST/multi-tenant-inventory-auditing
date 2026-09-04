<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One setting, for one school. The petty cash ceiling differs between them. */
class AppSetting extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'app_settings';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'updated_at' => 'datetime',
        ];
    }
}
