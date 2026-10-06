<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PushSubscriptionController
{
    public function store(Request $request, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        PushSubscription::updateOrCreate([
            'user_id' => $request->user()->id,
            'tenant_id' => $tenant->idOrFail(),
            'endpoint' => $data['endpoint'],
            'endpoint_hash' => hash('sha256', $data['endpoint']),
        ], [
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => Crypt::encryptString($data['keys']['auth']),
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('tenant_id', $tenant->idOrFail())
            ->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }
}
