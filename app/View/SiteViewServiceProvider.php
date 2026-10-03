<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Public-site view wiring: JSX whitespace rules for the ported templates (see
 * JsxWhitespace) and the site-wide view data the layouts need.
 */
final class SiteViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Built assets are referenced root-relative ("/build/assets/…"), never as absolute URLs made
        // from the request's Host header (a forged Host could otherwise poison a shared cache).
        Vite::createAssetPathsUsing(fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/'));

        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->prepareStringsForCompilationUsing(function (string $value) use ($blade): string {
                return JsxWhitespace::applies($blade->getPath(), $value) ? JsxWhitespace::compact($value) : $value;
            });
            // `@partial('view', [...])` / `@partialIf(...)`: like @include but ISOLATED — the partial sees
            // only the data it is given (React props semantics), never the caller's variables.
            // Output is trimmed: a React component never renders whitespace around its root element.
            $blade->directive('partial', fn (string $expr): string => "<?php echo trim(\$__env->make({$expr})->render()); ?>");
            $blade->directive('partialIf', fn (string $expr): string => "<?php \$__partialArgs = [{$expr}]; if (\$__env->exists(\$__partialArgs[0])) echo trim(\$__env->make(...\$__partialArgs)->render()); ?>");
        });
    }
}
