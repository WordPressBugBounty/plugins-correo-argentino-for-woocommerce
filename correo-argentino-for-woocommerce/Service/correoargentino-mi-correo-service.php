<?php
namespace Corrarfo\Service;

use Corrarfo\Lib\Utils;
use Exception;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

require_once CORRARFO_PLUGIN_DIR . 'Helper/correoargentino-order-prefix-helper.php';


class MiCorreoService implements ServiceMiCorreoInterface
{
    public $client;
    public $url;
    private $username;
    private $password;
    private $authHash;
    private $accessToken;

    /**
     * Prevents multiple failed login attempts within the same PHP process/request.
     */
    private static $renewalFailed = false;

    public function __construct()
    {
        $credentials = maybe_unserialize(get_option(CORRARFO_CREDENTIALS));
        $this->url = (int) ($credentials['sandbox'] ?? 0) === 1 ? CORRARFO_MI_CORREO_API_SANDBOX_URL : CORRARFO_MI_CORREO_API_URL;
        $this->authHash = $credentials['auth_hash'] ?? null;
        
        if ($this->isValidToken()) {
            $this->accessToken = $credentials['access_token'] ?? null;
        } else {
            $this->renewToken();
        }
    }

    /**
     * @throws Exception
     */
    public static function isValidToken(): bool
    {
        $credentials = maybe_unserialize(get_option(CORRARFO_CREDENTIALS));
        if (!isset($credentials['access_token']) || !isset($credentials['expire'])) {
            return false;
        }

        // Check if token belongs to the current environment
        $currentSandbox = (int) ($credentials['sandbox'] ?? 0);
        $tokenSandbox = (int) ($credentials['token_sandbox'] ?? -1);
        if ($currentSandbox !== $tokenSandbox) {
            return false;
        }

        try {
            $tz = new \DateTimeZone('America/Argentina/Buenos_Aires');
            $expires = new \DateTime($credentials['expire'], $tz);
            $now = new \DateTime('now');

            // 60-second safety buffer to avoid token expiration mid-request
            if ($expires->getTimestamp() - 60 < $now->getTimestamp()) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Transient key for the cross-request login cooldown.
     * Scoped by environment URL and credentials so changing either resets it.
     */
    private function getLoginCooldownKey(): string
    {
        return 'corrarfo_mc_login_cd_' . md5($this->url . '|' . (string) $this->authHash);
    }

    /**
     * @return bool
     */
    public function renewToken(): bool
    {
        if (self::$renewalFailed) {
            return false;
        }

        // A recent /token failure (in any request) means we must not hit the API again yet.
        $cooldownKey = $this->getLoginCooldownKey();
        if (false !== get_transient($cooldownKey)) {
            self::$renewalFailed = true;
            return false;
        }

        $response = $this->login();
        $credentials = Utils::getCredentials();
        if ($response && isset($response['token'])) {
            $credentials["access_token"] = $response["token"];
            $credentials["expire"] = $response["expire"];
            $credentials["token_sandbox"] = (int) ($credentials['sandbox'] ?? 0);
            $data = maybe_serialize($credentials);
            update_option(CORRARFO_CREDENTIALS, $data);

            // Update instance variable for immediate use
            $this->accessToken = $response["token"];
            self::$renewalFailed = false;
            delete_transient($cooldownKey);
            return true;
        }
        self::$renewalFailed = true;
        set_transient($cooldownKey, 1, CORRARFO_TOKEN_COOLDOWN_SECONDS);
        return false;
    }

    public function login()
    {
        $headers = $this->setHeaders([
            "Authorization" => "Basic " . $this->authHash
        ]);

        $request = wp_remote_post(
            $this->url . '/token',
            [
                'method' => 'POST',
                'headers' => $headers,
            ]
        );

        if (is_wp_error($request)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'MiCorreo: Login request failed: ' . $request->get_error_message(),
                    ['source' => 'correoargentino']
                );
            }
            return null;
        }

        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (wp_remote_retrieve_response_code($request) != 200) {
            return $response;
        }
        return $response;
    }

    public function setHeaders($headers = [])
    {
        $defaultHeaders = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Connection" => "keep-alive",
        ];
        return array_merge($defaultHeaders, $headers);
    }

    public function registerOrder($orderId)
    {
        $order = wc_get_order($orderId);
        corrarfo_save_prefixed_order_id_meta($order);
        $shippingMethods = $order->get_shipping_methods();
        $chosenShippingMethod = reset($shippingMethods);
        $corrarfo_data = Utils::getServiceDataFromShippingMethod($chosenShippingMethod);

        $orderData = $order->get_data();
        $shippingData = array_merge([
            'floor' => '',
            'department' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'phone' => '',
            'address_1' => '',
            'address_2' => '',
        ], $orderData['shipping']);
        $billing = $orderData['billing'];

        $branchId = $order->get_meta(CORRARFO_CHOSEN_BRANCH);
        $productType = $chosenShippingMethod ? $chosenShippingMethod->get_meta(CORRARFO_CHOSEN_PRODUCT_TYPE) : '';
        $items = $order->get_items();
        $shippingMethodName = $order->get_shipping_method();

        $streetName = $shippingData['address_1'];
        $altura = Utils::normalizeStreetNumber($shippingData['address_2'] ?? '');

        $isBranch = stripos($shippingMethodName, CORRARFO_BRANCH);
        $settings = array_merge(Utils::getSettings(), $corrarfo_data);

        $recipient = [
            "name" => $billing['first_name'] . ' ' . $billing['last_name'],
            "email" => $billing['email'],
            "phone" => $shippingData['phone'],
            "cellPhone" => $shippingData['phone'],
        ];

        $weight = 0;
        $height = 0;
        $restDimensions = [];

        foreach ($items as $item) {
            $quantity = max(1, (int) $item->get_quantity());

            $dims = Utils::getOrderItemDimensionsStrict($item);

            $productWeightGr = (float) $dims['weight'];
            $dimsArray = [
                (float) $dims['length'],
                (float) $dims['width'],
                (float) $dims['height'],
            ];

            $dimsArray = array_map('ceil', $dimsArray);
            sort($dimsArray);

            $weight += $productWeightGr * $quantity;
            $height += $dimsArray[0] * $quantity;

            for ($i = 0; $i < $quantity; $i++) {
                $restDimensions[] = [$dimsArray[1], $dimsArray[2]];
            }
        }

        $widths = array_column($restDimensions, 0);
        $lengths = array_column($restDimensions, 1);

        $width = max($widths);
        $length = max($lengths);

        $declaredValueTotal = array_reduce($items, function ($carry, $item) {
            $carry += (float) $item->get_total();
            return $carry;
        });

        $shipping = [
            "deliveryType" => $isBranch ? "S" : "D",
            "productType" => $productType,
            "declaredValue" => $declaredValueTotal,
            "weight" => $weight,
            "height" => $height,
            "length" => $length,
            "width" => $width,
            "agency" => "$branchId",
            "address" => [
                "streetName" => $streetName,
                "streetNumber" => Utils::streetNumberForApi($altura),
                "floor" => $shippingData['floor'],
                "apartment" => $shippingData["department"],
                "city" => $shippingData['city'],
                "provinceCode" => $shippingData['state'],
                "postalCode" => Utils::normalizeZipCode($shippingData['postcode'])
            ],
        ];

        $sender = [
            "name" => $settings['first_name'] . " " . $settings['last_name'],
            "phone" => Utils::cleanPhone($settings['phone']),
            "cellPhone" => Utils::cleanPhone($settings['cellphone']),
            "email" => $settings['email'],
            "originAddress" => [
                "streetName" => $settings['street_name'],
                "streetNumber" => $settings['street_number'],
                "floor" => $settings['floor'],
                "apartment" => $settings['department'],
                "city" => $settings['city_name'],
                "provinceCode" => $settings['state'],
                "postalCode" => Utils::normalizeZipCode($settings['zip_code'])
            ]
        ];
        $dimensionString = "{$length}x{$width}x{$height}";
        $weightKg = round($weight / 1000, 2);

        $order->update_meta_data('_corrarfo_dimensions', $dimensionString);
        $order->update_meta_data('_corrarfo_weight', $weightKg);
        $order->save();

        $extOrderId = $orderId;

        if (empty($settings['customer_id'])) {
            $credentials = Utils::getCredentials();
            if (!empty($credentials['idCliente'])) {
                $settings['customer_id'] = $credentials['idCliente'];
            }
        }
        $customOrderId = corrarfo_get_modified_order_id($orderId);

        $body = [
            "customerId" => $settings['customer_id'],
            "extOrderId" => strval($extOrderId),
            "orderNumber" => $customOrderId,
            "sender" => $sender,
            "recipient" => $recipient,
            "shipping" => $shipping
        ];

        $headers = $this->setHeaders([
            "Authorization" => "Bearer " . $this->accessToken,
            "Content-Type" => "application/json"
        ]);

        $request = wp_remote_post(
            $this->url . '/shipping/import',
            [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($body),
            ]
        );

        if (is_wp_error($request)) {
            $raw_error_message = $request->get_error_message();
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'MiCorreo: Order import request failed: ' . $raw_error_message,
                    ['source' => 'correoargentino']
                );
            }
            $friendly_message = Utils::formatApiErrorMessage($raw_error_message);
            $order->update_meta_data(CORRARFO_ORDER_API_ERROR, $friendly_message);
            $order->save();
            return [
                'error'             => true,
                'status'            => 500,
                'message'           => $friendly_message,
                'api_error_message' => $friendly_message,
                'raw_error_message' => $raw_error_message,
            ];
        }

        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (!is_array($response)) {
            $response = [];
        }

        $response["status"] = (int) wp_remote_retrieve_response_code($request);
        if ($response["status"] === 200) {
            $response['reference'] = $extOrderId;
            $order->update_meta_data(CORRARFO_TRACKING_NUMBER, $extOrderId);
            $order->delete_meta_data(CORRARFO_ORDER_API_ERROR);

            // Change order status only upon successful shipment creation
            if (\Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_AUTOSTATUS_ENABLED, 'no', CORRARFO_MI_CORREO) === 'yes') {
                $new_status = \Corrarfo\Lib\ServiceOptions::get(CORRARFO_OPTION_AUTOSTATUS_STATUS, '', CORRARFO_MI_CORREO);
                $valid_statuses = wc_get_order_statuses();

                if (isset($valid_statuses[$new_status])) {
                    $order->update_status($new_status, __('Automatic status change after successful shipment to MiCorreo.', 'correo-argentino-for-woocommerce'));
                } else {
                    $order->add_order_note(__('The status was not changed. Configured status is invalid.', 'correo-argentino-for-woocommerce'));
                }
            }

            $order->save();
        } else {
            $raw_message = !empty($response['message']) ? $response['message'] : wp_remote_retrieve_body($request);
            $error_message = Utils::formatApiErrorMessage(
                !empty($raw_message) ? $raw_message : __('Failed to import order into MiCorreo.', 'correo-argentino-for-woocommerce'),
                $response["status"]
            );
            $order->update_meta_data(CORRARFO_ORDER_API_ERROR, $error_message);
            $order->save();
            $response['message'] = $error_message;
            $response['api_error_message'] = $error_message;
        }

        return $response;
    }



    /**
     * @throws Exception
     */
    public function getRates($postalCode, $deliveryType, $dimensions, $settings = null)
    {
        if (get_option(CORRARFO_USE_RATES) == 0) {
            // No use rates
            return $this->emptyRates($deliveryType);
        }
        return $this->getCalculatedRates($postalCode, $deliveryType, $dimensions, $settings);
    }

    private function emptyRates(string $deliveryType): array
    {
        return [
            "customerId" => "",
            "validTo" => "",
            "rates" => [
                [
                    "deliveredType" => "$deliveryType",
                    "productType" => "CP",
                    "productName" => "Correo Argentino Clasico",
                    "price" => 0,
                    "deliveryTimeMin" => "2",
                    "deliveryTimeMax" => "5"
                ],
                [
                    "deliveredType" => "$deliveryType",
                    "productType" => "EP",
                    "productName" => "Correo Argentino Expreso",
                    "price" => 0,
                    "deliveryTimeMin" => "1",
                    "deliveryTimeMax" => "3"
                ],
            ]
        ];
    }

    public function getCalculatedRates($postalCode, $deliveryType, $dimensions, $settings = null)
    {
        $dimensions = array_map(function ($dimension) {
            return Utils::toInteger($dimension);
        }, $dimensions);


        $baseSettings = Utils::getSettings();
        $settings = is_array($settings) ? $settings : [];
        $settings = array_merge($baseSettings, $settings);

        if (!isset($this->accessToken)) {
            // Attempt to renew token once more before giving up
            $this->renewToken();
        }

        if (!isset($this->accessToken)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'MiCorreo: Failed to obtain valid access token for rates calculation.',
                    ['source' => 'correoargentino']
                );
            }
            return [
                'error'   => true,
                'status'  => 'error',
                'message' => 'Unable to authenticate with Correo Argentino API.',
                'rates'   => []
            ];
        }

        $headers = $this->setHeaders([
            "Authorization" => "Bearer " . $this->accessToken
        ]);

        if (empty($settings['customer_id'])) {
            $credentials = Utils::getCredentials();
            if (!empty($credentials['idCliente'])) {
                $settings['customer_id'] = $credentials['idCliente'];
            }
        }

        $body = [
            "customerId" => $settings["customer_id"],
            "postalCodeOrigin" => Utils::normalizeZipCode($settings["zip_code"]),
            "postalCodeDestination" => Utils::normalizeZipCode($postalCode),
            "deliveredType" => $deliveryType,
            "dimensions" => $dimensions
        ];

        $request = wp_safe_remote_post(
            $this->url . '/rates',
            [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($body)
            ]
        );

        if (is_wp_error($request)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'MiCorreo: Rates calculation request failed: ' . $request->get_error_message(),
                    ['source' => 'correoargentino']
                );
            }
            return [
                'error'   => true,
                'status'  => 'error',
                'message' => $request->get_error_message(),
                'rates'   => []
            ];
        }

        $response = json_decode(wp_remote_retrieve_body($request), true);
        if (!is_array($response)) {
            $response = [];
        }
        $response['status'] = wp_remote_retrieve_response_code($request);
        $response['message'] = wp_remote_retrieve_response_message($request);
        return $response;
    }

    /**
     * Performs the /agencies request with the current access token.
     *
     * @param array $query
     * @return array|\WP_Error
     */
    private function requestAgencies(array $query)
    {
        return wp_remote_get(
            add_query_arg($query, $this->url . '/agencies'),
            [
                'method' => 'GET',
                'headers' => $this->setHeaders([
                    "Authorization" => "Bearer " . $this->accessToken
                ]),
            ]
        );
    }

    /**
     * Returns the branches list, or false when it could not be loaded.
     * Branches are never served from stale data: if the API does not answer,
     * the caller must tell the customer to try again.
     */
    public function getBranches($query = [])
    {
        $defaultQuery = ["services" => 'pickup_availability'];
        $query = array_merge($defaultQuery, $query);
        
        $provinceCode = isset($query['provinceCode']) ? $query['provinceCode'] : 'ALL';
        $cacheKey = 'corrarfo_agencies_micorreo_' . $provinceCode;

        $cached = get_transient($cacheKey);
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        // A recent failure for this province: do not hit the API again until the cooldown expires.
        $cooldownKey = 'corrarfo_agencies_cd_micorreo_' . md5($this->url . '|' . $provinceCode);
        if (false !== get_transient($cooldownKey)) {
            return false;
        }

        // Make sure we hold a token before calling the API (renewal is itself guarded by a cooldown).
        if (empty($this->accessToken)) {
            $this->renewToken();
        }
        if (empty($this->accessToken)) {
            return false;
        }

        $request = $this->requestAgencies($query);

        // Token rejected by the API: renew once and retry once.
        if (!is_wp_error($request) && 401 === (int) wp_remote_retrieve_response_code($request)) {
            $this->accessToken = null;
            if ($this->renewToken()) {
                $request = $this->requestAgencies($query);
            }
        }

        $response = null;
        if (!is_wp_error($request) && 200 === (int) wp_remote_retrieve_response_code($request)) {
            $response = json_decode(wp_remote_retrieve_body($request), true);
        }

        if (!is_array($response)) {
            set_transient($cooldownKey, 1, CORRARFO_AGENCIES_COOLDOWN_SECONDS);
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'MiCorreo: Branches request failed for province ' . $provinceCode . ': ' .
                    (is_wp_error($request)
                        ? $request->get_error_message()
                        : 'HTTP ' . wp_remote_retrieve_response_code($request)),
                    ['source' => 'correoargentino']
                );
            }
            return false;
        }

        set_transient($cacheKey, $response, 12 * HOUR_IN_SECONDS);

        return $response;
    }

    public function createAccount($body)
    {
        $headers = $this->setHeaders([
            "Authorization" => "Bearer " . $this->accessToken,
            "Content-Type" => "application/json"
        ]);

        $request = wp_remote_post(
            $this->url . '/register?t=' . time(),
            [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($body),
            ]
        );
        $response = json_decode(wp_remote_retrieve_body($request), true);
        $statusCode = wp_remote_retrieve_response_code($request);

        return [
            'status' => $statusCode,
            'message' => $response["message"]
        ];

    }

    public function userValidate($email, $password)
    {
        // If we don't have an access token (e.g. just switched to sandbox), try to get one.
        if (empty($this->accessToken)) {
            $this->renewToken();
        }

        $headers = $this->setHeaders([
            "Authorization" => "Bearer " . $this->accessToken
        ]);

        $request = wp_remote_post(
            $this->url . '/users/validate',
            [
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode(["email" => $email, "password" => $password])
            ]
        );

        $response = json_decode(wp_remote_retrieve_body($request), true);
        $response["status"] = wp_remote_retrieve_response_code($request);
        $response["message"] = wp_remote_retrieve_response_message($request);

        if (isset($response['customerId'])) {
            $credentials = Utils::getCredentials();
            $credentials['idCliente'] = $response['customerId'];
            update_option(CORRARFO_CREDENTIALS, maybe_serialize($credentials));
        }

        return $response;
    }
}

// Backward compatibility alias
if (!class_exists('Corrarfo_MiCorreoService')) {
    class_alias(MiCorreoService::class, 'Corrarfo_MiCorreoService');
}
