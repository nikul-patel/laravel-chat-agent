# Package Extraction Plan

## Goal
Create a fully independent Laravel package that bundles all required assets and can be integrated into another Laravel 12 application with minimal configuration.

## Guiding Constraints
- Use Laravel package conventions (service provider, auto-discovery, publishing assets).
- Bundle migrations, models, services, MCP components, routes, views, config, and frontend assets (Soha CSS/JS) inside the package.
- Ensure publishable assets are optional while default behavior works without customization.
- Maintain test coverage and follow existing project coding standards.

## Work Breakdown
1. **Review Existing Implementation**
   - Audit current project structure to identify code that belongs in the package (models, controllers, services, MCP resources, assets, config).
   - Note dependencies, environment variables, and any assumptions.

2. **Create Package Skeleton**
   - Scaffold `packages/<Vendor>/<Package>` structure with PSR-4 autoloading and dedicated `composer.json`.
   - Add base service provider with auto-discovery and registration entries in the root `composer.json`.

3. **Configuration & Publishing**
   - Draft default config file with sensible defaults.
   - Register publishable assets for config, views, translations, and assets.
   - Document optional environment keys the package respects.

4. **Database Layer**
   - Move/create migrations into package and ensure they are auto-loaded via `loadMigrationsFrom` while also publishable.
   - Relocate or implement models with proper namespace, relationships, and casts.
   - Provide factories/seeders if the feature relies on seeded data.

5. **Application Services**
   - Implement service classes (and interfaces if needed) encapsulating existing logic.
   - Bind services within the provider for easy injection.

6. **MCP Components**
   - Package MCP Resource, Server, and Tools implementations and register them through the provider.
   - Ensure command/console hooks or route integrations required by MCP are included.

7. **HTTP Layer**
   - Bundle controllers, routes, form requests, resources, and middleware (if any) inside the package.
   - Ensure routes are loaded with customizable prefixes/guards and can be disabled if the host app chooses.

8. **Frontend Assets**
   - Move Soha CSS/JS (and any other assets) into `resources/` within the package.
   - Provide publishing tags and ensure assets load correctly by default via Laravel’s asset helper or Vite config guidance.

9. **Testing**
   - Create or migrate PHPUnit tests into the package namespace.
   - Ensure factories and testing setup allow consumers to run package tests (consider Orchestra Testbench if required).

10. **Documentation**
    - Update root README or package README with installation, configuration, MCP setup instructions, and troubleshooting.

11. **Validation**
    - Run pint formatting and targeted PHPUnit tests for the package to confirm functionality.
    - Prepare summary of changes and next steps for user verification.

## Next Action
Begin with step 1: review existing implementation artifacts related to the feature (models, services, MCP components, assets) to determine everything that must be migrated.

## Release Reminder
- After merging changes, tag the repository with the desired semantic version (e.g. `v0.1.1`) so the Release workflow runs SQLite + MySQL tests and publishes the GitHub release automatically.
- Next step: push a tag like `git tag v0.1.1 && git push origin v0.1.1` to exercise the updated pipeline.
