<?php

namespace App\Mail;

use App\Models\Parking;
use App\Models\Platform;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParkingCapacityReachedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Parking $parking,
        public Platform $platform,
        public Carbon $day,
        public int $occupied,
        public int $capacity
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Richiesta blocco disponibilità - {$this->parking->name} - {$this->day->format('d/m/Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.parking-capacity-reached',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
