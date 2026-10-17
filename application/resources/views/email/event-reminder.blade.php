<h1>Напоминаем: мероприятие «{{ $event->title }}» уже скоро</h1>

<p>Начало: {{ $event->starts_at->format('d.m.Y H:i') }}</p>

<p>Место проведения: {{ $event->venue->name }}, {{ $event->venue->address }}</p>

<p>Не забудьте взять с собой номер билета.</p>
