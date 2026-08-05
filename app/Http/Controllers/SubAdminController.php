<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubAdminRequest;
use App\Http\Requests\UpdateSubAdminRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SubAdminController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $subAdmins = User::query()
            ->where('role', User::ROLE_SUB_ADMIN)
            ->select(['id', 'name', 'email', 'is_active', 'created_at'])
            ->latest()
            ->paginate(15);

        return $this->success($subAdmins, 'تم جلب المشرفين الفرعيين بنجاح.');
    }

    public function store(StoreSubAdminRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $subAdmin = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_SUB_ADMIN,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->success($subAdmin->only(['id', 'name', 'email', 'is_active']), 'تم إنشاء المشرف الفرعي بنجاح.', 201);
    }

    public function show(User $sub_admin): JsonResponse
    {
        $this->assertIsSubAdmin($sub_admin);

        return $this->success($sub_admin->only(['id', 'name', 'email', 'is_active']), 'تم جلب المشرف الفرعي بنجاح.');
    }

    public function update(UpdateSubAdminRequest $request, User $sub_admin): JsonResponse
    {
        $this->assertIsSubAdmin($sub_admin);

        $validated = $request->validated();

        $sub_admin->fill([
            'name' => $validated['name'] ?? $sub_admin->name,
            'email' => $validated['email'] ?? $sub_admin->email,
            'is_active' => $validated['is_active'] ?? $sub_admin->is_active,
        ]);

        if (isset($validated['password'])) {
            $sub_admin->password = $validated['password'];
        }

        $sub_admin->save();

        return $this->success($sub_admin->only(['id', 'name', 'email', 'is_active']), 'تم تحديث المشرف الفرعي بنجاح.');
    }

    public function destroy(User $sub_admin): JsonResponse
    {
        $this->assertIsSubAdmin($sub_admin);

        if ($sub_admin->id === request()->user()->id) {
            return $this->error('لا يمكنك حذف حسابك الخاص.', 422);
        }

        $sub_admin->delete();

        return $this->success(null, 'تم حذف المشرف الفرعي بنجاح.');
    }

    private function assertIsSubAdmin(User $user): void
    {
        abort_unless($user->role === User::ROLE_SUB_ADMIN, 404);
    }
}
