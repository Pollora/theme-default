<?php

declare(strict_types=1);

namespace Theme\Default\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! $view->offsetExists('menu')) {
                $view->with('menu', $this->getMenu('primary'));
            }
        });
    }

    private function getMenu(string $location): array
    {
        try {
            $items = iterator_to_array(menu($location));
            if (! empty($items)) {
                return $items;
            }
        } catch (\Exception) {
            // Menu not configured, fall through to fallback
        }

        return $this->getFallbackMenu();
    }

    private function getFallbackMenu(): array
    {
        $items = [];

        $items[] = (object) [
            'title' => __('Home', 'pollora'),
            'url' => home_url('/'),
            'ID' => 0,
            'classes' => [],
            'current' => is_front_page(),
        ];

        $pages = get_pages(['sort_column' => 'menu_order,post_title', 'post_status' => 'publish']);

        foreach ($pages as $page) {
            $items[] = (object) [
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'ID' => $page->ID,
                'classes' => [],
                'current' => is_page($page->ID),
            ];
        }

        return $items;
    }
}