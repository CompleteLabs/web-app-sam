<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OutletResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\OutletHistory;
use App\Models\Region;
use App\Services\FileUploadService;
use App\Services\PhoneService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));
            $page = $request->input('page', 1);
            $search = $request->input('search');
            $sortColumn = $request->input('sort_column', 'code');
            $sortDirection = $request->input('sort_direction', 'asc');

            $query = Outlet::with([
                'badanUsaha:' . implode(',', BadanUsaha::LIST_COLUMNS),
                'division:' . implode(',', Division::LIST_COLUMNS),
                'region:' . implode(',', Region::LIST_COLUMNS),
                'cluster:' . implode(',', Cluster::LIST_COLUMNS),
            ]);

            // Scope by user
            $userScopes = $user->userScopes;
            if ($userScopes && $userScopes->count() > 0) {
                $query->where(function ($q) use ($userScopes) {
                    foreach ($userScopes as $scope) {
                        $q->orWhere(function ($sub) use ($scope) {
                            if ($scope->cluster_id) {
                                $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                                $sub->whereIn('outlets.cluster_id', $clusterIds);
                            } elseif ($scope->region_id) {
                                $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                                $sub->whereIn('outlets.region_id', $regionIds);
                            } elseif ($scope->division_id) {
                                $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                                $sub->whereIn('outlets.division_id', $divisionIds);
                            } elseif ($scope->badan_usaha_id) {
                                $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                                $sub->whereIn('outlets.badan_usaha_id', $badanUsahaIds);
                            }
                        });
                    }
                });
            }

            // Search global (name, code, address, district)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                });
            }

            // Sorting
            $allowedSorts = ['code', 'name', 'status', 'district'];
            if (! in_array($sortColumn, $allowedSorts)) {
                $sortColumn = 'code';
            }
            $query->orderBy($sortColumn, $sortDirection);

            $outlet = $query->paginate($perPage, Outlet::LIST_COLUMNS, 'page', $page);

            // Transform to resource
            $outlet->getCollection()->transform(function ($outletItem) {
                return OutletResource::make($outletItem);
            });

            return ResponseFormatter::paginated($outlet, 'Data outlet berhasil diambil');
        } catch (\Exception $error) {
            return ResponseFormatter::serverError('Gagal mengambil data outlet');
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $outlet = Outlet::with([
                'badanUsaha:' . implode(',', BadanUsaha::LIST_COLUMNS),
                'division:' . implode(',', Division::LIST_COLUMNS),
                'region:' . implode(',', Region::LIST_COLUMNS),
                'cluster:' . implode(',', Cluster::LIST_COLUMNS),
            ])
                ->where('id', $id)
                ->first();

            if (! $outlet) {
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            return ResponseFormatter::success(OutletResource::make($outlet), 'Data outlet berhasil diambil');
        } catch (Exception $error) {
            return ResponseFormatter::serverError('Gagal mengambil data outlet');
        }
    }

    /**
     * Store new outlet
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // 1. Validasi
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'owner_name' => 'required|string|max:255',
                'owner_phone' => [
                    'required',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
                'address' => 'required|string',
                'location' => 'required|string',
                'district' => 'required|string|max:255',
                'badan_usaha_id' => 'required|integer|exists:badan_usahas,id',
                'division_id' => 'required|integer|exists:divisions,id',
                'region_id' => 'required|integer|exists:regions,id',
                'cluster_id' => 'required|integer|exists:clusters,id',
                'photo_shop_sign' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_front' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_left' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_right' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_id_card' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'video' => 'nullable|mimes:mp4,avi,mov|max:10240',
            ], [
                'owner_phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
                'badan_usaha_id.exists' => 'Badan usaha tidak ditemukan.',
                'division_id.exists' => 'Divisi tidak ditemukan.',
                'region_id.exists' => 'Region tidak ditemukan.',
                'cluster_id.exists' => 'Cluster tidak ditemukan.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal menambahkan outlet');
            }

            $validated = $validator->validated();

            // Validasi tambahan nomor telepon
            if (!PhoneService::isValid($validated['owner_phone'])) {
                DB::rollBack();
                return ResponseFormatter::error(
                    null,
                    'Format nomor handphone pemilik tidak valid. Pastikan nomor dimulai dengan 08 atau +62 dan memiliki 10-13 digit.',
                    422
                );
            }

            // Normalisasi nomor telepon
            $validated['owner_phone'] = PhoneService::normalize($validated['owner_phone']);

            // Upload file ke temp folder (ASYNC)
            $tempFiles = $this->handleFileUploadsAsync($request, 'outlet');

            // Data outlet tanpa field file (field file dikosongkan, akan diupdate oleh job)
            $outletData = [
                'name' => strtoupper($validated['name']),
                'owner_name' => strtoupper($validated['owner_name']),
                'owner_phone' => $validated['owner_phone'],
                'address' => strtoupper($validated['address']),
                'location' => strtoupper($validated['location']),
                'district' => strtoupper($validated['district']),
                'badan_usaha_id' => $validated['badan_usaha_id'],
                'division_id' => $validated['division_id'],
                'region_id' => $validated['region_id'],
                'cluster_id' => $validated['cluster_id'],
                'status' => 'active',
                // Semua field file dikosongkan
                'photo_shop_sign' => '',
                'photo_front' => '',
                'photo_left' => '',
                'photo_right' => '',
                'photo_id_card' => '',
                'video' => '',
            ];

            $outlet = Outlet::create($outletData);

            // Dispatch job untuk proses file dan update field file di database
            if (!empty($tempFiles)) {
                \App\Jobs\ProcessFileUploadJob::dispatch(
                    $tempFiles,
                    Outlet::class,
                    $outlet->id,
                    Auth::id()
                );
            }

            DB::commit();

            $outlet->load([
                'badanUsaha:' . implode(',', BadanUsaha::LIST_COLUMNS),
                'division:' . implode(',', Division::LIST_COLUMNS),
                'region:' . implode(',', Region::LIST_COLUMNS),
                'cluster:' . implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(
                OutletResource::make($outlet),
                !empty($tempFiles)
                    ? 'Outlet berhasil ditambahkan. File sedang diproses di background.'
                    : 'Outlet berhasil ditambahkan'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error creating outlet: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal menambahkan outlet');
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $outlet = Outlet::find($id);
            if (!$outlet) {
                DB::rollBack();
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:20|unique:outlets,code,' . $id,
                'name' => 'sometimes|string|max:255',
                'owner_name' => 'required|string|max:255',
                'owner_phone' => [
                    'required',
                    'regex:/^(\\+62|62|0)8[1-9][0-9]{6,10}$/',
                ],
                'address' => 'sometimes|string',
                'location' => 'required|string',
                'district' => 'sometimes|string|max:255',
                'badan_usaha_id' => 'sometimes|integer|exists:badan_usahas,id',
                'division_id' => 'sometimes|integer|exists:divisions,id',
                'region_id' => 'sometimes|integer|exists:regions,id',
                'cluster_id' => 'sometimes|integer|exists:clusters,id',
                'status' => 'sometimes|in:active,inactive',
                'photo_shop_sign' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_front' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_left' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_right' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'photo_id_card' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'video' => 'nullable|mimes:mp4,avi,mov|max:10240',
            ], [
                'code.unique' => 'Kode outlet sudah digunakan.',
                'owner_phone.regex' => 'Format nomor telepon tidak valid. Gunakan format 62xxxxxxxxxxx atau 08xxxxxxxxxx.',
                'badan_usaha_id.exists' => 'Badan usaha tidak ditemukan.',
                'division_id.exists' => 'Divisi tidak ditemukan.',
                'region_id.exists' => 'Region tidak ditemukan.',
                'cluster_id.exists' => 'Cluster tidak ditemukan.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal update outlet');
            }

            $validated = $validator->validated();

            if (!PhoneService::isValid($validated['owner_phone'])) {
                DB::rollBack();
                return ResponseFormatter::error(
                    null,
                    'Format nomor handphone pemilik tidak valid. Pastikan nomor dimulai dengan 08 atau +62 dan memiliki 10-13 digit.',
                    422
                );
            }

            if (isset($validated['owner_phone'])) {
                $validated['owner_phone'] = PhoneService::normalize($validated['owner_phone']);
            }

            // Upload file ke temp folder (ASYNC)
            $tempFiles = $this->handleFileUploadsAsync($request, 'outlet');

            // Jika ada file baru, hapus file lama (tidak update field file di database di sini)
            if (!empty($tempFiles)) {
                foreach ($tempFiles as $field => $tempFileInfo) {
                    if (!empty($outlet->$field)) {
                        FileUploadService::deleteFile($outlet->$field);
                    }
                }
            }

            // Data update tanpa field file (field file dikosongkan, akan diupdate oleh job)
            $updateData = [];
            $basicFields = ['code', 'name', 'address', 'district', 'badan_usaha_id', 'division_id', 'region_id', 'cluster_id', 'status'];
            foreach ($basicFields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = in_array($field, ['code', 'name', 'address', 'district'])
                        ? strtoupper($validated[$field])
                        : $validated[$field];
                }
            }
            $updateData['owner_name'] = strtoupper($validated['owner_name']);
            $updateData['owner_phone'] = $validated['owner_phone'];
            $updateData['location'] = strtoupper($validated['location']);
            // Semua field file dikosongkan
            if (!empty($tempFiles)) {
                $updateData['photo_shop_sign'] = '';
                $updateData['photo_front'] = '';
                $updateData['photo_left'] = '';
                $updateData['photo_right'] = '';
                $updateData['photo_id_card'] = '';
                $updateData['video'] = '';
            }

            $outlet->update($updateData);

            // Dispatch job untuk proses file dan update field file di database
            if (!empty($tempFiles)) {
                \App\Jobs\ProcessFileUploadJob::dispatch(
                    $tempFiles,
                    Outlet::class,
                    $outlet->id,
                    Auth::id()
                );
            }

            DB::commit();

            $outlet->load([
                'badanUsaha:' . implode(',', BadanUsaha::LIST_COLUMNS),
                'division:' . implode(',', Division::LIST_COLUMNS),
                'region:' . implode(',', Region::LIST_COLUMNS),
                'cluster:' . implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(
                OutletResource::make($outlet),
                !empty($tempFiles)
                    ? 'Outlet berhasil diupdate. File sedang diproses di background.'
                    : 'Outlet berhasil diupdate'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating outlet: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal update outlet');
        }
    }

    /**
     * Upgrade outlet from LEAD level to NOO level
     */
    public function upgrade(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. Find outlet
            $outlet = Outlet::find($id);
            if (!$outlet) {
                DB::rollBack();
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            // 2. Validasi level
            if ($outlet->level !== 'LEAD') {
                DB::rollBack();
                return ResponseFormatter::error(
                    null,
                    'Outlet tidak dapat diupgrade. Hanya outlet dengan level "LEAD" yang dapat diupgrade ke level "NOO".',
                    422
                );
            }

            // 3. Validasi file
            $validator = Validator::make($request->all(), [
                'photo_id_card' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ], [
                'photo_id_card.required' => 'Foto KTP pemilik wajib diupload untuk upgrade outlet.',
                'photo_id_card.image' => 'File harus berupa gambar.',
                'photo_id_card.mimes' => 'Format foto harus JPG, JPEG, atau PNG.',
                'photo_id_card.max' => 'Ukuran foto maksimal 2MB.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal upgrade outlet');
            }

            $photoIdCard = $request->file('photo_id_card');
            if (!FileUploadService::isValidPhoto($photoIdCard)) {
                DB::rollBack();
                return ResponseFormatter::error(
                    null,
                    'Format foto KTP tidak didukung. Gunakan format JPG, JPEG, atau PNG.',
                    422
                );
            }
            if (!FileUploadService::isValidPhotoSize($photoIdCard)) {
                DB::rollBack();
                return ResponseFormatter::error(
                    null,
                    'Ukuran foto KTP terlalu besar. Maksimal 2MB.',
                    422
                );
            }

            // Upload ke temp folder, field photo_id_card dikosongkan dulu
            $tempFiles = [];
            $tempFiles['photo_id_card'] = FileUploadService::uploadPhotoAsync(
                $photoIdCard,
                'outlet',
                $outlet->code ?? 'outlet_' . $outlet->id
            );

            // Hapus file lama jika ada
            if (!empty($outlet->photo_id_card)) {
                FileUploadService::deleteFile($outlet->photo_id_card);
            }

            // Update outlet: field photo_id_card dikosongkan, level diubah
            $outlet->update([
                'level' => 'NOO',
                'photo_id_card' => '', // Akan diupdate oleh job
            ]);

            // Dispatch job untuk proses file dan update field file di database
            \App\Jobs\ProcessFileUploadJob::dispatch(
                $tempFiles,
                Outlet::class,
                $outlet->id,
                Auth::id()
            );

            // Simpan ke OutletHistory
            OutletHistory::create([
                'outlet_id' => $outlet->id,
                'from_level' => 'LEAD',
                'to_level' => 'NOO',
                'requested_by' => Auth::id(),
                'approved_by' => null,
                'approval_status' => null,
                'requested_at' => now(),
                'approved_at' => now(),
                'approval_notes' => null,
            ]);

            DB::commit();

            $outlet->load([
                'badanUsaha:' . implode(',', BadanUsaha::LIST_COLUMNS),
                'division:' . implode(',', Division::LIST_COLUMNS),
                'region:' . implode(',', Region::LIST_COLUMNS),
                'cluster:' . implode(',', Cluster::LIST_COLUMNS),
            ]);

            Log::info('Outlet upgraded successfully', [
                'outlet_id' => $outlet->id,
                'outlet_code' => $outlet->code,
                'from_level' => 'LEAD',
                'to_level' => 'NOO',
                'upgraded_by' => Auth::id(),
            ]);

            return ResponseFormatter::success(
                OutletResource::make($outlet),
                'Outlet berhasil diupgrade dari level "LEAD" ke level "NOO". Foto KTP sedang diproses di background.'
            );
        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error upgrading outlet: ' . $error->getMessage(), [
                'outlet_id' => $id,
                'error' => $error->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return ResponseFormatter::serverError('Gagal upgrade outlet');
        }
    }

    /**
     * Helper method - Handle file uploads ASYNC (for high performance)
     */
    private function handleFileUploadsAsync(Request $request, $action = 'outlet')
    {
        $tempFiles = [];

        // Handle photo uploads
        $photoFields = ['photo_shop_sign', 'photo_front', 'photo_left', 'photo_right', 'photo_id_card'];
        $uploadedPhotos = [];

        foreach ($photoFields as $field) {
            if ($request->hasFile($field)) {
                $uploadedPhotos[$field] = $request->file($field);
            }
        }

        if (!empty($uploadedPhotos)) {
            $tempPhotoFiles = FileUploadService::uploadOutletPhotosAsync($uploadedPhotos, $action);
            $tempFiles = array_merge($tempFiles, $tempPhotoFiles);
        }

        // Handle video upload
        if ($request->hasFile('video')) {
            $tempVideoFile = FileUploadService::uploadVideoAsync($request->file('video'), $action);
            $tempFiles['video'] = $tempVideoFile;
        }

        return $tempFiles;
    }

    /**
     * Get outlet with custom fields
     * This demonstrates how to retrieve an outlet with its custom field values
     */
    public function showWithCustomFields(Request $request, $id)
    {
        try {
            $outlet = Outlet::with([
                'badanUsaha',
                'division',
                'region',
                'cluster',
                'customFieldValues.customField'
            ])->find($id);

            if (!$outlet) {
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            return ResponseFormatter::success(
                new \App\Http\Resources\OutletWithCustomFieldsResource($outlet),
                'Detail outlet dengan custom fields berhasil diambil'
            );
        } catch (Exception $error) {
            Log::error('Error getting outlet with custom fields: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal mengambil detail outlet dengan custom fields');
        }
    }
}
