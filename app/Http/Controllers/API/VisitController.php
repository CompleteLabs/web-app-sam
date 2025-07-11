<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\VisitResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Visit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));
            $page = $request->input('page', 1);
            $sortColumn = $request->input('sort_column', 'visit_date');
            $sortDirection = $request->input('sort_direction', 'desc');
            $search = $request->input('search');
            $filters = $request->input('filters', []);

            $query = Visit::with([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
                'user:id,name,username,tm_id',
                'user.role:id,name',
            ])->where('user_id', Auth::user()->id);

            // Search global (nama outlet, kode outlet)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('outlet', function ($qo) use ($search) {
                        $qo->where('name', 'like', "%$search%")
                            ->orWhere('code', 'like', "%$search%");
                    });
                });
            }

            // Filter dinamis: hanya month, date, type
            if (! empty($filters)) {
                foreach ($filters as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if ($key === 'date') {
                        $query->whereDate('visit_date', $value);
                    } elseif ($key === 'month') {
                        $query->whereMonth('visit_date', $value);
                    } elseif ($key === 'type') {
                        if (is_array($value)) {
                            $query->whereIn('type', $value);
                        } else {
                            $query->where('type', $value);
                        }
                    }
                }
            }

            // Sorting dinamis (whitelist kolom untuk keamanan)
            $allowedSorts = ['visit_date', 'check_in_time', 'check_out_time', 'type', 'durasi_visit'];
            if (! in_array($sortColumn, $allowedSorts)) {
                $sortColumn = 'visit_date';
            }
            $query->orderBy($sortColumn, $sortDirection);
            // Tambahkan secondary sort agar urutan konsisten jika visit_date sama
            $query->orderBy('id', 'desc');

            $visit = $query->paginate($perPage, Visit::LIST_COLUMNS, 'page', $page);

            // Transform to resource
            $visit->getCollection()->transform(function ($visitItem) {
                return VisitResource::make($visitItem);
            });

            return ResponseFormatter::paginated($visit, 'Data kunjungan berhasil diambil');
        } catch (Exception $error) {
            return ResponseFormatter::serverError('Maaf, terjadi kendala saat mengambil data kunjungan. Silakan coba lagi.');
        }
    }

    public function show($id)
    {
        try {
            $visit = Visit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.division',
                'outlet.cluster',
            ])->findOrFail($id);

            return ResponseFormatter::success(VisitResource::make($visit), 'Detail visit berhasil diambil');
        } catch (Exception $error) {
            return ResponseFormatter::notFound('Maaf, data visit tidak ditemukan.');
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // 1. Comprehensive validation dengan custom messages
            $validator = Validator::make($request->all(), [
                'outlet_id' => 'required|integer|exists:outlets,id',
                'type' => 'required|string|in:EXTRACALL,PLANNED',
                'checkin_location' => 'required|string|max:500',
                'checkin_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ], [
                'outlet_id.required' => 'Outlet wajib dipilih.',
                'outlet_id.exists' => 'Outlet tidak ditemukan.',
                'type.required' => 'Tipe kunjungan wajib dipilih.',
                'type.in' => 'Tipe kunjungan tidak valid.',
                'checkin_location.required' => 'Lokasi check-in wajib diisi.',
                'checkin_photo.required' => 'Foto check-in wajib diupload.',
                'checkin_photo.image' => 'File harus berupa gambar.',
                'checkin_photo.mimes' => 'Format foto harus jpg, jpeg, atau png.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal melakukan check-in');
            }

            $validated = $validator->validated();

            // 2. Get outlet data
            $outlet = Outlet::findOrFail($validated['outlet_id']);

            // 3. Business logic validations
            $validationResult = $this->validateVisitRules($outlet->id);
            if ($validationResult !== true) {
                DB::rollBack();

                return $validationResult;
            }

            // 4. Handle photo upload asynchronously
            $tempFiles = $this->handleFileUploadsAsync($request, 'visit');

            // 5. Create visit (without photo - will be updated by job)
            $visit = Visit::create([
                'visit_date' => Carbon::today(),
                'user_id' => Auth::user()->id,
                'outlet_id' => $outlet->id,
                'type' => $validated['type'],
                'checkin_location' => $validated['checkin_location'],
                'checkin_time' => Carbon::now(),
                'checkin_photo' => '', // Will be updated by job
            ]);

            // 6. Dispatch job for file processing
            if (!empty($tempFiles)) {
                \App\Jobs\ProcessFileUploadJob::dispatch(
                    $tempFiles,
                    Visit::class,
                    $visit->id,
                    Auth::id()
                );
            }

            DB::commit();

            // 7. Load relationships for response
            $visit->load([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
                'user:id,name,username',
            ]);

            return ResponseFormatter::success(
                VisitResource::make($visit),
                'Berhasil check-in. Foto sedang diproses di background.'
            );

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error during check-in: '.$error->getMessage());

            return ResponseFormatter::serverError('Maaf, terjadi kendala saat memproses permintaan Anda. Silakan coba lagi.');
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. Find visit
            $visit = Visit::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if (! $visit) {
                DB::rollBack();

                return ResponseFormatter::notFound('Visit tidak ditemukan');
            }

            // 2. Check if already checked out
            if ($visit->checkout_time) {
                DB::rollBack();

                return ResponseFormatter::error(null, 'Visit sudah di-checkout sebelumnya');
            }

            // 3. Comprehensive validation
            $validator = Validator::make($request->all(), [
                'checkout_location' => 'required|string|max:500',
                'checkout_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'transaction' => 'required|string|max:1000',
                'report' => 'required|string|max:2000',
            ], [
                'checkout_location.required' => 'Lokasi check-out wajib diisi.',
                'checkout_photo.required' => 'Foto check-out wajib diupload.',
                'checkout_photo.image' => 'File harus berupa gambar.',
                'checkout_photo.mimes' => 'Format foto harus jpg, jpeg, atau png.',
                'transaction.required' => 'Informasi transaksi wajib diisi.',
                'report.required' => 'Laporan kunjungan wajib diisi.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal melakukan check-out');
            }

            $validated = $validator->validated();

            // 4. Business logic validation
            $validationResult = $this->validateCheckoutRules($visit);
            if ($validationResult !== true) {
                DB::rollBack();

                return $validationResult;
            }

            // 5. Calculate duration
            $checkinTime = Carbon::parse($visit->checkin_time);
            $checkoutTime = Carbon::now();
            $duration = $checkinTime->diffInMinutes($checkoutTime);

            // 6. Handle photo upload asynchronously
            $tempFiles = $this->handleFileUploadsAsync($request, 'visit');

            // 7. Update visit (without photo - will be updated by job)
            $visit->update([
                'checkout_location' => $validated['checkout_location'],
                'checkout_time' => $checkoutTime,
                'checkout_photo' => '', // Will be updated by job
                'transaction' => $validated['transaction'],
                'report' => $validated['report'],
                'duration' => $duration,
            ]);

            // 8. Dispatch job for file processing
            if (!empty($tempFiles)) {
                \App\Jobs\ProcessFileUploadJob::dispatch(
                    $tempFiles,
                    Visit::class,
                    $visit->id,
                    Auth::id()
                );
            }

            DB::commit();

            // 9. Load relationships for response
            $visit->load([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
                'user:id,name,username',
            ]);

            return ResponseFormatter::success(
                VisitResource::make($visit),
                'Berhasil check-out. Foto sedang diproses di background.'
            );

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error during check-out: '.$error->getMessage());

            return ResponseFormatter::serverError('Maaf, data visit tidak dapat diperbarui saat ini. Silakan coba lagi.');
        }
    }

    /**
     * Helper method - Validate visit business rules
     */
    private function validateVisitRules($outletId)
    {
        $today = Carbon::today();
        $userId = Auth::user()->id;

        // 1. Cek apakah ada visit berjalan (checkout_time masih null) hari ini
        $activeVisit = Visit::whereDate('visit_date', $today)
            ->where('user_id', $userId)
            ->whereNull('checkout_time')
            ->first();

        if ($activeVisit) {
            return ResponseFormatter::error(null, 'Masih ada visit yang berjalan, silakan check-out terlebih dahulu.', 400);
        }

        // 2. Cek apakah sudah pernah visit ke outlet ini hari ini
        $existingVisit = Visit::whereDate('visit_date', $today)
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->first();

        if ($existingVisit) {
            return ResponseFormatter::error(null, 'Anda sudah pernah visit ke outlet ini hari ini.', 400);
        }

        // 3. Check maximum visits per day (from config)
        $maxVisitsPerDay = config('business.visit.max_visits_per_day', 10);
        $todayVisitsCount = Visit::whereDate('visit_date', $today)
            ->where('user_id', $userId)
            ->count();

        if ($todayVisitsCount >= $maxVisitsPerDay) {
            return ResponseFormatter::error(null, "Maksimal {$maxVisitsPerDay} kunjungan per hari.", 400);
        }

        return true;
    }

    /**
     * Helper method - Validate checkout business rules
     */
    private function validateCheckoutRules($visit)
    {
        // 1. Check minimum visit duration (from config)
        $minDurationMinutes = config('business.visit.min_duration_minutes', 10);
        $checkinTime = Carbon::parse($visit->checkin_time);
        $now = Carbon::now();
        $currentDuration = $checkinTime->diffInMinutes($now);

        if ($currentDuration < $minDurationMinutes) {
            return ResponseFormatter::error(null, "Durasi kunjungan minimal {$minDurationMinutes} menit.", 400);
        }

        return true;
    }

    public function destroy($id)
    {
        try {
            $visit = Visit::findOrFail($id);
            $visit->delete();

            return ResponseFormatter::success(null, 'Visit berhasil dihapus');
        } catch (Exception $error) {
            return ResponseFormatter::serverError('Maaf, data visit tidak dapat dihapus saat ini. Silakan coba lagi.');
        }
    }

    public function check(Request $request)
    {
        try {
            $request->validate([
                'outlet_id' => ['required'],
            ]);

            $outlet = Outlet::find($request->outlet_id);

            // Cek apakah ada visit berjalan (checkout_time masih null) hari ini
            $visitBerjalan = Visit::whereDate('visit_date', date('Y-m-d'))
                ->where('user_id', Auth::user()->id)
                ->whereNull('checkout_time')
                ->first();
            if ($visitBerjalan) {
                return ResponseFormatter::error(null, 'Masih ada visit yang berjalan, silakan check-out terlebih dahulu.', 400);
            }

            // Cek apakah sudah pernah visit ke outlet_id ini hari ini
            $visit = Visit::whereDate('visit_date', date('Y-m-d'))
                ->where('user_id', Auth::user()->id)
                ->where('outlet_id', $outlet->id)
                ->latest()
                ->first();

            if (! $visit) {
                // Belum pernah visit ke outlet ini hari ini
                return ResponseFormatter::success(null, 'Anda belum melakukan check-in di outlet ini hari ini. Silakan lakukan check-in terlebih dahulu.');
            } else {
                // Sudah pernah visit ke outlet ini hari ini (baik sudah checkout maupun belum)
                return ResponseFormatter::error(null, 'Anda sudah pernah visit ke outlet ini hari ini.', 400);
            }
        } catch (Exception $error) {
            return ResponseFormatter::serverError();
        }
    }

    /**
     * Handle file uploads asynchronously and return temp file info
     *
     * @param Request $request
     * @param string $folder
     * @return array
     */
    private function handleFileUploadsAsync(Request $request, string $folder): array
    {
        $tempFiles = [];

        // Handle checkin photo
        if ($request->hasFile('checkin_photo')) {
            $tempFiles['checkin_photo'] = FileUploadService::uploadPhotoAsync(
                $request->file('checkin_photo'),
                $folder . '-checkin'
            );
        }

        // Handle checkout photo
        if ($request->hasFile('checkout_photo')) {
            $tempFiles['checkout_photo'] = FileUploadService::uploadPhotoAsync(
                $request->file('checkout_photo'),
                $folder . '-checkout'
            );
        }

        return $tempFiles;
    }
}
