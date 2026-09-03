<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Parking;
use App\Models\Platform;
use App\Models\ParkingListing;
use App\Models\Reservation;
use App\Models\ParkingProduct;
use App\Services\OverbookingNotificationService;
use App\Mail\ParkingCapacityReachedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Enums\ReservationStatus;

class OverbookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $service;
    private $parking;
    private $platform;
    private $product;
    private $listing;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(OverbookingNotificationService::class);
        
        $this->parking = Parking::create([
            'name' => 'Test Parking',
            'total_spots' => 10,
            'capacity_mode' => 'shared'
        ]);

        $this->platform = Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform',
            'is_active' => true,
            'contact_email' => 'partner@platform.com'
        ]);

        $this->product = ParkingProduct::create([
            'parking_id' => $this->parking->id,
            'name' => 'Test Product',
            'code' => 'TEST-PROD',
            'capacity' => 10,
            'price' => 0,
            'is_active' => true,
        ]);

        $this->listing = ParkingListing::create([
            'parking_id' => $this->parking->id,
            'platform_id' => $this->platform->id,
            'external_id' => 'EXT-123',
            'is_active' => true,
        ]);
        
        Mail::fake();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-06-09 12:00:00'));
    }

    public function test_no_email_if_occupied_less_than_capacity()
    {
        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 9,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertNothingQueued();
    }

    public function test_email_sent_if_occupied_equals_capacity()
    {
        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->hasTo('partner@platform.com') && 
                   $mail->parking->id === $this->parking->id &&
                   $mail->day->isSameDay(now());
        });
    }

    public function test_email_sent_if_occupied_greater_than_capacity()
    {
        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 11,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertQueued(ParkingCapacityReachedMail::class);
    }

    public function test_email_sent_for_future_day_covered_by_reservation()
    {
        // Reservation that starts 3 days from now
        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->addDays(3)->startOfDay(),
            'ends_at' => now()->addDays(5)->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        // It should queue an email for each day (day 3, day 4, day 5)
        Mail::assertQueued(ParkingCapacityReachedMail::class, 3);
        
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->day->isSameDay(now()->addDays(3));
        });
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->day->isSameDay(now()->addDays(4));
        });
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->day->isSameDay(now()->addDays(5));
        });
    }

    public function test_no_duplicate_emails_sent_same_day()
    {
        $reservation1 = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation1);
        
        $reservation2 = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'Jane Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 1,
            'status' => ReservationStatus::Confirmed
        ]);
        
        $this->service->checkAndNotifyForReservation($reservation2);

        Mail::assertQueued(ParkingCapacityReachedMail::class, 1);
    }

    public function test_no_email_sent_if_platform_inactive()
    {
        $this->platform->update(['is_active' => false]);

        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertNothingQueued();
    }

    public function test_no_email_sent_if_platform_has_no_contact_email()
    {
        $this->platform->update(['contact_email' => null]);

        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertNothingQueued();
    }

    public function test_email_sent_to_multiple_platforms_with_active_listings()
    {
        $platform2 = Platform::create([
            'name' => 'Test Platform 2',
            'slug' => 'test-platform-2',
            'is_active' => true,
            'contact_email' => 'partner2@platform.com'
        ]);

        $listing2 = ParkingListing::create([
            'parking_id' => $this->parking->id,
            'platform_id' => $platform2->id,
            'external_id' => 'EXT-456',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        Mail::assertQueued(ParkingCapacityReachedMail::class, 2);
        
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->hasTo('partner@platform.com');
        });
        
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->hasTo('partner2@platform.com');
        });
    }

    public function test_no_email_sent_for_inactive_listing()
    {
        $platform2 = Platform::create([
            'name' => 'Test Platform 2',
            'slug' => 'test-platform-2',
            'is_active' => true,
            'contact_email' => 'partner2@platform.com'
        ]);

        // Inactive listing for platform 2
        $listing2 = ParkingListing::create([
            'parking_id' => $this->parking->id,
            'platform_id' => $platform2->id,
            'external_id' => 'EXT-456',
            'is_active' => false,
        ]);

        $reservation = Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_product_id' => $this->product->id,
            'parking_listing_id' => $this->listing->id,
            'customer_name' => 'John Doe',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->endOfDay(),
            'spots' => 10,
            'status' => ReservationStatus::Confirmed
        ]);

        $this->service->checkAndNotifyForReservation($reservation);

        // Only platform 1 should get the email
        Mail::assertQueued(ParkingCapacityReachedMail::class, 1);
        
        Mail::assertQueued(ParkingCapacityReachedMail::class, function ($mail) {
            return $mail->hasTo('partner@platform.com');
        });
    }
}
