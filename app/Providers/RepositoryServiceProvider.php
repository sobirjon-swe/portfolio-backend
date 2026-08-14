<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ExperienceRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\NewsRepositoryInterface;
use App\Repositories\Contracts\PageViewRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\SkillRepositoryInterface;
use App\Repositories\Contracts\SocialLinkRepositoryInterface;
use App\Repositories\Contracts\TechnologyRepositoryInterface;
use App\Repositories\Eloquent\ExperienceRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\NewsRepository;
use App\Repositories\Eloquent\PageViewRepository;
use App\Repositories\Eloquent\PostRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\SkillRepository;
use App\Repositories\Eloquent\SocialLinkRepository;
use App\Repositories\Eloquent\TechnologyRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Map repository interfaces to their Eloquent implementations.
     *
     * @var array<class-string, class-string>
     */
    private array $repositories = [
        TechnologyRepositoryInterface::class => TechnologyRepository::class,
        ProjectRepositoryInterface::class => ProjectRepository::class,
        PostRepositoryInterface::class => PostRepository::class,
        NewsRepositoryInterface::class => NewsRepository::class,
        SkillRepositoryInterface::class => SkillRepository::class,
        SocialLinkRepositoryInterface::class => SocialLinkRepository::class,
        PageViewRepositoryInterface::class => PageViewRepository::class,
        MessageRepositoryInterface::class => MessageRepository::class,
        ExperienceRepositoryInterface::class => ExperienceRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
