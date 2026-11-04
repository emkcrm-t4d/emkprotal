<?php

declare(strict_types=1);

namespace App\Domain\Platform\Registry;

readonly class ModuleDefinition
{
    /**
     * @param  array<string, ActionDefinition>  $actions
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $domainNamespace,
        public string $description,
        public array $actions = [],
    ) {}
}
