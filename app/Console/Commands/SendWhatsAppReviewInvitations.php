<?php

namespace App\Console\Commands;

use App\Models\Parking;
use App\Services\WhatsAppReviewService;
use App\Tenancy\ParkingContext;
use Illuminate\Console\Command;

class SendWhatsAppReviewInvitations extends Command
{
    protected $signature = 'whatsapp:send-review-invitations';

    protected $description = 'Invia gli inviti a recensire su Google per le uscite registrate da almeno 24 ore';

    public function handle(ParkingContext $context, WhatsAppReviewService $reviews): int
    {
        Parking::active()->whereNotNull('whatsapp_reviews_enabled_at')->eachById(
            fn (Parking $parking) => $context->run($parking, fn () => $reviews->sendDue($parking))
        );

        return self::SUCCESS;
    }
}
