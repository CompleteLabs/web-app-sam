<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OutletResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
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
                'badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'division:'.implode(',', Division::LIST_COLUMNS),
                'region:'.implode(',', Region::LIST_COLUMNS),
                'cluster:'.implode(',', Cluster::LIST_COLUMNS),
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
                'badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'division:'.implode(',', Division::LIST_COLUMNS),
                'region:'.implode(',', Region::LIST_COLUMNS),
                'cluster:'.implode(',', Cluster::LIST_COLUMNS),
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
            // 1. Comprehensive validation dengan custom messages
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:20|unique:outlets,code',
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
                'code.unique' => 'Kode outlet sudah digunakan.',
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

            // 2. Normalize phone using service
            $validated['owner_phone'] = PhoneService::normalize($validated['owner_phone']);

            // 3. Handle file uploads
            $uploadedFiles = $this->handleFileUploads($request, 'store');

            // 4. Prepare outlet data
            $outletData = [
                'code' => strtoupper($validated['code']),
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
            ];

            // 5. Merge file uploads
            $outletData = array_merge($outletData, $uploadedFiles);

            // 6. Create outlet
            $outlet = Outlet::create($outletData);

            DB::commit();

            // 7. Load relationships for response
            $outlet->load([
                'badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'division:'.implode(',', Division::LIST_COLUMNS),
                'region:'.implode(',', Region::LIST_COLUMNS),
                'cluster:'.implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(OutletResource::make($outlet), 'Outlet berhasil ditambahkan');

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error creating outlet: '.$error->getMessage());

            return ResponseFormatter::serverError('Gagal menambahkan outlet');
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. Find outlet
            $outlet = Outlet::find($id);
            if (! $outlet) {
                DB::rollBack();

                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            // 2. Comprehensive validation
            $validator = Validator::make($request->all(), [
                'code' => 'sometimes|string|max:20|unique:outlets,code,'.$id,
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

            // 3. Normalize phone using service
            if (isset($validated['owner_phone'])) {
                $validated['owner_phone'] = PhoneService::normalize($validated['owner_phone']);
            }

            // 4. Handle file uploads if any
            $uploadedFiles = $this->handleFileUploads($request, 'update');

            // 4.1. Delete old files if new ones are uploaded
            foreach ($uploadedFiles as $field => $newFileName) {
                if (!empty($outlet->$field)) {
                    FileUploadService::deleteFile($outlet->$field);
                }
            }

            // 5. Prepare update data
            $updateData = [];

            // Basic fields
            $basicFields = ['code', 'name', 'address', 'district', 'badan_usaha_id', 'division_id', 'region_id', 'cluster_id', 'status'];
            foreach ($basicFields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = in_array($field, ['code', 'name', 'address', 'district'])
                        ? strtoupper($validated[$field])
                        : $validated[$field];
                }
            }

            // Required fields that are always updated
            $updateData['owner_name'] = strtoupper($validated['owner_name']);
            $updateData['owner_phone'] = $validated['owner_phone'];
            $updateData['location'] = strtoupper($validated['location']);

            // 6. Merge file uploads
            $updateData = array_merge($updateData, $uploadedFiles);

            // 7. Update outlet
            $outlet->update($updateData);

            DB::commit();

            // 8. Load relationships for response
            $outlet->load([
                'badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'division:'.implode(',', Division::LIST_COLUMNS),
                'region:'.implode(',', Region::LIST_COLUMNS),
                'cluster:'.implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(OutletResource::make($outlet), 'Outlet berhasil diupdate');

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating outlet: '.$error->getMessage());

            return ResponseFormatter::serverError('Gagal update outlet');
        }
    }

    /**
     * Helper method - Handle file uploads
     */
    private function handleFileUploads(Request $request, $action = 'store')
    {
        $uploadedFiles = [];

        // Handle photo uploads
        $photoFields = ['photo_shop_sign', 'photo_front', 'photo_left', 'photo_right', 'photo_id_card'];
        $uploadedPhotos = [];

        foreach ($photoFields as $field) {
            if ($request->hasFile($field)) {
                $uploadedPhotos[$field] = $request->file($field);
            }
        }

        if (! empty($uploadedPhotos)) {
            $fileNames = FileUploadService::uploadOutletPhotos($uploadedPhotos, $action);
            $uploadedFiles = array_merge($uploadedFiles, $fileNames);
        }

        // Handle video upload
        if ($request->hasFile('video')) {
            $videoName = FileUploadService::uploadVideo($request->file('video'), $action);
            $uploadedFiles['video'] = $videoName;
        }

        return $uploadedFiles;
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
