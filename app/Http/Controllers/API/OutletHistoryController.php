<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OutletHistoryResource;
use App\Models\Outlet;
use App\Models\OutletHistory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OutletHistoryController extends Controller
{
    /**
     * Get history for an outlet
     */
    public function history(Request $request, $outletId)
    {
        try {
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));

            $outlet = Outlet::find($outletId);
            if (!$outlet) {
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            $histories = OutletHistory::with(['requestedBy', 'approvedBy'])
                ->where('outlet_id', $outletId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $histories->getCollection()->transform(function ($history) {
                return OutletHistoryResource::make($history);
            });

            return ResponseFormatter::paginated($histories, 'History outlet berhasil diambil');
        } catch (Exception $error) {
            Log::error('Error getting outlet history: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal mengambil history outlet');
        }
    }

    /**
     * Request history change for an outlet
     */
    public function requestOutletHistory(Request $request, $outletId)
    {
        DB::beginTransaction();
        try {
            $outlet = Outlet::find($outletId);
            if (!$outlet) {
                DB::rollBack();
                return ResponseFormatter::notFound('Outlet tidak ditemukan');
            }

            $validator = Validator::make($request->all(), [
                'to_level' => 'required|string|in:LEAD,NOO,MEMBER',
                'notes' => 'nullable|string|max:1000',
            ], [
                'to_level.required' => 'Level tujuan wajib diisi.',
                'to_level.in' => 'Level tujuan tidak valid.',
                'notes.max' => 'Catatan maksimal 1000 karakter.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal request perubahan history');
            }

            $validated = $validator->validated();

            if ($outlet->level === $validated['to_level']) {
                DB::rollBack();
                return ResponseFormatter::error(null, 'Level outlet sudah sama dengan yang diminta');
            }

            $pendingRequest = $outlet->pendingOutletHistory;
            if ($pendingRequest) {
                DB::rollBack();
                return ResponseFormatter::error(null, 'Masih ada request perubahan history yang pending');
            }

            $validTransitions = $this->getValidTransitions($outlet->level);
            if (!in_array($validated['to_level'], $validTransitions)) {
                DB::rollBack();
                return ResponseFormatter::error(null, "Perubahan level dari {$outlet->level} ke {$validated['to_level']} tidak diizinkan");
            }

            $outletHistory = $outlet->requestOutletHistory(
                $validated['to_level'],
                Auth::user()->id,
                $validated['notes'] ?? null
            );

            DB::commit();

            $outletHistory->load(['requestedBy', 'approvedBy']);

            return ResponseFormatter::success(
                OutletHistoryResource::make($outletHistory),
                $outletHistory->approval_status === OutletHistory::STATUS_AUTO_APPROVED
                    ? 'History outlet berhasil diubah'
                    : 'Request perubahan history berhasil dibuat dan menunggu approval'
            );

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error requesting history change: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal request perubahan history');
        }
    }

    /**
     * Get pending approvals (for approvers)
     */
    public function pendingApprovals(Request $request)
    {
        try {
            $perPage = $request->input('per_page', config('business.pagination.default_per_page'));

            $pendingApprovals = OutletHistory::with(['outlet', 'requestedBy'])
                ->where('approval_status', OutletHistory::STATUS_PENDING)
                ->orderBy('requested_at', 'asc')
                ->paginate($perPage);

            $pendingApprovals->getCollection()->transform(function ($approval) {
                return OutletHistoryResource::make($approval);
            });

            return ResponseFormatter::paginated($pendingApprovals, 'Pending approvals berhasil diambil');
        } catch (Exception $error) {
            Log::error('Error getting pending approvals: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal mengambil pending approvals');
        }
    }

    /**
     * Approve or reject history change
     */
    public function processApproval(Request $request, $historyId)
    {
        DB::beginTransaction();
        try {
            $outletHistory = OutletHistory::with('outlet')->find($historyId);
            if (!$outletHistory) {
                DB::rollBack();
                return ResponseFormatter::notFound('Request perubahan history tidak ditemukan');
            }

            if ($outletHistory->approval_status !== OutletHistory::STATUS_PENDING) {
                DB::rollBack();
                return ResponseFormatter::error(null, 'Request sudah diproses sebelumnya');
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|string|in:approve,reject',
                'notes' => 'nullable|string|max:1000',
            ], [
                'action.required' => 'Action wajib diisi.',
                'action.in' => 'Action harus approve atau reject.',
                'notes.max' => 'Catatan maksimal 1000 karakter.',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ResponseFormatter::validation($validator->errors(), 'Gagal memproses approval');
            }

            $validated = $validator->validated();

            $isApproved = $validated['action'] === 'approve';

            $outletHistory->update([
                'approval_status' => $isApproved ? OutletHistory::STATUS_APPROVED : OutletHistory::STATUS_REJECTED,
                'approved_by' => Auth::user()->id,
                'approved_at' => now(),
                'approval_notes' => $validated['notes'] ?? null,
            ]);

            if ($isApproved) {
                $outletHistory->outlet->update(['level' => $outletHistory->to_level]);
            }

            DB::commit();

            $outletHistory->load(['outlet', 'requestedBy', 'approvedBy']);

            return ResponseFormatter::success(
                OutletHistoryResource::make($outletHistory),
                $isApproved ? 'History change berhasil diapprove' : 'History change berhasil direject'
            );

        } catch (Exception $error) {
            DB::rollBack();
            Log::error('Error processing approval: ' . $error->getMessage());
            return ResponseFormatter::serverError('Gagal memproses approval');
        }
    }

    /**
     * Get valid transitions for current level
     */
    private function getValidTransitions(string $currentLevel): array
    {
        $transitions = [
            OutletHistory::OUTLET_LEVEL_LEAD => [OutletHistory::OUTLET_LEVEL_NOO],
            OutletHistory::OUTLET_LEVEL_NOO => [OutletHistory::OUTLET_LEVEL_MEMBER],
            OutletHistory::OUTLET_LEVEL_MEMBER => [],
        ];

        return $transitions[$currentLevel] ?? [];
    }
}
