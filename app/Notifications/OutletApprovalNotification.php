<?php

namespace App\Notifications;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class OutletApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $outlet;
    protected $status; // 'approved' or 'rejected'
    protected $approvedBy;
    protected $notes;
    protected $newCode;
    protected $newLimit;

    public function __construct(Outlet $outlet, string $status, User $approvedBy, ?string $notes = null, ?string $newCode = null, ?int $newLimit = null)
    {
        $this->outlet = $outlet;
        $this->status = $status;
        $this->approvedBy = $approvedBy;
        $this->notes = $notes;
        $this->newCode = $newCode;
        $this->newLimit = $newLimit;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $data = [
            'outlet_id' => $this->outlet->id,
            'outlet_name' => $this->outlet->name,
            'outlet_code' => $this->outlet->code,
            'status' => $this->status,
            'approved_by' => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ],
            'timestamp' => now()->toISOString(),
        ];

        if ($this->status === 'approved') {
            $data['title'] = '🎉 Outlet Disetujui!';
            $data['message'] = "Outlet {$this->outlet->name} telah disetujui dan menjadi MEMBER";
            $data['type'] = 'success';
            $data['icon'] = 'check-circle';
            $data['color'] = 'green';

            if ($this->newCode) {
                $data['new_code'] = $this->newCode;
            }
            if ($this->newLimit) {
                $data['new_limit'] = $this->newLimit;
            }
        } else {
            $data['title'] = '❌ Outlet Ditolak';
            $data['message'] = "Outlet {$this->outlet->name} ditolak untuk menjadi MEMBER";
            $data['type'] = 'error';
            $data['icon'] = 'x-circle';
            $data['color'] = 'red';

            if ($this->notes) {
                $data['rejection_reason'] = $this->notes;
            }
        }

        return $data;
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}
