<?php

declare(strict_types=1);

namespace App\Domain\Platform\Registry;

interface ModuleRegistryInterface
{
    /**
     * Get all registered core module definitions.
     *
     * @return array<string, ModuleDefinition>
     */
    public function getModules(): array;

    /**
     * Get a specific module definition by key.
     */
    public function getModule(string $moduleKey): ?ModuleDefinition;

    /**
     * Check if a module key is registered in the static catalog.
     */
    public function hasModule(string $moduleKey): bool;

    /**
     * Get all declared actions across all modules.
     *
     * @return array<string, ActionDefinition>
     */
    public function getDeclaredActions(): array;

    /**
     * Check if a permission string is a registered declared action.
     */
    public function isActionRegistered(string $actionToken): bool;

    /**
     * Check if an action is delegable by Workspace Managers to Staff.
     */
    public function isDelegable(string $actionToken): bool;

    /**
     * Check if an action is strictly non-delegable managerial action.
     */
    public function isStrictlyNonDelegable(string $actionToken): bool;

    /**
     * Check if a module is enabled for a specific workspace.
     */
    public function isModuleEnabledForWorkspace(string $moduleKey, ?string $workspaceId): bool;
}
