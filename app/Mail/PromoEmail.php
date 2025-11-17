<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromoEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Los datos de la promoción.
     */
    public string $title;
    public string $body;
    public string $actionUrl;

    /**
     * Create a new message instance.
     *
     * @param string $title Título de la promoción
     * @param string $body Texto del cuerpo del mensaje
     * @param string $actionUrl URL del botón de acción
     */
    public function __construct(string $title, string $body, string $actionUrl)
    {
        $this->title = $title;
        $this->body = $body;
        $this->actionUrl = $actionUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.promo',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
