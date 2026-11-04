<?php

declare(strict_types=1);

namespace App\Domain\Platform\Registry;

readonly class ActionDefinition
{
    public bool $isManagerial;

    public function __construct(
        public string $token,
        public string $moduleKey,
        public string $name,
        public string $description,
        public bool $isDelegable,
        public bool $isStrictlyNonDelegable,
        ?bool $isManagerial = null,
    ) {
        $this->isManagerial = $isManagerial ?? $isStrictlyNonDelegable;
    }
}
