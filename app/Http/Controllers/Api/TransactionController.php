<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    // =============================================
    // LOGIC:
    // person_type:      creditor=Lendhar, debtor=Dendhar
    // transaction_type: given=↑ diya,    received=↓ liya
    // =============================================

    // 1. ADD TRANSACTION
    // POST /api/transactions
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'person_type'        => 'required|in:debtor,creditor',
            'transaction_type'   => 'required|in:given,received',
            'total_amount'       => 'required|numeric|min:0',
            'pending_amount'     => 'required|numeric|min:0',
            'payment_type'       => 'required|in:to_pay,to_receive',
            'is_recurring'       => 'required|boolean',
            'installment_amount' => 'required_if:is_recurring,true|nullable|numeric|min:0',
            'installment_date'   => 'required_if:is_recurring,true|nullable|date',
            'date'               => 'required|date',
            'note'               => 'nullable|string|max:500',
            'screenshot_name'    => 'nullable|image|mimes:jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Screenshot upload
        $screenshotPath = null;
        if ($request->hasFile('screenshot_name')) {
            $screenshotPath = $request->file('screenshot_name')
                                      ->store('transactions/screenshots', 'public');
        }

        $transaction = Transaction::create([
            'user_id'            => $request->user()->id,
            'name'               => $request->name,
            'phone'              => $request->phone,
            'person_type'        => $request->person_type,
            'transaction_type'   => $request->transaction_type,
            'total_amount'       => $request->total_amount,
            'pending_amount'     => $request->pending_amount,
            'payment_type'       => $request->payment_type,
            'is_recurring'       => $request->is_recurring,
            'installment_amount' => $request->is_recurring ? $request->installment_amount : null,
            'installment_date'   => $request->is_recurring ? $request->installment_date : null,
            'date'               => $request->date,
            'note'               => $request->note,
            'screenshot_name'    => $screenshotPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction added successfully',
            'data'    => $this->formatTransaction($transaction),
        ], 201);
    }

    // 2. GET ALL TRANSACTIONS
    // GET /api/transactions
    public function index(Request $request)
    {
        $query = Transaction::where('user_id', $request->user()->id)
                            ->orderBy('date', 'desc');

        if ($request->has('person_type')) {
            $query->where('person_type', $request->person_type);
        }
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }
        if ($request->has('is_recurring')) {
            $query->where('is_recurring', $request->is_recurring);
        }

        $transactions  = $query->get();
        $totalGiven    = $transactions->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived = $transactions->where('transaction_type', 'received')->sum('pending_amount');

        return response()->json([
            'success' => true,
            'message' => 'Transactions fetched successfully',
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_amount'       => $transactions->sum('total_amount'),
                'total_pending'      => $transactions->sum('pending_amount'),
                'total_given'        => $totalGiven,
                'total_received'     => $totalReceived,
            ],
            'data' => $transactions->map(fn($t) => $this->formatTransaction($t)),
        ], 200);
    }

    // 3. GET SINGLE TRANSACTION
    // GET /api/transactions/{id}
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
                                  ->where('user_id', $request->user()->id)
                                  ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction fetched successfully',
            'data'    => $this->formatTransaction($transaction),
        ], 200);
    }

    // 4. UPDATE TRANSACTION
    // POST /api/transactions/{id}  (POST for file upload)
    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
                                  ->where('user_id', $request->user()->id)
                                  ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'               => 'nullable|string|max:255',
            'phone'              => 'nullable|string|max:20',
            'person_type'        => 'sometimes|in:debtor,creditor',
            'transaction_type'   => 'sometimes|in:given,received',
            'total_amount'       => 'sometimes|numeric|min:0',
            'pending_amount'     => 'sometimes|numeric|min:0',
            'payment_type'       => 'sometimes|in:to_pay,to_receive',
            'is_recurring'       => 'sometimes|boolean',
            'installment_amount' => 'nullable|numeric|min:0',
            'installment_date'   => 'nullable|date',
            'date'               => 'sometimes|date',
            'note'               => 'nullable|string|max:500',
            'screenshot_name'    => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $isRecurring = $request->has('is_recurring')
            ? $request->is_recurring
            : $transaction->is_recurring;

        // Screenshot update
        $screenshotPath = $transaction->screenshot_name;
        if ($request->hasFile('screenshot_name')) {
            // Purana delete karo
            if ($screenshotPath && Storage::disk('public')->exists($screenshotPath)) {
                Storage::disk('public')->delete($screenshotPath);
            }
            $screenshotPath = $request->file('screenshot_name')
                                      ->store('transactions/screenshots', 'public');
        }

        $transaction->update([
            'name'               => $request->name             ?? $transaction->name,
            'phone'              => $request->phone            ?? $transaction->phone,
            'person_type'        => $request->person_type      ?? $transaction->person_type,
            'transaction_type'   => $request->transaction_type ?? $transaction->transaction_type,
            'total_amount'       => $request->total_amount     ?? $transaction->total_amount,
            'pending_amount'     => $request->pending_amount   ?? $transaction->pending_amount,
            'payment_type'       => $request->payment_type     ?? $transaction->payment_type,
            'is_recurring'       => $isRecurring,
            'installment_amount' => $isRecurring
                ? ($request->installment_amount ?? $transaction->installment_amount)
                : null,
            'installment_date'   => $isRecurring
                ? ($request->installment_date ?? $transaction->installment_date)
                : null,
            'date'               => $request->date ?? $transaction->date,
            'note'               => $request->has('note') ? $request->note : $transaction->note,
            'screenshot_name'    => $screenshotPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully',
            'data'    => $this->formatTransaction($transaction->fresh()),
        ], 200);
    }

    // 5. DELETE TRANSACTION
    // DELETE /api/transactions/{id}
    public function destroy(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
                                  ->where('user_id', $request->user()->id)
                                  ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        // Screenshot delete karo
        if ($transaction->screenshot_name &&
            Storage::disk('public')->exists($transaction->screenshot_name)) {
            Storage::disk('public')->delete($transaction->screenshot_name);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully',
        ], 200);
    }

    // =============================================
    // PRIVATE: Format transaction with screenshot_url
    // =============================================
    private function formatTransaction(Transaction $t): array
    {
        return [
            'id'                 => $t->id,
            'user_id'            => $t->user_id,
            'name'               => $t->name,
            'phone'              => $t->phone,
            'person_type'        => $t->person_type,
            'transaction_type'   => $t->transaction_type,
            'arrow'              => $t->transaction_type === 'given' ? '↑' : '↓',
            'total_amount'       => $t->total_amount,
            'pending_amount'     => $t->pending_amount,
            'payment_type'       => $t->payment_type,
            'is_recurring'       => $t->is_recurring,
            'installment_amount' => $t->installment_amount,
            'installment_date'   => $t->installment_date,
            'date'               => $t->date,
            'note'               => $t->note,
            'screenshot_url'     => $t->screenshot_name
                                    ? asset('storage/' . $t->screenshot_name)
                                    : null,
            'created_at'         => $t->created_at,
            'updated_at'         => $t->updated_at,
        ];
    }
}
