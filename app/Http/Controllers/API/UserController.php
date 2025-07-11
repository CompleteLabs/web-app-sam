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
                'cluster' => 'Format cluster harus berupa angka tunggal atau beberapa angka dipisahkan koma (contoh: 395 atau 395,396)',
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
            $scopeData['badan_usaha_id'] = $this->parseMultipleValues($request->badanusaha);
        }
        if (in_array('division_id', $scopeFields)) {
            $scopeData['division_id'] = $this->parseMultipleValues($request->divisi);
        }
        if (in_array('region_id', $scopeFields)) {
            $scopeData['region_id'] = $this->parseMultipleValues($request->region);
        }
        if (in_array('cluster_id', $scopeFields)) {
            $scopeData['cluster_id'] = $this->parseMultipleValues($request->cluster);
        }

        $scope = new UserScope;
        $scope->user_id = $user->id;
        foreach ($scopeData as $key => $val) {
            $scope->$key = $val;
        }
        $scope->save();
    }

    /**
     * Parse multiple values from comma-separated string to array
     *
     * Examples:
     * - "395,396" → [395, 396]
     * - "395" → [395]
     * - 395 → [395]
     * - [395, 396] → [395, 396]
     * - null/empty → null
     */
    private function parseMultipleValues($value): ?array
    {
        if (empty($value)) {
            return null;
        }

        // If it's already an array, return as is
        if (is_array($value)) {
            return array_map('intval', $value);
        }

        // If it's a string, split by comma and convert to integers
        if (is_string($value)) {
            $values = explode(',', $value);
            $values = array_map('trim', $values);
            $values = array_filter($values, function($val) {
                return !empty($val) && is_numeric($val);
            });

            if (empty($values)) {
                return null;
            }

            return array_map('intval', $values);
        }

        // If it's a single numeric value
        if (is_numeric($value)) {
            return [intval($value)];
        }

        return null;
    }

    // Update user
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);
            if (! $user) {
                DB::rollBack();
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
                'role' => 'sometimes|integer|exists:roles,id',
                'badanusaha' => 'nullable|string',
                'divisi' => 'nullable|string',
                'region' => 'nullable|string',
                'cluster' => 'nullable|string',
            ], [
                'phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
                'cluster' => 'Format cluster harus berupa angka tunggal atau beberapa angka dipisahkan koma (contoh: 395 atau 395,396)',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal mengupdate user');
            }

            $validated = $validator->validated();

            // Normalize phone if provided
            if (isset($validated['phone'])) {
                $phone = PhoneService::normalize($validated['phone']);

                // Check phone uniqueness
                if (User::where('phone', $phone)->where('id', '!=', $id)->exists()) {
                    DB::rollBack();
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

            // Update user basic info
            $userUpdateData = collect($validated)->except(['role', 'badanusaha', 'divisi', 'region', 'cluster'])->toArray();
            if (!empty($userUpdateData)) {
                $user->update($userUpdateData);
            }

            // Update role if provided
            if (isset($validated['role'])) {
                $user->role_id = $validated['role'];
                $user->save();
            }

            // Update user scope if any scope-related fields are provided
            if ($request->hasAny(['badanusaha', 'divisi', 'region', 'cluster'])) {
                $this->updateUserScope($user, $request);
            }

            DB::commit();

            // Load relationships for response
            $user->load(['role:id,name', 'userScopes']);

            return ResponseFormatter::success(UserResource::make($user), 'User berhasil diupdate');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: '.$e->getMessage());

            return ResponseFormatter::serverError('Gagal mengupdate user');
        }
    }

    /**
     * Update user scope for existing user
     */
    private function updateUserScope(User $user, Request $request): void
    {
        // Find existing user scope or create new one
        $scope = UserScope::where('user_id', $user->id)->first();

        if (!$scope) {
            $scope = new UserScope();
            $scope->user_id = $user->id;
        }

        // Update scope data if provided in request
        if ($request->has('badanusaha')) {
            $scope->badan_usaha_id = $this->parseMultipleValues($request->badanusaha);
        }
        if ($request->has('divisi')) {
            $scope->division_id = $this->parseMultipleValues($request->divisi);
        }
        if ($request->has('region')) {
            $scope->region_id = $this->parseMultipleValues($request->region);
        }
        if ($request->has('cluster')) {
            $scope->cluster_id = $this->parseMultipleValues($request->cluster);
        }

        $scope->save();
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
