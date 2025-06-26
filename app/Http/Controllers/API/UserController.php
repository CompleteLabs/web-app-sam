<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Services\PasswordService;
use App\Services\PhoneService;
use App\Services\WhatsAppService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // List all users
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));
            $search = $request->input('search');
            $sortColumn = $request->input('sort_column', 'name');
            $sortDirection = $request->input('sort_direction', 'asc');

            $query = User::with([
                'role:id,name',
                'userScopes:user_id,badan_usaha_id,division_id,region_id,cluster_id',
            ]);

            // Search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('username', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%");
                });
            }

            // Sorting
            $allowedSorts = ['name', 'username', 'phone', 'created_at'];
            if (! in_array($sortColumn, $allowedSorts)) {
                $sortColumn = 'name';
            }
            $query->orderBy($sortColumn, $sortDirection);

            $users = $query->paginate($perPage, [
                'id', 'name', 'username', 'phone', 'email', 'role_id', 'tm_id', 'created_at', 'updated_at',
            ]);

            // Transform to resource
            $users->getCollection()->transform(function ($user) {
                return UserResource::make($user);
            });

            return ResponseFormatter::paginated($users, 'List user berhasil diambil');
        } catch (Exception $e) {
            return ResponseFormatter::serverError('Gagal mengambil data user');
        }
    }

    // Show user detail
    public function show($id)
    {
        try {
            $user = User::with([
                'role:id,name',
                'userScopes:user_id,badan_usaha_id,division_id,region_id,cluster_id',
            ])->find($id);

            if (! $user) {
                return ResponseFormatter::notFound('User tidak ditemukan');
            }

            return ResponseFormatter::success(UserResource::make($user), 'Detail user berhasil diambil');
        } catch (Exception $e) {
            return ResponseFormatter::serverError('Gagal mengambil detail user');
        }
    }

    // Create user
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:50|unique:users',
                'phone' => [
                    'required',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
                'role' => 'required|integer|exists:roles,id',
                'badanusaha' => 'nullable|string',
                'divisi' => 'nullable|string',
                'region' => 'nullable|string',
                'cluster' => 'nullable|string',
            ], [
                'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal menambahkan user');
            }

            $validated = $validator->validated();

            // Normalize phone number using service
            $phone = PhoneService::normalize($validated['phone']);

            // Check phone uniqueness after normalization
            if (User::where('phone', $phone)->exists()) {
                DB::rollBack();

                return ResponseFormatter::validation(['phone' => ['Nomor sudah terdaftar']], 'Gagal menambahkan user');
            }

            // Generate password using service
            $passwordData = PasswordService::generateAndHash();

            // Get role for scope handling
            $role = Role::find($validated['role']);
            if (! $role) {
                DB::rollBack();

                return ResponseFormatter::validation(['role' => ['Role tidak ditemukan']], 'Gagal menambahkan user');
            }

            // Prepare user data
            $userData = [
                'name' => strtoupper($validated['name']),
                'username' => strtolower($validated['username']),
                'phone' => $phone,
                'password' => $passwordData['hashed'],
                'role_id' => $validated['role'],
                'tm_id' => Auth::user()->id,
            ];

            // Create user
            $user = User::create($userData);

            // Create user scope
            $this->createUserScope($user, $role, $request);

            // Send credentials via WhatsApp
            if (config('business.user.auto_send_credentials') && $user->phone) {
                WhatsAppService::sendUserCredentials($user->phone, $user->username, $passwordData['plain']);
            }

            DB::commit();

            // Load relationships for response
            $user->load(['role:id,name', 'userScopes']);

            return ResponseFormatter::success(UserResource::make($user), 'User berhasil dibuat');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: '.$e->getMessage());

            return ResponseFormatter::serverError('Gagal menambahkan user');
        }
    }

    /**
     * Create user scope based on role requirements
     */
    private function createUserScope(User $user, Role $role, Request $request): void
    {
        $scopeFields = [];
        if (is_array($role->scope_required_fields)) {
            $scopeFields = $role->scope_required_fields;
        } elseif (! empty($role->scope_required_fields)) {
            $scopeFields = json_decode($role->scope_required_fields, true);
        }

        if (empty($scopeFields)) {
            return;
        }

        $scopeData = [];
        if (in_array('badan_usaha_id', $scopeFields)) {
            $scopeData['badan_usaha_id'] = $request->badanusaha ?? null;
        }
        if (in_array('division_id', $scopeFields)) {
            $scopeData['division_id'] = $request->divisi ?? null;
        }
        if (in_array('region_id', $scopeFields)) {
            $scopeData['region_id'] = $request->region ?? null;
        }
        if (in_array('cluster_id', $scopeFields)) {
            $scopeData['cluster_id'] = $request->cluster ?? null;
        }

        $scope = new UserScope;
        $scope->user_id = $user->id;
        foreach ($scopeData as $key => $val) {
            $scope->$key = $val;
        }
        $scope->save();
    }

    // Update user
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (! $user) {
                return ResponseFormatter::notFound('User tidak ditemukan');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'username' => 'sometimes|string|max:50|unique:users,username,'.$id,
                'email' => 'nullable|email|unique:users,email,'.$id,
                'password' => 'sometimes|string|min:6',
                'phone' => [
                    'sometimes',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
            ], [
                'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
            ]);

            if ($validator->fails()) {
                return ResponseFormatter::validation($validator->errors(), 'Gagal mengupdate user');
            }

            $validated = $validator->validated();

            // Normalize phone if provided
            if (isset($validated['phone'])) {
                $phone = PhoneService::normalize($validated['phone']);

                // Check phone uniqueness
                if (User::where('phone', $phone)->where('id', '!=', $id)->exists()) {
                    return ResponseFormatter::validation(['phone' => ['Nomor sudah terdaftar']], 'Gagal mengupdate user');
                }

                $validated['phone'] = $phone;
            }

            // Hash password if provided
            if (isset($validated['password'])) {
                $validated['password'] = PasswordService::hash($validated['password']);
            }

            // Ensure proper formatting
            if (isset($validated['name'])) {
                $validated['name'] = strtoupper($validated['name']);
            }
            if (isset($validated['username'])) {
                $validated['username'] = strtolower($validated['username']);
            }

            $user->update($validated);

            // Load relationships for response
            $user->load(['role:id,name', 'userScopes']);

            return ResponseFormatter::success(UserResource::make($user), 'User berhasil diupdate');
        } catch (Exception $e) {
            Log::error('Error updating user: '.$e->getMessage());

            return ResponseFormatter::serverError('Gagal mengupdate user');
        }
    }

    // Delete user
    public function destroy($id)
    {
        try {
            $user = User::find($id);
            if (! $user) {
                return ResponseFormatter::notFound('User tidak ditemukan');
            }

            // Prevent deleting self
            if ($user->id == Auth::user()->id) {
                return ResponseFormatter::error(null, 'Tidak dapat menghapus akun sendiri', 403);
            }

            $user->delete();

            return ResponseFormatter::success(null, 'User berhasil dihapus');
        } catch (Exception $e) {
            Log::error('Error deleting user: '.$e->getMessage());

            return ResponseFormatter::serverError('Gagal menghapus user');
        }
    }
}
