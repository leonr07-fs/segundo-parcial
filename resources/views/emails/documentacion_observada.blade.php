<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .header { background-color: #d9534f; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .doc-list { background-color: #f9f9f9; padding: 15px; border-left: 4px solid #d9534f; margin: 15px 0; }
        .doc-item { margin-bottom: 10px; }
        .btn { display: inline-block; padding: 12px 20px; background-color: #0275d8; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 15px; }
        .footer { font-size: 0.8em; color: #777; text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Acción Requerida: Documentación Observada</h2>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $postulante->nombres }}</strong>,</p>
            <p>Hemos revisado la documentación que enviaste para tu postulación (Código: <strong>{{ $inscripcion->codigo }}</strong>). Lamentablemente, hemos encontrado observaciones en algunos documentos y necesitamos que los corrijas para poder continuar con tu proceso.</p>
            
            <div class="doc-list">
                <h3>Documentos a corregir:</h3>
                <ul>
                    @foreach($documentos as $doc)
                        <li class="doc-item">
                            <strong>{{ strtoupper($doc->tipo) }}</strong><br>
                            <span style="color: #d9534f;">Motivo del rechazo:</span> {{ $doc->observacion ?? 'El documento no cumple con los requisitos visuales o de formato.' }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p>Por favor, haz clic en el siguiente enlace para subir únicamente los archivos corregidos. No es necesario que llenes tus datos personales de nuevo.</p>
            
            <div style="text-align: center;">
                <a href="{{ $link }}" class="btn">Subsanar Documentos Ahora</a>
            </div>

            <p style="margin-top: 20px;">Si tienes alguna duda, puedes responder a este correo.</p>
        </div>
        <div class="footer">
            Este es un mensaje automático del Sistema CUP FICCT. Por favor, no comparta su enlace de corrección con terceros.
        </div>
    </div>
</body>
</html>
