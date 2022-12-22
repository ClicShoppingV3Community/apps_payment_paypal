<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT

   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC;

  use ClicShopping\OM\CLICSHOPPING;

  class EC extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigAbstract
  {
    protected $pm_code = 'paypal_express';
    protected $pm_pf_code = 'paypal_pro_payflow_ec';

    public bool $is_uninstallable = true;
    public $is_migratable = true;
    public ?int $sort_order = 100;

    protected function init()
    {
      $this->title = $this->app->getDef('module_ec_title');
      $this->short_title = $this->app->getDef('module_ec_short_title');
      $this->introduction = $this->app->getDef('module_ec_introduction');

      $this->is_installed = \defined('CLICSHOPPING_APP_PAYPAL_EC_STATUS') && (trim(CLICSHOPPING_APP_PAYPAL_EC_STATUS) != '');

      if (!function_exists('curl_init')) {
        $this->req_notes[] = $this->app->getDef('module_ec_error_curl');
      }

      if (\defined('CLICSHOPPING_APP_PAYPAL_GATEWAY')) {
        if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && !$this->app->hasCredentials('EC')) { // PayPal
          $this->req_notes[] = $this->app->getDef('module_ec_error_credentials');
        } elseif ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '0') && !$this->app->hasCredentials('EC', 'payflow')) { // Payflow
          $this->req_notes[] = $this->app->getDef('module_ec_error_credentials_payflow');
        }
      }
    }

    public function install()
    {
      parent::install();

      if (\defined('MODULE_PAYMENT_INSTALLED')) {
        $installed = explode(';', MODULE_PAYMENT_INSTALLED);
      }

      $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

      $this->app->saveCfgParam('MODULE_PAYMENT_INSTALLED', implode(';', $installed));
    }

    public function uninstall()
    {
      parent::uninstall();

      $installed = explode(';', MODULE_PAYMENT_INSTALLED);
      $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

      if ($installed_pos !== false) {
        unset($installed[$installed_pos]);

        $this->app->saveCfgParam('MODULE_PAYMENT_INSTALLED', implode(';', $installed));
      }
    }

    public function canMigrate()
    {
      return $this->doMigrationCheck($this->pm_code) || $this->doMigrationCheck($this->pm_pf_code);
    }

    protected function doMigrationCheck($class)
    {
      if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'includes/modules/payment/' . $class . '.php')) {
        if (!class_exists($class)) {
          include_once(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'includes/modules/payment/' . $class . '.php');
        }

        $module = new $class();

        if (isset($module->signature)) {
          $sig = explode('|', $module->signature);

          if (isset($sig[0]) && ($sig[0] == 'paypal') && isset($sig[1]) && ($sig[1] == $class) && isset($sig[2])) {
            return version_compare($sig[2], 4) >= 0;
          }
        }
      }

      return false;
    }

    public function migrate()
    {
      $is_payflow = false;

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER')) {
        $server = (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') ? 'LIVE' : 'SANDBOX';

        if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_SELLER_ACCOUNT')) {
          if (!\is_null(MODULE_PAYMENT_PAYPAL_EXPRESS_SELLER_ACCOUNT)) {
            if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $server . '_SELLER_EMAIL') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_' . $server . '_SELLER_EMAIL'))) {
              $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_' . $server . '_SELLER_EMAIL', MODULE_PAYMENT_PAYPAL_EXPRESS_SELLER_ACCOUNT);
            }
          }

          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_SELLER_ACCOUNT');
        }

        if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME') && \defined('MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD') && \defined('MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE')) {
          if (!\is_null(MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME) && !\is_null(MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD) && !\is_null(MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE)) {
            if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_USERNAME') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_USERNAME'))) {
              if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_PASSWORD') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_PASSWORD'))) {
                if (!\defined('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_SIGNATURE') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_SIGNATURE'))) {
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_USERNAME', MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME);
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_PASSWORD', MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD);
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_' . $server . '_API_SIGNATURE', MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE);
                }
              }
            }
          }

          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME');
          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD');
          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE');
        }
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER')) {
        $is_payflow = true;

        $server = (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER == 'Live') ? 'LIVE' : 'SANDBOX';

        if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VENDOR') && \defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_USERNAME') && \defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PASSWORD') && \defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PARTNER')) {
          if (!\is_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VENDOR) && !\is_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PASSWORD) && !\is_null(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PARTNER)) {
            if (!\defined('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_VENDOR') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_VENDOR'))) {
              if (!\defined('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PASSWORD') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PASSWORD'))) {
                if (!\defined('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PARTNER') || !!\is_null(\constant('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PARTNER'))) {
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_VENDOR', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VENDOR);
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_USER', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_USERNAME);
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PASSWORD', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PASSWORD);
                  $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_PF_' . $server . '_PARTNER', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PARTNER);
                }
              }
            }
          }

          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VENDOR');
          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_USERNAME');
          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PASSWORD');
          $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PARTNER');
        }
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_ACCOUNT_OPTIONAL')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_ACCOUNT_OPTIONAL', (MODULE_PAYMENT_PAYPAL_EXPRESS_ACCOUNT_OPTIONAL == 'True') ? '1' : '0');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_ACCOUNT_OPTIONAL');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_INSTANT_UPDATE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_INSTANT_UPDATE', (MODULE_PAYMENT_PAYPAL_EXPRESS_INSTANT_UPDATE == 'True') ? '1' : '0');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_INSTANT_UPDATE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_CHECKOUT_IMAGE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_IMAGE', (MODULE_PAYMENT_PAYPAL_EXPRESS_CHECKOUT_IMAGE == 'Static') ? '0' : '1');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_CHECKOUT_IMAGE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_PAGE_STYLE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_PAGE_STYLE', MODULE_PAYMENT_PAYPAL_EXPRESS_PAGE_STYLE);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_PAGE_STYLE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PAGE_STYLE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_PAGE_STYLE', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PAGE_STYLE);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PAGE_STYLE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_TRANSACTION_METHOD', (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD == 'Sale') ? '1' : '0');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_METHOD')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_TRANSACTION_METHOD', (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_METHOD == 'Sale') ? '1' : '0');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_METHOD');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_ORDER_STATUS_ID', MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_ORDER_STATUS_ID');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ORDER_STATUS_ID')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_ORDER_STATUS_ID', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ORDER_STATUS_ID);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ORDER_STATUS_ID');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_ZONE', MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_ZONE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ZONE')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_ZONE', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ZONE);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_ZONE');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_SORT_ORDER', MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER, 'Sort Order', 'Sort order of display (lowest to highest).');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_SORT_ORDER');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_SORT_ORDER')) {
        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_SORT_ORDER', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_SORT_ORDER, 'Sort Order', 'Sort order of display (lowest to highest).');
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_SORT_ORDER');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTIONS_ORDER_STATUS_ID')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTIONS_ORDER_STATUS_ID');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTIONS_ORDER_STATUS_ID')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTIONS_ORDER_STATUS_ID');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS')) {
        $status = '-1';

        if ((MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS == 'True') && \defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER')) {
          if (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') {
            $status = '1';
          } else {
            $status = '0';
          }
        }

        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_STATUS', $status);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_STATUS')) {
        $status = '-1';

        if ((MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_STATUS == 'True') && \defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER')) {
          if (MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER == 'Live') {
            $status = '1';
          } else {
            $status = '0';
          }
        }

        $this->app->saveCfgParam('CLICSHOPPING_APP_PAYPAL_EC_STATUS', $status);
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_STATUS');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_TRANSACTION_SERVER');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_VERIFY_SSL')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_VERIFY_SSL');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VERIFY_SSL')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_VERIFY_SSL');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_PROXY')) {
        if (!empty(MODULE_PAYMENT_PAYPAL_EXPRESS_PROXY) && empty(CLICSHOPPING_HTTP_PROXY)) {
          $this->app->saveCfgParam('CLICSHOPPING_HTTP_PROXY', MODULE_PAYMENT_PAYPAL_EXPRESS_PROXY);
        }

        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_PROXY');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PROXY')) {
        if (!empty(MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PROXY) && empty(CLICSHOPPING_HTTP_PROXY)) {
          $this->app->saveCfgParam('CLICSHOPPING_HTTP_PROXY', MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PROXY);
        }

        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_PROXY');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_DEBUG_EMAIL')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_DEBUG_EMAIL');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_DEBUG_EMAIL')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_PRO_PAYFLOW_EC_DEBUG_EMAIL');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_CHECKOUT_FLOW')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_CHECKOUT_FLOW');
      }

      if (\defined('MODULE_PAYMENT_PAYPAL_EXPRESS_DISABLE_IE_COMPAT')) {
        $this->app->deleteCfgParam('MODULE_PAYMENT_PAYPAL_EXPRESS_DISABLE_IE_COMPAT');
      }

      if ($is_payflow === true) {
        $installed = explode(';', MODULE_PAYMENT_INSTALLED);
        $installed_pos = array_search($this->pm_pf_code . '.php', $installed);

        if ($installed_pos !== false) {
          unset($installed[$installed_pos]);

          $this->app->saveCfgParam('MODULE_PAYMENT_INSTALLED', implode(';', $installed));
        }
      }
    }
  }
