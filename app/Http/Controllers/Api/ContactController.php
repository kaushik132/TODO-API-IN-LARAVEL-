<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    // =============================================
    // RULE:
    // Contacts = transactions table ke unique phone numbers
    // Alag contacts table nahi — sab transactions se aata hai
    // =============================================

    // 1. GET ALL CONTACTS
    // GET /api/contacts-list
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $contacts = Transaction::where('user_id', $userId)
            ->select(
                'name',
                'phone',
                DB::raw('MAX(avatar) as avatar'),
                DB::raw('MAX(address) as address'),
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->groupBy('name', 'phone')
            ->orderBy('last_transaction', 'desc')
            ->get()
            ->map(fn($c) => $this->formatContact($c));

        return response()->json([
            'success' => true,
            'message' => 'Contacts fetched successfully',
            'data'    => $contacts,
        ], 200);
    }

    // 2. GET CONTACT BY PHONE
    // GET /api/contacts-list/{phone}
    public function show(Request $request, $phone)
    {
        $userId = $request->user()->id;

        $contact = Transaction::where('user_id', $userId)
            ->where('phone', $phone)
            ->select(
                'name',
                'phone',
                DB::raw('MAX(avatar) as avatar'),
                DB::raw('MAX(address) as address'),
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received')
            )
            ->groupBy('name', 'phone')
            ->first();

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact fetched successfully',
            'data'    => $this->formatContact($contact),
        ], 200);
    }

    // 3. UPDATE CONTACT BY PHONE (name, address, avatar)
    // POST /api/contacts-list/{phone}
    public function update(Request $request, $phone)
    {
        $userId = $request->user()->id;

        // Check karo phone exist karta hai transactions mein
        $exists = Transaction::where('user_id', $userId)
                             ->where('phone', $phone)
                             ->exists();

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'avatar'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $updateData = [];

        // Name update
        if ($request->filled('name')) {
            $updateData['name'] = $request->name;
        }

        // Address update
        if ($request->has('address')) {
            $updateData['address'] = $request->address;
        }

        // Avatar upload
        if ($request->hasFile('avatar')) {
            // Purani avatar delete karo
            $oldAvatar = Transaction::where('user_id', $userId)
                                    ->where('phone', $phone)
                                    ->whereNotNull('avatar')
                                    ->value('avatar');

            if ($oldAvatar && Storage::disk('public')->exists($oldAvatar)) {
                Storage::disk('public')->delete($oldAvatar);
            }

            $updateData['avatar'] = $request->file('avatar')
                                            ->store('contacts/avatars', 'public');
        }

        // Saari transactions update karo is phone ke liye
        if (!empty($updateData)) {
            Transaction::where('user_id', $userId)
                       ->where('phone', $phone)
                       ->update($updateData);
        }

        // Updated contact wapas do
        $contact = Transaction::where('user_id', $userId)
            ->where('phone', $phone)
            ->select(
                'name', 'phone',
                DB::raw('MAX(avatar) as avatar'),
                DB::raw('MAX(address) as address'),
                DB::raw('SUM(CASE WHEN transaction_type = "given" THEN pending_amount ELSE 0 END) as total_given'),
                DB::raw('SUM(CASE WHEN transaction_type = "received" THEN pending_amount ELSE 0 END) as total_received')
            )
            ->groupBy('name', 'phone')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data'    => $this->formatContact($contact),
        ], 200);
    }

    // 4. DELETE CONTACT BY PHONE (saari transactions bhi delete)
    // DELETE /api/contacts-list/{phone}
    public function destroy(Request $request, $phone)
    {
        $userId = $request->user()->id;

        $count = Transaction::where('user_id', $userId)
                            ->where('phone', $phone)
                            ->count();

        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        // Avatar delete karo
        $avatar = Transaction::where('user_id', $userId)
                             ->where('phone', $phone)
                             ->whereNotNull('avatar')
                             ->value('avatar');

        if ($avatar && Storage::disk('public')->exists($avatar)) {
            Storage::disk('public')->delete($avatar);
        }

        // Saari transactions delete karo
        Transaction::where('user_id', $userId)
                   ->where('phone', $phone)
                   ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact and all transactions deleted successfully',
        ], 200);
    }

    // =============================================
    // PRIVATE: Contact format karo
    // =============================================
    private function formatContact($contact): array
    {
        $balance = $contact->total_given - $contact->total_received;

        return [
            'name'           => $contact->name,
            'phone'          => $contact->phone,
            'address'        => $contact->address,
            'avatar_url'     => $contact->avatar
                                ? asset('storage/' . $contact->avatar)
                                : null,
            'total_given'    => $contact->total_given,
            'total_received' => $contact->total_received,
            'balance'        => abs($balance),
            'balance_label'  => $balance > 0
                ? "₹" . number_format(abs($balance)) . " You Will Receive"
                : ($balance < 0 ? "₹" . number_format(abs($balance)) . " You Will Pay" : "Settled"),
            'balance_type'   => $balance > 0 ? 'you_will_receive' : ($balance < 0 ? 'you_will_pay' : 'settled'),
        ];
    }
}
