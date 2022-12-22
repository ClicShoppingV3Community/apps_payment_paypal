<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT

   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal;

  use ClicShopping\OM\FileSystem;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;

  class PayPal extends \ClicShopping\OM\AppAbstract
  {
    protected $api_version = 204;
    protected string $identifier = 'ClicShopping_PPapp_v5';

    protected function init()
    {
    }

    public function log($module, $action, $result, $request, $response, $server, $is_ipn = false)
    {
      $do_log = false;

      if (\defined('CLICSHOPPING_APP_PAYPAL_LOG_TRANSACTIONS') && \in_array(CLICSHOPPING_APP_PAYPAL_LOG_TRANSACTIONS, ['1', '0'])) {
        $do_log = true;

        if ((CLICSHOPPING_APP_PAYPAL_LOG_TRANSACTIONS == '0') && ($result === 1)) {
          $do_log = false;
        }
      }

      if ($do_log !== true) {
        return false;
      }

      $filter = ['ACCT', 'CVV2', 'ISSUENUMBER'];

      $request_string = '';

      if (\is_array($request)) {
        foreach ($request as $key => $value) {
          if ((str_contains($key, '_nh-dns')) || \in_array($key, $filter)) {
            $value = '**********';
          }

          $request_string .= $key . ': ' . $value . "\n";
        }
      } else {
        $request_string = $request;
      }

      $response_string = '';

      if (\is_array($response)) {
        foreach ($response as $key => $value) {
          if (\is_array($value)) {
            $value = http_build_query($value);
          } elseif ((str_contains($key, '_nh-dns')) || \in_array($key, $filter)) {
            $value = '**********';
          }

          $response_string .= $key . ': ' . $value . "\n";
        }
      } else {
        $response_string = $response;
      }

      $this->db->save('clicshopping_app_paypal_log', [
          'customers_id' => isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : 0,
          'module' => $module,
          'action' => $action . (($is_ipn === true) ? ' [IPN]' : ''),
          'result' => $result,
          'server' => ($server == 'live') ? 1 : -1,
          'request' => trim($request_string),
          'response' => trim($response_string),
          'ip_address' => HTTP::getIpAddress(true),
          'date_added' => 'now()'
        ]
      );
    }

    public function migrate()
    {
      $migrated = false;

      foreach ($this->getConfigModules() as $module) {
        if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $module . '_STATUS') && $this->getConfigModuleInfo($module, 'is_migratable')) {
          $this->saveCfgParam('CLICSHOPPING_APP_PAYPAL_' . $module . '_STATUS', '');

          $m = Registry::get('PayPalAdminConfig' . $module);

          if ($m->canMigrate()) {
            $m->migrate();

            if ($migrated === false) {
              $migrated = true;
            }
          }
        }
      }

      return $migrated;
    }

    /**
     * @return array|mixed
     */
    public function getConfigModules(): mixed
    {
      static $result;

      if (!isset($result)) {
        $result = [];

        $directory = CLICSHOPPING::BASE_DIR . 'Apps/Payment/PayPal/Module/ClicShoppingAdmin/Config';
        $name_space_config = 'ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config';
        $trigger_message = 'ClicShopping\Apps\Payment\PayPal\PayPal::getConfigModules(): ';

        $this->getConfigApps($result, $directory, $name_space_config, $trigger_message);
      }

      return $result;
    }

    public function getConfigModuleInfo($module, $info)
    {
      if (!Registry::exists('PayPalAdminConfig' . $module)) {
        $class = 'ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\\' . $module . '\\' . $module;

        Registry::set('PayPalAdminConfig' . $module, new $class);
      }

      return Registry::get('PayPalAdminConfig' . $module)->$info;
    }

    public function hasCredentials($module, $type = null)
    {

      if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $module . '_STATUS')) {
        return false;
      }

      $server = \constant('CLICSHOPPING_APP_PAYPAL_' . $module . '_STATUS');

      if (!\in_array($server, array('1', '2'))) {
        return false;
      }

      $server = ($server == '1') ? 'LIVE' : 'SANDBOX';

      if ($type == 'email') {
        $creds = array('CLICSHOPPING_APP_PAYPAL_' . $server . '_SELLER_EMAIL');
      } elseif (substr($type, 0, 7) == 'payflow') {
        if (\strlen($type) > 7) {
          $creds = ['CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_' . strtoupper(substr($type, 8))];
        } else {
          $creds = ['CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_VENDOR',
            'CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PASSWORD',
            'CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PARTNER'
          ];
        }
      } else {
        $creds = array('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_USERNAME',
          'CLICSHOPPING_APP_PAYPAL_' . $server . '_API_PASSWORD',
          'CLICSHOPPING_APP_PAYPAL_' . $server . '_API_SIGNATURE');
      }

      foreach ($creds as $c) {
        if (!\defined($c) || (\strlen(trim(\constant($c))) < 1)) {
          return false;
        }
      }

      return true;
    }

    public function getCredentials($module, $type)
    {

      if (\constant('CLICSHOPPING_APP_PAYPAL_' . $module . '_STATUS') == '1') {
        if ($type == 'email') {
          return \constant('CLICSHOPPING_APP_PAYPAL_LIVE_SELLER_EMAIL');
        } elseif ($type == 'email_primary') {
          return \constant('CLICSHOPPING_APP_PAYPAL_LIVE_SELLER_EMAIL_PRIMARY');
        } elseif (substr($type, 0, 7) == 'payflow') {
          return \constant('CLICSHOPPING_APP_PAYPAL_PF_LIVE_' . strtoupper(substr($type, 8)));
        } else {
          return \constant('CLICSHOPPING_APP_PAYPAL_LIVE_API_' . strtoupper($type));
        }
      }

      if ($type == 'email') {
        return \constant('CLICSHOPPING_APP_PAYPAL_SANDBOX_SELLER_EMAIL');
      } elseif ($type == 'email_primary') {
        return \constant('CLICSHOPPING_APP_PAYPAL_SANDBOX_SELLER_EMAIL_PRIMARY');
      } elseif (substr($type, 0, 7) == 'payflow') {
        return \constant('CLICSHOPPING_APP_PAYPAL_PF_SANDBOX_' . strtoupper(substr($type, 8)));
      } else {
        return \constant('CLICSHOPPING_APP_PAYPAL_SANDBOX_API_' . strtoupper($type));
      }
    }

    public function hasApiCredentials($server, $type = null)
    {

      $server = ($server == 'live') ? 'LIVE' : 'SANDBOX';

      if ($type == 'email') {
        $creds = array('CLICSHOPPING_APP_PAYPAL_' . $server . '_SELLER_EMAIL');
      } elseif (substr($type, 0, 7) == 'payflow') {
        $creds = array('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_' . strtoupper(substr($type, 8)));
      } else {
        $creds = array('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_USERNAME',
          'CLICSHOPPING_APP_PAYPAL_' . $server . '_API_PASSWORD',
          'CLICSHOPPING_APP_PAYPAL_' . $server . '_API_SIGNATURE');
      }

      foreach ($creds as $c) {
        if (!\defined($c) || (\strlen(trim(\constant($c))) < 1)) {
          return false;
        }
      }

      return true;
    }

    public function getApiCredentials($server, $type)
    {
      if (($server == 'live') && \defined('CLICSHOPPING_APP_PAYPAL_LIVE_API_' . strtoupper($type))) {
        return \constant('CLICSHOPPING_APP_PAYPAL_LIVE_API_' . strtoupper($type));
      } elseif (\defined('CLICSHOPPING_APP_PAYPAL_SANDBOX_API_' . strtoupper($type))) {
        return \constant('CLICSHOPPING_APP_PAYPAL_SANDBOX_API_' . strtoupper($type));
      }
    }

// APP calls require $server to be "live" or "sandbox"
    public function getApiResult($module, $call, array $extra_params = null, $server = null, $is_ipn = false)
    {
      $class = 'ClicShopping\Apps\Payment\PayPal\API\\' . $call;

      $API = new $class($server);

      $result = $API->execute($extra_params);

      $this->log($module, $call, ($result['success'] === true) ? 1 : -1, $result['req'], $result['res'], $server, $is_ipn);

      return $result['res'];
    }

    public function makeApiCall($url, $parameters = null, array $headers = null)
    {
      $server = parse_url($url);

      $p = ['url' => $url,
        'parameters' => $parameters,
        'headers' => $headers
      ];

      if ((substr($server['host'], -10) == 'paypal.com')) {
        $p['cafile'] = CLICSHOPPING::BASE_DIR . 'Apps/Payment/PayPal/work/paypal.com.crt';
      }

      return HTTP::getResponse($p);
    }

    public function formatCurrencyRaw($total, $currency_code = null, $currency_value = null)
    {
      $CLICSHOPPING_Currencies = Registry::get('Currencies');

      if (empty($currency_code)) {
        $currency_code = isset($_SESSION['currency']) ? $_SESSION['currency'] : DEFAULT_CURRENCY;
      }

      if (!isset($currency_value) || !is_numeric($currency_value)) {
        $currency_value = $CLICSHOPPING_Currencies->currencies[$currency_code]['value'];
      }

      return number_format(round($total * $currency_value, 4), $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    public function getApiVersion()
    {
      return $this->api_version;
    }

     /**
     * @return string
     */
    public function getIdentifier() :String
    {
      return $this->identifier;
    }

    public function logUpdate($message, $version)
    {
      if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Apps/Payment/PayPal/work')) {
        file_put_contents(CLICSHOPPING::BASE_DIR . 'Apps/Payment/PayPal/work/update_log-' . $version . '.php', '[' . date('d-M-Y H:i:s') . '] ' . $message . "\n", FILE_APPEND);
      }
    }
  }