<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    // =============================================
    // ✅ CORRECT LOGIC:
    //
    // PERSON TYPE (Contact Classification):
    // - creditor = Lendhar (jinse liya hai, ab dena hai)
    // - debtor   = Dendhar (jinhe diya hai, ab lena hai)
    //
    // TRANSACTION TYPE (Money Movement):
    // - given    = ↑ Paisa diya (to anyone)
    // - received = ↓ Paisa liya (from anyone)
    // =============================================

    // =============================================
    // 1. ADD TRANSACTION (Create)
    // POST /api/transactions
    // =============================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // --- Person Details ---
            'name'                      => 'required|string|max:255',
            'phone'                     => 'required|string|max:20',
            'person_type'               => 'required|in:debtor,creditor',

            // --- Transaction Type ---
            'transaction_type'          => 'required|in:given,received',

            // --- Amounts ---
            'total_amount'              => 'required|numeric|min:0',
            'pending_amount'            => 'required|numeric|min:0',

            // --- Payment Direction ---
            'payment_type'              => 'required|in:to_pay,to_receive',

            // --- Recurring Payment ---
            'is_recurring'              => 'required|boolean',
            'installment_amount'        => 'required_if:is_recurring,true|nullable|numeric|min:0',
            'installment_date'          => 'required_if:is_recurring,true|nullable|date',

            // --- Note & Date ---
            'date'                      => 'required|date',
            'note'                      => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction added successfully',
            'data'    => $transaction,
        ], 201);
    }

    // =============================================
    // 2. GET ALL TRANSACTIONS (List)
    // GET /api/transactions
    // =============================================
    public function index(Request $request)
    {
        $query = Transaction::where('user_id', $request->user()->id)
                            ->orderBy('date', 'desc');

        // Optional Filters
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

        $transactions = $query->get();

        // Summary
        $totalAmount   = $transactions->sum('total_amount');
        $pendingAmount = $transactions->sum('pending_amount');
        $totalGiven    = $transactions->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived = $transactions->where('transaction_type', 'received')->sum('pending_amount');

        return response()->json([
            'success' => true,
            'message' => 'Transactions fetched successfully',
            'summary' => [
                'total_transactions' => $transactions->count(),
                'total_amount'       => $totalAmount,
                'total_pending'      => $pendingAmount,
                'total_given'        => $totalGiven,
                'total_received'     => $totalReceived,
            ],
            'data' => $transactions,
        ], 200);
    }

    // =============================================
    // 3. GET SINGLE TRANSACTION (Show)
    // GET /api/transactions/{id}
    // =============================================
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
            'data'    => $transaction,
        ], 200);
    }

    // =============================================
    // 4. UPDATE TRANSACTION (Edit)
    // PUT /api/transactions/{id}
    // =============================================
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Agar is_recurring false ho gaya toh installment fields clear karo
        $isRecurring = $request->has('is_recurring') ? $request->is_recurring : $transaction->is_recurring;

        $transaction->update([
            'name'               => $request->name               ?? $transaction->name,
            'phone'              => $request->phone              ?? $transaction->phone,
            'person_type'        => $request->person_type        ?? $transaction->person_type,
            'transaction_type'   => $request->transaction_type   ?? $transaction->transaction_type,
            'total_amount'       => $request->total_amount       ?? $transaction->total_amount,
            'pending_amount'     => $request->pending_amount     ?? $transaction->pending_amount,
            'payment_type'       => $request->payment_type       ?? $transaction->payment_type,
            'is_recurring'       => $isRecurring,
            'installment_amount' => $isRecurring ? ($request->installment_amount ?? $transaction->installment_amount) : null,
            'installment_date'   => $isRecurring ? ($request->installment_date   ?? $transaction->installment_date)   : null,
            'date'               => $request->date               ?? $transaction->date,
            'note'               => $request->has('note') ? $request->note : $transaction->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully',
            'data'    => $transaction->fresh(),
        ], 200);
    }

    // =============================================
    // 5. DELETE TRANSACTION
    // DELETE /api/transactions/{id}
    // =============================================
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

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully',
        ], 200);
    }
}
