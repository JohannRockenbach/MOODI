<x-mail::message>
# {{ $title }}

{{ $body }}

<x-mail::button :url="$actionUrl" color="success">
Ver Promoción
</x-mail::button>

---

**{{ config('app.name') }}**  
*Tu restaurante de confianza*

Gracias por elegirnos,<br>
El equipo de {{ config('app.name') }}

<x-mail::subcopy>
Si tienes alguna pregunta, no dudes en contactarnos.
</x-mail::subcopy>
</x-mail::message>
