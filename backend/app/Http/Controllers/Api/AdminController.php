<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\InstantWinWindow;
use App\Models\Prize;
use App\Models\RegisteredInvoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'totals' => [
                'clients' => User::query()->where('role', 'client')->count(),
                'cashiers' => User::query()->where('role', 'cashier')->count(),
                'invoices' => RegisteredInvoice::query()->count(),
                'generated_coupons' => Coupon::query()->where('status', 'generated')->count(),
                'delivered_coupons' => Coupon::query()->where('status', 'delivered')->count(),
            ],
            'prize_inventory' => Prize::query()->get(),
        ]);
    }

    public function campaigns(): JsonResponse
    {
        return response()->json([
            'data' => Campaign::query()->latest('id')->get(),
        ]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $campaign = Campaign::query()->create($this->validateCampaign($request));

        return response()->json([
            'message' => 'Campaña creada.',
            'campaign' => $campaign,
        ], 201);
    }

    public function updateCampaign(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign->update($this->validateCampaign($request, $campaign->id));

        return response()->json([
            'message' => 'Campaña actualizada.',
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function prizes(): JsonResponse
    {
        return response()->json([
            'data' => Prize::query()->orderBy('name')->get(),
        ]);
    }

    public function storePrize(Request $request): JsonResponse
    {
        $prize = Prize::query()->create($this->validatePrize($request));

        return response()->json([
            'message' => 'Premio creado.',
            'prize' => $prize,
        ], 201);
    }

    public function updatePrize(Request $request, Prize $prize): JsonResponse
    {
        $prize->update($this->validatePrize($request, $prize->id));

        return response()->json([
            'message' => 'Premio actualizado.',
            'prize' => $prize->fresh(),
        ]);
    }

    public function windows(): JsonResponse
    {
        return response()->json([
            'data' => InstantWinWindow::query()->with('prize')->latest('id')->get(),
        ]);
    }

    public function storeWindow(Request $request): JsonResponse
    {
        $window = InstantWinWindow::query()->create($this->validateWindow($request));

        return response()->json([
            'message' => 'Ventana creada.',
            'window' => $window->fresh('prize'),
        ], 201);
    }

    public function updateWindow(Request $request, InstantWinWindow $window): JsonResponse
    {
        $window->update($this->validateWindow($request));

        return response()->json([
            'message' => 'Ventana actualizada.',
            'window' => $window->fresh('prize'),
        ]);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json([
            'data' => AuditLog::query()->latest('id')->limit(100)->get(),
        ]);
    }

    private function validateCampaign(Request $request, ?int $campaignId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,paused,closed'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'invoice_min_amount_for_shot' => ['required', 'numeric', 'min:0'],
            'amount_per_point' => ['required', 'numeric', 'min:0.01'],
            'points_per_block' => ['required', 'integer', 'min:1'],
            'daily_max_points' => ['required', 'integer', 'min:0'],
            'daily_max_invoices' => ['required', 'integer', 'min:1'],
            'coupon_ttl_hours' => ['required', 'integer', 'min:1'],
            'games_enabled' => ['required', 'boolean'],
            'major_prizes_enabled' => ['required', 'boolean'],
            'invoice_scan_enabled' => ['required', 'boolean'],
            'redemption_enabled' => ['required', 'boolean'],
        ]);
    }

    private function validatePrize(Request $request, ?int $prizeId = null): array
    {
        return $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'in:major,consolation'],
            'redemption_type' => ['required', 'in:direct,instant_win'],
            'points_cost' => ['nullable', 'integer', 'min:0'],
            'shots_cost' => ['nullable', 'integer', 'min:0'],
            'total_stock' => ['required', 'integer', 'min:0'],
            'reserved_stock' => ['nullable', 'integer', 'min:0'],
            'delivered_stock' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateWindow(Request $request): array
    {
        return $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'prize_id' => ['required', 'integer', 'exists:prizes,id'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'is_consumed' => ['nullable', 'boolean'],
            'consumed_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'consumed_at' => ['nullable', 'date'],
        ]);
    }
}
