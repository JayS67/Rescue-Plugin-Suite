# Rescue Plugin Suite registry framework

The registry framework is the architectural source of truth for new and migrated modules. Modules register their capabilities once, then the core uses those registrations to build navigation, settings, help, analytics, setup steps, integrations, imports, exports and resets.

## Registry types

`Plugin_UI_Suite_Registry` stores these primary types:

- `modules`
- `settings`
- `navigation`
- `integrations`
- `analytics`
- `permissions`
- `help`
- `notifications`
- `assets`
- `migrations`
- `updates`
- `setup_steps`

## Feature flags

Every module should register flags:

```php
Plugin_UI_Suite_Registry::register_module('payments', [
  'name' => 'Payments',
  'flags' => [
    'installed' => true,
    'enabled' => true,
    'hidden' => false,
    'experimental' => false,
    'beta' => false,
    'deprecated' => false,
    'future_premium' => false,
  ],
]);
```

Hidden, disabled or future-premium modules are excluded from registry-driven navigation and settings rendering.

## Settings metadata

Each registered setting may declare module, page, section, field ID, label, description, field type, default value, validation callback, sanitisation callback, conditional visibility rules, dependencies, capability, exportability, sensitivity, search keywords, tooltip and help text.

The registry can render settings pages, render field types, sanitise registry-owned saves, search settings, export settings and omit sensitive values unless an administrator explicitly opts in.

## Navigation

Navigation entries declare their context (`admin`, `tab`, `payments`, etc.), module, slug, label, capability, callback and order. Core admin tabs are now built through `Plugin_UI_Suite_Registry::render_tabs()`, and root admin menu entries are built through `Plugin_UI_Suite_Registry::add_admin_menus()`.

## Integrations

Integration records should include configuration fields, capabilities, supported features, conditional settings and help guides. Payment providers now register with the integration registry so the admin UI can determine which provider settings are relevant.

## Help, analytics and setup

Help guides, analytics panels and setup steps should be registered by the owning module. The Registry tab reads these registries directly, which lets future Help Centre, Analytics and Setup Wizard screens populate themselves from installed modules.

## Import, export and reset

The Registry tab provides registry-based export, import and module reset actions. Sensitive settings are excluded from export by default. Module reset restores only the selected module from defaults.

## Migration rule

When migrating existing manual code, do it in two steps:

1. Register the module, fields, navigation, help, analytics and setup metadata.
2. Replace the manual renderer with registry rendering once the field metadata fully represents the old UI.

Do not add new settings without registering them.
