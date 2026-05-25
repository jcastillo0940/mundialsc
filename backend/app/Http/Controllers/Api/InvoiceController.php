<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegisteredInvoice;
use App\Support\Audit;
use App\Support\ContestInvoiceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ContestInvoiceRegistrationService $registrationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = RegisteredInvoice::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');

        return response()->json([
            'data' => $query->paginate(15),
            'totals' => [
                'approved_points' => (int) RegisteredInvoice::query()
                    ->where('user_id', $request->user()->id)
                    ->where('validation_status', 'approved')
                    ->sum('points_awarded'),
                'approved_invoices' => (int) RegisteredInvoice::query()
                    ->where('user_id', $request->user()->id)
                    ->where('validation_status', 'approved')
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_raw_text' => ['required', 'string'],
            'purchase_amount' => ['required', 'numeric', 'min:0.01'],
            'branch_id' => ['nullable', 'integer'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'issued_at' => ['nullable', 'date'],
        ]);

        $result = $this->registrationService->register($request->user(), $data);

        Audit::log('invoice.registered', 'registered_invoice', $result['invoice']->id, $request->user(), $request, [
            'cufe' => $result['invoice']->cufe,
            'validation_status' => $result['invoice']->validation_status,
        ]);

        return response()->json([
            'message' => $result['message'],
            'invoice' => $result['invoice'],
        ], 201);
    }
}
