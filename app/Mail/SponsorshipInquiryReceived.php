<?php

namespace App\Mail;

use App\Models\SponsorshipInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SponsorshipInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SponsorshipInquiry $inquiry)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New sponsorship inquiry from {$this->inquiry->full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sponsorship-inquiry-received',
        );
    }
}
