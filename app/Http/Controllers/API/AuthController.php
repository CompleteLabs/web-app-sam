<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Services\FileUploadService;
use App\Services\OtpService;
use App\Services\PhoneService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * User - Fetch profile ✅
     */
    public function profile(Request $request)
    {
        try {
            $user = User::with([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ])
                ->where('id', Auth::user()->id)
                ->first();

            Log::info('Profile fetched successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);

            return ResponseFormatter::success(
                UserResource::make($user),
                'Data profil pengguna berhasil diambil'
            );
        } catch (Exception $error) {
            Log::error('Error fetching profile: ' . $error->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $error->getMessage()
            ]);
            return ResponseFormatter::error(null, 'Gagal mengambil profil', 500);
        }
    }

    /**
     * User - Update profile information ✅
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . Auth::id(),
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::validation(
                $validator->errors(),
                'Periksa kembali data yang Anda masukkan.'
            );
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $oldData = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone
            ];

            // Update only provided fields
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }

            $user->save();
            DB::commit();

            // Reload user with relationships
            $updatedUser = User::with([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ])
                ->where('id', $user->id)
                ->first();

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'old_data' => $oldData,
                'updated_fields' => array_keys($request->only(['name', 'email', 'phone']))
            ]);

            return ResponseFormatter::success(
                UserResource::make($updatedUser),
                'Profil berhasil diperbarui'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating profile: ' . $error->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['name', 'email', 'phone']),
                'error' => $error->getMessage()
            ]);
            return ResponseFormatter::error(null, 'Gagal memperbarui profil', 500);
        }
    }

    /**
     * User - Update profile photo ✅
     */
    public function updatePhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|file|mimes:jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::validation(
                $validator->errors(),
                'File foto tidak valid. Pastikan file berformat JPG, JPEG, atau PNG dan berukuran maksimal 5MB.'
            );
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $photo = $request->file('photo');
            $oldPhoto = $user->photo;

            // Validate file type and size using FileUploadService
            if (!FileUploadService::isValidPhoto($photo)) {
                return ResponseFormatter::error(
                    null,
                    'Format foto tidak didukung. Gunakan format JPG, JPEG, atau PNG.',
                    422
                );
            }

            if (!FileUploadService::isValidPhotoSize($photo)) {
                return ResponseFormatter::error(
                    null,
                    'Ukuran foto terlalu besar. Maksimal 5MB.',
                    422
                );
            }

            // Upload new photo first
            $fileName = FileUploadService::uploadPhoto($photo, 'profile', $user->username);

            // Update user photo in database
            $user->photo = $fileName;
            $user->save();

            DB::commit();

            // Delete old photo after successful database update
            if ($oldPhoto) {
                try {
                    $deleted = FileUploadService::deleteFile($oldPhoto);
                    if ($deleted) {
                        Log::info('Old profile photo deleted successfully', [
                            'user_id' => $user->id,
                            'old_photo' => $oldPhoto
                        ]);
                    } else {
                        Log::warning('Failed to delete old profile photo', [
                            'user_id' => $user->id,
                            'old_photo' => $oldPhoto
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Error deleting old profile photo', [
                        'user_id' => $user->id,
                        'old_photo' => $oldPhoto,
                        'error' => $e->getMessage()
                    ]);
                    // Continue since the new photo is already uploaded and database updated
                }
            }

            // Reload user with relationships
            $updatedUser = User::with([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ])
                ->where('id', $user->id)
                ->first();

            Log::info('Profile photo updated successfully', [
                'user_id' => $user->id,
                'old_photo' => $oldPhoto,
                'new_photo' => $fileName
            ]);

            return ResponseFormatter::success(
                UserResource::make($updatedUser),
                'Foto profil berhasil diperbarui'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating profile photo: ' . $error->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $error->getMessage()
            ]);
            return ResponseFormatter::error(null, 'Gagal memperbarui foto profil', 500);
        }
    }

    /**
     * User - Update password ✅
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string|min:8',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'new_password_confirmation.required' => 'Konfirmasi password baru wajib diisi.',
            'new_password_confirmation.min' => 'Konfirmasi password baru minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::validation(
                $validator->errors(),
                'Periksa kembali data yang Anda masukkan.'
            );
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return ResponseFormatter::error(
                    null,
                    'Password saat ini tidak benar.',
                    422
                );
            }

            // Check if new password is different from current
            if (Hash::check($request->new_password, $user->password)) {
                return ResponseFormatter::error(
                    null,
                    'Password baru harus berbeda dengan password saat ini.',
                    422
                );
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();
            
            DB::commit();

            // Reload user with relationships
            $updatedUser = User::with([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ])
                ->where('id', $user->id)
                ->first();

            Log::info('Password updated successfully', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);

            return ResponseFormatter::success(
                UserResource::make($updatedUser),
                'Password berhasil diperbarui'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating password: ' . $error->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $error->getMessage()
            ]);
            return ResponseFormatter::error(null, 'Gagal memperbarui password', 500);
        }
    }

    /**
     * User - Login ✅
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $supportedVersions = implode(',', config('business.supported_versions'));

        $validator = Validator::make($request->all(), [
            'version' => "required|string|in:{$supportedVersions}",
            'username' => 'required|string',
            'password' => 'required|string',
            'notif_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $userMessage = 'Periksa kembali data yang Anda masukkan.';

            if ($errors->has('version')) {
                $userMessage = 'Perbarui aplikasi SAM Anda ke versi terbaru.';
            }
            if ($errors->has('notif_id')) {
                $userMessage = 'Pastikan perangkat Anda terhubung dengan benar.';
            }

            return ResponseFormatter::validation($errors, $userMessage);
        }

        try {
            $credentials = $request->only(['username', 'password']);

            if (! Auth::attempt($credentials)) {
                // Jika gagal login lokal, coba ke API eksternal
                try {
                    $client = new \GuzzleHttp\Client;
                    $response = $client->post(config('business.external_api.login_url'), [
                        'form_params' => [
                            'username' => $request->username,
                            'password' => $request->password,
                            'version' => '1.0.3', // Versi API eksternal
                            'notif_id' => $request->notif_id,
                        ],
                        'timeout' => config('business.external_api.timeout'),
                    ]);
                    $data = json_decode($response->getBody(), true);
                    if (isset($data['data']['user'])) {
                        DB::beginTransaction();
                        try {
                            $userData = $data['data']['user'];
                            // Simpan user ke database lokal
                            $user = new \App\Models\User;
                            $user->username = $userData['username'] ?? $request->username;
                            $user->name = $userData['nama_lengkap'] ?? $request->name;
                            $user->email = $userData['email'] ?? null;
                            $user->password = bcrypt($request->password);

                            $roleId = null;
                            if (isset($userData['role']['name'])) {
                                $role = \App\Models\Role::where('name', $userData['role']['name'])->first();
                                if ($role) {
                                    $roleId = $role->id;
                                }
                            }
                            $user->role_id = $roleId;

                            $tmId = null;
                            if (isset($userData['tm_id'])) {
                                $tm = \App\Models\User::where('username', $userData['tm_id'])->first();
                                if ($tm) {
                                    $tmId = $tm->id;
                                }
                            }

                            $user->tm_id = $tmId;

                            $user->save();

                            // Simpan scope ke user_scope
                            $scope = new \App\Models\UserScope;
                            $scope->user_id = $user->id;

                            // Ambil scope_required_fields dari role
                            // Mapping id scope secara hirarkis
                            $scopeFields = [];
                            if (isset($role) && $role && $role->scope_required_fields) {
                                $scopeFields = is_array($role->scope_required_fields)
                                    ? $role->scope_required_fields
                                    : json_decode($role->scope_required_fields, true);
                            }
                            $scopeData = [];
                            // Badan Usaha
                            if (in_array('badan_usaha_id', $scopeFields)) {
                                $badanUsahaId = null;
                                if (isset($userData['badanusaha']['name'])) {
                                    $bu = \App\Models\BadanUsaha::where('name', $userData['badanusaha']['name'])->first();
                                    if ($bu) {
                                        $badanUsahaId = $bu->id;
                                    }
                                }
                                $scopeData['badan_usaha_id'] = $badanUsahaId;
                            }
                            // Division
                            if (in_array('division_id', $scopeFields)) {
                                $divisionId = null;
                                if (isset($userData['divisi']['name'])) {
                                    $query = \App\Models\Division::where('name', $userData['divisi']['name']);
                                    if (! empty($scopeData['badan_usaha_id'])) {
                                        $query->where('badan_usaha_id', $scopeData['badan_usaha_id']);
                                    }
                                    $div = $query->first();
                                    if ($div) {
                                        $divisionId = $div->id;
                                    }
                                }
                                $scopeData['division_id'] = $divisionId;
                            }
                            // Region
                            if (in_array('region_id', $scopeFields)) {
                                $regionId = null;
                                if (isset($userData['region']['name'])) {
                                    $query = \App\Models\Region::where('name', $userData['region']['name']);
                                    if (! empty($scopeData['badan_usaha_id'])) {
                                        $query->where('badan_usaha_id', $scopeData['badan_usaha_id']);
                                    }
                                    if (! empty($scopeData['division_id'])) {
                                        $query->where('division_id', $scopeData['division_id']);
                                    }
                                    $reg = $query->first();
                                    if ($reg) {
                                        $regionId = $reg->id;
                                    }
                                }
                                $scopeData['region_id'] = $regionId;
                            }
                            // Cluster
                            if (in_array('cluster_id', $scopeFields)) {
                                $clusterId = null;
                                if (isset($userData['cluster']['name'])) {
                                    $query = \App\Models\Cluster::where('name', $userData['cluster']['name']);
                                    if (! empty($scopeData['badan_usaha_id'])) {
                                        $query->where('badan_usaha_id', $scopeData['badan_usaha_id']);
                                    }
                                    if (! empty($scopeData['division_id'])) {
                                        $query->where('division_id', $scopeData['division_id']);
                                    }
                                    if (! empty($scopeData['region_id'])) {
                                        $query->where('region_id', $scopeData['region_id']);
                                    }
                                    $clu = $query->first();
                                    if ($clu) {
                                        $clusterId = $clu->id;
                                    }
                                }
                                $scopeData['cluster_id'] = $clusterId;
                            }
                            // Simpan ke user_scope
                            foreach ($scopeData as $key => $val) {
                                $scope->$key = $val;
                            }
                            $scope->save();

                            DB::commit();

                            // Login ulang
                            if (! Auth::attempt($credentials)) {
                                return ResponseFormatter::error(null, 'Cek kembali username dan password anda', 401);
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();

                            return ResponseFormatter::error(null, 'Gagal membuat user baru', 500);
                        }
                    } else {
                        return ResponseFormatter::error(null, 'Cek kembali username dan password anda', 401);
                    }
                } catch (\Exception $e) {
                    return ResponseFormatter::error(null, 'Gagal terhubung ke sistem eksternal', 401);
                }
            }

            $user = User::with([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'role.permissions:id,name',
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ])
                ->select(User::LIST_COLUMNS)
                ->where('username', $request->username)
                ->first();

            if (! $user) {
                return ResponseFormatter::error(null, 'User tidak ditemukan', 404);
            }

            $user->notif_id = $request->notif_id;
            $user->update();

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => UserResource::make($user),
            ], 'Login berhasil');
        } catch (Exception $error) {
            return ResponseFormatter::error(null, 'Terjadi kesalahan pada server.', 500);
        }
    }

    /**
     * User - Logout ✅
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return ResponseFormatter::success(
                null,
                'Anda telah berhasil keluar dari aplikasi'
            );
        } catch (Exception $error) {
            return ResponseFormatter::error(null, 'Gagal logout', 500);
        }
    }

    /**
     * Send OTP to WhatsApp - Enhanced ✅
     *
     * @unauthenticated
     */
    public function sendOtp(Request $request)
    {
        try {
            // 1. Comprehensive validation dengan custom messages
            $validator = Validator::make($request->all(), [
                'phone' => [
                    'required',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
            ], [
                'phone.required' => 'Nomor handphone wajib diisi.',
                'phone.regex' => 'Format nomor handphone tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
            ]);

            if ($validator->fails()) {
                return ResponseFormatter::validation($validator->errors(), 'Gagal mengirim OTP');
            }

            $validated = $validator->validated();

            // 2. Normalize phone using service
            $phone = PhoneService::normalize($validated['phone']);

            // 3. Business logic validations
            $validationResult = $this->validateOtpSendRules($phone);
            if ($validationResult !== true) {
                return $validationResult;
            }

            // 4. Generate and send OTP
            OtpService::generateAndSend($phone);

            // 5. Log successful OTP send
            Log::info('OTP sent successfully', [
                'phone' => substr($phone, 0, 5).'xxx'.substr($phone, -3), // Masked phone
                'timestamp' => now(),
            ]);

            return ResponseFormatter::success(null, 'OTP berhasil dikirim ke WhatsApp Anda');

        } catch (Exception $error) {
            Log::error('Error sending OTP: '.$error->getMessage(), [
                'phone' => isset($phone) ? substr($phone, 0, 5).'xxx'.substr($phone, -3) : 'unknown',
                'error' => $error->getMessage(),
            ]);

            return ResponseFormatter::serverError('Gagal mengirim OTP. Silakan coba lagi.');
        }
    }

    /**
     * Verify OTP and login - Enhanced ✅
     *
     * @unauthenticated
     */
    public function verifyOtp(Request $request)
    {
        DB::beginTransaction();
        try {
            // 1. Comprehensive validation dengan custom messages
            $otpLength = config('business.otp.length');
            $validator = Validator::make($request->all(), [
                'phone' => [
                    'required',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
                'otp' => "required|string|size:{$otpLength}",
                'notif_id' => 'required|string|max:255',
            ], [
                'phone.required' => 'Nomor handphone wajib diisi.',
                'phone.regex' => 'Format nomor handphone tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
                'otp.required' => 'Kode OTP wajib diisi.',
                'otp.size' => "Kode OTP harus {$otpLength} digit.",
                'notif_id.required' => 'notif_id wajib diisi.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal verifikasi OTP');
            }

            $validated = $validator->validated();

            // 2. Normalize phone using service
            $phone = PhoneService::normalize($validated['phone']);

            // 3. Business logic validations
            $validationResult = $this->validateOtpVerifyRules($phone, $validated['otp']);
            if ($validationResult !== true) {
                DB::rollBack();

                return $validationResult;
            }

            // 4. Find user
            $user = User::where('phone', $phone)->first();
            if (! $user) {
                DB::rollBack();
                Log::warning('OTP verification failed - user not found', [
                    'phone' => substr($phone, 0, 5).'xxx'.substr($phone, -3),
                ]);

                return ResponseFormatter::notFound('Nomor handphone tidak terdaftar');
            }

            // 5. Update user data
            $user->update([
                'notif_id' => $validated['notif_id'],
            ]);

            // 6. Authenticate user
            Auth::login($user);

            // 7. Clear OTP cache
            OtpService::clear($phone);

            // 8. Clear rate limiting cache
            $this->clearOtpAttempts($phone);

            DB::commit();

            // 9. Load relationships for response
            $user->load([
                'role:'.implode(',', Role::LIST_COLUMNS),
                'role.permissions:id,name',
                'userScopes:'.implode(',', UserScope::LIST_COLUMNS),
            ]);

            // 10. Generate token
            $token = $user->createToken('whatsapp-otp')->plainTextToken;

            // 11. Log successful login
            Log::info('OTP verification successful', [
                'user_id' => $user->id,
                'phone' => substr($phone, 0, 5).'xxx'.substr($phone, -3),
                'login_method' => 'otp',
            ]);

            return ResponseFormatter::success([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => UserResource::make($user),
            ], 'Login berhasil');

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error verifying OTP: '.$error->getMessage(), [
                'phone' => isset($phone) ? substr($phone, 0, 5).'xxx'.substr($phone, -3) : 'unknown',
                'error' => $error->getMessage(),
            ]);

            return ResponseFormatter::serverError('Terjadi kesalahan saat verifikasi OTP. Silakan coba lagi.');
        }
    }

    /**
     * Helper method - Validate OTP send business rules
     */
    private function validateOtpSendRules($phone)
    {
        // 1. Check if phone exists in users table
        if (! User::where('phone', $phone)->exists()) {
            return ResponseFormatter::notFound('Nomor handphone tidak terdaftar');
        }

        // 2. Rate limiting - OTP send requests per phone
        $maxOtpRequests = config('business.otp.max_requests_per_hour', 5);
        $otpRequestKey = "otp_requests:{$phone}";
        $otpRequestTimeKey = "otp_requests_time:{$phone}";
        $currentRequests = Cache::get($otpRequestKey, 0);

        if ($currentRequests >= $maxOtpRequests) {
            $firstRequestTime = Cache::get($otpRequestTimeKey);
            if ($firstRequestTime) {
                // Ensure we have a Carbon instance
                if (is_string($firstRequestTime)) {
                    $firstRequestTime = Carbon::parse($firstRequestTime);
                }

                $elapsedMinutes = $firstRequestTime->diffInMinutes(now());

                // Reset counter if 1 hour has passed
                if ($elapsedMinutes >= 60) {
                    Cache::forget($otpRequestKey);
                    Cache::forget($otpRequestTimeKey);
                    $currentRequests = 0; // Reset for next check
                } else {
                    $remainingMinutes = 60 - $elapsedMinutes;
                    $remainingSeconds = $remainingMinutes * 60;

                    return ResponseFormatter::error(null, "Terlalu banyak permintaan OTP. Silakan coba lagi dalam {$remainingSeconds} detik.", 429);
                }
            }
        }

        // 3. Check if OTP was recently sent (avoid spam)
        $lastSentKey = "otp_last_sent:{$phone}";
        $cooldownSeconds = config('business.otp.cooldown_seconds', 60);
        $lastSent = Cache::get($lastSentKey);

        if ($lastSent) {
            // Ensure we have a Carbon instance
            if (is_string($lastSent)) {
                $lastSent = Carbon::parse($lastSent);
            }

            $elapsedSeconds = $lastSent->diffInSeconds(now());
            if ($elapsedSeconds < $cooldownSeconds) {
                $remainingTime = $cooldownSeconds - $elapsedSeconds;

                return ResponseFormatter::error(null, "Silakan tunggu {$remainingTime} detik sebelum meminta OTP lagi.", 429);
            }
        }

        // 4. Update rate limiting counters
        if ($currentRequests == 0) {
            // First request, set the timestamp
            Cache::put($otpRequestTimeKey, now(), 3600); // 1 hour
        }
        Cache::put($otpRequestKey, $currentRequests + 1, 3600); // 1 hour
        Cache::put($lastSentKey, now(), $cooldownSeconds);

        return true;
    }

    /**
     * Helper method - Validate OTP verify business rules
     */
    private function validateOtpVerifyRules($phone, $otp)
    {
        // 1. Rate limiting - OTP verification attempts per phone
        $maxAttempts = config('business.otp.max_attempts', 5);
        $attemptKey = "otp_attempts:{$phone}";
        $currentAttempts = Cache::get($attemptKey, 0);

        if ($currentAttempts >= $maxAttempts) {
            $lockoutMinutes = config('business.otp.lockout_minutes', 30);

            return ResponseFormatter::error(null, "Terlalu banyak percobaan. Akun dikunci selama {$lockoutMinutes} menit.", 429);
        }

        // 2. Verify OTP
        if (! OtpService::verify($phone, $otp)) {
            // Increment failed attempts
            Cache::put($attemptKey, $currentAttempts + 1, 1800); // 30 minutes

            $remainingAttempts = $maxAttempts - ($currentAttempts + 1);
            if ($remainingAttempts > 0) {
                return ResponseFormatter::unauthorized("OTP salah atau kadaluarsa. Sisa percobaan: {$remainingAttempts}");
            } else {
                $lockoutMinutes = config('business.otp.lockout_minutes', 30);

                return ResponseFormatter::error(null, "Terlalu banyak percobaan. Akun dikunci selama {$lockoutMinutes} menit.", 429);
            }
        }

        return true;
    }

    /**
     * Helper method - Clear OTP attempts cache
     */
    private function clearOtpAttempts($phone)
    {
        Cache::forget("otp_attempts:{$phone}");
        Cache::forget("otp_requests:{$phone}");
        Cache::forget("otp_requests_time:{$phone}");
        Cache::forget("otp_last_sent:{$phone}");
    }

    /**
     * Helper method - Format user permissions safely
     */
    private function formatPermissions($user)
    {
        try {
            // Gunakan permissions dari role relationship yang sudah di-load
            if ($user->relationLoaded('role') && $user->role && $user->role->relationLoaded('permissions')) {
                return $user->role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                });
            }

            // Fallback: load permissions manually jika belum di-load
            $user->load('role.permissions');

            return $user->role->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ];
            });
        } catch (Exception $e) {
            Log::warning('Error formatting permissions', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            // Return empty collection jika ada error
            return collect([]);
        }
    }
}
