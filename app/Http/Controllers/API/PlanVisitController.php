<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanVisitResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlanVisitController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));
            $search = $request->input('search');
            $month = $request->input('month');
            $year = $request->input('year');
            $date = $request->input('filters.date', $request->input('date'));
            $outlet = $request->input('outlet');

            $query = PlanVisit::with([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
            ])->where('user_id', Auth::user()->id);

            if ($search) {
                $query->whereHas('outlet', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('code', 'like', "%$search%");
                });
            }
            if ($month) {
                $query->whereMonth('visit_date', $month);
                if ($year) {
                    $query->whereYear('visit_date', $year);
                }
            }
            if ($date) {
                $query->whereBetween('visit_date', [
                    Carbon::parse($date)->startOfDay(),
                    Carbon::parse($date)->endOfDay()
                ]);
            }
            if ($outlet) {
                $query->where('outlet_id', $outlet);
            }

            $planVisit = $query->orderBy('visit_date')->paginate($perPage, PlanVisit::LIST_COLUMNS, 'page');

            // Transform to resource
            $planVisit->getCollection()->transform(function ($planVisitItem) {
                return PlanVisitResource::make($planVisitItem);
            });

            return ResponseFormatter::paginated($planVisit, 'List plan visit berhasil diambil');
        } catch (Exception $error) {
            return ResponseFormatter::serverError('Maaf, terjadi kendala saat mengambil data plan visit. Silakan coba lagi.');
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // 1. Comprehensive validation dengan custom messages
            $validator = Validator::make($request->all(), [
                'visit_date' => 'required|date|after:today',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ], [
                'visit_date.required' => 'Tanggal kunjungan wajib diisi.',
                'visit_date.date' => 'Format tanggal tidak valid.',
                'visit_date.after' => 'Tanggal kunjungan harus setelah hari ini.',
                'outlet_id.required' => 'Outlet wajib dipilih.',
                'outlet_id.exists' => 'Outlet tidak ditemukan.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal menambahkan plan visit');
            }

            $validated = $validator->validated();

            // 2. Get outlet data
            $outlet = Outlet::findOrFail($validated['outlet_id']);

            // 3. Business logic validation - H-3 rule
            $validationResult = $this->validatePlanAdvance($validated['visit_date']);
            if ($validationResult !== true) {
                DB::rollBack();

                return $validationResult;
            }

            // 4. Check for duplicates
            if ($this->isDuplicatePlan($validated['visit_date'], $outlet->id)) {
                DB::rollBack();

                return ResponseFormatter::error(null, 'Plan visit untuk outlet dan tanggal ini sudah ada');
            }

            // 5. Create plan visit
            $planVisit = PlanVisit::create([
                'user_id' => Auth::user()->id,
                'outlet_id' => $outlet->id,
                'visit_date' => Carbon::parse($validated['visit_date']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            // 6. Load relationships for response
            $planVisit->load([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(PlanVisitResource::make($planVisit), 'Plan visit berhasil ditambahkan');

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error creating plan visit: '.$error->getMessage());

            return ResponseFormatter::serverError('Terjadi kesalahan pada server.');
        }
    }

    /**
     * Update plan visit
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. Find plan visit
            $planVisit = PlanVisit::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if (! $planVisit) {
                DB::rollBack();

                return ResponseFormatter::notFound('Plan visit tidak ditemukan');
            }

            // 2. Comprehensive validation
            $validator = Validator::make($request->all(), [
                'visit_date' => 'required|date|after:today',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ], [
                'visit_date.required' => 'Tanggal kunjungan wajib diisi.',
                'visit_date.date' => 'Format tanggal tidak valid.',
                'visit_date.after' => 'Tanggal kunjungan harus setelah hari ini.',
                'outlet_id.required' => 'Outlet wajib dipilih.',
                'outlet_id.exists' => 'Outlet tidak ditemukan.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return ResponseFormatter::validation($validator->errors(), 'Gagal mengupdate plan visit');
            }

            $validated = $validator->validated();

            // 3. Get outlet data
            $outlet = Outlet::findOrFail($validated['outlet_id']);

            // 4. Business logic validation - H-3 rule
            $validationResult = $this->validatePlanAdvance($validated['visit_date']);
            if ($validationResult !== true) {
                DB::rollBack();

                return $validationResult;
            }

            // 5. Check for duplicates (exclude current plan)
            if ($this->isDuplicatePlan($validated['visit_date'], $outlet->id, $id)) {
                DB::rollBack();

                return ResponseFormatter::error(null, 'Plan visit untuk outlet dan tanggal ini sudah ada');
            }

            // 6. Update plan visit
            $planVisit->update([
                'outlet_id' => $outlet->id,
                'visit_date' => Carbon::parse($validated['visit_date']),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            // 7. Load relationships for response
            $planVisit->load([
                'outlet:'.implode(',', Outlet::LIST_COLUMNS),
                'outlet.badanUsaha:'.implode(',', BadanUsaha::LIST_COLUMNS),
                'outlet.region:'.implode(',', Region::LIST_COLUMNS),
                'outlet.division:'.implode(',', Division::LIST_COLUMNS),
                'outlet.cluster:'.implode(',', Cluster::LIST_COLUMNS),
            ]);

            return ResponseFormatter::success(PlanVisitResource::make($planVisit), 'Plan visit berhasil diupdate');

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error updating plan visit: '.$error->getMessage());

            return ResponseFormatter::serverError('Terjadi kesalahan pada server.');
        }
    }

    /**
     * Helper method - Validate plan advance days
     */
    private function validatePlanAdvance($visitDate)
    {
        $planAdvanceDays = config('business.visit.plan_advance_days');
        $visitDate = Carbon::parse($visitDate);
        $today = Carbon::today();
        $minAllowedDate = $today->copy()->addDays($planAdvanceDays);

        if ($visitDate->lt($minAllowedDate)) {
            return ResponseFormatter::error(null, "Plan visit hanya bisa dibuat minimal H-{$planAdvanceDays}. Silakan pilih tanggal ".$minAllowedDate->format('d-m-Y').' atau setelahnya.');
        }

        return true;
    }

    /**
     * Helper method - Check duplicate plan
     */
    private function isDuplicatePlan($visitDate, $outletId, $excludeId = null)
    {
        $query = PlanVisit::whereDate('visit_date', Carbon::parse($visitDate))
            ->where('user_id', Auth::user()->id)
            ->where('outlet_id', $outletId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function destroy($id)
    {
        try {
            $planVisit = PlanVisit::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if (! $planVisit) {
                return ResponseFormatter::notFound('Plan visit tidak ditemukan');
            }

            // Tidak bisa hapus jika sudah H-X ke bawah dari visit_date
            $deleteAdvanceDays = config('business.visit.delete_advance_days');
            $tanggalVisit = Carbon::parse($planVisit->visit_date);
            $now = Carbon::now();

            // Jika hari ini >= visit_date - deleteAdvanceDays (artinya sudah H-X, H-X+1, atau hari H)
            if ($now->greaterThanOrEqualTo($tanggalVisit->copy()->subDays($deleteAdvanceDays))) {
                return ResponseFormatter::error(null, "Tidak bisa menghapus plan visit pada H-{$deleteAdvanceDays}, H-1, atau hari H. Minimal hanya bisa dihapus sebelum H-".($deleteAdvanceDays + 1).' dari tanggal visit.');
            }

            $planVisit->delete();

            return ResponseFormatter::success(null, 'Plan visit berhasil dihapus');
        } catch (Exception $error) {
            return ResponseFormatter::serverError('Maaf, terjadi kendala saat menghapus plan visit. Silakan coba lagi.');
        }
    }
}
