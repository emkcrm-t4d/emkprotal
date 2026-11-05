<?php

declare(strict_types=1);

use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Livewire\Component;

arch('controllers and livewire components strictly forbid withoutGlobalScope per DEC-01')
    ->expect(['App\Http\Controllers', 'App\Livewire'])
    ->not->toUse('withoutGlobalScope')
    ->not->toUse('withoutGlobalScopes');

arch('application code strictly forbids dynamic evaluation per DEC-02')
    ->expect('App')
    ->not->toUse(['eval', 'create_function']);

arch('domain classes do not invoke global route helper per architecture discipline')
    ->expect('App\Domain')
    ->not->toUse('route');

test('all workspace livewire components implement ValidatesWorkspaceOnHydration per DEC-01', function () {
    $dir = realpath(__DIR__ . '/../../app/Livewire');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $checkedCount = 0;

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace([$dir . DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $className = 'App\\Livewire\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            // Exclude public forms, portal member components, and navigation shell
            if (
                str_starts_with($className, 'App\\Livewire\\Portal\\') ||
                $className === 'App\\Livewire\\Forms\\PublicMembershipForm' ||
                $className === 'App\\Livewire\\Admin\\AdminShell'
            ) {
                continue;
            }

            if (class_exists($className) && is_subclass_of($className, Component::class)) {
                $traits = class_uses_recursive($className);
                expect(isset($traits[ValidatesWorkspaceOnHydration::class]))
                    ->toBeTrue("Component [{$className}] does not use ValidatesWorkspaceOnHydration trait.");
                $checkedCount++;
            }
        }
    }

    expect($checkedCount)->toBeGreaterThanOrEqual(30);
});

test('app codebase strictly avoids eval and create_function in source code per DEC-02', function () {
    $dir = realpath(__DIR__ . '/../../app');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $tokens = token_get_all(file_get_contents($file->getPathname()));

            foreach ($tokens as $token) {
                if (is_array($token)) {
                    $tokenType = $token[0];
                    $tokenText = strtolower($token[1]);

                    // Assert no T_EVAL token
                    expect($tokenType)->not->toBe(T_EVAL, "File [{$file->getPathname()}] contains eval() statement.");

                    if ($tokenType === T_STRING && $tokenText === 'create_function') {
                        $this->fail("File [{$file->getPathname()}] contains create_function() call.");
                    }
                }
            }
        }
    }
});

test('domain classes strictly avoid global route helper in source code per architecture discipline', function () {
    $dir = realpath(__DIR__ . '/../../app/Domain');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());

            // Use the canonical regex matching global route( but not ->route( or ::route(
            $matches = preg_match('/(?<!->|::|\bfunction\s)\broute\s*\(/i', $content);
            expect($matches)->toBe(0, "File [{$file->getPathname()}] uses global route() helper instead of URL::route().");
        }
    }
});
