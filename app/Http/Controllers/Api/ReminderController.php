<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReminderController extends Controller
{
    // =============================================
    // 1. CREATE REMINDER
    // POST /api/reminders
    // =============================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:255',
            'amount'          => 'required|numeric|min:0',
            'reminder_date'   => 'required|date|after_or_equal:today',
            'reminder_before' => 'required|in:0,1,2,3',  // 0=same day, 1=1 day, 2=2 days, 3=3 days
            'note'            => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $reminder = Reminder::create([
            'user_id'         => $request->user()->id,
            'title'           => $request->title,
            'amount'          => $request->amount,
            'reminder_date'   => $request->reminder_date,
            'reminder_before' => $request->reminder_before,
            'note'            => $request->note,
            'is_notified'     => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reminder created successfully',
            'data'    => $reminder,
        ], 201);
    }

    // =============================================
    // 2. GET ALL REMINDERS (Home screen ke liye)
    // GET /api/reminders
    // =============================================
    public function index(Request $request)
    {
        $query = Reminder::where('user_id', $request->user()->id)
                         ->orderBy('reminder_date', 'asc');

        // Filter by status (pending/complete)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range (calendar ke liye)
        if ($request->has('from_date')) {
            $query->whereDate('reminder_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('reminder_date', '<=', $request->to_date);
        }

        $reminders = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Reminders fetched successfully',
            'summary' => [
                'total'    => $reminders->count(),
                'pending'  => $reminders->where('status', 'pending')->count(),
                'complete' => $reminders->where('status', 'complete')->count(),
            ],
            'data' => $reminders,
        ], 200);
    }

    // =============================================
    // 3. GET SINGLE REMINDER
    // GET /api/reminders/{id}
    // =============================================
    public function show(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
                            ->where('user_id', $request->user()->id)
                            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reminder fetched successfully',
            'data'    => $reminder,
        ], 200);
    }

    // =============================================
    // 4. UPDATE REMINDER
    // PUT /api/reminders/{id}
    // =============================================
    public function update(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
                            ->where('user_id', $request->user()->id)
                            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'           => 'sometimes|string|max:255',
            'amount'          => 'sometimes|numeric|min:0',
            'reminder_date'   => 'sometimes|date',
            'reminder_before' => 'sometimes|in:0,1,2,3',
            'note'            => 'nullable|string|max:500',
            'status'          => 'sometimes|in:pending,complete',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $reminder->update([
            'title'           => $request->title           ?? $reminder->title,
            'amount'          => $request->amount          ?? $reminder->amount,
            'reminder_date'   => $request->reminder_date   ?? $reminder->reminder_date,
            'reminder_before' => $request->reminder_before ?? $reminder->reminder_before,
            'note'            => $request->has('note') ? $request->note : $reminder->note,
            'status'          => $request->status          ?? $reminder->status,
            'is_notified'     => false, // date change hone pe dobara notify karo
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reminder updated successfully',
            'data'    => $reminder->fresh(),
        ], 200);
    }

    // =============================================
    // 5. DELETE REMINDER
    // DELETE /api/reminders/{id}
    // =============================================
    public function destroy(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
                            ->where('user_id', $request->user()->id)
                            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        $reminder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reminder deleted successfully',
        ], 200);
    }

    // =============================================
    // 6. MARK AS COMPLETE
    // POST /api/reminders/{id}/complete
    // =============================================
    public function markComplete(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
                            ->where('user_id', $request->user()->id)
                            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        $reminder->update(['status' => 'complete']);

        return response()->json([
            'success' => true,
            'message' => 'Reminder marked as complete',
            'data'    => $reminder->fresh(),
        ], 200);
    }
}
