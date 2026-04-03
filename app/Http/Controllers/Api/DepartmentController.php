<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    // =============================================
    // 1. GET ALL DEPARTMENTS
    // GET /api/departments
    // =============================================
    public function index(Request $request)
    {
        $user = $request->user();

        // Sirf super_admin sabke departments dekh sakta hai
        if ($user->isSuperAdmin()) {
            $departments = Department::withCount('users')
                                     ->orderBy('name')
                                     ->get();
        } else {
            // Baaki log sirf apna department dekh sakte hain
            $departments = Department::withCount('users')
                                     ->where('id', $user->department_id)
                                     ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Departments fetched successfully',
            'data'    => $departments,
        ], 200);
    }

    // =============================================
    // 2. CREATE DEPARTMENT (Sirf super_admin)
    // POST /api/departments
    // =============================================
    public function store(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can create departments',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $department = Department::create([
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data'    => $department,
        ], 201);
    }

    // =============================================
    // 3. UPDATE DEPARTMENT (Sirf super_admin)
    // PUT /api/departments/{id}
    // =============================================
    public function update(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can update departments',
            ], 403);
        }

        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'nullable|string|max:255|unique:departments,name,' . $id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $department->update([
            'name'        => $request->name        ?? $department->name,
            'description' => $request->description ?? $department->description,
            'is_active'   => $request->has('is_active') ? $request->is_active : $department->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'data'    => $department->fresh(),
        ], 200);
    }

    // =============================================
    // 4. DELETE DEPARTMENT (Sirf super_admin)
    // DELETE /api/departments/{id}
    // =============================================
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can delete departments',
            ], 403);
        }

        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        // Users ko null kar do
        User::where('department_id', $id)->update(['department_id' => null]);

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ], 200);
    }

    // =============================================
    // 5. GET DEPARTMENT MEMBERS
    // GET /api/departments/{id}/members
    // =============================================
    public function members(Request $request, $id)
    {
        $user = $request->user();

        // Member sirf apna department dekh sakta hai
        if (!$user->isSuperAdmin() && $user->department_id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view your own department members',
            ], 403);
        }

        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $members = User::where('department_id', $id)
                       ->select('id', 'name', 'email', 'role', 'avatar', 'created_at')
                       ->get()
                       ->map(function ($member) {
                           return [
                               'id'         => $member->id,
                               'name'       => $member->name,
                               'email'      => $member->email,
                               'role'       => $member->role,
                               'avatar_url' => $member->avatar
                                   ? asset('storage/' . $member->avatar)
                                   : null,
                               'joined_at'  => $member->created_at->format('d M Y'),
                           ];
                       });

        return response()->json([
            'success'    => true,
            'message'    => 'Department members fetched successfully',
            'department' => $department->name,
            'data'       => $members,
        ], 200);
    }

    // =============================================
    // 6. ASSIGN USER TO DEPARTMENT (super_admin/admin)
    // POST /api/departments/assign-user
    // =============================================
    public function assignUser(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser->isSuperAdmin() && !$authUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or Super Admin can assign users',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id'       => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            'role'          => 'nullable|in:super_admin,admin,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Admin sirf apne department mein assign kar sakta hai
        if ($authUser->isAdmin() && $authUser->department_id != $request->department_id) {
            return response()->json([
                'success' => false,
                'message' => 'Admin can only assign users to their own department',
            ], 403);
        }

        $targetUser = User::find($request->user_id);

        $targetUser->update([
            'department_id' => $request->department_id,
            'role'          => $request->role ?? $targetUser->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User assigned to department successfully',
            'data'    => [
                'user'       => $targetUser->name,
                'department' => Department::find($request->department_id)->name,
                'role'       => $targetUser->fresh()->role,
            ],
        ], 200);
    }

    // =============================================
    // 7. MY DEPARTMENT INFO
    // GET /api/departments/my-department
    // =============================================
    public function myDepartment(Request $request)
    {
        $user = $request->user();

        if (!$user->department_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to any department',
            ], 404);
        }

        $department = Department::withCount('users')->find($user->department_id);

        $members = User::where('department_id', $user->department_id)
                       ->select('id', 'name', 'email', 'role', 'avatar')
                       ->get()
                       ->map(function ($m) {
                           return [
                               'id'         => $m->id,
                               'name'       => $m->name,
                               'email'      => $m->email,
                               'role'       => $m->role,
                               'avatar_url' => $m->avatar ? asset('storage/' . $m->avatar) : null,
                           ];
                       });

        return response()->json([
            'success' => true,
            'message' => 'My department fetched successfully',
            'data'    => [
                'department' => $department,
                'my_role'    => $user->role,
                'members'    => $members,
            ],
        ], 200);
    }
}
