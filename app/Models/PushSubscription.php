<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding'];
}
