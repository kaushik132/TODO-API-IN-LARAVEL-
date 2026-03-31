<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class QuickTransactionController extends Controller
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

    // STEP 1: GET CONTACTS LIST
    // GET /api/quick/contacts?person_type=creditor
    // GET /api/quick/contacts?person_type=debtor
    public function contactsList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'person_type' => 'required|in:debtor,creditor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId     = $request->user()->id;
        $personType = $request->person_type;

        $contacts = Transaction::where('user_id', $userId)
            ->where('person_type', $personType)
            ->select('name', 'phone', 'person_type',
                \DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                \DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received'),
                \DB::raw('MAX(date) as last_date')
            )
            ->groupBy('name', 'phone', 'person_type')
            ->get()
            ->map(function ($c) use ($personType) {
                if ($personType === 'creditor') {
                    $pending = $c->total_received - $c->total_given;
                } else {
                    $pending = $c->total_given - $c->total_received;
                }

                if ($pending <= 0) return null;

                return [
                    'name'          => $c->name,
                    'phone'         => $c->phone,
                    'person_type'   => $c->person_type,
                    'total_pending' => $pending,
                    'last_date'     => $c->last_date,
                ];
            })
            ->filter()->values()
            ->sortByDesc('last_date')
            ->values();

        return response()->json([
            'success' => true,
            'message' => ucfirst($personType) . 's list fetched',
            'person_type' => $personType,
            'data'    => $contacts,
        ], 200);
    }

    // STEP 2: GET CONTACT PROFILE
    // GET /api/quick/contacts/{phone}/profile
    public function contactProfile(Request $request, $phone)
    {
        $userId = $request->user()->id;

        $transactions = Transaction::where('user_id', $userId)
            ->where('phone', $phone)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        $name          = $transactions->first()->name;
        $personType    = $transactions->first()->person_type;
        $totalGiven    = $transactions->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived = $transactions->where('transaction_type', 'received')->sum('pending_amount');

        if ($personType === 'creditor') {
            $pending = $totalReceived - $totalGiven;
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Pay"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_pay' : 'settled';
        } else {
            $pending = $totalGiven - $totalReceived;
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Receive"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_receive' : 'settled';
        }

        $recent = $transactions->take(10)->map(function ($txn) {
            return [
                'id'               => $txn->id,
                'person_type'      => $txn->person_type,
                'transaction_type' => $txn->transaction_type,
                'direction'        => $txn->transaction_type === 'given' ? 'given' : 'received',
                'arrow'            => $txn->transaction_type === 'given' ? '↑' : '↓',
                'amount'           => $txn->pending_amount,
                'date'             => Carbon::parse($txn->date)->format('d M Y'),
                'time'             => Carbon::parse($txn->created_at)->format('h:iA'),
                'note'             => $txn->note,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Contact profile fetched',
            'contact' => [
                'name'           => $name,
                'phone'          => $phone,
                'person_type'    => $personType,
                'total_given'    => $totalGiven,
                'total_received' => $totalReceived,
                'pending_amount' => abs($pending),
                'balance_label'  => $balanceLabel,
                'balance_type'   => $balanceType,
            ],
            'recent_transactions' => $recent,
        ], 200);
    }

    // STEP 3: QUICK ADD TRANSACTION
    // POST /api/quick/transaction
    public function quickAdd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'phone'            => 'required|string|max:20',
            'person_type'      => 'required|in:debtor,creditor',
            'transaction_type' => 'required|in:given,received',
            'amount'           => 'required|numeric|min:1',
            'note'             => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Determine payment_type based on person and transaction type
        if ($request->person_type === 'creditor') {
            // Creditor: If given -> reducing debt (to_pay), if received -> increasing debt
            $paymentType = $request->transaction_type === 'given' ? 'to_pay' : 'to_pay';
        } else {
            // Debtor: If given -> increasing receivable, if received -> reducing receivable (to_receive)
            $paymentType = $request->transaction_type === 'given' ? 'to_receive' : 'to_receive';
        }

        $transaction = Transaction::create([
            'user_id'          => $request->user()->id,
            'name'             => $request->name,
            'phone'            => $request->phone,
            'person_type'      => $request->person_type,
            'transaction_type' => $request->transaction_type,
            'total_amount'     => $request->amount,
            'pending_amount'   => $request->amount,
            'payment_type'     => $paymentType,
            'is_recurring'     => false,
            'date'             => Carbon::today()->toDateString(),
            'note'             => $request->note,
        ]);

        // Updated contact profile
        $allTxns       = Transaction::where('user_id', $request->user()->id)
                                    ->where('phone', $request->phone)
                                    ->get();
        $totalGiven    = $allTxns->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived = $allTxns->where('transaction_type', 'received')->sum('pending_amount');

        if ($request->person_type === 'creditor') {
            $pending = $totalReceived - $totalGiven;
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Pay"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_pay' : 'settled';
        } else {
            $pending = $totalGiven - $totalReceived;
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Receive"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_receive' : 'settled';
        }

        return response()->json([
            'success'     => true,
            'message'     => $request->transaction_type === 'given'
                ? '↑ Given transaction added successfully'
                : '↓ Received transaction added successfully',
            'transaction' => [
                'id'               => $transaction->id,
                'person_type'      => $transaction->person_type,
                'transaction_type' => $transaction->transaction_type,
                'direction'        => $transaction->transaction_type === 'given' ? 'given' : 'received',
                'arrow'            => $transaction->transaction_type === 'given' ? '↑' : '↓',
                'amount'           => $transaction->pending_amount,
                'date'             => Carbon::parse($transaction->date)->format('d M Y'),
                'time'             => Carbon::parse($transaction->created_at)->format('h:iA'),
            ],
            'contact' => [
                'name'           => $request->name,
                'phone'          => $request->phone,
                'person_type'    => $request->person_type,
                'total_given'    => $totalGiven,
                'total_received' => $totalReceived,
                'pending_amount' => abs($pending),
                'balance_label'  => $balanceLabel,
                'balance_type'   => $balanceType,
            ],
        ], 201);
    }
}
