<h1>Вы успешно приобрели билет на мероприятие «{{ $event->title }}»</h1>

<p>Дата начала: {{ $event->starts_at->format('d.m.Y H:i') }}</p>

<p>Ваши билеты:</p>
<ul>
    @foreach ($tickets as $ticket)
        <li>{{ $ticket->number }} — {{ $ticket->ticketType->name }}</li>
    @endforeach
</ul>

<p>Покажите номер билета на входе.</p>
