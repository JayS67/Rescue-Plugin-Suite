<?php
/**
 * Generic provider-based payments module for the suite.
 */
if (!defined('ABSPATH')) exit;

interface StraySafe_Payment_Provider_Interface {
  public function get_id();
  public function get_name();
  public function get_capabilities();
  public function get_configuration_fields();
  public function validate_configuration(array $settings);
  public function create_checkout(array $request, array $settings);
}

abstract class StraySafe_Abstract_Payment_Provider implements StraySafe_Payment_Provider_Interface {
  protected $id = '';
  protected $name = '';
  protected $capabilities = [];
  protected $fields = [];
  public function get_id() { return $this->id; }
  public function get_name() { return $this->name; }
  public function get_capabilities() { return wp_parse_args($this->capabilities, StraySafe_Payment_Gateway_Manager::default_capabilities()); }
  public function get_configuration_fields() { return $this->fields; }
  public function validate_configuration(array $settings) {
    $missing = [];
    foreach ($this->fields as $key => $field) {
      if (!empty($field['required']) && empty($settings[$key])) $missing[] = $field['label'];
    }
    return ['connected' => empty($missing), 'message' => empty($missing) ? __('Configuration present. Live credential checks can be added by the provider adapter.', 'straysafe-ui-suite') : sprintf(__('Missing: %s', 'straysafe-ui-suite'), implode(', ', $missing))];
  }
  public function create_checkout(array $request, array $settings) {
    return new WP_Error('provider_not_connected', __('This provider adapter is ready for credentials but does not include a remote API implementation yet.', 'straysafe-ui-suite'));
  }
}

final class StraySafe_Stripe_Payment_Provider extends StraySafe_Abstract_Payment_Provider {
  const API_BASE = 'https://api.stripe.com/v1/';
  public function __construct(){ $this->id='stripe'; $this->name='Stripe'; $this->capabilities=['subscriptions'=>true,'variable_subscriptions'=>true,'variable_one_off_amounts'=>true,'apple_pay'=>true,'google_pay'=>true,'link'=>true,'gift_aid'=>true,'fee_recovery'=>true,'saved_cards'=>true,'embedded_checkout'=>true]; $this->fields=['publishable_key'=>['label'=>'Publishable Key','type'=>'text','required'=>true],'secret_key'=>['label'=>'Secret Key','type'=>'password','required'=>true],'webhook_secret'=>['label'=>'Webhook Secret','type'=>'password','required'=>false],'test_mode'=>['label'=>'Test Mode','type'=>'checkbox','required'=>false]]; }
  public function validate_configuration(array $settings) {
    $status = parent::validate_configuration($settings);
    if (!$status['connected']) return $status;
    if (strpos((string)$settings['secret_key'], 'sk_') !== 0) return ['connected'=>false,'message'=>__('Stripe secret keys must start with sk_.', 'straysafe-ui-suite')];
    if (strpos((string)$settings['publishable_key'], 'pk_') !== 0) return ['connected'=>false,'message'=>__('Stripe publishable keys must start with pk_.', 'straysafe-ui-suite')];
    return ['connected'=>true,'message'=>__('Stripe credentials are configured. Checkout will use Stripe Checkout with automatic payment methods.', 'straysafe-ui-suite')];
  }
  public function create_checkout(array $request, array $settings) {
    $status = $this->validate_configuration($settings);
    if (empty($status['connected'])) return new WP_Error('stripe_not_configured', $status['message']);
    $amount = isset($request['amount']) ? (float)$request['amount'] : 0;
    if ($amount <= 0) return new WP_Error('stripe_invalid_amount', __('Please choose a valid donation amount.', 'straysafe-ui-suite'));
    $currency = strtolower(preg_replace('/[^a-zA-Z]/', '', $request['currency'] ?? 'gbp')) ?: 'gbp';
    $unit_amount = $this->unit_amount($amount, $currency);
    if ($unit_amount < 1) return new WP_Error('stripe_invalid_amount', __('The selected amount is too small for Stripe.', 'straysafe-ui-suite'));
    $is_recurring = ($request['type'] ?? 'one_off') === 'recurring';
    $line_item = $is_recurring ? ['price'=>$this->recurring_price($settings, $unit_amount, $currency), 'quantity'=>1] : ['price_data'=>['currency'=>$currency,'product_data'=>['name'=>__('Donation', 'straysafe-ui-suite')],'unit_amount'=>$unit_amount], 'quantity'=>1];
    if (is_wp_error($line_item['price'] ?? null)) return $line_item['price'];
    $body = [
      'mode' => $is_recurring ? 'subscription' : 'payment',
      'success_url' => esc_url_raw($request['success_url'] ?? home_url('/')),
      'cancel_url' => esc_url_raw($request['cancel_url'] ?? home_url('/')),
      'automatic_payment_methods' => ['enabled' => 'true'],
      'line_items' => [$line_item],
      'metadata' => ['source'=>'straysafe_payments','donation_type'=>$is_recurring?'recurring':'one_off','amount'=>(string)$amount,'currency'=>strtoupper($currency)],
    ];
    if ($is_recurring) $body['subscription_data'] = ['metadata'=>$body['metadata']];
    else $body['payment_intent_data'] = ['metadata'=>$body['metadata']];
    $session = $this->request('checkout/sessions', $settings, $body);
    if (is_wp_error($session)) return $session;
    if (empty($session['url'])) return new WP_Error('stripe_checkout_missing_url', __('Stripe did not return a Checkout URL.', 'straysafe-ui-suite'));
    return ['url'=>esc_url_raw($session['url']),'id'=>sanitize_text_field($session['id'] ?? ''),'provider'=>'stripe'];
  }
  private function recurring_price(array $settings, $unit_amount, $currency) {
    $lookup_key = 'straysafe_donation_monthly_' . strtolower($currency) . '_' . (int)$unit_amount;
    $existing = $this->request('prices', $settings, ['active'=>'true','limit'=>1,'lookup_keys'=>[$lookup_key]], 'GET');
    if (is_wp_error($existing)) return $existing;
    if (!empty($existing['data'][0]['id'])) return sanitize_text_field($existing['data'][0]['id']);
    $matching = $this->request('prices', $settings, ['active'=>'true','currency'=>$currency,'limit'=>100], 'GET');
    if (is_wp_error($matching)) return $matching;
    foreach (($matching['data'] ?? []) as $candidate) {
      if ((int)($candidate['unit_amount'] ?? 0) === (int)$unit_amount && ($candidate['recurring']['interval'] ?? '') === 'month' && !empty($candidate['id'])) return sanitize_text_field($candidate['id']);
    }
    $price = $this->request('prices', $settings, ['currency'=>$currency,'unit_amount'=>(int)$unit_amount,'recurring'=>['interval'=>'month'],'product_data'=>['name'=>__('Monthly donation', 'straysafe-ui-suite')],'lookup_key'=>$lookup_key], 'POST');
    if (is_wp_error($price)) return $price;
    if (empty($price['id'])) return new WP_Error('stripe_price_missing_id', __('Stripe did not return a recurring Price ID.', 'straysafe-ui-suite'));
    return sanitize_text_field($price['id']);
  }
  private function unit_amount($amount, $currency) {
    $zero_decimal = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
    return in_array(strtolower($currency), $zero_decimal, true) ? (int)round((float)$amount) : (int)round((float)$amount * 100);
  }
  private function request($endpoint, array $settings, array $body=[], $method='POST') {
    $secret = trim((string)($settings['secret_key'] ?? ''));
    if ($secret === '') return new WP_Error('stripe_missing_secret', __('Stripe secret key is not configured.', 'straysafe-ui-suite'));
    $url = self::API_BASE . ltrim($endpoint, '/');
    $args = ['method'=>$method,'timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$secret,'Content-Type'=>'application/x-www-form-urlencoded']];
    if ($method === 'GET' && !empty($body)) $url = add_query_arg($body, $url); else $args['body'] = http_build_query($body, '', '&');
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) return new WP_Error('stripe_request_failed', sprintf(__('Stripe request failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
      $message = $json['error']['message'] ?? __('Stripe returned an error while creating checkout.', 'straysafe-ui-suite');
      return new WP_Error('stripe_api_error', sanitize_text_field($message), ['status'=>$code]);
    }
    return is_array($json) ? $json : new WP_Error('stripe_invalid_response', __('Stripe returned an invalid response.', 'straysafe-ui-suite'));
  }
}
final class StraySafe_PayPal_Payment_Provider extends StraySafe_Abstract_Payment_Provider {
  const LIVE_API = 'https://api-m.paypal.com/';
  const SANDBOX_API = 'https://api-m.sandbox.paypal.com/';
  const PLAN_CACHE_KEY = 'straysafe_payments_paypal_plan_cache_v1';
  public function __construct(){ $this->id='paypal'; $this->name='PayPal'; $this->capabilities=['subscriptions'=>true,'variable_subscriptions'=>true,'variable_one_off_amounts'=>true,'apple_pay'=>false,'google_pay'=>false,'fee_recovery'=>true,'saved_cards'=>false,'embedded_checkout'=>false]; $this->fields=['client_id'=>['label'=>'Client ID','type'=>'text','required'=>true],'secret'=>['label'=>'Secret','type'=>'password','required'=>true],'webhook_id'=>['label'=>'Webhook ID','type'=>'text','required'=>false],'sandbox_mode'=>['label'=>'Sandbox Mode','type'=>'checkbox','required'=>false]]; }
  public function validate_configuration(array $settings) {
    $status = parent::validate_configuration($settings);
    if (!$status['connected']) return $status;
    return ['connected'=>true,'message'=>!empty($settings['sandbox_mode']) ? __('PayPal sandbox credentials are configured.', 'straysafe-ui-suite') : __('PayPal live credentials are configured.', 'straysafe-ui-suite')];
  }
  public function create_checkout(array $request, array $settings) {
    $status = $this->validate_configuration($settings);
    if (empty($status['connected'])) return new WP_Error('paypal_not_configured', $status['message']);
    $amount = isset($request['amount']) ? (float)$request['amount'] : 0;
    if ($amount <= 0) return new WP_Error('paypal_invalid_amount', __('Please choose a valid donation amount.', 'straysafe-ui-suite'));
    $currency = strtoupper(preg_replace('/[^a-zA-Z]/', '', $request['currency'] ?? 'GBP')) ?: 'GBP';
    $value = number_format($amount, 2, '.', '');
    $success = esc_url_raw($request['success_url'] ?? home_url('/'));
    $cancel = esc_url_raw($request['cancel_url'] ?? home_url('/'));
    $is_recurring = ($request['type'] ?? 'one_off') === 'recurring';
    if ($is_recurring) {
      $plan_id = $this->subscription_plan($settings, $value, $currency);
      if (is_wp_error($plan_id)) return $plan_id;
      $checkout = $this->request('v1/billing/subscriptions', $settings, ['plan_id'=>$plan_id,'custom_id'=>'straysafe_payments','application_context'=>['brand_name'=>get_bloginfo('name'),'user_action'=>'SUBSCRIBE_NOW','return_url'=>$success,'cancel_url'=>$cancel]], 'POST');
    } else {
      $checkout = $this->request('v2/checkout/orders', $settings, ['intent'=>'CAPTURE','purchase_units'=>[['description'=>__('Donation', 'straysafe-ui-suite'),'custom_id'=>'straysafe_payments','amount'=>['currency_code'=>$currency,'value'=>$value]]],'application_context'=>['brand_name'=>get_bloginfo('name'),'user_action'=>'PAY_NOW','return_url'=>$success,'cancel_url'=>$cancel]], 'POST');
    }
    if (is_wp_error($checkout)) return $checkout;
    $url = $this->approval_url($checkout['links'] ?? []);
    if (!$url) return new WP_Error('paypal_checkout_missing_url', __('PayPal did not return an approval URL.', 'straysafe-ui-suite'));
    return ['url'=>esc_url_raw($url),'id'=>sanitize_text_field($checkout['id'] ?? ''),'provider'=>'paypal'];
  }
  private function subscription_plan(array $settings, $value, $currency) {
    $cache = get_option(self::PLAN_CACHE_KEY, []);
    $mode = !empty($settings['sandbox_mode']) ? 'sandbox' : 'live';
    $key = $mode . '_' . strtolower($currency) . '_' . str_replace('.', '_', $value);
    if (!empty($cache[$key])) return sanitize_text_field($cache[$key]);
    $product = $this->request('v1/catalogs/products', $settings, ['name'=>__('Monthly donation', 'straysafe-ui-suite'),'type'=>'SERVICE','category'=>'NONPROFIT'], 'POST');
    if (is_wp_error($product)) return $product;
    if (empty($product['id'])) return new WP_Error('paypal_product_missing_id', __('PayPal did not return a product ID for subscriptions.', 'straysafe-ui-suite'));
    $plan = $this->request('v1/billing/plans', $settings, ['product_id'=>$product['id'],'name'=>sprintf(__('Monthly donation %1$s %2$s', 'straysafe-ui-suite'), $currency, $value),'status'=>'ACTIVE','billing_cycles'=>[['frequency'=>['interval_unit'=>'MONTH','interval_count'=>1],'tenure_type'=>'REGULAR','sequence'=>1,'total_cycles'=>0,'pricing_scheme'=>['fixed_price'=>['currency_code'=>$currency,'value'=>$value]]]],'payment_preferences'=>['auto_bill_outstanding'=>true,'setup_fee_failure_action'=>'CONTINUE','payment_failure_threshold'=>3]], 'POST');
    if (is_wp_error($plan)) return $plan;
    if (empty($plan['id'])) return new WP_Error('paypal_plan_missing_id', __('PayPal did not return a subscription plan ID.', 'straysafe-ui-suite'));
    $cache[$key] = sanitize_text_field($plan['id']);
    update_option(self::PLAN_CACHE_KEY, array_slice($cache, -200, null, true), false);
    return $cache[$key];
  }
  private function approval_url(array $links) { foreach ($links as $link) if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) return $link['href']; return ''; }
  private function request($endpoint, array $settings, array $body=[], $method='POST') {
    $token = $this->access_token($settings);
    if (is_wp_error($token)) return $token;
    $response = wp_remote_request($this->base_url($settings).ltrim($endpoint,'/'), ['method'=>$method,'timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>empty($body)?null:wp_json_encode($body)]);
    if (is_wp_error($response)) return new WP_Error('paypal_request_failed', sprintf(__('PayPal request failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) return new WP_Error('paypal_api_error', sanitize_text_field($json['message'] ?? $json['details'][0]['description'] ?? __('PayPal returned an error while creating checkout.', 'straysafe-ui-suite')), ['status'=>$code]);
    return is_array($json) ? $json : new WP_Error('paypal_invalid_response', __('PayPal returned an invalid response.', 'straysafe-ui-suite'));
  }
  private function access_token(array $settings) {
    $client = trim((string)($settings['client_id'] ?? '')); $secret = trim((string)($settings['secret'] ?? ''));
    if ($client === '' || $secret === '') return new WP_Error('paypal_missing_credentials', __('PayPal client ID and secret are required.', 'straysafe-ui-suite'));
    $response = wp_remote_post($this->base_url($settings).'v1/oauth2/token', ['timeout'=>20,'headers'=>['Authorization'=>'Basic '.base64_encode($client.':'.$secret),'Accept'=>'application/json','Accept-Language'=>'en_US'],'body'=>['grant_type'=>'client_credentials']]);
    if (is_wp_error($response)) return new WP_Error('paypal_token_failed', sprintf(__('PayPal authentication failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ((int)wp_remote_retrieve_response_code($response) >= 300 || empty($json['access_token'])) return new WP_Error('paypal_token_failed', sanitize_text_field($json['error_description'] ?? __('PayPal authentication failed.', 'straysafe-ui-suite')));
    return sanitize_text_field($json['access_token']);
  }
  private function base_url(array $settings) { return !empty($settings['sandbox_mode']) ? self::SANDBOX_API : self::LIVE_API; }
}
final class StraySafe_Square_Payment_Provider extends StraySafe_Abstract_Payment_Provider {
  const LIVE_API = 'https://connect.squareup.com/';
  const SANDBOX_API = 'https://connect.squareupsandbox.com/';
  const API_VERSION = '2024-06-04';
  public function __construct(){ $this->id='square'; $this->name='Square'; $this->capabilities=['subscriptions'=>true,'variable_subscriptions'=>false,'variable_one_off_amounts'=>true,'apple_pay'=>true,'google_pay'=>true,'fee_recovery'=>true,'saved_cards'=>true,'embedded_checkout'=>true]; $this->fields=['application_id'=>['label'=>'Application ID','type'=>'text','required'=>true],'access_token'=>['label'=>'Access Token','type'=>'password','required'=>true],'location_id'=>['label'=>'Location ID','type'=>'text','required'=>true],'subscription_plan_variation_id'=>['label'=>'Subscription Plan Variation ID','type'=>'text','required'=>false],'sandbox_mode'=>['label'=>'Sandbox Mode','type'=>'checkbox','required'=>false]]; }
  public function validate_configuration(array $settings) {
    $status = parent::validate_configuration($settings);
    if (!$status['connected']) return $status;
    return ['connected'=>true,'message'=>!empty($settings['sandbox_mode']) ? __('Square sandbox credentials are configured.', 'straysafe-ui-suite') : __('Square live credentials are configured.', 'straysafe-ui-suite')];
  }
  public function create_checkout(array $request, array $settings) {
    $status = $this->validate_configuration($settings);
    if (empty($status['connected'])) return new WP_Error('square_not_configured', $status['message']);
    $amount = isset($request['amount']) ? (float)$request['amount'] : 0;
    if ($amount <= 0) return new WP_Error('square_invalid_amount', __('Please choose a valid donation amount.', 'straysafe-ui-suite'));
    $currency = strtoupper(preg_replace('/[^a-zA-Z]/', '', $request['currency'] ?? 'GBP')) ?: 'GBP';
    $is_recurring = ($request['type'] ?? 'one_off') === 'recurring';
    $body = [
      'idempotency_key' => wp_generate_uuid4(),
      'quick_pay' => ['name'=>__('Donation', 'straysafe-ui-suite'),'price_money'=>['amount'=>$this->minor_amount($amount, $currency),'currency'=>$currency],'location_id'=>sanitize_text_field($settings['location_id'] ?? '')],
      'checkout_options' => ['redirect_url'=>esc_url_raw($request['success_url'] ?? home_url('/')),'accepted_payment_methods'=>['apple_pay'=>true,'google_pay'=>true]],
      'pre_populated_data' => new stdClass(),
    ];
    if ($is_recurring) {
      $plan = sanitize_text_field($settings['subscription_plan_variation_id'] ?? '');
      if ($plan === '') return new WP_Error('square_missing_subscription_plan', __('Square recurring donations require a Subscription Plan Variation ID.', 'straysafe-ui-suite'));
      $body['checkout_options']['subscription_plan_id'] = $plan;
    }
    $link = $this->request('v2/online-checkout/payment-links', $settings, $body, 'POST');
    if (is_wp_error($link)) return $link;
    $url = $link['payment_link']['url'] ?? '';
    if (!$url) return new WP_Error('square_checkout_missing_url', __('Square did not return a checkout URL.', 'straysafe-ui-suite'));
    return ['url'=>esc_url_raw($url),'id'=>sanitize_text_field($link['payment_link']['id'] ?? ''),'provider'=>'square'];
  }
  private function minor_amount($amount, $currency) { $zero_decimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF']; return in_array(strtoupper($currency), $zero_decimal, true) ? (int)round((float)$amount) : (int)round((float)$amount * 100); }
  private function request($endpoint, array $settings, array $body=[], $method='POST') {
    $token = trim((string)($settings['access_token'] ?? ''));
    if ($token === '') return new WP_Error('square_missing_token', __('Square access token is required.', 'straysafe-ui-suite'));
    $response = wp_remote_request($this->base_url($settings).ltrim($endpoint, '/'), ['method'=>$method,'timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token,'Square-Version'=>self::API_VERSION,'Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>empty($body)?null:wp_json_encode($body)]);
    if (is_wp_error($response)) return new WP_Error('square_request_failed', sprintf(__('Square request failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) return new WP_Error('square_api_error', sanitize_text_field($json['errors'][0]['detail'] ?? $json['errors'][0]['code'] ?? __('Square returned an error while creating checkout.', 'straysafe-ui-suite')), ['status'=>$code]);
    return is_array($json) ? $json : new WP_Error('square_invalid_response', __('Square returned an invalid response.', 'straysafe-ui-suite'));
  }
  private function base_url(array $settings) { return !empty($settings['sandbox_mode']) ? self::SANDBOX_API : self::LIVE_API; }
}
final class StraySafe_GoCardless_Payment_Provider extends StraySafe_Abstract_Payment_Provider {
  const LIVE_API = 'https://api.gocardless.com/';
  const SANDBOX_API = 'https://api-sandbox.gocardless.com/';
  const API_VERSION = '2015-07-06';
  public function __construct(){ $this->id='gocardless'; $this->name='GoCardless'; $this->capabilities=['subscriptions'=>true,'variable_subscriptions'=>true,'variable_one_off_amounts'=>false,'apple_pay'=>false,'google_pay'=>false,'gift_aid'=>true,'fee_recovery'=>false,'saved_cards'=>false,'embedded_checkout'=>false]; $this->fields=['access_token'=>['label'=>'Access Token','type'=>'password','required'=>true],'webhook_secret'=>['label'=>'Webhook Secret','type'=>'password','required'=>false],'sandbox_mode'=>['label'=>'Sandbox Mode','type'=>'checkbox','required'=>false]]; }
  public function validate_configuration(array $settings) { $status = parent::validate_configuration($settings); if (!$status['connected']) return $status; return ['connected'=>true,'message'=>!empty($settings['sandbox_mode']) ? __('GoCardless sandbox access token is configured.', 'straysafe-ui-suite') : __('GoCardless live access token is configured.', 'straysafe-ui-suite')]; }
  public function create_checkout(array $request, array $settings) {
    $status = $this->validate_configuration($settings);
    if (empty($status['connected'])) return new WP_Error('gocardless_not_configured', $status['message']);
    $amount = isset($request['amount']) ? (float)$request['amount'] : 0;
    if ($amount <= 0) return new WP_Error('gocardless_invalid_amount', __('Please choose a valid Direct Debit amount.', 'straysafe-ui-suite'));
    $currency = strtoupper(preg_replace('/[^a-zA-Z]/', '', $request['currency'] ?? 'GBP')) ?: 'GBP';
    $session_token = wp_generate_password(32, false, false);
    $return_url = add_query_arg(['session_token'=>$session_token], rest_url('straysafe-payments/v1/gocardless-return'));
    $pending = ['amount'=>$amount,'currency'=>$currency,'type'=>($request['type'] ?? 'one_off') === 'recurring' ? 'recurring' : 'one_off','success_url'=>esc_url_raw($request['success_url'] ?? home_url('/')),'cancel_url'=>esc_url_raw($request['cancel_url'] ?? home_url('/')),'created_at'=>time(),'mode'=>!empty($settings['sandbox_mode'])?'sandbox':'live'];
    $flow = $this->request('redirect_flows', $settings, ['redirect_flows'=>['description'=>__('Donation Direct Debit mandate', 'straysafe-ui-suite'),'session_token'=>$session_token,'success_redirect_url'=>$return_url,'metadata'=>['source'=>'straysafe_payments','donation_type'=>$pending['type'],'amount'=>(string)$amount,'currency'=>$currency]]], 'POST');
    if (is_wp_error($flow)) return $flow;
    $redirect = $flow['redirect_flows'] ?? [];
    if (empty($redirect['redirect_url']) || empty($redirect['id'])) return new WP_Error('gocardless_redirect_missing_url', __('GoCardless did not return a redirect URL.', 'straysafe-ui-suite'));
    $pending['redirect_flow_id'] = sanitize_text_field($redirect['id']);
    StraySafe_Payments_Module::store_gocardless_pending_flow($session_token, $pending);
    return ['url'=>esc_url_raw($redirect['redirect_url']),'id'=>sanitize_text_field($redirect['id']),'provider'=>'gocardless'];
  }
  public function complete_redirect_flow(array $settings, $redirect_flow_id, $session_token) {
    $flow = $this->request('redirect_flows/'.rawurlencode($redirect_flow_id).'/actions/complete', $settings, ['data'=>['session_token'=>$session_token]], 'POST');
    if (is_wp_error($flow)) return $flow;
    return $flow['redirect_flows'] ?? [];
  }
  public function create_payment(array $settings, $mandate_id, array $pending) {
    return $this->request('payments', $settings, ['payments'=>['amount'=>$this->minor_amount($pending['amount'], $pending['currency']),'currency'=>$pending['currency'],'description'=>__('Donation', 'straysafe-ui-suite'),'links'=>['mandate'=>$mandate_id],'metadata'=>['source'=>'straysafe_payments','type'=>'one_off']]], 'POST');
  }
  public function create_subscription(array $settings, $mandate_id, array $pending) {
    return $this->request('subscriptions', $settings, ['subscriptions'=>['amount'=>$this->minor_amount($pending['amount'], $pending['currency']),'currency'=>$pending['currency'],'name'=>__('Monthly donation', 'straysafe-ui-suite'),'interval_unit'=>'monthly','links'=>['mandate'=>$mandate_id],'metadata'=>['source'=>'straysafe_payments','type'=>'recurring']]], 'POST');
  }
  public function cancel_mandate(array $settings, $mandate_id) { return $this->request('mandates/'.rawurlencode($mandate_id).'/actions/cancel', $settings, ['data'=>new stdClass()], 'POST'); }
  private function minor_amount($amount, $currency) { $zero_decimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF']; return in_array(strtoupper($currency), $zero_decimal, true) ? (int)round((float)$amount) : (int)round((float)$amount * 100); }
  private function request($endpoint, array $settings, array $body=[], $method='POST') {
    $token = trim((string)($settings['access_token'] ?? ''));
    if ($token === '') return new WP_Error('gocardless_missing_token', __('GoCardless access token is required.', 'straysafe-ui-suite'));
    $response = wp_remote_request($this->base_url($settings).ltrim($endpoint, '/'), ['method'=>$method,'timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token,'GoCardless-Version'=>self::API_VERSION,'Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>empty($body)?null:wp_json_encode($body)]);
    if (is_wp_error($response)) return new WP_Error('gocardless_request_failed', sprintf(__('GoCardless request failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) return new WP_Error('gocardless_api_error', sanitize_text_field($json['error']['message'] ?? __('GoCardless returned an error.', 'straysafe-ui-suite')), ['status'=>$code]);
    return is_array($json) ? $json : new WP_Error('gocardless_invalid_response', __('GoCardless returned an invalid response.', 'straysafe-ui-suite'));
  }
  private function base_url(array $settings) { return !empty($settings['sandbox_mode']) ? self::SANDBOX_API : self::LIVE_API; }
}
final class StraySafe_SumUp_Payment_Provider extends StraySafe_Abstract_Payment_Provider {
  const API_BASE = 'https://api.sumup.com/';
  public function __construct(){ $this->id='sumup'; $this->name='SumUp'; $this->capabilities=['subscriptions'=>false,'variable_subscriptions'=>false,'variable_one_off_amounts'=>true,'apple_pay'=>true,'google_pay'=>true,'fee_recovery'=>false,'saved_cards'=>false,'embedded_checkout'=>false]; $this->fields=['merchant_code'=>['label'=>'Merchant Code','type'=>'text','required'=>true],'api_key'=>['label'=>'API Key','type'=>'password','required'=>true],'sandbox_mode'=>['label'=>'Sandbox Mode','type'=>'checkbox','required'=>false]]; }
  public function validate_configuration(array $settings) { $status = parent::validate_configuration($settings); if (!$status['connected']) return $status; return ['connected'=>true,'message'=>!empty($settings['sandbox_mode']) ? __('SumUp sandbox API key is configured.', 'straysafe-ui-suite') : __('SumUp live API key is configured.', 'straysafe-ui-suite')]; }
  public function create_checkout(array $request, array $settings) {
    if (($request['type'] ?? 'one_off') === 'recurring') return new WP_Error('sumup_recurring_not_supported', __('SumUp checkout does not support recurring donations in this integration.', 'straysafe-ui-suite'));
    $status = $this->validate_configuration($settings);
    if (empty($status['connected'])) return new WP_Error('sumup_not_configured', $status['message']);
    $amount = isset($request['amount']) ? (float)$request['amount'] : 0;
    if ($amount <= 0) return new WP_Error('sumup_invalid_amount', __('Please choose a valid donation amount.', 'straysafe-ui-suite'));
    $currency = strtoupper(preg_replace('/[^a-zA-Z]/', '', $request['currency'] ?? 'GBP')) ?: 'GBP';
    $reference = 'straysafe-' . wp_generate_uuid4();
    $checkout = $this->request('v0.1/checkouts', $settings, ['checkout_reference'=>$reference,'amount'=>(float)number_format($amount, 2, '.', ''),'currency'=>$currency,'merchant_code'=>sanitize_text_field($settings['merchant_code'] ?? ''),'description'=>__('Donation', 'straysafe-ui-suite'),'redirect_url'=>esc_url_raw($request['success_url'] ?? home_url('/')),'hosted_checkout'=>['enabled'=>true]], 'POST');
    if (is_wp_error($checkout)) return $checkout;
    $url = $checkout['hosted_checkout_url'] ?? '';
    if (!$url) return new WP_Error('sumup_checkout_missing_url', __('SumUp did not return a hosted checkout URL.', 'straysafe-ui-suite'));
    $methods = !empty($checkout['id']) ? $this->payment_methods($settings, $checkout['id']) : [];
    return ['url'=>esc_url_raw($url),'id'=>sanitize_text_field($checkout['id'] ?? ''),'provider'=>'sumup','payment_methods'=>$methods];
  }
  private function payment_methods(array $settings, $checkout_id) {
    $methods = $this->request('v0.1/checkouts/'.rawurlencode($checkout_id).'/payment-methods', $settings, [], 'GET');
    if (is_wp_error($methods) || empty($methods['items']) || !is_array($methods['items'])) return [];
    return array_values(array_filter(array_map(function($method){ return sanitize_key($method['id'] ?? ''); }, $methods['items'])));
  }
  private function request($endpoint, array $settings, array $body=[], $method='POST') {
    $key = trim((string)($settings['api_key'] ?? ''));
    if ($key === '') return new WP_Error('sumup_missing_key', __('SumUp API key is required.', 'straysafe-ui-suite'));
    $args = ['method'=>$method,'timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json','Accept'=>'application/json']];
    if ($method !== 'GET') $args['body'] = wp_json_encode($body);
    $response = wp_remote_request(self::API_BASE.ltrim($endpoint, '/'), $args);
    if (is_wp_error($response)) return new WP_Error('sumup_request_failed', sprintf(__('SumUp request failed: %s', 'straysafe-ui-suite'), $response->get_error_message()));
    $code = (int) wp_remote_retrieve_response_code($response);
    $json = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) return new WP_Error('sumup_api_error', sanitize_text_field($json['message'] ?? $json['error_message'] ?? __('SumUp returned an error while creating checkout.', 'straysafe-ui-suite')), ['status'=>$code]);
    return is_array($json) ? $json : new WP_Error('sumup_invalid_response', __('SumUp returned an invalid response.', 'straysafe-ui-suite'));
  }
}


final class StraySafe_Payment_Gateway_Manager {
  private $providers = [];
  public static function default_capabilities() { return ['subscriptions'=>false,'variable_subscriptions'=>false,'variable_one_off_amounts'=>false,'apple_pay'=>false,'google_pay'=>false,'gift_aid'=>false,'fee_recovery'=>false,'saved_cards'=>false,'embedded_checkout'=>false]; }
  public function __construct() {
    foreach ([new StraySafe_Stripe_Payment_Provider(), new StraySafe_PayPal_Payment_Provider(), new StraySafe_Square_Payment_Provider(), new StraySafe_GoCardless_Payment_Provider(), new StraySafe_SumUp_Payment_Provider()] as $provider) $this->register($provider);
    /**
     * Fires when the payment gateway manager is ready for provider registration.
     *
     * @param StraySafe_Payment_Gateway_Manager $manager Gateway manager.
     */
    do_action('straysafe_payments_register_providers', $this);
  }
  public function register(StraySafe_Payment_Provider_Interface $provider) { $this->providers[$provider->get_id()] = $provider; }
  public function unregister($provider_id) { unset($this->providers[sanitize_key($provider_id)]); }
  public function all() {
    /**
     * Filters the registered payment providers.
     *
     * @param array $providers Provider objects keyed by provider ID.
     */
    $providers = apply_filters('straysafe_payments_providers', $this->providers);
    return is_array($providers) ? $providers : [];
  }
  public function get($id) { $all = $this->all(); return $all[$id] ?? reset($all); }
  public function create_checkout(array $request, array $settings) {
    $provider = $this->get($settings['active_provider'] ?? 'stripe');
    if (!$provider) return new WP_Error('no_provider', __('No payment provider is available.', 'straysafe-ui-suite'));
    /**
     * Allows extensions to fully handle checkout before the provider adapter runs.
     *
     * Return null to continue with the active provider, an array with a checkout URL
     * to complete the request, or WP_Error to stop checkout.
     *
     * @param null|array|WP_Error $result   Checkout result override.
     * @param array               $request  Normalized checkout request.
     * @param array               $settings Full module settings.
     * @param StraySafe_Payment_Provider_Interface $provider Active provider.
     */
    $handled = apply_filters('straysafe_payments_custom_checkout_handler', null, $request, $settings, $provider);
    if (null !== $handled) return $handled;
    /**
     * Filters the checkout request passed to the provider adapter.
     *
     * @param array $request Checkout request.
     * @param array $settings Full module settings.
     * @param StraySafe_Payment_Provider_Interface $provider Active provider.
     */
    $request = apply_filters('straysafe_payments_checkout_request', $request, $settings, $provider);
    $result = $provider->create_checkout($request, $settings['provider_settings'][$provider->get_id()] ?? []);
    /**
     * Filters the provider checkout result.
     *
     * @param array|WP_Error $result Provider result.
     * @param array          $request Checkout request.
     * @param array          $settings Full module settings.
     * @param StraySafe_Payment_Provider_Interface $provider Active provider.
     */
    return apply_filters('straysafe_payments_checkout_result', $result, $request, $settings, $provider);
  }
}

if (!function_exists('registerPaymentProvider')) {
  function registerPaymentProvider(StraySafe_Payment_Provider_Interface $provider) { StraySafe_Payments_Module::gateway_manager()->register($provider); }
}
if (!function_exists('unregisterPaymentProvider')) {
  function unregisterPaymentProvider($provider_id) { StraySafe_Payments_Module::gateway_manager()->unregister($provider_id); }
}
if (!function_exists('registerPaymentCampaign')) {
  function registerPaymentCampaign($slug, array $campaign) { StraySafe_Payments_Module::register_campaign($slug, $campaign); }
}
if (!function_exists('registerPaymentType')) {
  function registerPaymentType($type, array $definition) { StraySafe_Payments_Module::register_payment_type($type, $definition); }
}

final class StraySafe_Payments_Module {
  const OPTION_KEY = 'straysafe_payments_settings_v1';
  const AUDIT_KEY = 'straysafe_payments_audit_v1';
  const WEBHOOK_EVENTS_KEY = 'straysafe_payments_webhook_events_v1';
  const PAYMENT_EVENTS_KEY = 'straysafe_payments_payment_events_v1';
  const SUBSCRIPTION_EVENTS_KEY = 'straysafe_payments_subscription_events_v1';
  const GOCARDLESS_PENDING_KEY = 'straysafe_payments_gocardless_pending_v1';
  const GIFT_AID_KEY = 'straysafe_payments_gift_aid_v1';
  private static $registered_campaigns = [];
  private static $registered_payment_types = [];
  public static function register_campaign($slug, array $campaign) {
    $slug = sanitize_key($slug);
    if ($slug !== '') self::$registered_campaigns[$slug] = $campaign;
  }
  public static function register_payment_type($type, array $definition) {
    $type = sanitize_key($type);
    if ($type !== '') self::$registered_payment_types[$type] = $definition;
  }
  public static function payment_types() {
    $types = array_merge([
      'one_off'=>['label'=>__('One-off','straysafe-ui-suite'),'settings_key'=>'one_off','recurring'=>false],
      'recurring'=>['label'=>__('Monthly','straysafe-ui-suite'),'settings_key'=>'recurring','recurring'=>true],
    ], self::$registered_payment_types);
    /**
     * Filters payment type definitions available to checkout and extensions.
     *
     * @param array $types Payment type definitions keyed by type ID.
     */
    return apply_filters('straysafe_payments_payment_types', $types);
  }
  public static function init() { add_action('admin_init',[__CLASS__,'register_settings']); add_action('rest_api_init',[__CLASS__,'register_rest_routes']); add_action('admin_menu',[__CLASS__,'admin_menu']); add_action('admin_enqueue_scripts',[__CLASS__,'enqueue_admin_assets']); add_action('wp_ajax_straysafe_payments_checkout',[__CLASS__,'handle_checkout']); add_action('wp_ajax_nopriv_straysafe_payments_checkout',[__CLASS__,'handle_checkout']); add_action('admin_post_straysafe_payments_export_gift_aid',[__CLASS__,'export_gift_aid']); add_shortcode('donation_widget',[__CLASS__,'render_shortcode']); add_action('init',[__CLASS__,'register_block']); add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_frontend_assets']); }
  public static function gateway_manager() { static $m; if (!$m) $m = new StraySafe_Payment_Gateway_Manager(); return $m; }
  public static function defaults() { return ['active_provider'=>'stripe','provider_settings'=>[],'general'=>['enabled'=>1,'default_type'=>'one_off','currency'=>'GBP','success_url'=>home_url('/thank-you/'),'cancel_url'=>home_url('/'),'button_text'=>'Continue','thank_you_message'=>'Thank you for your support.','enable_campaigns'=>1,'title'=>'Support our work','subtitle'=>'Choose an amount that works for you','intro_text'=>'','learn_more_url'=>''],'one_off'=>['enabled'=>1,'allow_custom'=>1,'min'=>1,'max'=>10000,'default'=>5,'presets'=>[['amount'=>'3','description'=>'Makes a small contribution'],['amount'=>'5','description'=>'Supports essential work'],['amount'=>'10','description'=>'Helps fund ongoing services'],['amount'=>'17.50','description'=>'Creates meaningful impact']]],'recurring'=>['enabled'=>1,'allow_custom'=>1,'min'=>1,'max'=>1000,'default'=>5,'presets'=>[['amount'=>'3','description'=>'Provides steady monthly support'],['amount'=>'5','description'=>'Funds reliable monthly help'],['amount'=>'10','description'=>'Sustains ongoing work'],['amount'=>'17.50','description'=>'Creates lasting impact']]],'campaigns'=>[],'appearance'=>['primary'=>'#401268','background'=>'#ffffff','text'=>'#1f2937','radius'=>16,'shadow'=>1,'mode'=>'light','show_logo'=>0]]; }
  public static function settings() {
    $settings = self::merge_settings(get_option(self::OPTION_KEY, []), self::defaults());
    $settings['campaigns'] = self::registered_campaigns($settings['campaigns'] ?? []);
    /**
     * Filters loaded payment settings after defaults are merged.
     *
     * @param array $settings Complete payment settings.
     */
    return apply_filters('straysafe_payments_settings', $settings);
  }
  private static function merge_settings($settings, $defaults) {
    if (!is_array($settings)) return $defaults;
    foreach ($defaults as $key => $value) {
      if (is_array($value)) $settings[$key] = self::merge_settings($settings[$key] ?? [], $value);
      elseif (!array_key_exists($key, $settings)) $settings[$key] = $value;
    }
    return $settings;
  }
  private static function registered_campaigns($campaigns) {
    $campaigns = is_array($campaigns) ? $campaigns : [];
    foreach (self::$registered_campaigns as $slug=>$campaign) $campaigns[$slug] = self::sanitize_registered_campaign($slug, $campaign);
    /**
     * Filters reusable campaigns available to shortcodes and checkout.
     *
     * @param array $campaigns Campaign definitions keyed by campaign slug.
     */
    $campaigns = apply_filters('straysafe_payments_campaigns', $campaigns);
    $clean = [];
    foreach ((array)$campaigns as $slug=>$campaign) $clean[sanitize_key($slug)] = self::sanitize_registered_campaign($slug, (array)$campaign);
    return array_filter($clean);
  }
  private static function sanitize_registered_campaign($slug, array $campaign) {
    $campaign['slug'] = sanitize_key($campaign['slug'] ?? $slug);
    $sanitized = self::sanitize_campaigns([$campaign]);
    return $sanitized[$campaign['slug']] ?? [];
  }
  public static function register_settings() {
    register_setting('straysafe_payments_settings', self::OPTION_KEY, ['type'=>'array','sanitize_callback'=>[__CLASS__,'sanitize_settings'],'default'=>self::defaults()]);
    /**
     * Fires after Payments registers its Settings API option.
     */
    do_action('straysafe_payments_register_settings');
  }
  private static function admin_capability() {
    /**
     * Filters the capability required to manage Payments settings.
     *
     * @param string $capability WordPress capability.
     */
    return apply_filters('straysafe_payments_admin_capability', 'manage_options');
  }
  public static function admin_menu() {
    add_menu_page('Payments','Payments',self::admin_capability(),'straysafe-payments',[__CLASS__,'render_admin_page'],'dashicons-money-alt',56);
  }
  public static function enqueue_frontend_assets() {
    wp_enqueue_style('straysafe-payments', STRAYSAFE_SUITE_URL.'assets/css/payments.css', [], STRAYSAFE_SUITE_VERSION);
    wp_enqueue_script('straysafe-payments', STRAYSAFE_SUITE_URL.'assets/js/payments.js', [], STRAYSAFE_SUITE_VERSION, true);
    $config = ['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('straysafe_payments_checkout')];
    /**
     * Filters frontend JavaScript configuration for the donation widget.
     *
     * @param array $config Localized script data.
     */
    wp_localize_script('straysafe-payments','StraySafePayments',apply_filters('straysafe_payments_frontend_config', $config));
    /**
     * Fires after Payments frontend assets are enqueued.
     */
    do_action('straysafe_payments_enqueue_assets');
  }
  public static function enqueue_admin_assets($hook) { if ($hook !== 'toplevel_page_straysafe-payments') return; self::enqueue_frontend_assets(); }
  public static function register_block() { if (function_exists('register_block_type')) register_block_type('asm-suite/donation-widget',['api_version'=>2,'title'=>'Payments Donation Widget','category'=>'widgets','attributes'=>['campaign'=>['type'=>'string'],'default'=>['type'=>'string'],'theme'=>['type'=>'string']],'render_callback'=>function($a=[]){ return self::render_shortcode($a); }]); }
  public static function register_rest_routes() { register_rest_route('straysafe-payments/v1','/stripe-webhook',['methods'=>'POST','callback'=>[__CLASS__,'handle_stripe_webhook'],'permission_callback'=>'__return_true']); register_rest_route('straysafe-payments/v1','/paypal-webhook',['methods'=>'POST','callback'=>[__CLASS__,'handle_paypal_webhook'],'permission_callback'=>'__return_true']); register_rest_route('straysafe-payments/v1','/gocardless-webhook',['methods'=>'POST','callback'=>[__CLASS__,'handle_gocardless_webhook'],'permission_callback'=>'__return_true']); register_rest_route('straysafe-payments/v1','/gocardless-return',['methods'=>'GET','callback'=>[__CLASS__,'handle_gocardless_return'],'permission_callback'=>'__return_true']); }
  public static function sanitize_settings($input) {
    $input = is_array($input) ? wp_unslash($input) : [];
    $clean = self::settings();
    $providers = self::gateway_manager()->all();
    $active = sanitize_key($input['active_provider'] ?? $clean['active_provider']);
    $clean['active_provider'] = isset($providers[$active]) ? $active : (isset($providers['stripe']) ? 'stripe' : (string) array_key_first($providers));
    foreach ($providers as $id=>$provider) {
      foreach ($provider->get_configuration_fields() as $key=>$field) {
        $v = $input['provider_settings'][$id][$key] ?? '';
        $field_type = $field['type'] ?? 'text';
        if ($field_type === 'checkbox') $clean['provider_settings'][$id][$key] = !empty($v)?1:0; elseif ($field_type === 'password' && $v === '') $clean['provider_settings'][$id][$key] = $clean['provider_settings'][$id][$key] ?? ''; else $clean['provider_settings'][$id][$key] = sanitize_text_field((string)$v);
      }
    }
    $g=$input['general']??[];
    foreach(['title','subtitle','intro_text','button_text','thank_you_message'] as $k) $clean['general'][$k]=sanitize_text_field($g[$k]??$clean['general'][$k]);
    foreach(['success_url','cancel_url','learn_more_url'] as $k) $clean['general'][$k]=esc_url_raw($g[$k]??$clean['general'][$k]);
    $clean['general']['enabled']=!empty($g['enabled'])?1:0;
    $clean['general']['enable_campaigns']=!empty($g['enable_campaigns'])?1:0;
    $clean['general']['currency']=preg_replace('/[^A-Z]/','',strtoupper($g['currency']??$clean['general']['currency'])) ?: 'GBP';
    $clean['general']['default_type']=in_array(($g['default_type']??'one_off'),['one_off','recurring'],true)?$g['default_type']:'one_off';
    foreach(['one_off','recurring'] as $section){
      $in=$input[$section]??[];
      $clean[$section]['enabled']=!empty($in['enabled'])?1:0;
      $clean[$section]['allow_custom']=!empty($in['allow_custom'])?1:0;
      foreach(['min','max','default'] as $k) $clean[$section][$k]=max(0,(float)($in[$k]??$clean[$section][$k]));
      $clean[$section]['presets']=[];
      foreach(($in['presets']??[]) as $row){
        if (!is_array($row) || !is_numeric($row['amount']??'')) continue;
        $clean[$section]['presets'][]=['amount'=>(string)max(0,(float)$row['amount']),'description'=>sanitize_text_field($row['description']??''),'icon'=>sanitize_text_field($row['icon']??''),'colour'=>sanitize_hex_color($row['colour']??'') ?: '#401268','campaign'=>sanitize_key($row['campaign']??'')];
      }
    }
    $clean['campaigns']=self::sanitize_campaigns($input['campaigns']??[]);
    $a=$input['appearance']??[];
    foreach(['primary','background','text'] as $k) $clean['appearance'][$k]=sanitize_hex_color($a[$k]??$clean['appearance'][$k]) ?: $clean['appearance'][$k];
    $clean['appearance']['radius']=max(0,min(48,(int)($a['radius']??16)));
    $clean['appearance']['shadow']=!empty($a['shadow'])?1:0;
    $clean['appearance']['mode']=($a['mode']??'light')==='dark'?'dark':'light';
    $clean['appearance']['show_logo']=!empty($a['show_logo'])?1:0;
    self::audit('settings_saved',['provider'=>$clean['active_provider']]);
    return apply_filters('straysafe_payments_sanitized_settings',$clean,$input);
  }
  public static function audit($event,$data=[]) {
    $entry = array_merge(['time'=>current_time('mysql'),'event'=>sanitize_key($event),'user'=>get_current_user_id()],['data'=>$data]);
    $log = get_option(self::AUDIT_KEY, []);
    $log[] = $entry;
    update_option(self::AUDIT_KEY, array_slice($log, -100), false);
    /**
     * Fires after a Payments audit entry is written.
     *
     * @param array $entry Audit entry.
     */
    do_action('straysafe_payments_audit_logged', $entry);
  }
  public static function store_gocardless_pending_flow($session_token, array $pending) {
    $flows = get_option(self::GOCARDLESS_PENDING_KEY, []);
    $flows[sanitize_text_field($session_token)] = self::sanitize_event_data($pending);
    foreach ($flows as $token=>$flow) if (!empty($flow['created_at']) && (int)$flow['created_at'] < time() - DAY_IN_SECONDS) unset($flows[$token]);
    update_option(self::GOCARDLESS_PENDING_KEY, array_slice($flows, -200, null, true), false);
  }
  public static function handle_gocardless_return($request) {
    $session_token = sanitize_text_field($request->get_param('session_token') ?? '');
    $redirect_flow_id = sanitize_text_field($request->get_param('redirect_flow_id') ?? '');
    $flows = get_option(self::GOCARDLESS_PENDING_KEY, []);
    $pending = $flows[$session_token] ?? null;
    if (!$pending || $redirect_flow_id === '') return new WP_REST_Response(['message'=>__('GoCardless mandate session was not found.', 'straysafe-ui-suite')], 400);
    $settings = self::settings();
    $provider = self::gateway_manager()->get('gocardless');
    if (!$provider instanceof StraySafe_GoCardless_Payment_Provider) return new WP_REST_Response(['message'=>__('GoCardless provider is not available.', 'straysafe-ui-suite')], 400);
    $gc_settings = $settings['provider_settings']['gocardless'] ?? [];
    $flow = $provider->complete_redirect_flow($gc_settings, $redirect_flow_id, $session_token);
    if (is_wp_error($flow)) { wp_safe_redirect(add_query_arg('gocardless_error', rawurlencode($flow->get_error_message()), $pending['cancel_url'])); exit; }
    $mandate_id = sanitize_text_field($flow['links']['mandate'] ?? '');
    if ($mandate_id === '') { wp_safe_redirect(add_query_arg('gocardless_error', rawurlencode(__('GoCardless did not return a mandate.', 'straysafe-ui-suite')), $pending['cancel_url'])); exit; }
    self::record_subscription_event($mandate_id, 'mandate_created', 'gocardless-return-'.$redirect_flow_id, ['provider'=>'gocardless','redirect_flow'=>$redirect_flow_id,'customer'=>$flow['links']['customer'] ?? ''], 'gocardless');
    $result = ($pending['type'] ?? 'one_off') === 'recurring' ? $provider->create_subscription($gc_settings, $mandate_id, $pending) : $provider->create_payment($gc_settings, $mandate_id, $pending);
    if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('gocardless_error', rawurlencode($result->get_error_message()), $pending['cancel_url'])); exit; }
    unset($flows[$session_token]); update_option(self::GOCARDLESS_PENDING_KEY, $flows, false);
    if (($pending['type'] ?? 'one_off') === 'recurring') self::record_subscription_event(sanitize_text_field($result['subscriptions']['id'] ?? $mandate_id), 'created', 'gocardless-return-'.$redirect_flow_id, ['provider'=>'gocardless','mandate'=>$mandate_id], 'gocardless');
    else self::record_payment_event(sanitize_text_field($result['payments']['id'] ?? $mandate_id), 'created', 'gocardless-return-'.$redirect_flow_id, ['provider'=>'gocardless','mandate'=>$mandate_id], 'gocardless');
    wp_safe_redirect($pending['success_url']); exit;
  }
  public static function handle_gocardless_webhook($request) {
    $settings = self::settings();
    $gc = $settings['provider_settings']['gocardless'] ?? [];
    $secret = trim((string)($gc['webhook_secret'] ?? ''));
    if ($secret === '') return new WP_REST_Response(['message'=>__('GoCardless webhook secret is not configured.', 'straysafe-ui-suite')], 400);
    $payload = method_exists($request, 'get_body') ? $request->get_body() : file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_WEBHOOK_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_WEBHOOK_SIGNATURE'])) : '';
    if (!self::verify_gocardless_signature($payload, $signature, $secret)) return new WP_REST_Response(['message'=>__('Invalid GoCardless webhook signature.', 'straysafe-ui-suite')], 498);
    $body = json_decode((string)$payload, true);
    if (!is_array($body) || empty($body['events']) || !is_array($body['events'])) return new WP_REST_Response(['message'=>__('Invalid GoCardless webhook payload.', 'straysafe-ui-suite')], 400);
    foreach ($body['events'] as $event) {
      $event_id = sanitize_text_field($event['id'] ?? md5(wp_json_encode($event)));
      if (self::webhook_event_processed($event_id)) { self::audit('gocardless_webhook_duplicate', ['event_id'=>$event_id]); continue; }
      $resource_type = sanitize_key($event['resource_type'] ?? '');
      $action = sanitize_key($event['action'] ?? '');
      $links = is_array($event['links'] ?? null) ? $event['links'] : [];
      if ($resource_type === 'payments') self::record_payment_event(sanitize_text_field($links['payment'] ?? $event_id), $action, $event_id, ['provider'=>'gocardless','cause'=>$event['details']['cause'] ?? '', 'description'=>$event['details']['description'] ?? '', 'mandate'=>$links['mandate'] ?? ''], 'gocardless');
      elseif ($resource_type === 'subscriptions') self::record_subscription_event(sanitize_text_field($links['subscription'] ?? $event_id), $action, $event_id, ['provider'=>'gocardless','cause'=>$event['details']['cause'] ?? '', 'description'=>$event['details']['description'] ?? ''], 'gocardless');
      elseif ($resource_type === 'mandates') self::record_subscription_event(sanitize_text_field($links['mandate'] ?? $event_id), 'mandate_'.$action, $event_id, ['provider'=>'gocardless','cause'=>$event['details']['cause'] ?? '', 'description'=>$event['details']['description'] ?? ''], 'gocardless');
      else self::audit('gocardless_webhook_ignored', ['event_id'=>$event_id,'resource_type'=>$resource_type,'action'=>$action]);
      self::mark_webhook_event_processed($event_id, 'gocardless:'.$resource_type.'.'.$action);
    }
    return new WP_REST_Response(['received'=>true], 200);
  }
  private static function verify_gocardless_signature($payload, $signature, $secret) {
    return $payload !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
  }
  public static function handle_paypal_webhook($request) {
    $settings = self::settings();
    $paypal = $settings['provider_settings']['paypal'] ?? [];
    $webhook_id = trim((string)($paypal['webhook_id'] ?? ''));
    if ($webhook_id === '') return new WP_REST_Response(['message'=>__('PayPal webhook ID is not configured.', 'straysafe-ui-suite')], 400);
    $payload = method_exists($request, 'get_body') ? $request->get_body() : file_get_contents('php://input');
    $event = json_decode((string)$payload, true);
    if (!is_array($event) || empty($event['event_type'])) return new WP_REST_Response(['message'=>__('Invalid PayPal webhook payload.', 'straysafe-ui-suite')], 400);
    if (!self::verify_paypal_webhook($paypal, $webhook_id, $event)) return new WP_REST_Response(['message'=>__('Invalid PayPal webhook signature.', 'straysafe-ui-suite')], 400);
    $event_id = sanitize_text_field($event['id'] ?? ($event['event_type'].'-'.($event['resource']['id'] ?? md5((string)$payload))));
    if (self::webhook_event_processed($event_id)) { self::audit('paypal_webhook_duplicate', ['event_id'=>$event_id,'type'=>sanitize_text_field($event['event_type'])]); return new WP_REST_Response(['received'=>true,'duplicate'=>true], 200); }
    $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
    $type = sanitize_text_field($event['event_type']);
    switch ($type) {
      case 'CHECKOUT.ORDER.APPROVED':
      case 'CHECKOUT.ORDER.COMPLETED':
        self::record_payment_event(sanitize_text_field($resource['id'] ?? $event_id), strtolower(str_replace('CHECKOUT.ORDER.', '', $type)), $event_id, ['provider'=>'paypal','payer'=>$resource['payer']['payer_id'] ?? '', 'status'=>$resource['status'] ?? ''], 'paypal');
        break;
      case 'PAYMENT.CAPTURE.COMPLETED':
      case 'PAYMENT.CAPTURE.DENIED':
      case 'PAYMENT.CAPTURE.DECLINED':
      case 'PAYMENT.CAPTURE.REFUNDED':
      case 'PAYMENT.CAPTURE.REVERSED':
      case 'PAYMENT.CAPTURE.PENDING':
        self::record_payment_event(sanitize_text_field($resource['id'] ?? $event_id), strtolower(str_replace('PAYMENT.CAPTURE.', '', $type)), $event_id, ['provider'=>'paypal','amount'=>$resource['amount']['value'] ?? '', 'currency'=>$resource['amount']['currency_code'] ?? '', 'status'=>$resource['status'] ?? ''], 'paypal');
        break;
      case 'BILLING.SUBSCRIPTION.CREATED':
      case 'BILLING.SUBSCRIPTION.ACTIVATED':
      case 'BILLING.SUBSCRIPTION.UPDATED':
      case 'BILLING.SUBSCRIPTION.CANCELLED':
      case 'BILLING.SUBSCRIPTION.SUSPENDED':
      case 'BILLING.SUBSCRIPTION.EXPIRED':
      case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
        self::record_subscription_event(sanitize_text_field($resource['id'] ?? $event_id), strtolower(str_replace('BILLING.SUBSCRIPTION.', '', $type)), $event_id, ['provider'=>'paypal','subscriber'=>$resource['subscriber']['payer_id'] ?? '', 'status'=>$resource['status'] ?? '', 'plan_id'=>$resource['plan_id'] ?? ''], 'paypal');
        break;
      default:
        self::audit('paypal_webhook_ignored', ['event_id'=>$event_id,'type'=>$type]);
    }
    self::mark_webhook_event_processed($event_id, 'paypal:'.$type);
    return new WP_REST_Response(['received'=>true], 200);
  }
  private static function verify_paypal_webhook(array $settings, $webhook_id, array $event) {
    $headers = array_change_key_case(function_exists('getallheaders') ? (array)getallheaders() : [], CASE_LOWER);
    foreach (['paypal-auth-algo','paypal-cert-url','paypal-transmission-id','paypal-transmission-sig','paypal-transmission-time'] as $header) { $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header)); if (empty($headers[$header]) && isset($_SERVER[$server_key])) $headers[$header] = sanitize_text_field(wp_unslash($_SERVER[$server_key])); }
    $token = self::paypal_access_token($settings);
    if (is_wp_error($token)) return false;
    $body = ['auth_algo'=>$headers['paypal-auth-algo'] ?? '', 'cert_url'=>$headers['paypal-cert-url'] ?? '', 'transmission_id'=>$headers['paypal-transmission-id'] ?? '', 'transmission_sig'=>$headers['paypal-transmission-sig'] ?? '', 'transmission_time'=>$headers['paypal-transmission-time'] ?? '', 'webhook_id'=>$webhook_id, 'webhook_event'=>$event];
    $response = wp_remote_post(self::paypal_base_url($settings).'v1/notifications/verify-webhook-signature', ['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>wp_json_encode($body)]);
    if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) >= 300) return false;
    $json = json_decode((string)wp_remote_retrieve_body($response), true);
    return is_array($json) && ($json['verification_status'] ?? '') === 'SUCCESS';
  }
  private static function paypal_access_token(array $settings) {
    $client = trim((string)($settings['client_id'] ?? '')); $secret = trim((string)($settings['secret'] ?? ''));
    if ($client === '' || $secret === '') return new WP_Error('paypal_missing_credentials', __('PayPal client ID and secret are required.', 'straysafe-ui-suite'));
    $response = wp_remote_post(self::paypal_base_url($settings).'v1/oauth2/token', ['timeout'=>20,'headers'=>['Authorization'=>'Basic '.base64_encode($client.':'.$secret),'Accept'=>'application/json','Accept-Language'=>'en_US'],'body'=>['grant_type'=>'client_credentials']]);
    if (is_wp_error($response)) return $response;
    $json = json_decode((string)wp_remote_retrieve_body($response), true);
    return !empty($json['access_token']) ? sanitize_text_field($json['access_token']) : new WP_Error('paypal_token_failed', __('PayPal authentication failed.', 'straysafe-ui-suite'));
  }
  private static function paypal_base_url(array $settings) { return !empty($settings['sandbox_mode']) ? StraySafe_PayPal_Payment_Provider::SANDBOX_API : StraySafe_PayPal_Payment_Provider::LIVE_API; }
  public static function handle_stripe_webhook($request) {
    $settings = self::settings();
    $stripe = $settings['provider_settings']['stripe'] ?? [];
    $secret = trim((string)($stripe['webhook_secret'] ?? ''));
    if ($secret === '') return new WP_REST_Response(['message'=>__('Stripe webhook secret is not configured.', 'straysafe-ui-suite')], 400);
    $payload = method_exists($request, 'get_body') ? $request->get_body() : file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_STRIPE_SIGNATURE'])) : '';
    if (!self::verify_stripe_signature($payload, $signature, $secret)) return new WP_REST_Response(['message'=>__('Invalid Stripe webhook signature.', 'straysafe-ui-suite')], 400);
    $event = json_decode((string)$payload, true);
    if (!is_array($event) || empty($event['type'])) return new WP_REST_Response(['message'=>__('Invalid Stripe webhook payload.', 'straysafe-ui-suite')], 400);
    $event_id = sanitize_text_field($event['id'] ?? ($event['type'].'-'.($event['data']['object']['id'] ?? md5((string)$payload))));
    if (self::webhook_event_processed($event_id)) {
      self::audit('stripe_webhook_duplicate', ['event_id'=>$event_id,'type'=>sanitize_text_field($event['type'])]);
      return new WP_REST_Response(['received'=>true,'duplicate'=>true], 200);
    }
    $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
    $type = sanitize_text_field($event['type']);
    switch ($type) {
      case 'checkout.session.completed':
        self::record_checkout_session_completed($object, $event_id);
        break;
      case 'payment_intent.succeeded':
        self::record_payment_event(sanitize_text_field($object['id'] ?? ''), 'succeeded', $event_id, ['customer'=>$object['customer'] ?? '', 'amount'=>$object['amount_received'] ?? $object['amount'] ?? '', 'currency'=>$object['currency'] ?? '', 'session'=>$object['metadata']['checkout_session'] ?? '']);
        break;
      case 'payment_intent.payment_failed':
        self::record_payment_event(sanitize_text_field($object['id'] ?? ''), 'failed', $event_id, ['customer'=>$object['customer'] ?? '', 'amount'=>$object['amount'] ?? '', 'currency'=>$object['currency'] ?? '', 'failure_message'=>$object['last_payment_error']['message'] ?? '']);
        break;
      case 'invoice.paid':
      case 'invoice.payment_succeeded':
        self::record_invoice_event($object, 'paid', $event_id);
        break;
      case 'invoice.payment_failed':
        self::record_invoice_event($object, 'failed', $event_id);
        break;
      case 'customer.subscription.created':
      case 'customer.subscription.updated':
      case 'customer.subscription.deleted':
        self::record_subscription_event(sanitize_text_field($object['id'] ?? ''), sanitize_key($object['status'] ?? ($type === 'customer.subscription.deleted' ? 'deleted' : 'updated')), $event_id, ['customer'=>$object['customer'] ?? '', 'current_period_start'=>$object['current_period_start'] ?? '', 'current_period_end'=>$object['current_period_end'] ?? '', 'cancel_at_period_end'=>$object['cancel_at_period_end'] ?? false, 'event_type'=>$type]);
        break;
      default:
        self::audit('stripe_webhook_ignored', ['event_id'=>$event_id,'type'=>$type]);
    }
    self::mark_webhook_event_processed($event_id, $type);
    return new WP_REST_Response(['received'=>true], 200);
  }
  private static function record_checkout_session_completed(array $object, $event_id) {
    $mode = sanitize_key($object['mode'] ?? '');
    $session_id = sanitize_text_field($object['id'] ?? '');
    if ($mode === 'subscription') {
      self::record_subscription_event(sanitize_text_field($object['subscription'] ?? $session_id), 'checkout_completed', $event_id, ['session'=>$session_id,'customer'=>$object['customer'] ?? '', 'payment_status'=>$object['payment_status'] ?? '']);
    } else {
      self::record_payment_event(sanitize_text_field($object['payment_intent'] ?? $session_id), sanitize_key($object['payment_status'] ?? 'checkout_completed'), $event_id, ['session'=>$session_id,'customer'=>$object['customer'] ?? '', 'amount'=>$object['amount_total'] ?? '', 'currency'=>$object['currency'] ?? '']);
    }
  }
  private static function record_invoice_event(array $object, $status, $event_id) {
    $invoice_id = sanitize_text_field($object['id'] ?? '');
    $subscription_id = sanitize_text_field($object['subscription'] ?? '');
    self::record_payment_event($invoice_id, 'invoice_'.$status, $event_id, ['subscription'=>$subscription_id,'customer'=>$object['customer'] ?? '', 'amount'=>$object['amount_paid'] ?? $object['amount_due'] ?? '', 'currency'=>$object['currency'] ?? '', 'hosted_invoice_url'=>$object['hosted_invoice_url'] ?? '']);
    if ($subscription_id !== '') self::record_subscription_event($subscription_id, 'invoice_'.$status, $event_id, ['invoice'=>$invoice_id,'customer'=>$object['customer'] ?? '', 'amount'=>$object['amount_paid'] ?? $object['amount_due'] ?? '', 'currency'=>$object['currency'] ?? '']);
  }
  public static function record_payment_event($payment_id, $status, $event_id, array $data=[], $provider='stripe') {
    $payment_id = $payment_id !== '' ? sanitize_text_field($payment_id) : $event_id;
    $events = get_option(self::PAYMENT_EVENTS_KEY, []);
    $events[$payment_id] = ['id'=>$payment_id,'status'=>sanitize_key($status),'event_id'=>sanitize_text_field($event_id),'updated_at'=>current_time('mysql'),'data'=>self::sanitize_event_data($data)];
    update_option(self::PAYMENT_EVENTS_KEY, array_slice($events, -500, null, true), false);
    self::audit(sanitize_key($provider).'_payment_event_recorded', ['payment_id'=>$payment_id,'status'=>sanitize_key($status),'event_id'=>sanitize_text_field($event_id)]);
    /**
     * Fires after a payment event is recorded.
     *
     * @param string $payment_id Payment identifier.
     * @param string $status Payment status.
     * @param string $event_id Webhook or source event identifier.
     * @param array  $data Sanitized event data.
     * @param string $provider Provider ID.
     */
    do_action('straysafe_payments_payment_event_recorded', $payment_id, sanitize_key($status), sanitize_text_field($event_id), self::sanitize_event_data($data), sanitize_key($provider));
  }
  public static function record_subscription_event($subscription_id, $status, $event_id, array $data=[], $provider='stripe') {
    $subscription_id = $subscription_id !== '' ? sanitize_text_field($subscription_id) : $event_id;
    $events = get_option(self::SUBSCRIPTION_EVENTS_KEY, []);
    $events[$subscription_id] = ['id'=>$subscription_id,'status'=>sanitize_key($status),'event_id'=>sanitize_text_field($event_id),'updated_at'=>current_time('mysql'),'data'=>self::sanitize_event_data($data)];
    update_option(self::SUBSCRIPTION_EVENTS_KEY, array_slice($events, -500, null, true), false);
    self::audit(sanitize_key($provider).'_subscription_event_recorded', ['subscription_id'=>$subscription_id,'status'=>sanitize_key($status),'event_id'=>sanitize_text_field($event_id)]);
    /**
     * Fires after a subscription event is recorded.
     *
     * @param string $subscription_id Subscription identifier.
     * @param string $status Subscription status.
     * @param string $event_id Webhook or source event identifier.
     * @param array  $data Sanitized event data.
     * @param string $provider Provider ID.
     */
    do_action('straysafe_payments_subscription_event_recorded', $subscription_id, sanitize_key($status), sanitize_text_field($event_id), self::sanitize_event_data($data), sanitize_key($provider));
  }
  private static function sanitize_event_data(array $data) {
    $clean = [];
    foreach ($data as $key=>$value) {
      if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode($value);
        $value = false === $encoded ? '' : $encoded;
      }
      $clean[sanitize_key($key)] = is_bool($value) ? (bool)$value : sanitize_text_field((string)$value);
    }
    return $clean;
  }
  private static function webhook_event_processed($event_id) {
    $events = get_option(self::WEBHOOK_EVENTS_KEY, []);
    return isset($events[$event_id]);
  }
  private static function mark_webhook_event_processed($event_id, $type) {
    $events = get_option(self::WEBHOOK_EVENTS_KEY, []);
    $events[sanitize_text_field($event_id)] = ['type'=>sanitize_text_field($type),'processed_at'=>current_time('mysql')];
    update_option(self::WEBHOOK_EVENTS_KEY, array_slice($events, -500, null, true), false);
  }
  private static function verify_stripe_signature($payload, $header, $secret) {
    if ($payload === '' || $header === '' || $secret === '') return false;
    $parts = [];
    foreach (explode(',', $header) as $piece) { $kv = explode('=', trim($piece), 2); if (count($kv) === 2) $parts[$kv[0]][] = $kv[1]; }
    $timestamp = isset($parts['t'][0]) ? (int)$parts['t'][0] : 0;
    if ($timestamp <= 0 || abs(time() - $timestamp) > 300 || empty($parts['v1'])) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($parts['v1'] as $signature) if (hash_equals($expected, $signature)) return true;
    return false;
  }
  private static function sanitize_campaigns($input) {
    $campaigns = [];
    if (!is_array($input)) return $campaigns;
    foreach ($input as $row) {
      if (!is_array($row)) continue;
      $slug = sanitize_key($row['slug'] ?? '');
      if ($slug === '') continue;
      $campaigns[$slug] = [
        'slug'=>$slug,
        'title'=>sanitize_text_field($row['title'] ?? ''),
        'subtitle'=>sanitize_text_field($row['subtitle'] ?? ''),
        'description'=>sanitize_textarea_field($row['description'] ?? ''),
        'featured_image'=>esc_url_raw($row['featured_image'] ?? ''),
        'goal'=>max(0,(float)($row['goal'] ?? 0)),
        'progress'=>max(0,(float)($row['progress'] ?? 0)),
        'primary'=>sanitize_hex_color($row['primary'] ?? '') ?: '',
        'background'=>sanitize_hex_color($row['background'] ?? '') ?: '',
        'text'=>sanitize_hex_color($row['text'] ?? '') ?: '',
        'success_url'=>esc_url_raw($row['success_url'] ?? ''),
        'thank_you_message'=>sanitize_text_field($row['thank_you_message'] ?? ''),
        'one_off_presets'=>self::sanitize_campaign_presets($row['one_off_presets'] ?? ''),
        'recurring_presets'=>self::sanitize_campaign_presets($row['recurring_presets'] ?? ''),
      ];
    }
    return $campaigns;
  }
  private static function sanitize_campaign_presets($value) {
    $presets = [];
    $lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string)$value);
    foreach ((array)$lines as $line) {
      if (is_array($line)) { $amount = $line['amount'] ?? ''; $description = $line['description'] ?? ''; }
      else { $parts = array_map('trim', explode('|', (string)$line, 2)); $amount = $parts[0] ?? ''; $description = $parts[1] ?? ''; }
      if (!is_numeric($amount)) continue;
      $presets[] = ['amount'=>(string)max(0,(float)$amount),'description'=>sanitize_text_field($description)];
    }
    return $presets;
  }
  private static function campaign_presets_text($presets) {
    $lines = [];
    foreach ((array)$presets as $preset) if (isset($preset['amount'])) $lines[] = $preset['amount'].' | '.($preset['description'] ?? '');
    return implode("\n", $lines);
  }
  private static function apply_campaign($settings, $campaign_slug) {
    $slug = sanitize_key($campaign_slug);
    if ($slug === '' || empty($settings['general']['enable_campaigns']) || empty($settings['campaigns'][$slug]) || !is_array($settings['campaigns'][$slug])) return apply_filters('straysafe_payments_applied_campaign_settings', $settings, $slug, null);
    $campaign = $settings['campaigns'][$slug];
    foreach (['title','subtitle'] as $key) if (!empty($campaign[$key])) $settings['general'][$key] = $campaign[$key];
    if (!empty($campaign['description'])) $settings['general']['intro_text'] = $campaign['description'];
    if (!empty($campaign['success_url'])) $settings['general']['success_url'] = $campaign['success_url'];
    if (!empty($campaign['thank_you_message'])) $settings['general']['thank_you_message'] = $campaign['thank_you_message'];
    foreach (['primary','background','text'] as $key) if (!empty($campaign[$key])) $settings['appearance'][$key] = $campaign[$key];
    if (!empty($campaign['one_off_presets'])) $settings['one_off']['presets'] = $campaign['one_off_presets'];
    if (!empty($campaign['recurring_presets'])) $settings['recurring']['presets'] = $campaign['recurring_presets'];
    $settings['active_campaign'] = $campaign;
    /**
     * Filters settings after a campaign has been applied.
     *
     * @param array $settings Campaign-adjusted settings.
     * @param string $slug Campaign slug.
     * @param array $campaign Campaign definition.
     */
    return apply_filters('straysafe_payments_applied_campaign_settings', $settings, $slug, $campaign);
  }
  private static function fee_profile($provider_id, $currency='GBP') {
    $currency = strtoupper($currency);
    $profiles = [
      'stripe'=>['rate'=>0.015,'fixed'=>0.20],
      'paypal'=>['rate'=>0.029,'fixed'=>0.30],
      'square'=>['rate'=>0.0175,'fixed'=>0.00],
      'gocardless'=>['rate'=>0.01,'fixed'=>0.20],
    ];
    $profile = $profiles[$provider_id] ?? ['rate'=>0,'fixed'=>0];
    if (in_array($currency, ['JPY','KRW','VND'], true)) $profile['fixed'] = 0;
    return apply_filters('straysafe_payments_fee_profile', $profile, $provider_id, $currency);
  }
  private static function amount_with_fee_recovery($amount, $provider_id, $currency='GBP') {
    $profile = self::fee_profile($provider_id, $currency);
    $rate = max(0, min(0.3, (float)($profile['rate'] ?? 0)));
    $fixed = max(0, (float)($profile['fixed'] ?? 0));
    if ($rate <= 0 && $fixed <= 0) return (float)$amount;
    return round(((float)$amount + $fixed) / (1 - $rate), 2);
  }
  public static function handle_checkout() {
    check_ajax_referer('straysafe_payments_checkout','nonce');
    $campaign = sanitize_key($_POST['campaign'] ?? '');
    $s = self::apply_campaign(self::settings(), $campaign);
    $type = sanitize_key($_POST['type'] ?? 'one_off');
    $payment_types = self::payment_types();
    $definition = $payment_types[$type] ?? $payment_types['one_off'];
    $section = $definition['settings_key'] ?? ($type === 'recurring' ? 'recurring' : 'one_off');
    $amount = (float)($_POST['amount'] ?? 0);
    /**
     * Fires before checkout validation.
     *
     * @param array $request Raw checkout request context.
     * @param array $settings Active payment settings.
     */
    do_action('straysafe_payments_before_checkout_validation', ['type'=>$type,'amount'=>$amount,'campaign'=>$campaign], $s);
    $validation_error = null;
    if (empty($s['general']['enabled']) || empty($s[$section]['enabled'] ?? 0) || $amount < (float)($s[$section]['min'] ?? 0) || $amount > (float)($s[$section]['max'] ?? PHP_FLOAT_MAX)) $validation_error = __('Please enter a valid amount.', 'straysafe-ui-suite');
    /**
     * Filters checkout validation errors.
     *
     * @param null|string $validation_error Error message or null.
     * @param array       $request Checkout request context.
     * @param array       $settings Active payment settings.
     */
    $validation_error = apply_filters('straysafe_payments_checkout_validation_error', $validation_error, ['type'=>$type,'amount'=>$amount,'campaign'=>$campaign,'payment_type'=>$definition], $s);
    if ($validation_error) wp_send_json_error(['message'=>$validation_error], 400);
    $provider = self::gateway_manager()->get($s['active_provider']);
    $caps = $provider ? $provider->get_capabilities() : [];
    $checkout_amount = (!empty($caps['fee_recovery']) && !empty($_POST['fee_recovery'])) ? self::amount_with_fee_recovery($amount, $s['active_provider'], $s['general']['currency']) : $amount;
    $request = ['amount'=>$checkout_amount,'base_amount'=>$amount,'type'=>$type,'payment_type'=>$definition,'currency'=>$s['general']['currency'],'success_url'=>$s['general']['success_url'],'cancel_url'=>$s['general']['cancel_url'],'campaign'=>$campaign,'fee_recovery'=>!empty($_POST['fee_recovery']),'gift_aid'=>!empty($_POST['gift_aid'])];
    /**
     * Fires immediately before creating checkout.
     *
     * @param array $request Normalized checkout request.
     * @param array $settings Active payment settings.
     */
    do_action('straysafe_payments_before_checkout', $request, $s);
    $result = self::gateway_manager()->create_checkout($request, $s);
    if (is_wp_error($result)) {
      /**
       * Fires when checkout creation fails.
       *
       * @param WP_Error $result Error returned by checkout.
       * @param array    $request Normalized checkout request.
       * @param array    $settings Active payment settings.
       */
      do_action('straysafe_payments_checkout_failed', $result, $request, $s);
      wp_send_json_error(['message'=>$result->get_error_message()], 400);
    }
    if (!empty($caps['gift_aid']) && !empty($_POST['gift_aid'])) self::record_gift_aid($result['id'] ?? wp_generate_uuid4(), ['provider'=>$s['active_provider'],'campaign'=>$campaign,'type'=>$type,'amount'=>$checkout_amount,'currency'=>$s['general']['currency']]);
    /**
     * Fires after checkout is created successfully.
     *
     * @param array $result Checkout result.
     * @param array $request Normalized checkout request.
     * @param array $settings Active payment settings.
     */
    do_action('straysafe_payments_checkout_created', $result, $request, $s);
    wp_send_json_success($result);
  }
  private static function record_gift_aid($checkout_id, array $data) {
    $checkout_id = sanitize_text_field($checkout_id);
    $rows = get_option(self::GIFT_AID_KEY, []);
    $rows[$checkout_id] = [
      'checkout_id'=>$checkout_id,
      'status'=>'declared',
      'declaration_date'=>current_time('mysql'),
      'provider'=>sanitize_key($data['provider'] ?? ''),
      'campaign'=>sanitize_key($data['campaign'] ?? ''),
      'type'=>sanitize_key($data['type'] ?? ''),
      'amount'=>sanitize_text_field((string)($data['amount'] ?? '')),
      'currency'=>sanitize_text_field((string)($data['currency'] ?? '')),
    ];
    update_option(self::GIFT_AID_KEY, array_slice($rows, -1000, null, true), false);
    self::audit('gift_aid_declared', ['checkout_id'=>$checkout_id,'provider'=>$rows[$checkout_id]['provider'],'date'=>$rows[$checkout_id]['declaration_date']]);
  }
  public static function export_gift_aid() {
    if (!current_user_can(self::admin_capability())) wp_die('Permission denied.');
    check_admin_referer('straysafe_payments_export_gift_aid');
    $rows = get_option(self::GIFT_AID_KEY, []);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=straysafe-gift-aid.csv');
    $out = fopen('php://output', 'w');
    if (false === $out) wp_die('Unable to open CSV output.');
    fputcsv($out, ['checkout_id','status','declaration_date','provider','campaign','type','amount','currency']);
    foreach ($rows as $row) fputcsv($out, array_map([__CLASS__,'safe_csv_cell'], [$row['checkout_id'] ?? '', $row['status'] ?? '', $row['declaration_date'] ?? '', $row['provider'] ?? '', $row['campaign'] ?? '', $row['type'] ?? '', $row['amount'] ?? '', $row['currency'] ?? '']));
    fclose($out); exit;
  }
  private static function safe_csv_cell($value) {
    $value = (string)$value;
    return preg_match('/^[=+\-@]/', $value) ? "\t".$value : $value;
  }
  public static function render_shortcode($atts=[]) {
    $s=apply_filters('straysafe_payments_widget_settings', self::apply_campaign(self::settings(), $atts['campaign'] ?? ''), $atts);
    if (empty($s['general']['enabled'])) return '';
    $p=self::gateway_manager()->get($s['active_provider']);
    $caps=$p?$p->get_capabilities():[];
    $supports_recurring=!empty($caps['subscriptions']) && !empty($s['recurring']['enabled']);
    $default=($atts['default']??$s['general']['default_type'])==='monthly'?'recurring':($atts['default']??$s['general']['default_type']);
    $default=$default==='recurring' && $supports_recurring ? 'recurring' : 'one_off';
    $widget_id=function_exists('wp_unique_id') ? wp_unique_id('ss-payments-') : 'ss-payments-'.uniqid();
    $describedby=$widget_id.'-impact '.$widget_id.'-error';
    ob_start(); ?>
<section id="<?php echo esc_attr($widget_id); ?>" class="ss-payments-widget ss-payments-<?php echo esc_attr($s['appearance']['mode']); ?>" style="--ssp-primary:<?php echo esc_attr($s['appearance']['primary']); ?>;--ssp-on-primary:<?php echo esc_attr(self::readable_text_color($s['appearance']['primary'])); ?>;--ssp-bg:<?php echo esc_attr($s['appearance']['background']); ?>;--ssp-text:<?php echo esc_attr($s['appearance']['text']); ?>;--ssp-radius:<?php echo (int)$s['appearance']['radius']; ?>px" data-currency="<?php echo esc_attr($s['general']['currency']); ?>" data-default-type="<?php echo esc_attr($default); ?>" data-provider="<?php echo esc_attr($p?$p->get_id():''); ?>" data-campaign="<?php echo esc_attr($s['active_campaign']['slug'] ?? ''); ?>" data-fee-rate="<?php echo esc_attr(self::fee_profile($s['active_provider'],$s['general']['currency'])['rate'] ?? 0); ?>" data-fee-fixed="<?php echo esc_attr(self::fee_profile($s['active_provider'],$s['general']['currency'])['fixed'] ?? 0); ?>" aria-labelledby="<?php echo esc_attr($widget_id); ?>-title">
  <h2 id="<?php echo esc_attr($widget_id); ?>-title"><?php echo esc_html($s['general']['title']); ?></h2>
  <p class="ss-payments-subtitle"><?php echo esc_html($s['general']['subtitle']); ?></p>
  <?php if($s['general']['intro_text']): ?><p class="ss-payments-intro"><?php echo esc_html($s['general']['intro_text']); ?></p><?php endif; ?>
  <?php if($s['general']['learn_more_url']): ?><p><a class="ss-payments-learn" href="<?php echo esc_url($s['general']['learn_more_url']); ?>"><?php esc_html_e('Learn more','straysafe-ui-suite'); ?></a></p><?php endif; ?>
  <?php if(!empty($s['active_campaign']['featured_image'])): ?><img class="ss-payments-campaign-image" src="<?php echo esc_url($s['active_campaign']['featured_image']); ?>" alt="" loading="lazy" /><?php endif; ?>
  <?php if(!empty($s['active_campaign']['goal'])): $progress=min(100, max(0, ((float)($s['active_campaign']['progress'] ?? 0) / (float)$s['active_campaign']['goal']) * 100)); ?><div class="ss-payments-campaign-progress" aria-label="<?php esc_attr_e('Campaign progress','straysafe-ui-suite'); ?>"><span style="width:<?php echo esc_attr($progress); ?>%"></span></div><p class="ss-payments-hint"><?php echo esc_html(self::money($s['active_campaign']['progress'] ?? 0,$s['general']['currency']).' raised of '.self::money($s['active_campaign']['goal'],$s['general']['currency'])); ?></p><?php endif; ?>
  <div class="ss-payments-types" role="tablist" aria-label="<?php esc_attr_e('Donation type','straysafe-ui-suite'); ?>">
    <button id="<?php echo esc_attr($widget_id); ?>-tab-one-off" type="button" role="tab" data-payment-type="one_off" aria-controls="<?php echo esc_attr($widget_id); ?>-panel-one-off"><?php esc_html_e('One-off','straysafe-ui-suite'); ?></button>
    <?php if($supports_recurring): ?><button id="<?php echo esc_attr($widget_id); ?>-tab-recurring" type="button" role="tab" data-payment-type="recurring" aria-controls="<?php echo esc_attr($widget_id); ?>-panel-recurring"><?php esc_html_e('Monthly','straysafe-ui-suite'); ?> <span aria-hidden="true">&hearts;</span></button><?php endif; ?>
  </div>
  <?php foreach(['one_off'=>'one-off','recurring'=>'recurring'] as $section=>$slug): if(empty($s[$section]['enabled']) || ($section==='recurring' && !$supports_recurring)) continue; ?>
    <div id="<?php echo esc_attr($widget_id.'-panel-'.$slug); ?>" class="ss-payments-amounts" role="tabpanel" data-payment-section="<?php echo esc_attr($section); ?>" data-min-amount="<?php echo esc_attr($s[$section]['min']); ?>" data-max-amount="<?php echo esc_attr($s[$section]['max']); ?>" data-min-label="<?php echo esc_attr(self::money($s[$section]['min'],$s['general']['currency'])); ?>" data-max-label="<?php echo esc_attr(self::money($s[$section]['max'],$s['general']['currency'])); ?>" aria-labelledby="<?php echo esc_attr($widget_id.'-tab-'.$slug); ?>" <?php echo $section===$default?'':'hidden'; ?>>
      <?php foreach($s[$section]['presets'] as $preset): ?><button type="button" data-payment-amount="<?php echo esc_attr($preset['amount']); ?>" data-payment-description="<?php echo esc_attr($preset['description']); ?>" aria-pressed="false"><?php echo esc_html(self::money($preset['amount'],$s['general']['currency'])); ?></button><?php endforeach; ?>
      <?php if(!empty($s[$section]['allow_custom']) && ($section==='one_off' || !empty($caps['variable_subscriptions']))): ?>
        <label for="<?php echo esc_attr($widget_id.'-'.$section.'-custom'); ?>"><?php esc_html_e('Choose your own amount','straysafe-ui-suite'); ?><span class="ss-payments-hint"><?php printf(esc_html__('Between %1$s and %2$s','straysafe-ui-suite'), esc_html(self::money($s[$section]['min'],$s['general']['currency'])), esc_html(self::money($s[$section]['max'],$s['general']['currency']))); ?></span><input id="<?php echo esc_attr($widget_id.'-'.$section.'-custom'); ?>" type="number" min="<?php echo esc_attr($s[$section]['min']); ?>" max="<?php echo esc_attr($s[$section]['max']); ?>" step="0.01" inputmode="decimal" data-payment-custom data-payment-description="<?php esc_attr_e('Custom donation amount selected.','straysafe-ui-suite'); ?>" aria-describedby="<?php echo esc_attr($describedby); ?>" /></label>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if(!empty($caps['fee_recovery'])): ?><label class="ss-payments-fee-recovery"><input type="checkbox" data-payment-fee-recovery /> <span><?php esc_html_e('I\'d like to cover the payment processing fees.', 'straysafe-ui-suite'); ?></span></label><p class="ss-payments-total" data-payment-total aria-live="polite"></p><?php endif; ?>
  <?php if(!empty($caps['gift_aid'])): ?><label class="ss-payments-gift-aid"><input type="checkbox" data-payment-gift-aid /> <span><?php esc_html_e('Yes, I want to Gift Aid my donation and any donations I make in the future or have made in the past four years to this charity. I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that tax year it is my responsibility to pay any difference.', 'straysafe-ui-suite'); ?></span></label><?php endif; ?>
  <p id="<?php echo esc_attr($widget_id); ?>-impact" class="ss-payments-impact" data-payment-impact aria-live="polite"></p>
  <p id="<?php echo esc_attr($widget_id); ?>-error" class="ss-payments-error" data-payment-error role="status" aria-live="polite"></p>
  <button type="button" class="ss-payments-continue" data-payment-continue disabled><?php echo esc_html($s['general']['button_text']); ?></button>
</section><?php return ob_get_clean(); }
  private static function money($amount,$currency){ $symbol=['GBP'=>'£','USD'=>'$','EUR'=>'€'][$currency]??$currency.' '; return $symbol.rtrim(rtrim(number_format((float)$amount,2), '0'), '.'); }
  private static function readable_text_color($hex){ $hex=ltrim((string)$hex,'#'); if(strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; if(strlen($hex)!==6) return '#ffffff'; $r=hexdec(substr($hex,0,2)); $g=hexdec(substr($hex,2,2)); $b=hexdec(substr($hex,4,2)); $l=(0.2126*$r+0.7152*$g+0.0722*$b)/255; return $l>0.55?'#111827':'#ffffff'; }
  private static function dashboard_rows($filters=[]) {
    $rows = [];
    foreach ((array)get_option(self::PAYMENT_EVENTS_KEY, []) as $event) {
      $data = is_array($event['data'] ?? null) ? $event['data'] : [];
      $amount = self::dashboard_amount($data['amount'] ?? $event['amount'] ?? 0, $data['currency'] ?? $event['currency'] ?? 'GBP');
      $row = [
        'id'=>$event['id'] ?? '', 'status'=>sanitize_key($event['status'] ?? ''), 'date'=>$event['updated_at'] ?? '',
        'provider'=>sanitize_key($data['provider'] ?? ''), 'campaign'=>sanitize_key($data['campaign'] ?? ''),
        'type'=>sanitize_key($data['type'] ?? (strpos((string)($event['status'] ?? ''),'subscription') !== false ? 'recurring' : 'one_off')),
        'currency'=>strtoupper(sanitize_text_field($data['currency'] ?? 'GBP')), 'amount'=>$amount,
      ];
      if (self::dashboard_row_matches($row, $filters)) $rows[] = $row;
    }
    foreach ((array)get_option(self::GIFT_AID_KEY, []) as $gift) {
      $row = ['id'=>$gift['checkout_id'] ?? '', 'status'=>'declared', 'date'=>$gift['declaration_date'] ?? '', 'provider'=>sanitize_key($gift['provider'] ?? ''), 'campaign'=>sanitize_key($gift['campaign'] ?? ''), 'type'=>sanitize_key($gift['type'] ?? ''), 'currency'=>strtoupper(sanitize_text_field($gift['currency'] ?? 'GBP')), 'amount'=>(float)($gift['amount'] ?? 0)];
      if (self::dashboard_row_matches($row, $filters)) $rows[] = $row;
    }
    return $rows;
  }
  private static function dashboard_amount($amount, $currency) { $amount=(float)$amount; return $amount > 1000 && !in_array(strtoupper($currency), ['JPY','KRW','VND'], true) ? $amount/100 : $amount; }
  private static function dashboard_row_matches($row, $filters) {
    foreach (['campaign','provider','type','currency'] as $key) if (!empty($filters[$key]) && strtolower((string)$row[$key]) !== strtolower((string)$filters[$key])) return false;
    $time = !empty($row['date']) ? strtotime($row['date']) : 0;
    if (!empty($filters['date_from']) && $time && $time < strtotime($filters['date_from'].' 00:00:00')) return false;
    if (!empty($filters['date_to']) && $time && $time > strtotime($filters['date_to'].' 23:59:59')) return false;
    return true;
  }
  private static function dashboard_sum($rows, $callback) { $sum=0; foreach($rows as $row) if($callback($row)) $sum+=(float)$row['amount']; return $sum; }
  private static function dashboard_top($rows, $key) { $totals=[]; foreach($rows as $row){ $label=$row[$key] ?: __('Unassigned','straysafe-ui-suite'); $totals[$label]=($totals[$label]??0)+(float)$row['amount']; } arsort($totals); return array_slice($totals,0,5,true); }
  private static function render_dashboard($settings, $providers) {
    $filters = ['campaign'=>sanitize_key($_GET['dashboard_campaign']??''),'provider'=>sanitize_key($_GET['dashboard_provider']??''),'type'=>sanitize_key($_GET['dashboard_type']??''),'currency'=>strtoupper(sanitize_text_field($_GET['dashboard_currency']??'')),'date_from'=>sanitize_text_field($_GET['dashboard_date_from']??''),'date_to'=>sanitize_text_field($_GET['dashboard_date_to']??'')];
    $rows = self::dashboard_rows($filters); $now=current_time('timestamp');
    $success = ['succeeded','paid','invoice_paid','completed','created','declared','approved']; $refund = ['refunded','reversed','chargeback','failed'];
    $today = self::dashboard_sum($rows, function($r) use ($now){ return !empty($r['date']) && date('Y-m-d',strtotime($r['date']))===date('Y-m-d',$now); });
    $month = self::dashboard_sum($rows, function($r) use ($now){ return !empty($r['date']) && date('Y-m',strtotime($r['date']))===date('Y-m',$now); });
    $year = self::dashboard_sum($rows, function($r) use ($now){ return !empty($r['date']) && date('Y',strtotime($r['date']))===date('Y',$now); });
    $recurring = self::dashboard_sum($rows, function($r){ return $r['type']==='recurring' || strpos($r['status'],'subscription') !== false; });
    $avg = count($rows)?array_sum(array_column($rows,'amount'))/count($rows):0;
    $successful = count(array_filter($rows, function($r) use ($success){ return in_array($r['status'],$success,true) || strpos($r['status'],'paid') !== false; })); $refunded=count(array_filter($rows, function($r) use ($refund){ return in_array($r['status'],$refund,true) || strpos($r['status'],'refund') !== false; }));
    $success_rate = count($rows)?round(($successful/count($rows))*100,1):0; $refund_rate=count($rows)?round(($refunded/count($rows))*100,1):0;
    echo '<style>.ssp-dashboard{display:grid;gap:16px;margin:16px 0}.ssp-dashboard-filters,.ssp-dashboard-cards,.ssp-dashboard-charts{display:grid;gap:12px}.ssp-dashboard-filters{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));align-items:end}.ssp-dashboard-cards{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}.ssp-card,.ssp-chart{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px}.ssp-card strong{display:block;font-size:1.35rem}.ssp-bar{height:12px;background:#eef2f7;border-radius:999px;overflow:hidden}.ssp-bar span{display:block;height:100%;background:#401268}.ssp-chart-row{display:grid;grid-template-columns:minmax(90px,160px) 1fr auto;gap:8px;align-items:center;margin:8px 0}@media(max-width:600px){.ssp-chart-row{grid-template-columns:1fr}.ssp-dashboard-filters{grid-template-columns:1fr}}</style>';
    echo '<div class="ssp-dashboard"><h2>'.esc_html__('Payments Dashboard','straysafe-ui-suite').'</h2><form class="ssp-dashboard-filters" method="get"><input type="hidden" name="page" value="straysafe-payments"/>';
    echo '<label>Campaign <select name="dashboard_campaign"><option value="">All</option>'; foreach($settings['campaigns']??[] as $slug=>$c) echo '<option value="'.esc_attr($slug).'" '.selected($filters['campaign'],$slug,false).'>'.esc_html($slug).'</option>'; echo '</select></label>';
    echo '<label>Provider <select name="dashboard_provider"><option value="">All</option>'; foreach($providers as $id=>$p) echo '<option value="'.esc_attr($id).'" '.selected($filters['provider'],$id,false).'>'.esc_html($p->get_name()).'</option>'; echo '</select></label>';
    echo '<label>Date from <input type="date" name="dashboard_date_from" value="'.esc_attr($filters['date_from']).'"/></label><label>Date to <input type="date" name="dashboard_date_to" value="'.esc_attr($filters['date_to']).'"/></label><label>Donation type <select name="dashboard_type"><option value="">All</option><option value="one_off" '.selected($filters['type'],'one_off',false).'>One-off</option><option value="recurring" '.selected($filters['type'],'recurring',false).'>Recurring</option></select></label><label>Currency <input name="dashboard_currency" value="'.esc_attr($filters['currency']).'" placeholder="GBP"/></label><button class="button button-primary">Filter</button></form>';
    foreach ([['Today',$today],['This month',$month],['This year',$year],['Recurring monthly income',$recurring],['Average donation',$avg],['Payment success rate',$success_rate.'%'],['Refund rate',$refund_rate.'%']] as $card) echo '<div class="ssp-card"><span>'.esc_html($card[0]).'</span><strong>'.esc_html(is_numeric($card[1])?self::money($card[1],$filters['currency']?:'GBP'):$card[1]).'</strong></div>';
    echo '<div class="ssp-dashboard-charts"><div class="ssp-chart"><h3>Campaign progress</h3>'.self::dashboard_campaign_progress_chart($settings['campaigns']??[],$filters['currency']?:'GBP').'</div><div class="ssp-chart"><h3>Top campaigns</h3>'.self::dashboard_chart(self::dashboard_top($rows,'campaign'),$filters['currency']?:'GBP').'</div><div class="ssp-chart"><h3>Top payment methods</h3>'.self::dashboard_chart(self::dashboard_top($rows,'provider'),$filters['currency']?:'GBP').'</div></div></div>';
  }
  private static function dashboard_campaign_progress_chart($campaigns, $currency){ $items=[]; foreach((array)$campaigns as $slug=>$campaign){ if(!empty($campaign['goal'])) $items[$slug]=['progress'=>(float)($campaign['progress']??0),'goal'=>(float)$campaign['goal']]; } if(empty($items)) return '<p>'.esc_html__('No campaign goals configured.','straysafe-ui-suite').'</p>'; $html=''; foreach($items as $slug=>$data){ $pct=$data['goal']>0?min(100,($data['progress']/$data['goal'])*100):0; $html.='<div class="ssp-chart-row"><span>'.esc_html($slug).'</span><div class="ssp-bar"><span style="width:'.esc_attr($pct).'%"></span></div><strong>'.esc_html(self::money($data['progress'],$currency).' / '.self::money($data['goal'],$currency)).'</strong></div>'; } return $html; }
  private static function dashboard_chart($items,$currency){ if(empty($items)) return '<p>'.esc_html__('No data yet.','straysafe-ui-suite').'</p>'; $max=max($items); $html=''; foreach($items as $label=>$value){ $pct=$max>0?($value/$max)*100:0; $html.='<div class="ssp-chart-row"><span>'.esc_html($label).'</span><div class="ssp-bar"><span style="width:'.esc_attr($pct).'%"></span></div><strong>'.esc_html(self::money($value,$currency)).'</strong></div>'; } return $html; }
  public static function render_admin_page() {
    if(!current_user_can(self::admin_capability())) return;
    $s=self::settings();
    $providers=self::gateway_manager()->all();
    $name=self::OPTION_KEY;
    echo '<div class="wrap"><h1>'.esc_html__('Payments','straysafe-ui-suite').'</h1>';
    self::render_dashboard($s, $providers);
    if (isset($_GET['settings-updated'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Payments settings saved.','straysafe-ui-suite').'</p></div>';
    echo '<form method="post" action="'.esc_url(admin_url('options.php')).'">';
    settings_fields('straysafe_payments_settings');
    echo '<h2>'.esc_html__('Payment Provider','straysafe-ui-suite').'</h2><p>'.esc_html__('Choose the checkout provider and enter its credentials. The selected provider controls the capabilities shown by the donation widget.','straysafe-ui-suite').'</p>';
    foreach($providers as $id=>$p){
      echo '<label style="display:block;margin:8px 0"><input type="radio" name="'.esc_attr($name).'[active_provider]" value="'.esc_attr($id).'" '.checked($s['active_provider'],$id,false).'/> '.esc_html($p->get_name()).'</label><div class="ssp-provider-fields" data-provider="'.esc_attr($id).'"><table class="form-table" role="presentation">';
      $status=$p->validate_configuration($s['provider_settings'][$id]??[]);
      echo '<tr><th>'.esc_html__('Connection status','straysafe-ui-suite').'</th><td><strong>'.esc_html($status['connected']?'Connected':'Needs configuration').'</strong><p class="description">'.esc_html($status['message']).'</p></td></tr>';
      foreach($p->get_configuration_fields() as $key=>$f){
        $val=$s['provider_settings'][$id][$key]??'';
        echo '<tr><th><label for="ssp-'.esc_attr($id.'-'.$key).'">'.esc_html($f['label']).'</label></th><td>';
        if($f['type']==='checkbox') echo '<input id="ssp-'.esc_attr($id.'-'.$key).'" type="checkbox" name="'.esc_attr($name).'[provider_settings]['.esc_attr($id).']['.esc_attr($key).']" value="1" '.checked($val,1,false).'/>';
        else { $display_val = $f['type']==='password' ? '' : $val; echo '<input id="ssp-'.esc_attr($id.'-'.$key).'" class="regular-text" type="'.esc_attr($f['type']).'" name="'.esc_attr($name).'[provider_settings]['.esc_attr($id).']['.esc_attr($key).']" value="'.esc_attr($display_val).'" autocomplete="off"/>'; if($f['type']==='password' && $val !== '') echo '<p class="description">'.esc_html__('Saved. Leave blank to keep the existing value.','straysafe-ui-suite').'</p>'; }
        echo '</td></tr>';
      }
      echo '</table></div>';
    }
    echo '<h2>'.esc_html__('General','straysafe-ui-suite').'</h2><table class="form-table" role="presentation">';
    foreach(['enabled'=>'Enable widget','enable_campaigns'=>'Enable campaigns'] as $k=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="checkbox" name="'.esc_attr($name).'[general]['.esc_attr($k).']" value="1" '.checked($s['general'][$k],1,false).'/></td></tr>';
    foreach(['title','subtitle','intro_text','currency','success_url','cancel_url','button_text','thank_you_message','learn_more_url'] as $k) echo '<tr><th>'.esc_html(ucwords(str_replace('_',' ',$k))).'</th><td><input class="regular-text" name="'.esc_attr($name).'[general]['.esc_attr($k).']" value="'.esc_attr($s['general'][$k]).'"/></td></tr>';
    echo '<tr><th>'.esc_html__('Default type','straysafe-ui-suite').'</th><td><select name="'.esc_attr($name).'[general][default_type]"><option value="one_off" '.selected($s['general']['default_type'],'one_off',false).'>One-off</option><option value="recurring" '.selected($s['general']['default_type'],'recurring',false).'>Recurring</option></select></td></tr></table><h2>'.esc_html__('Amounts','straysafe-ui-suite').'</h2>';
    foreach(['one_off'=>'One-off Donations','recurring'=>'Recurring Donations'] as $section=>$label){
      echo '<h3>'.esc_html($label).'</h3><p><label><input type="checkbox" name="'.esc_attr($name).'['.esc_attr($section).'][enabled]" value="1" '.checked($s[$section]['enabled'],1,false).'/> Enabled</label> <label><input type="checkbox" name="'.esc_attr($name).'['.esc_attr($section).'][allow_custom]" value="1" '.checked($s[$section]['allow_custom'],1,false).'/> Allow custom amount</label></p>';
      foreach(['min','max','default'] as $k) echo '<label style="margin-right:12px">'.esc_html(ucfirst($k)).' <input class="small-text" name="'.esc_attr($name).'['.esc_attr($section).']['.esc_attr($k).']" value="'.esc_attr($s[$section][$k]).'"/></label>';
      echo '<table class="widefat striped"><thead><tr><th>Amount</th><th>Description</th><th>Icon</th><th>Colour</th><th>Campaign</th></tr></thead><tbody>';
      $rows=array_pad($s[$section]['presets'],8,[]);
      foreach($rows as $i=>$r) echo '<tr><td><input name="'.esc_attr($name).'['.esc_attr($section).'][presets]['.(int)$i.'][amount]" value="'.esc_attr($r['amount']??'').'"/></td><td><input class="regular-text" name="'.esc_attr($name).'['.esc_attr($section).'][presets]['.(int)$i.'][description]" value="'.esc_attr($r['description']??'').'"/></td><td><input name="'.esc_attr($name).'['.esc_attr($section).'][presets]['.(int)$i.'][icon]" value="'.esc_attr($r['icon']??'').'"/></td><td><input type="color" name="'.esc_attr($name).'['.esc_attr($section).'][presets]['.(int)$i.'][colour]" value="'.esc_attr($r['colour']??'#401268').'"/></td><td><input name="'.esc_attr($name).'['.esc_attr($section).'][presets]['.(int)$i.'][campaign]" value="'.esc_attr($r['campaign']??'').'"/></td></tr>';
      echo '</tbody></table>';
    }
    echo '<h2>'.esc_html__('Campaigns','straysafe-ui-suite').'</h2><p class="description">'.esc_html__('Create reusable campaign overrides. Use the slug in the shortcode, for example [donation_widget campaign="winter"]. Suggested amount lines use: amount | impact description.','straysafe-ui-suite').'</p><div id="ssp-campaigns">';
    $campaign_rows = array_values($s['campaigns'] ?? []); $campaign_rows[] = [];
    foreach($campaign_rows as $i=>$campaign){
      echo '<details class="ssp-campaign" '.(!empty($campaign['slug'])?'open':'').'><summary>'.esc_html(!empty($campaign['slug']) ? $campaign['slug'] : __('New campaign','straysafe-ui-suite')).'</summary><table class="form-table" role="presentation">';
      foreach(['slug'=>'Slug','title'=>'Title','subtitle'=>'Subtitle','featured_image'=>'Featured image URL','success_url'=>'Success page URL','thank_you_message'=>'Thank you message'] as $k=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input class="regular-text" name="'.esc_attr($name).'[campaigns]['.(int)$i.']['.esc_attr($k).']" value="'.esc_attr($campaign[$k]??'').'"/></td></tr>';
      echo '<tr><th>'.esc_html__('Description','straysafe-ui-suite').'</th><td><textarea class="large-text" rows="3" name="'.esc_attr($name).'[campaigns]['.(int)$i.'][description]">'.esc_textarea($campaign['description']??'').'</textarea></td></tr>';
      echo '<tr><th>'.esc_html__('Goal / progress','straysafe-ui-suite').'</th><td><input class="small-text" name="'.esc_attr($name).'[campaigns]['.(int)$i.'][goal]" value="'.esc_attr($campaign['goal']??'').'"/> <input class="small-text" name="'.esc_attr($name).'[campaigns]['.(int)$i.'][progress]" value="'.esc_attr($campaign['progress']??'').'"/></td></tr>';
      foreach(['primary'=>'Primary colour','background'=>'Background colour','text'=>'Text colour'] as $k=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="color" name="'.esc_attr($name).'[campaigns]['.(int)$i.']['.esc_attr($k).']" value="'.esc_attr($campaign[$k]??'#ffffff').'"/></td></tr>';
      echo '<tr><th>'.esc_html__('One-off suggested amounts','straysafe-ui-suite').'</th><td><textarea class="large-text" rows="4" name="'.esc_attr($name).'[campaigns]['.(int)$i.'][one_off_presets]">'.esc_textarea(self::campaign_presets_text($campaign['one_off_presets']??[])).'</textarea></td></tr>';
      echo '<tr><th>'.esc_html__('Recurring suggested amounts','straysafe-ui-suite').'</th><td><textarea class="large-text" rows="4" name="'.esc_attr($name).'[campaigns]['.(int)$i.'][recurring_presets]">'.esc_textarea(self::campaign_presets_text($campaign['recurring_presets']??[])).'</textarea></td></tr>';
      echo '</table></details>';
    }
    echo '</div><p><button type="button" class="button" id="ssp-add-campaign">'.esc_html__('Add another campaign','straysafe-ui-suite').'</button></p><script>document.getElementById("ssp-add-campaign").addEventListener("click",function(){var c=document.querySelector("#ssp-campaigns .ssp-campaign:last-child"),n=c.cloneNode(true),i=document.querySelectorAll("#ssp-campaigns .ssp-campaign").length;n.querySelector("summary").textContent="New campaign";n.querySelectorAll("input,textarea").forEach(function(el){el.name=el.name.replace(/\\[campaigns\\]\\[\\d+\\]/,"[campaigns]["+i+"]");if(el.type!=="color")el.value="";});n.open=true;c.parentNode.appendChild(n);});</script>';
    echo '<h2>'.esc_html__('Appearance','straysafe-ui-suite').'</h2><table class="form-table" role="presentation">';
    foreach(['primary'=>'Primary colour','background'=>'Background colour','text'=>'Text colour'] as $k=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="color" name="'.esc_attr($name).'[appearance]['.esc_attr($k).']" value="'.esc_attr($s['appearance'][$k]).'"/></td></tr>';
    echo '<tr><th>Corner radius</th><td><input type="number" min="0" max="48" name="'.esc_attr($name).'[appearance][radius]" value="'.esc_attr($s['appearance']['radius']).'"/></td></tr><tr><th>Mode</th><td><select name="'.esc_attr($name).'[appearance][mode]"><option value="light" '.selected($s['appearance']['mode'],'light',false).'>Light</option><option value="dark" '.selected($s['appearance']['mode'],'dark',false).'>Dark</option></select></td></tr></table>';
    $gift_rows = get_option(self::GIFT_AID_KEY, []);
    echo '<h2>'.esc_html__('Payment history and Gift Aid','straysafe-ui-suite').'</h2><p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=straysafe_payments_export_gift_aid'),'straysafe_payments_export_gift_aid')).'">'.esc_html__('Export Gift Aid CSV','straysafe-ui-suite').'</a></p>';
    echo '<table class="widefat striped"><thead><tr><th>Checkout</th><th>Status</th><th>Date</th><th>Provider</th><th>Campaign</th><th>Amount</th><th>Gift Aid</th></tr></thead><tbody>';
    if (empty($gift_rows)) echo '<tr><td colspan="7">'.esc_html__('No Gift Aid declarations recorded yet.','straysafe-ui-suite').'</td></tr>';
    foreach(array_reverse($gift_rows) as $row) echo '<tr><td>'.esc_html($row['checkout_id']??'').'</td><td>'.esc_html($row['status']??'').'</td><td>'.esc_html($row['declaration_date']??'').'</td><td>'.esc_html($row['provider']??'').'</td><td>'.esc_html($row['campaign']??'').'</td><td>'.esc_html(($row['currency']??'').' '.($row['amount']??'')).'</td><td>'.esc_html(($row['status']??'')==='declared' ? __('Yes','straysafe-ui-suite') : __('No','straysafe-ui-suite')).'</td></tr>';
    echo '</tbody></table>';
    submit_button('Save Payments Settings');
    echo '</form><hr/><h2>'.esc_html__('Preview','straysafe-ui-suite').'</h2><p class="description">'.esc_html__('This preview renders the saved donation widget exactly as WordPress will render it from the shortcode.','straysafe-ui-suite').'</p><div class="ssp-payments-preview">'.self::render_shortcode().'</div></div>';
    echo '<script>document.querySelectorAll("input[name=\"'.esc_js($name).'[active_provider]\"]").forEach(function(r){function u(){var c=document.querySelector("input[name=\"'.esc_js($name).'[active_provider]\"]:checked");document.querySelectorAll(".ssp-provider-fields").forEach(function(e){e.style.display=c&&e.dataset.provider===c.value?"block":"none"})}r.addEventListener("change",u);u();});</script>';
  }
}
StraySafe_Payments_Module::init();
