<?php

namespace App\Mail;

use App\Models\InscripcionPagos\Inscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentacionObservadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Inscripcion $inscripcion,
        public Collection $documentosObservados
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ATENCIÓN: Tu documentación CUP ha sido observada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.documentacion_observada',
            with: [
                'postulante' => $this->inscripcion->postulante,
                'inscripcion' => $this->inscripcion,
                'documentos' => $this->documentosObservados,
                'link' => url('/postulante/subsanar-documentos/' . $this->inscripcion->codigo)
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
