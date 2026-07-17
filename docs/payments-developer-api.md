# Payments Developer API

The Payments module is extensible through WordPress actions, filters, and small registration helpers. Third-party plugins can add providers, campaigns, payment types, checkout handlers, reporting integrations, and UI/settings adjustments without editing suite files.

Load integrations from a plugin after the suite is available, for example on `plugins_loaded` or by using the documented hooks below.

## Registration helpers

### `registerPaymentProvider( StraySafe_Payment_Provider_Interface $provider )`

Registers a payment provider object with the gateway manager.

```php
add_action('straysafe_payments_register_providers', function ($manager) {
  registerPaymentProvider(new Acme_Direct_Bank_Provider());
});
```

### `unregisterPaymentProvider( string $provider_id )`

Removes a provider from the runtime registry.

```php
add_action('straysafe_payments_register_providers', function () {
  unregisterPaymentProvider('sumup');
});
```

### `registerPaymentCampaign( string $slug, array $campaign )`

Registers a reusable campaign that can be used with `[donation_widget campaign="winter"]`.

```php
add_action('init', function () {
  registerPaymentCampaign('winter', [
    'title' => 'Winter rescue appeal',
    'subtitle' => 'Help animals stay safe this winter',
    'description' => 'Every gift funds urgent food, shelter and care.',
    'goal' => 10000,
    'progress' => 2500,
    'primary' => '#185abc',
    'success_url' => home_url('/winter-thank-you/'),
    'thank_you_message' => 'Thank you for supporting the winter appeal.',
    'one_off_presets' => [
      ['amount' => '10', 'description' => 'Feeds an animal for a day'],
      ['amount' => '25', 'description' => 'Funds warm bedding'],
    ],
  ]);
});
```

### `registerPaymentType( string $type, array $definition )`

Registers custom payment type metadata for validation and custom checkout handlers. The existing widget submits `one_off` and `recurring`; custom frontends can submit additional types to the existing checkout endpoint.

```php
add_action('init', function () {
  registerPaymentType('sponsor', [
    'label' => 'Sponsorship',
    'settings_key' => 'recurring',
    'recurring' => true,
  ]);
});
```

## Provider API

Providers implement `StraySafe_Payment_Provider_Interface` or extend `StraySafe_Abstract_Payment_Provider`.

```php
final class Acme_Direct_Bank_Provider extends StraySafe_Abstract_Payment_Provider {
  public function __construct() {
    $this->id = 'acme_bank';
    $this->name = 'Acme Bank';
    $this->capabilities = [
      'subscriptions' => true,
      'variable_subscriptions' => true,
      'variable_one_off_amounts' => true,
      'fee_recovery' => false,
      'gift_aid' => true,
    ];
    $this->fields = [
      'api_key' => ['label' => 'API key', 'type' => 'password', 'required' => true],
    ];
  }

  public function create_checkout(array $request, array $settings) {
    if (empty($settings['api_key'])) {
      return new WP_Error('acme_missing_key', 'Acme Bank API key is missing.');
    }

    return [
      'url' => add_query_arg([
        'amount' => $request['amount'],
        'currency' => $request['currency'],
      ], 'https://checkout.example.test/donate'),
      'id' => wp_generate_uuid4(),
      'provider' => $this->get_id(),
    ];
  }
}
```

## Checkout handler example

Use `straysafe_payments_custom_checkout_handler` to bypass the active provider for a custom payment type or provider-specific scenario.

```php
add_filter('straysafe_payments_custom_checkout_handler', function ($result, $request, $settings, $provider) {
  if (($request['type'] ?? '') !== 'sponsor') {
    return $result;
  }

  return [
    'url' => add_query_arg([
      'amount' => $request['amount'],
      'currency' => $request['currency'],
      'campaign' => $request['campaign'] ?? '',
    ], home_url('/sponsorship-checkout/')),
    'id' => wp_generate_uuid4(),
    'provider' => 'sponsorship',
  ];
}, 10, 4);
```

## Actions

### `straysafe_payments_register_providers`

Fires when the gateway manager is ready to accept provider registration.

Parameters:

- `StraySafe_Payment_Gateway_Manager $manager`

### `straysafe_payments_register_settings`

Fires after the module registers its Settings API option.

Parameters: none.

### `straysafe_payments_enqueue_assets`

Fires after the payment widget frontend style and script are enqueued.

Parameters: none.

### `straysafe_payments_before_checkout_validation`

Fires after nonce verification and before amount/type validation.

Parameters:

- `array $request` — raw checkout context: `type`, `amount`, and `campaign`.
- `array $settings` — active settings after campaign overrides.

### `straysafe_payments_before_checkout`

Fires after validation and before checkout creation.

Parameters:

- `array $request` — normalized checkout request passed toward the provider.
- `array $settings` — active settings after campaign overrides.

### `straysafe_payments_checkout_created`

Fires after checkout is created successfully.

Parameters:

- `array $result` — provider checkout result.
- `array $request` — normalized checkout request.
- `array $settings` — active settings after campaign overrides.

### `straysafe_payments_checkout_failed`

Fires when checkout creation returns a `WP_Error`.

Parameters:

- `WP_Error $result`
- `array $request`
- `array $settings`

### `straysafe_payments_payment_event_recorded`

Fires after a payment event is saved to payment history.

Parameters:

- `string $payment_id`
- `string $status`
- `string $event_id`
- `array $data`
- `string $provider`

### `straysafe_payments_subscription_event_recorded`

Fires after a subscription event is saved.

Parameters:

- `string $subscription_id`
- `string $status`
- `string $event_id`
- `array $data`
- `string $provider`

### `straysafe_payments_audit_logged`

Fires after an audit log entry is written.

Parameters:

- `array $entry`

## Filters

### `straysafe_payments_providers`

Filters registered providers.

Parameters:

- `array $providers`

Return `array` of provider objects keyed by provider ID.

### `straysafe_payments_settings`

Filters loaded settings after defaults and registered campaigns are merged.

Parameters:

- `array $settings`

Return the adjusted settings array.

### `straysafe_payments_admin_capability`

Filters the capability required to view and update the Payments admin page.

Parameters:

- `string $capability`

Return a WordPress capability string.

### `straysafe_payments_frontend_config`

Filters localized JavaScript config for the donation widget.

Parameters:

- `array $config`

Return the adjusted config array.

### `straysafe_payments_payment_types`

Filters payment type definitions available to checkout handlers.

Parameters:

- `array $types`

Return an array keyed by payment type ID.

### `straysafe_payments_campaigns`

Filters reusable campaigns available to shortcodes and checkout.

Parameters:

- `array $campaigns`

Return an array keyed by campaign slug.

### `straysafe_payments_applied_campaign_settings`

Filters settings after a campaign is applied, or when a requested campaign is missing.

Parameters:

- `array $settings`
- `string $slug`
- `array|null $campaign`

Return the adjusted settings array.

### `straysafe_payments_widget_settings`

Filters settings used to render a specific donation widget instance.

Parameters:

- `array $settings`
- `array $atts`

Return the adjusted settings array.

### `straysafe_payments_fee_profile`

Filters provider fee recovery calculations.

Parameters:

- `array $profile` — `rate` and `fixed` values.
- `string $provider_id`
- `string $currency`

Return the adjusted fee profile.

### `straysafe_payments_sanitized_settings`

Filters settings immediately before they are stored by the Settings API.

Parameters:

- `array $clean`
- `array $input`

Return sanitized settings.

### `straysafe_payments_checkout_validation_error`

Filters checkout validation result.

Parameters:

- `null|string $validation_error`
- `array $request`
- `array $settings`

Return `null` for valid checkout or a user-facing error string.

### `straysafe_payments_custom_checkout_handler`

Lets extensions completely handle checkout creation.

Parameters:

- `null|array|WP_Error $result`
- `array $request`
- `array $settings`
- `StraySafe_Payment_Provider_Interface $provider`

Return `null` to continue with the active provider, a checkout result array to stop provider execution, or `WP_Error` to fail checkout.

### `straysafe_payments_checkout_request`

Filters the normalized checkout request before provider execution.

Parameters:

- `array $request`
- `array $settings`
- `StraySafe_Payment_Provider_Interface $provider`

Return the adjusted request array.

### `straysafe_payments_checkout_result`

Filters the provider checkout result before it is returned to the browser.

Parameters:

- `array|WP_Error $result`
- `array $request`
- `array $settings`
- `StraySafe_Payment_Provider_Interface $provider`

Return an adjusted result array or `WP_Error`.

## Recording events from custom integrations

Custom webhook handlers can use the public recording methods to populate the built-in history, dashboard, and audit log.

```php
StraySafe_Payments_Module::record_payment_event(
  'pay_123',
  'succeeded',
  'acme_event_123',
  ['provider' => 'acme_bank', 'amount' => '25.00', 'currency' => 'GBP'],
  'acme_bank'
);

StraySafe_Payments_Module::record_subscription_event(
  'sub_123',
  'active',
  'acme_event_124',
  ['provider' => 'acme_bank', 'amount' => '10.00', 'currency' => 'GBP'],
  'acme_bank'
);

StraySafe_Payments_Module::audit('acme_webhook_received', ['event_id' => 'acme_event_123']);
```
