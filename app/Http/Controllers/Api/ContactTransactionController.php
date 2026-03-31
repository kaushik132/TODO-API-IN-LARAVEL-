<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class ContactTransactionController extends Controller
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

    // 1. CONTACTS LIST
    // GET /api/contacts
    public function contacts(Request $request)
    {
        $userId = $request->user()->id;

        $contacts = Transaction::where('user_id', $userId)
            ->select(
                'name',
                'phone',
                'person_type',
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->groupBy('name', 'phone', 'person_type')
            ->orderBy('last_transaction', 'desc')
            ->get()
            ->map(function ($contact) {
                // Calculate pending based on person type
                if ($contact->person_type === 'creditor') {
                    // Creditor: Received - Given = Pending to Pay
                    $pending = $contact->total_received - $contact->total_given;
                    $balanceType = $pending > 0 ? 'you_will_pay' : 'settled';
                } else {
                    // Debtor: Given - Received = Pending to Receive
                    $pending = $contact->total_given - $contact->total_received;
                    $balanceType = $pending > 0 ? 'you_will_receive' : 'settled';
                }

                return [
                    'name'             => $contact->name,
                    'phone'            => $contact->phone,
                    'person_type'      => $contact->person_type,
                    'total_given'      => $contact->total_given,    // ↑ Given
                    'total_received'   => $contact->total_received, // ↓ Received
                    'pending_amount'   => abs($pending),
                    'balance_type'     => $balanceType,
                    'last_transaction' => $contact->last_transaction,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Contacts fetched successfully',
            'data'    => $contacts,
        ], 200);
    }

    // 2. CONTACT WISE TRANSACTIONS (Chat Style)
    // GET /api/contacts/{phone}/transactions
    public function contactTransactions(Request $request, $phone)
    {
        $userId = $request->user()->id;

        $transactions = Transaction::where('user_id', $userId)
            ->where('phone', $phone)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($txn) {
                return [
                    'id'               => $txn->id,
                    'name'             => $txn->name,
                    'phone'            => $txn->phone,
                    'person_type'      => $txn->person_type,
                    'transaction_type' => $txn->transaction_type,
                    'direction'        => $txn->transaction_type === 'given' ? 'given' : 'received',
                    'arrow'            => $txn->transaction_type === 'given' ? '↑' : '↓',
                    'total_amount'     => $txn->total_amount,
                    'pending_amount'   => $txn->pending_amount,
                    'payment_type'     => $txn->payment_type,
                    'is_recurring'     => $txn->is_recurring,
                    'date'             => $txn->date,
                    'note'             => $txn->note,
                    'time'             => Carbon::parse($txn->created_at)->format('h:iA'),
                    'created_at'       => $txn->created_at,
                ];
            });

        $personType    = $transactions->first()['person_type'] ?? 'creditor';
        $totalGiven    = $transactions->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived = $transactions->where('transaction_type', 'received')->sum('pending_amount');

        // Calculate pending based on person type
        if ($personType === 'creditor') {
            $pending = $totalReceived - $totalGiven; // Dena hai
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Pay"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_pay' : 'settled';
        } else {
            $pending = $totalGiven - $totalReceived; // Lena hai
            $balanceLabel = $pending > 0
                ? "₹" . number_format(abs($pending)) . " You Will Receive"
                : "Settled";
            $balanceType = $pending > 0 ? 'you_will_receive' : 'settled';
        }

        $contactName = $transactions->first()['name'] ?? '';

        return response()->json([
            'success' => true,
            'message' => 'Contact transactions fetched successfully',
            'contact' => [
                'name'           => $contactName,
                'phone'          => $phone,
                'person_type'    => $personType,
                'total_given'    => $totalGiven,
                'total_received' => $totalReceived,
                'pending_amount' => abs($pending),
                'balance_label'  => $balanceLabel,
                'balance_type'   => $balanceType,
            ],
            'data' => $transactions,
        ], 200);
    }

    // 3. BALANCE SUMMARY
    // GET /api/contacts/balance-summary
    public function balanceSummary(Request $request)
    {
        $userId = $request->user()->id;

        $contacts = Transaction::where('user_id', $userId)
            ->select(
                'name',
                'phone',
                'person_type',
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received')
            )
            ->groupBy('name', 'phone', 'person_type')
            ->get();

        $youWillReceive = 0;
        $youWillPay     = 0;

        foreach ($contacts as $contact) {
            if ($contact->person_type === 'creditor') {
                // Creditor: Received - Given = Pay
                $pending = $contact->total_received - $contact->total_given;
                if ($pending > 0) {
                    $youWillPay += $pending;
                }
            } else {
                // Debtor: Given - Received = Receive
                $pending = $contact->total_given - $contact->total_received;
                if ($pending > 0) {
                    $youWillReceive += $pending;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Balance summary fetched successfully',
            'data'    => [
                'you_will_receive' => $youWillReceive,
                'you_will_pay'     => $youWillPay,
                'net_balance'      => $youWillReceive - $youWillPay,
            ],
        ], 200);
    }

    // 4. STATEMENT (Month wise)
    // GET /api/contacts/{phone}/statement
    public function statement(Request $request, $phone)
    {
        $userId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year'  => 'nullable|integer|min:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $month = $request->month ?? Carbon::now()->month;
        $year  = $request->year  ?? Carbon::now()->year;

        $query = Transaction::where('user_id', $userId)->where('phone', $phone);

        if ($request->filter === 'this_month') {
            $query->whereMonth('date', $month)->whereYear('date', $year);
        } elseif ($request->filter === 'last_month') {
            $lastMonth = Carbon::now()->subMonth();
            $query->whereMonth('date', $lastMonth->month)->whereYear('date', $lastMonth->year);
        } elseif ($request->filter === 'this_year') {
            $query->whereYear('date', $year);
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->get();

        $personType     = $transactions->first()->person_type ?? 'creditor';
        $totalGiven     = $transactions->where('transaction_type', 'given')->sum('pending_amount');
        $totalReceived  = $transactions->where('transaction_type', 'received')->sum('pending_amount');

        if ($personType === 'creditor') {
            $pending = $totalReceived - $totalGiven;
            $youWillPay = $pending > 0 ? $pending : 0;
            $youWillReceive = 0;
        } else {
            $pending = $totalGiven - $totalReceived;
            $youWillReceive = $pending > 0 ? $pending : 0;
            $youWillPay = 0;
        }

        $grouped = $transactions->groupBy(function ($txn) {
            return Carbon::parse($txn->date)->format('d M Y');
        })->map(function ($group, $date) {
            return [
                'date'         => $date,
                'transactions' => $group->map(function ($txn) {
                    return [
                        'id'               => $txn->id,
                        'transaction_type' => $txn->transaction_type,
                        'direction'        => $txn->transaction_type === 'given' ? 'given' : 'received',
                        'arrow'            => $txn->transaction_type === 'given' ? '↑' : '↓',
                        'total_amount'     => $txn->total_amount,
                        'pending_amount'   => $txn->pending_amount,
                        'note'             => $txn->note ?? 'Balance',
                        'time'             => Carbon::parse($txn->created_at)->format('h:iA'),
                    ];
                })->values(),
            ];
        })->values();

        $contactName = $transactions->first()->name ?? '';

        return response()->json([
            'success' => true,
            'message' => 'Statement fetched successfully',
            'contact' => [
                'name'        => $contactName,
                'phone'       => $phone,
                'person_type' => $personType,
            ],
            'filter'  => $request->filter ?? 'all',
            'balance_summary' => [
                'you_will_receive' => $youWillReceive,
                'you_will_pay'     => $youWillPay,
            ],
            'data' => $grouped,
        ], 200);
    }

    // 5. PAYABLES LIST
    // Jin creditors ko paisa dena hai
    // GET /api/contacts/payables
    public function payables(Request $request)
    {
        $userId = $request->user()->id;
        $filter = $request->filter ?? 'all';

        $contacts = Transaction::where('user_id', $userId)
            ->select(
                'name', 'phone', 'person_type',
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received'),
                DB::raw('MIN(installment_date) as upcoming_due'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->where('person_type', 'creditor')  // Only creditors (lendhar)
            ->groupBy('name', 'phone', 'person_type')
            ->get()
            ->map(function ($contact) use ($filter) {
                $pending = $contact->total_received - $contact->total_given;

                if ($pending <= 0) return null; // Skip if nothing to pay

                $dueDate = $contact->upcoming_due;
                if ($filter === 'due_payment' && !$dueDate) return null;
                if ($filter === 'upcoming_due' && (!$dueDate || Carbon::parse($dueDate)->isPast())) return null;

                return [
                    'name'          => $contact->name,
                    'phone'         => $contact->phone,
                    'total_pending' => $pending,
                    'upcoming_due'  => $dueDate ? Carbon::parse($dueDate)->format('d M Y') : null,
                    'due_status'    => $dueDate
                        ? (Carbon::parse($dueDate)->isPast() ? 'overdue' : 'upcoming')
                        : 'no_due',
                ];
            })
            ->filter()->values();

        return response()->json([
            'success' => true,
            'message' => 'Payables fetched successfully',
            'summary' => [
                'total_payable' => $contacts->sum('total_pending'),
                'total_people'  => $contacts->count(),
            ],
            'data' => $contacts,
        ], 200);
    }

    // 6. RECEIVABLES LIST
    // Jin debtors se paisa lena hai
    // GET /api/contacts/receivables
    public function receivables(Request $request)
    {
        $userId = $request->user()->id;
        $filter = $request->filter ?? 'all';

        $contacts = Transaction::where('user_id', $userId)
            ->select(
                'name', 'phone', 'person_type',
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received'),
                DB::raw('MIN(installment_date) as upcoming_due'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->where('person_type', 'debtor') 
            ->groupBy('name', 'phone', 'person_type')
            ->get()
            ->map(function ($contact) use ($filter) {
                $pending = $contact->total_given - $contact->total_received;

                if ($pending <= 0) return null; // Skip if nothing to receive

                $dueDate = $contact->upcoming_due;
                if ($filter === 'due_payment' && !$dueDate) return null;
                if ($filter === 'upcoming_due' && (!$dueDate || Carbon::parse($dueDate)->isPast())) return null;

                return [
                    'name'          => $contact->name,
                    'phone'         => $contact->phone,
                    'total_pending' => $pending,
                    'upcoming_due'  => $dueDate ? Carbon::parse($dueDate)->format('d M Y') : null,
                    'due_status'    => $dueDate
                        ? (Carbon::parse($dueDate)->isPast() ? 'overdue' : 'upcoming')
                        : 'no_due',
                ];
            })
            ->filter()->values();

        return response()->json([
            'success' => true,
            'message' => 'Receivables fetched successfully',
            'summary' => [
                'total_receivable' => $contacts->sum('total_pending'),
                'total_people'     => $contacts->count(),
            ],
            'data' => $contacts,
        ], 200);
    }
}
