<?php

declare(strict_types=1);

namespace %theme_namespace%\Providers;

use Illuminate\Support\ServiceProvider;
use Pollora\Block\Infrastructure\Services\BlockRegistrar;

class BlocksServiceProvider extends ServiceProvider
{
    public function boot(BlockRegistrar $registrar): void
    {
        $directory = dirname(__DIR__, 2).'/resources/views/blocks';

        // Deferred to `init`, which is when WordPress accepts block
        // registrations at all.
        //
        // Calling registerDirectory() straight from boot() looked right and
        // did nothing: a theme's providers boot before WordPress has defined
        // register_block_type(), and the registrar answers that by returning
        // immediately. No error, no notice — the blocks simply never existed,
        // on every framework version. Theme v1.4.0 shipped that way.
        if (! function_exists('add_action')) {
            return;
        }

        add_action('init', static function () use ($registrar, $directory): void {
            $registrar->registerDirectory(
                directory: $directory,
                containerName: 'theme',
            );
        });
    }
}
