<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Support\WebhookSignature;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Lottery;

class MockShopController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function __invoke(Request $request, Shop $shop): JsonResponse
    {
        abort_unless(
            WebhookSignature::verify($request->getContent(), $shop->secret, $request->header(WebhookSignature::HEADER)),
            401,
            'Invalid signature.',
        );

        if (Lottery::odds(config()->float('services.mock_shop.failure_rate'))->choose()) {
            abort(503, 'Mock shop is temporarily unavailable.');
        }

        Log::info('Mock shop received legal text.', [
            'shop_id' => $shop->id,
            'idempotency_key' => $request->header('Idempotency-Key'),
        ]);

        return response()->json(['received' => true]);
    }
}
