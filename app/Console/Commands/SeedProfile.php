<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Experience;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\Technology;
use Illuminate\Console\Command;

/**
 * Fills an empty install with the profile facts that never change per
 * environment: employment history, the tech list and the social links.
 *
 * Entering these through the admin panel is a long session of form-filling,
 * and a fresh deploy starts with none of it — the site renders its frame with
 * nothing inside. This is not a database seeder (`db:seed` also creates a test
 * user with a known factory password, which must never run in production); it
 * is a command safe to run on a live box.
 *
 * Idempotent: each row is matched on its natural key, so running it twice
 * changes nothing and it can be re-run after adding entries below. It never
 * overwrites an existing row — edits made in the admin panel win.
 */
class SeedProfile extends Command
{
    protected $signature = 'portfolio:seed-profile';

    protected $description = 'Create the employment history, technologies and social links if they are missing';

    public function handle(): int
    {
        $created = [
            'experiences' => $this->seedExperiences(),
            'technologies' => $this->seedTechnologies(),
            'skills' => $this->seedSkills(),
            'social links' => $this->seedSocialLinks(),
        ];

        foreach ($created as $label => $count) {
            $this->line($count > 0
                ? "  <info>+</info> {$count} {$label} created"
                : "  <comment>=</comment> {$label} already present, nothing to do");
        }

        $this->newLine();
        $this->info('Done. Edit anything above in the admin panel — re-running this will not undo your changes.');

        return self::SUCCESS;
    }

    private function seedExperiences(): int
    {
        $created = 0;

        foreach ($this->experiences() as $row) {
            $exists = Experience::query()
                ->where('company', $row['company'])
                ->where('start_date', $row['start_date'])
                ->exists();

            if ($exists) {
                continue;
            }

            Experience::query()->create($row);
            $created++;
        }

        return $created;
    }

    private function seedTechnologies(): int
    {
        $created = 0;

        foreach ($this->technologies() as [$name, $icon, $category]) {
            if (Technology::query()->where('name', $name)->exists()) {
                continue;
            }

            Technology::query()->create(['name' => $name, 'icon' => $icon, 'category' => $category]);
            $created++;
        }

        return $created;
    }

    private function seedSkills(): int
    {
        $created = 0;

        foreach ($this->skills() as [$name, $category]) {
            if (Skill::query()->where('name', $name)->exists()) {
                continue;
            }

            Skill::query()->create(['name' => $name, 'category' => $category]);
            $created++;
        }

        return $created;
    }

    private function seedSocialLinks(): int
    {
        $created = 0;

        foreach ($this->socialLinks() as [$platform, $url]) {
            if (SocialLink::query()->where('platform', $platform)->exists()) {
                continue;
            }

            SocialLink::query()->create(['platform' => $platform, 'url' => $url]);
            $created++;
        }

        return $created;
    }

    /**
     * Newest first by sort_order — the listing orders on it, descending.
     *
     * @return list<array<string, mixed>>
     */
    private function experiences(): array
    {
        return [
            [
                'company' => '"Single integrator - UZINFOCOM" LLC',
                'url' => 'https://uzinfocom.uz',
                'start_date' => '06.2026',
                'end_date' => null,
                'sort_order' => 30,
                'role' => [
                    'en' => 'Software Engineer',
                    'uz' => 'Software Engineer',
                    'ru' => 'Software Engineer',
                ],
                'description' => [
                    'en' => "Software Engineer on Uzbekistan's government digital platforms.\nProject details, architecture and technologies are not disclosed.",
                    'uz' => "O‘zbekistonning davlat raqamli platformalari ustida ishlayman.\nLoyiha tafsilotlari, arxitektura va texnologiyalar oshkor qilinmaydi.",
                    'ru' => "Работаю над государственными цифровыми платформами Узбекистана.\nДетали проектов, архитектура и технологии не разглашаются.",
                ],
            ],
            [
                'company' => 'DOCCO',
                'url' => null,
                'start_date' => '03.2025',
                'end_date' => '05.2026',
                'sort_order' => 20,
                'role' => [
                    'en' => 'Backend Engineer',
                    'uz' => 'Backend muhandis',
                    'ru' => 'Backend-инженер',
                ],
                'description' => [
                    'en' => implode("\n", [
                        'Designed and built backend services in PHP, Laravel and MySQL for a production CRM platform.',
                        'Built an HR salary and payroll system: employee management, salary calculation, bonuses, deductions, tax logic and automated payroll reporting.',
                        'Cut average API response time from ~800ms to ~150ms with Redis caching, reducing database load by ~60%.',
                        'Refactored complex queries and designed indexing strategies, cutting execution time on the highest-traffic endpoints by 40–50%.',
                        'Containerised the services with Docker and set up Bitbucket CI/CD, replacing manual releases with an automated pipeline.',
                        'Built logging, monitoring and error tracking that roughly halved mean time to resolution on production issues.',
                    ]),
                    'uz' => implode("\n", [
                        'Production CRM platformasi uchun PHP, Laravel va MySQL’da backend xizmatlarini loyihalab, ishlab chiqdim.',
                        'HR maosh va payroll tizimini qurdim: xodimlar boshqaruvi, maosh hisobi, bonus, ushlab qolishlar, soliq mantiqi va avtomatik hisobotlar.',
                        'Redis kesh orqali API javob vaqtini ~800ms dan ~150ms gacha tushirdim, bazaga yuklamani ~60% kamaytirdim.',
                        'Murakkab so‘rovlarni qayta yozib, indekslash strategiyasini tuzdim — eng yuklamali endpointlarda bajarilish vaqti 40–50% qisqardi.',
                        'Xizmatlarni Docker’ga o‘tkazdim va Bitbucket CI/CD yo‘lga qo‘ydim — qo‘lda relis o‘rniga avtomatik quvur.',
                        'Log, monitoring va xatolarni kuzatish tizimini qurdim — production muammolarini hal qilish vaqti taxminan ikki barobar qisqardi.',
                    ]),
                    'ru' => implode("\n", [
                        'Спроектировал и разработал бэкенд-сервисы на PHP, Laravel и MySQL для production CRM-платформы.',
                        'Построил систему расчёта зарплат и payroll: управление сотрудниками, расчёт зарплаты, бонусы, удержания, налоговая логика и автоматические отчёты.',
                        'Снизил среднее время ответа API с ~800мс до ~150мс за счёт кеширования в Redis, уменьшив нагрузку на базу на ~60%.',
                        'Переписал сложные запросы и выстроил стратегию индексирования — время выполнения на самых нагруженных эндпоинтах сократилось на 40–50%.',
                        'Перевёл сервисы в Docker и настроил Bitbucket CI/CD, заменив ручные релизы автоматическим пайплайном.',
                        'Построил логирование, мониторинг и трекинг ошибок — время устранения production-проблем сократилось примерно вдвое.',
                    ]),
                ],
            ],
            [
                'company' => 'DOCCO',
                'url' => null,
                'start_date' => '01.2025',
                'end_date' => '02.2025',
                'sort_order' => 10,
                'role' => [
                    'en' => 'Backend Engineer Intern',
                    'uz' => 'Backend muhandis (intern)',
                    'ru' => 'Backend-инженер (стажёр)',
                ],
                'description' => [
                    'en' => implode("\n", [
                        'Set up an acceptance testing framework with Codeception, covering the critical features.',
                        'Applied SOLID, DRY and KISS across codebase improvements.',
                        'Optimised MySQL queries through indexing and refactoring, improving common operations by an estimated 35–45%.',
                        'Worked on production systems alongside senior engineers.',
                    ]),
                    'uz' => implode("\n", [
                        'Codeception bilan acceptance test muhitini yo‘lga qo‘ydim, muhim funksiyalarni qopladim.',
                        'Kod yaxshilashlarida SOLID, DRY va KISS tamoyillarini qo‘lladim.',
                        'MySQL so‘rovlarini indekslash va refaktoring orqali optimallashtirdim — keng ishlatiladigan amallar ~35–45% tezlashdi.',
                        'Katta tajribali muhandislar bilan birga production tizimlar ustida ishladim.',
                    ]),
                    'ru' => implode("\n", [
                        'Настроил acceptance-тестирование на Codeception, покрыв критичные функции.',
                        'Применял SOLID, DRY и KISS при улучшении кодовой базы.',
                        'Оптимизировал MySQL-запросы через индексирование и рефакторинг — типовые операции ускорились примерно на 35–45%.',
                        'Работал над production-системами вместе с senior-инженерами.',
                    ]),
                ],
            ],
        ];
    }

    /**
     * The `icon` value is the Simple Icons key the frontend catalog looks up
     * (lowercased name); anything it cannot match falls back to a monogram.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function technologies(): array
    {
        return [
            ['PHP', 'php', 'backend'],
            ['Laravel', 'laravel', 'backend'],
            ['MySQL', 'mysql', 'database'],
            ['PostgreSQL', 'postgresql', 'database'],
            ['Redis', 'redis', 'database'],
            ['Docker', 'docker', 'devops'],
            ['Nginx', 'nginx', 'devops'],
            ['Git', 'git', 'tools'],
            ['React', 'react', 'frontend'],
            ['Vue.js', 'vue.js', 'frontend'],
            ['JavaScript', 'javascript', 'frontend'],
            ['Tailwind CSS', 'tailwind css', 'frontend'],
        ];
    }

    /**
     * What I can do, as opposed to what I use — the tech list above answers the
     * second question and a recruiter needs both. Deliberately kept in English:
     * these are the terms a CV and a job ad share, and translating "query
     * optimization" only makes it harder to match.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function skills(): array
    {
        return [
            ['REST API design & development', 'backend'],
            ['Layered architecture (Controller → Service → Repository)', 'backend'],
            ['Authentication & authorization', 'backend'],
            ['Caching strategy', 'backend'],
            ['Database design & indexing', 'data'],
            ['Query optimization', 'data'],
            ['Automated testing', 'quality'],
            ['Logging, monitoring & error tracking', 'quality'],
            ['CI/CD pipelines', 'delivery'],
            ['Containerized deployment', 'delivery'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function socialLinks(): array
    {
        return [
            ['GitHub', 'https://github.com/sobirjon-swe'],
            ['LinkedIn', 'https://www.linkedin.com/in/sobirjon-swe/'],
            ['Telegram', 'https://t.me/sobirjonswe'],
            ['Email', 'mailto:sobirjon.swe@gmail.com'],
        ];
    }
}
