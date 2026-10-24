<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

// Демо-данные для показа: php artisan migrate:fresh --seed
// Аккаунты: admin@chipta.uz, organizer@chipta.uz, user@chipta.uz, пароль у всех password
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Администратор',
            'email' => 'admin@chipta.uz',
            'role' => UserRole::Admin,
        ]);

        $organiser = User::factory()->create([
            'name' => 'Chipta Events',
            'email' => 'organizer@chipta.uz',
            'role' => UserRole::Organiser,
        ]);

        User::factory()->create([
            'name' => 'Азиз',
            'email' => 'user@chipta.uz',
            'role' => UserRole::Participant,
        ]);

        $categories = collect([
            'Концерты' => 'Живая музыка всех жанров',
            'Театр' => 'Спектакли и постановки',
            'Стендап' => 'Юмор и открытые микрофоны',
            'Спорт' => 'Матчи и турниры',
            'Выставки' => 'Искусство, фото и дизайн',
            'Детям' => 'Спектакли и праздники для детей',
        ])->map(fn (string $description, string $name) => Category::factory()->create([
            'name' => $name,
            'description' => $description,
        ]));

        $venues = collect([
            'navo' => ['Концертный зал «Navo»', 'Ташкент, Юнусабадский район', 1200],
            'sahna' => ['Театр «Sahna»', 'Ташкент, Мирабадский район', 600],
            'kulgu' => ['Стендап-клуб «Kulgu»', 'Ташкент, Шайхантахурский район', 120],
            'yoshlik' => ['Стадион «Yoshlik»', 'Ташкент, Чиланзарский район', 15000],
            'rang' => ['Галерея «Rang»', 'Ташкент, Яккасарайский район', 400],
        ])->map(fn (array $venue) => Venue::factory()->create([
            'name' => $venue[0],
            'address' => $venue[1],
            'description' => null,
            'capacity' => $venue[2],
        ]));

        // tickets: [название, цена в сумах, количество]
        $events = [
            [
                'title' => 'Джазовый вечер «Blue Notes»',
                'description' => "Живой джаз от столичных музыкантов: стандарты и авторские композиции.\nДва отделения с антрактом.",
                'category' => 'Концерты',
                'venue' => 'navo',
                'starts' => [6, '19:00'],
                'minutes' => 180,
                'tickets' => [['Стандарт', 150000, 120], ['VIP', 400000, 20]],
            ],
            [
                'title' => 'Симфонический оркестр: музыка из кино',
                'description' => 'Темы из любимых фильмов в исполнении симфонического оркестра.',
                'category' => 'Концерты',
                'venue' => 'navo',
                'starts' => [14, '19:30'],
                'minutes' => 120,
                'tickets' => [['Партер', 200000, 300], ['Балкон', 120000, 200]],
            ],
            [
                'title' => 'Спектакль «Ревизор»',
                'description' => 'Классическая комедия Гоголя в новой постановке.',
                'category' => 'Театр',
                'venue' => 'sahna',
                'starts' => [10, '18:30'],
                'minutes' => 180,
                'tickets' => [['Партер', 120000, 150], ['Амфитеатр', 80000, 100]],
            ],
            [
                'title' => 'Стендап: открытый микрофон',
                'description' => 'Начинающие и опытные комики проверяют новый материал на зрителях.',
                'category' => 'Стендап',
                'venue' => 'kulgu',
                'starts' => [3, '20:00'],
                'minutes' => 120,
                'tickets' => [['Входной билет', 50000, 80]],
            ],
            [
                'title' => 'Футбол: финал городского кубка',
                'description' => 'Решающий матч сезона. Вход на стадион открывается за час до начала.',
                'category' => 'Спорт',
                'venue' => 'yoshlik',
                'starts' => [18, '18:00'],
                'minutes' => 120,
                'tickets' => [['Трибуна', 40000, 2000], ['VIP-ложа', 250000, 40]],
            ],
            [
                'title' => 'Выставка «Ranglar»: современное искусство',
                'description' => 'Живопись, графика и инсталляции молодых художников.',
                'category' => 'Выставки',
                'venue' => 'rang',
                'starts' => [2, '10:00'],
                'minutes' => 480,
                'tickets' => [['Взрослый', 30000, 300], ['Студенческий', 15000, 200]],
            ],
            [
                'title' => 'Детский спектакль «Золушка»',
                'description' => 'Музыкальная сказка для детей от 4 лет.',
                'category' => 'Детям',
                'venue' => 'sahna',
                'starts' => [8, '11:00'],
                'minutes' => 90,
                'tickets' => [['Детский', 40000, 100], ['Взрослый', 60000, 100]],
            ],
            [
                // Черновик: в каталоге его видит только организатор (EventPolicy::view)
                'title' => 'Новогодний концерт',
                'description' => 'Программа ещё готовится.',
                'category' => 'Концерты',
                'venue' => 'navo',
                'starts' => [60, '20:00'],
                'minutes' => 180,
                'tickets' => [['Стандарт', 200000, 500]],
                'status' => EventStatus::Draft,
            ],
        ];

        foreach ($events as $data) {
            $startsAt = $this->tashkentTime(...$data['starts']);

            $event = Event::factory()->create([
                'title' => $data['title'],
                'description' => $data['description'],
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($data['minutes']),
                'organizer_id' => $organiser->id,
                'category_id' => $categories[$data['category']]->id,
                'venue_id' => $venues[$data['venue']]->id,
                'status' => $data['status'] ?? EventStatus::Published,
            ]);

            foreach ($data['tickets'] as [$name, $price, $quantity]) {
                TicketType::factory()->create([
                    'event_id' => $event->id,
                    'name' => $name,
                    'description' => null,
                    'price' => $price,
                    'quantity' => $quantity,
                ]);
            }
        }
    }

    // В базе время хранится в UTC (config/app.php), а афиша должна быть по Ташкенту (UTC+5)
    private function tashkentTime(int $daysFromNow, string $time): Carbon
    {
        return now('Asia/Tashkent')->addDays($daysFromNow)->setTimeFromTimeString($time)->utc();
    }
}
