<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Outlet;
use App\Models\OutletHistory;
use App\Services\NotificationService;
use App\Notifications\OutletApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function notification_api_returns_user_notifications()
    {
        $user = User::factory()->create();

        // Create a test notification
        $user->notify(new OutletApprovalNotification(
            new Outlet(['name' => 'Test Outlet', 'code' => 'TEST001']),
            'approved',
            $user
        ));

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'data',
                        'read_at',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'unread_count'
                ]
            ]);
    }

    /** @test */
    public function user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();

        // Create a test notification
        $user->notify(new OutletApprovalNotification(
            new Outlet(['name' => 'Test Outlet', 'code' => 'TEST001']),
            'approved',
            $user
        ));

        $notification = $user->notifications()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function user_can_get_unread_count()
    {
        $user = User::factory()->create();

        // Create multiple test notifications
        for ($i = 0; $i < 3; $i++) {
            $user->notify(new OutletApprovalNotification(
                new Outlet(['name' => "Test Outlet $i", 'code' => "TEST00$i"]),
                'approved',
                $user
            ));
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 3
            ]);
    }

    /** @test */
    public function notification_service_sends_outlet_approval_notification()
    {
        Notification::fake();

        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $outlet = Outlet::factory()->create();

        // Create outlet history with requester
        OutletHistory::create([
            'outlet_id' => $outlet->id,
            'from_level' => 'NOO',
            'to_level' => 'MEMBER',
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'approval_status' => 'APPROVED',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        $this->actingAs($approver);

        NotificationService::sendOutletApproval($outlet, 'NEW001', 5000000);

        Notification::assertSentTo(
            $requester,
            OutletApprovalNotification::class,
            function ($notification) use ($outlet) {
                return $notification->outlet->id === $outlet->id &&
                       $notification->status === 'approved';
            }
        );
    }

    /** @test */
    public function notification_service_sends_outlet_rejection_notification()
    {
        Notification::fake();

        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $outlet = Outlet::factory()->create();

        // Create outlet history with requester
        OutletHistory::create([
            'outlet_id' => $outlet->id,
            'from_level' => 'NOO',
            'to_level' => 'NOO',
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
            'approval_status' => 'REJECTED',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        $this->actingAs($approver);

        NotificationService::sendOutletRejection($outlet, 'Tidak memenuhi syarat');

        Notification::assertSentTo(
            $requester,
            OutletApprovalNotification::class,
            function ($notification) use ($outlet) {
                return $notification->outlet->id === $outlet->id &&
                       $notification->status === 'rejected';
            }
        );
    }
}
