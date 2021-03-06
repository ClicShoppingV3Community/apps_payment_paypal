<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\Module\Payment;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\HTTP;

  use ClicShopping\Apps\Payment\PayPal\PayPal as PayPalApp;

  use ClicShopping\Sites\Common\B2BCommon;

  class EC implements \ClicShopping\OM\Modules\PaymentInterface
  {

    public string $code;
    public $title;
    public $description;
    public $enabled;
    public $app;

    public function __construct()
    {
      $CLICSHOPPING_Customer = Registry::get('Customer');

      if (Registry::exists('Order')) {
        $CLICSHOPPING_Order = Registry::get('Order');
      }

      if (!Registry::exists('PayPal')) {
        Registry::set('PayPal', new PayPalApp());
      }

      $this->app = Registry::get('PayPal');
      $this->app->loadDefinitions('modules/EC/EC');

      $this->signature = 'paypal|paypal_express|' . $this->app->getVersion() . '|2.4';
      $this->api_version = $this->app->getApiVersion();

      $this->code = 'EC';
      $this->title = $this->app->getDef('module_ec_title');
      $this->public_title = $this->app->getDef('module_ec_public_title');
      $this->description = '<div class="text-md-center">' . HTML::button($this->app->getDef('module_ec_legacy_admin_app_button'), null, $this->app->link('Configure&module=EC'), 'primary') . '</div>';

// Activation module du paiement selon les groupes B2B

      if (\defined('CLICSHOPPING_APP_PAYPAL_EC_STATUS')) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if (B2BCommon::getPaymentUnallowed($this->code)) {
            if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '2' || CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        } else {
          if (\defined('CLICSHOPPING_APP_PAYPAL_EC_NO_AUTHORIZE') && CLICSHOPPING_APP_PAYPAL_EC_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {

              if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '2' || CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') {
                $this->enabled = true;
              } else {
                $this->enabled = false;
              }
            }
          }
        }
      }

      $this->sort_order = \defined('CLICSHOPPING_APP_PAYPAL_EC_SORT_ORDER') ? CLICSHOPPING_APP_PAYPAL_EC_SORT_ORDER : 0;

      $this->order_status = \defined('CLICSHOPPING_APP_PAYPAL_EC_ORDER_STATUS_ID') && ((int)CLICSHOPPING_APP_PAYPAL_EC_ORDER_STATUS_ID > 0) ? (int)CLICSHOPPING_APP_PAYPAL_EC_ORDER_STATUS_ID : 0;

      if (\defined('CLICSHOPPING_APP_PAYPAL_EC_STATUS')) {
        if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '2') {
          $this->title .= ' [Sandbox]';
          $this->public_title .= ' (' . $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code . '; Sandbox)';
        }
      }

      if (!function_exists('curl_init')) {
        $this->description .= '<div class="alert alert-warning" role="alert">' . $this->app->getDef('module_ec_error_curl') . '</div>';

        $this->enabled = false;
      }

      if ($this->enabled === true) {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          if (!$this->app->hasCredentials('EC')) {
            $this->description .= '<div class="alert alert-warning" role="alert">' . $this->app->getDef('module_ec_error_credentials') . '</div>';

            $this->enabled = false;
          }
        } else { // Payflow
          if (!$this->app->hasCredentials('EC', 'payflow')) {
            $this->description .= '<div class="alert alert-warning" role="alert">' . $this->app->getDef('module_ec_error_credentials_payflow') . '</div>';

            $this->enabled = false;
          }
        }
      }

      if ($this->enabled === true) {
        if (isset($CLICSHOPPING_Order) && is_object($CLICSHOPPING_Order)) {
          $this->update_status();
        }
      }

      if (isset($_GET['Cart'])) {
        if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '1')) {
          header('X-UA-Compatible: IE=edge', true);
        }
      }

// When changing the shipping address due to no shipping rates being available, head straight to the checkout confirmation page
      if ((isset($_GET['Checkout']) && isset($_GET['Billing'])) && isset($_SESSION['appPayPalEcRightTurn'])) {
        unset($_SESSION['appPayPalEcRightTurn']);

        if (isset($_SESSION['payment']) && ($_SESSION['payment'] == $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code)) {
// Sales condition, pass the verification
// bug the data is not sent
          if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
            $_POST['conditions'] = 1;
          }

          CLICSHOPPING::redirect(null, 'Checkout&Confirmation');
        }
      }
    }

    public function update_status()
    {
      $CLICSHOPPING_Order = Registry::get('Order');

      if (($this->enabled === true) && ((int)CLICSHOPPING_APP_PAYPAL_EC_ZONE > 0)) {
        $check_flag = false;

        $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => CLICSHOPPING_APP_PAYPAL_EC_ZONE,
          'zone_country_id' => $CLICSHOPPING_Order->billing['country']['id']
        ],
          'zone_id'
        );

        while ($Qcheck->fetch()) {
          if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') === $CLICSHOPPING_Order->delivery['zone_id'])) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag === false) {
          $this->enabled = false;
        }
      }
    }

    public function checkout_initialization_method()
    {
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');

      $string = '';

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') {
        if (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '0') {
          if (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_IMAGE == '1') {
            if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') {
              $image_button = 'https://fpdbs.paypal.com/dynamicimageweb?cmd=_dynamic-image';
            } else {
              $image_button = 'https://fpdbs.sandbox.paypal.com/dynamicimageweb?cmd=_dynamic-image';
            }

            $params = ['locale=' . $this->app->getDef('module_ec_button_locale')];

            if ($this->app->hasCredentials('EC')) {
              $response_array = $this->app->getApiResult('EC', 'GetPalDetails');

              if (isset($response_array['PAL'])) {
                $params[] = 'pal=' . $response_array['PAL'];
                $params[] = 'ordertotal=' . $this->app->formatCurrencyRaw($CLICSHOPPING_ShoppingCart->show_total());
              }
            }

            if (!empty($params)) {
              $image_button .= '&' . implode('&', $params);
            }
          } else {
            $image_button = $this->app->getDef('module_ec_button_url');
          }

          $button_title = HTML::outputProtected($this->app->getDef('module_ec_button_title'));

          if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '2') {
            $button_title .= ' (' . $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code . '; Sandbox)';
          }

          $string .= HTML::link(CLICSHOPPING::link(null, 'order&callback&paypal&ec', false, false), '<img src="' . $image_button . '" border="0" alt="" title="' . $button_title . '" />');
        } else {
          $CLICSHOPPING_Template->addBlock('<script src="https://www.paypalobjects.com/api/checkout.js" async></script>', 'footer_scripts');

          $merchant_id = (CLICSHOPPING_APP_PAYPAL_EC_STATUS === '1') ? CLICSHOPPING_APP_PAYPAL_LIVE_MERCHANT_ID : CLICSHOPPING_APP_PAYPAL_SANDBOX_MERCHANT_ID;
          if (empty($merchant_id)) $merchant_id = ' ';

          $server = (CLICSHOPPING_APP_PAYPAL_EC_STATUS === '1') ? 'production' : 'sandbox';

          $ppecset_url = CLICSHOPPING::link(null, 'order&callback&paypal&ec&format=json', false, false);

          switch (CLICSHOPPING_APP_PAYPAL_EC_INCONTEXT_BUTTON_COLOR) {
            case '3':
              $button_color = 'silver';
              break;

            case '2':
              $button_color = 'blue';
              break;

            case '1':
            default:
              $button_color = 'gold';
              break;
          }

          switch (CLICSHOPPING_APP_PAYPAL_EC_INCONTEXT_BUTTON_SIZE) {
            case '3':
              $button_size = 'medium';
              break;

            case '1':
              $button_size = 'tiny';
              break;

            case '2':
            default:
              $button_size = 'small';
              break;
          }

          switch (CLICSHOPPING_APP_PAYPAL_EC_INCONTEXT_BUTTON_SHAPE) {
            case '2':
              $button_shape = 'rect';
              break;

            case '1':
            default:
              $button_shape = 'pill';
              break;
          }

          $string .= <<<EOD
<span id="ppECButton"></span>
<script>
window.paypalCheckoutReady = function () {
  paypal.checkout.setup('${merchant_id}', {
    environment: '{$server}',
    buttons: [
      {
        container: 'ppECButton',
        color: '${button_color}',
        size: '${button_size}',
        shape: '${button_shape}',
        click: function (event) {
          event.preventDefault();

          paypal.checkout.initXO();

          var action = $.getJSON('${ppecset_url}');

          action.done(function (data) {
            paypal.checkout.startFlow(data.token);
          });

          action.fail(function () {
            paypal.checkout.closeFlow();
          });
        }
      }
    ]
  });
};
</script>
EOD;
        }
      } else {
        $image_button = $this->app->getDef('module_ec_button_url');

        $button_title = HTML::outputProtected($this->app->getDef('module_ec_button_title'));

        if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '2') {
          $button_title .= ' (' . $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code . '; Sandbox)';
        }

        $string .= HTML::link(CLICSHOPPING::link(null, 'order&callback&paypal&ec', false, false), '<img src="' . $image_button . '" border="0" alt="" title="' . $button_title . '" />');
      }

      return $string;
    }

    public function javascript_validation()
    {
      return false;
    }

    public function selection()
    {
      if (CLICSHOPPING_APP_PAYPAL_EC_LOGO == 'True') {
        $this->public_title = $this->public_title . '&nbsp;&nbsp;&nbsp;<img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" border="0" alt="PayPal Logo" style="padding: 3px;" />';
      }

      return array('id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, 'module' => $this->public_title);
    }

    public function pre_confirmation_check()
    {
      $CLICSHOPPING_Order = Registry::get('Order');


      if (!isset($_SESSION['appPayPalEcResult'])) {
        CLICSHOPPING::redirect(null, 'order&callback&paypal&ec');
      }

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
        if (!\in_array($_SESSION['appPayPalEcResult']['ACK'], array('Success', 'SuccessWithWarning'))) {
          CLICSHOPPING::redirect(null, 'Cart&error_message=' . stripslashes($_SESSION['appPayPalEcResult']['L_LONGMESSAGE0']));
        } elseif (!isset($_SESSION['appPayPalEcSecret']) || ($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_CUSTOM'] != $_SESSION['appPayPalEcSecret'])) {
          CLICSHOPPING::redirect(null, 'Cart');
        }
      } else { // Payflow
        if ($_SESSION['appPayPalEcResult']['RESULT'] != '0') {
          CLICSHOPPING::redirect(null, 'Cart&error_message=' . urlencode($_SESSION['appPayPalEcResult']['CLICSHOPPING_ERROR_MESSAGE']));
        } elseif (!isset($_SESSION['appPayPalEcSecret']) || ($_SESSION['appPayPalEcResult']['CUSTOM'] != $_SESSION['appPayPalEcSecret'])) {
          CLICSHOPPING::redirect(null, 'Cart');
        }
      }

      $CLICSHOPPING_Order->info['payment_method'] = '<img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" border="0" alt="PayPal Logo" style="padding: 3px;" />';
    }

    public function confirmation()
    {
      if (!isset($_SESSION['comments'])) {
        $_SESSION['comments'] = null;
      }

      $confirmation = false;

      if (empty($_SESSION['comments'])) {
        $confirmation = array('fields' => array(array('title' => $this->app->getDef('module_ec_field_comments'),
          'field' => HTML::textareaField('ppecomments', '', '60', '5', 'class="form-control"'))));
      }

      return $confirmation;
    }

    public function process_button()
    {
      return false;
    }

    public function before_process()
    {
      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') {
        $this->before_process_paypal();
      } else {
        $this->before_process_payflow();
      }
    }

    public function before_process_paypal()
    {
      global $response_array;

      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_Address = Registry::get('Address');

      if (!isset($_SESSION['appPayPalEcResult'])) {
        CLICSHOPPING::redirect(null, 'order&callback&paypal&ec');
      }

      if (\in_array($_SESSION['appPayPalEcResult']['ACK'], array('Success', 'SuccessWithWarning'))) {
        if (!isset($_SESSION['appPayPalEcSecret']) || ($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_CUSTOM'] != $_SESSION['appPayPalEcSecret'])) {
          CLICSHOPPING::redirect(null, 'Cart');
        }
      } else {
        CLICSHOPPING::redirect(null, 'Cart&error_message=' . stripslashes($_SESSION['appPayPalEcResult']['L_LONGMESSAGE0']));
      }

      if (empty($_SESSION['comments'])) {
        if (isset($_POST['ppecomments']) && !\is_null($_POST['ppecomments'])) {
          $_SESSION['comments'] = HTML::sanitize($_POST['ppecomments']);

          $CLICSHOPPING_Order->info['comments'] = $_SESSION['comments'];
        }
      }

      $params = array('TOKEN' => $_SESSION['appPayPalEcResult']['TOKEN'],
        'PAYERID' => $_SESSION['appPayPalEcResult']['PAYERID'],
        'PAYMENTREQUEST_0_AMT' => $this->app->formatCurrencyRaw($CLICSHOPPING_Order->info['total']),
        'PAYMENTREQUEST_0_CURRENCYCODE' => $CLICSHOPPING_Order->info['currency']);

      if (is_numeric($_SESSION['sendto']) && ($_SESSION['sendto'] > 0)) {
        $params['PAYMENTREQUEST_0_SHIPTONAME'] = $CLICSHOPPING_Order->delivery['firstname'] . ' ' . $CLICSHOPPING_Order->delivery['lastname'];
        $params['PAYMENTREQUEST_0_SHIPTOSTREET'] = $CLICSHOPPING_Order->delivery['street_address'];
        $params['PAYMENTREQUEST_0_SHIPTOSTREET2'] = $CLICSHOPPING_Order->delivery['suburb'];
        $params['PAYMENTREQUEST_0_SHIPTOCITY'] = $CLICSHOPPING_Order->delivery['city'];
        $params['PAYMENTREQUEST_0_SHIPTOSTATE'] = $CLICSHOPPING_Address->getZoneCode($CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'], $CLICSHOPPING_Order->delivery['state']);
        $params['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $CLICSHOPPING_Order->delivery['country']['iso_code_2'];
        $params['PAYMENTREQUEST_0_SHIPTOZIP'] = $CLICSHOPPING_Order->delivery['postcode'];
      }

      $response_array = $this->app->getApiResult('EC', 'DoExpressCheckoutPayment', $params);

      if (!\in_array($response_array['ACK'], array('Success', 'SuccessWithWarning'))) {
        if ($response_array['L_ERRORCODE0'] == '10486') {
          if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') {
            $paypal_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
          } else {
            $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout';
          }

          $paypal_url .= '&token=' . $_SESSION['appPayPalEcResult']['TOKEN'];

          HTTP::redirect($paypal_url);
        }

        CLICSHOPPING::redirect(null, 'Cart&error_message=' . stripslashes($response_array['L_LONGMESSAGE0']));
      }
    }

    public function before_process_payflow()
    {
      global $response_array;

      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_Address = Registry::get('Address');

      if (!isset($_SESSION['appPayPalEcResult'])) {
        CLICSHOPPING::redirect(null, 'order&callback&paypal&ec');
      }

      if ($_SESSION['appPayPalEcResult']['RESULT'] == '0') {
        if (!isset($_SESSION['appPayPalEcSecret']) || ($_SESSION['appPayPalEcResult']['CUSTOM'] != $_SESSION['appPayPalEcSecret'])) {
          CLICSHOPPING::redirect(null, 'Cart');
        }
      } else {
        CLICSHOPPING::redirect(null, 'Cart&error_message=' . urlencode($_SESSION['appPayPalEcResult']['CLICSHOPPING_ERROR_MESSAGE']));
      }

      if (empty($_SESSION['comments'])) {
        if (isset($_POST['ppecomments']) && !\is_null($_POST['ppecomments'])) {
          $_SESSION['comments'] = HTML::sanitize($_POST['ppecomments']);

          $CLICSHOPPING_Order->info['comments'] = $_SESSION['comments'];
        }
      }

      $params = array('EMAIL' => $CLICSHOPPING_Order->customer['email_address'],
        'TOKEN' => $_SESSION['appPayPalEcResult']['TOKEN'],
        'PAYERID' => $_SESSION['appPayPalEcResult']['PAYERID'],
        'AMT' => $this->app->formatCurrencyRaw($CLICSHOPPING_Order->info['total']),
        'CURRENCY' => $CLICSHOPPING_Order->info['currency']);

      if (is_numeric($_SESSION['sendto']) && ($_SESSION['sendto'] > 0)) {
        $params['SHIPTONAME'] = $CLICSHOPPING_Order->delivery['firstname'] . ' ' . $CLICSHOPPING_Order->delivery['lastname'];
        $params['SHIPTOSTREET'] = $CLICSHOPPING_Order->delivery['street_address'];
        $params['SHIPTOSTREET2'] = $CLICSHOPPING_Order->delivery['suburb'];
        $params['SHIPTOCITY'] = $CLICSHOPPING_Order->delivery['city'];
        $params['SHIPTOSTATE'] = $CLICSHOPPING_Address->getZoneCode($CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'], $CLICSHOPPING_Order->delivery['state']);
        $params['SHIPTOCOUNTRY'] = $CLICSHOPPING_Order->delivery['country']['iso_code_2'];
        $params['SHIPTOZIP'] = $CLICSHOPPING_Order->delivery['postcode'];
      }

      $response_array = $this->app->getApiResult('EC', 'PayflowDoExpressCheckoutPayment', $params);

      if ($response_array['RESULT'] != '0') {
        CLICSHOPPING::redirect(null, 'Cart&error_message=' . urlencode($response_array['CLICSHOPPING_ERROR_MESSAGE']));
      }
    }

    public function after_process()
    {
      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') {
        $this->after_process_paypal();
      } else {
        $this->after_process_payflow();
      }
    }

    public function after_process_paypal()
    {
      global $response_array;

      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_Hooks = Registry::get('Hooks');
      $CLICSHOPPING_Template = Registry::get('Template');

      $this->lastInsertOrderId = $CLICSHOPPING_Order->getLastOrderId();

      $pp_result = 'Transaction ID: ' . HTML::outputProtected($response_array['PAYMENTINFO_0_TRANSACTIONID']) . "\n" .
        'Payer Status: ' . HTML::outputProtected($_SESSION['appPayPalEcResult']['PAYERSTATUS']) . "\n" .
        'Address Status: ' . HTML::outputProtected($_SESSION['appPayPalEcResult']['ADDRESSSTATUS']) . "\n" .
        'Payment Status: ' . HTML::outputProtected($response_array['PAYMENTINFO_0_PAYMENTSTATUS']) . "\n" .
        'Payment Type: ' . HTML::outputProtected($response_array['PAYMENTINFO_0_PAYMENTTYPE']) . "\n" .
        'Pending Reason: ' . HTML::outputProtected($response_array['PAYMENTINFO_0_PENDINGREASON']);

      $sql_data_array = ['orders_id' => $this->lastInsertOrderId,
        'orders_status_id' => (int)CLICSHOPPING_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
        'orders_status_invoice_id' => (int)$CLICSHOPPING_Order->info['order_status_invoice'],
        'admin_user_name' => '',
        'date_added' => 'now()',
        'customer_notified' => '0',
        'comments' => $pp_result
      ];

      $this->app->db->save('orders_status_history', $sql_data_array);

      $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'includes/Module/Hooks/Shop/CheckoutProcess/';

      if (is_dir($source_folder)) {
        $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutProcess*');

        if (\is_array($files_get)) {
          foreach ($files_get as $value) {
            if (!empty($value['name'])) {
              $CLICSHOPPING_Hooks->call('CheckoutProcess', $value['name']);
            }
          }
        }
      }

      unset($_SESSION['appPayPalEcResult']);
      unset($_SESSION['appPayPalEcSecret']);
    }

    public function after_process_payflow()
    {
      global $response_array;

      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_Hooks = Registry::get('Hooks');
      $CLICSHOPPING_Template = Registry::get('Template');

      $pp_result = 'Transaction ID: ' . HTML::outputProtected($response_array['PNREF']) . "\n" .
        'Gateway: Payflow' . "\n" .
        'PayPal ID: ' . HTML::outputProtected($response_array['PPREF']) . "\n" .
        'Payer Status: ' . HTML::outputProtected($_SESSION['appPayPalEcResult']['PAYERSTATUS']) . "\n" .
        'Address Status: ' . HTML::outputProtected($_SESSION['appPayPalEcResult']['ADDRESSSTATUS']) . "\n" .
        'Payment Status: ' . HTML::outputProtected($response_array['PENDINGREASON']) . "\n" .
        'Payment Type: ' . HTML::outputProtected($response_array['PAYMENTTYPE']) . "\n" .
        'Response: ' . HTML::outputProtected($response_array['RESPMSG']) . "\n";

      $sql_data_array = ['orders_id' => $CLICSHOPPING_Order->Insert(),
        'orders_status_id' => (int)CLICSHOPPING_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
        'orders_status_invoice_id' => (int)$CLICSHOPPING_Order->info['order_status_invoice'],
        'admin_user_name' => '',
        'date_added' => 'now()',
        'customer_notified' => '0',
        'comments' => $pp_result
      ];

      $this->app->db->save('orders_status_history', $sql_data_array);

      unset($_SESSION['appPayPalEcResult']);
      unset($_SESSION['appPayPalEcSecret']);

// Manually call PayflowInquiry to retrieve more details about the transaction and to allow admin post-transaction actions
      $response = $this->app->getApiResult('APP', 'PayflowInquiry', array('ORIGID' => $response_array['PNREF']));

      if (isset($response['RESULT']) && ($response['RESULT'] == '0')) {
        $result = 'Transaction ID: ' . HTML::outputProtected($response['ORIGPNREF']) . "\n" .
          'Gateway: Payflow' . "\n";

        $pending_reason = $response['TRANSSTATE'];
        $payment_status = null;

        switch ($response['TRANSSTATE']) {
          case '3':
            $pending_reason = 'authorization';
            $payment_status = 'Pending';
            break;

          case '4':
            $pending_reason = 'other';
            $payment_status = 'In-Progress';
            break;

          case '6':
            $pending_reason = 'scheduled';
            $payment_status = 'Pending';
            break;

          case '8':
          case '9':
            $pending_reason = 'None';
            $payment_status = 'Completed';
            break;
        }

        if (isset($payment_status)) {
          $result .= 'Payment Status: ' . HTML::outputProtected($payment_status) . "\n";
        }

        $result .= 'Pending Reason: ' . HTML::outputProtected($pending_reason) . "\n";

        switch ($response['AVSADDR']) {
          case 'Y':
            $result .= 'AVS Address: Match' . "\n";
            break;

          case 'N':
            $result .= 'AVS Address: No Match' . "\n";
            break;
        }

        switch ($response['AVSZIP']) {
          case 'Y':
            $result .= 'AVS ZIP: Match' . "\n";
            break;

          case 'N':
            $result .= 'AVS ZIP: No Match' . "\n";
            break;
        }

        switch ($response['IAVS']) {
          case 'Y':
            $result .= 'IAVS: International' . "\n";
            break;

          case 'N':
            $result .= 'IAVS: USA' . "\n";
            break;
        }

        switch ($response['CVV2MATCH']) {
          case 'Y':
            $result .= 'CVV2: Match' . "\n";
            break;

          case 'N':
            $result .= 'CVV2: No Match' . "\n";
            break;
        }

        $sql_data_array = ['orders_id' => (int)$CLICSHOPPING_Order->Insert(),
          'orders_status_id' => (int)CLICSHOPPING_APP_PAYPAL_TRANSACTIONS_ORDER_STATUS_ID,
          'orders_status_invoice_id' => (int)$CLICSHOPPING_Order->info['order_status_invoice'],
          'admin_user_name' => '',
          'date_added' => 'now()',
          'customer_notified' => '0',
          'comments' => $result
        ];

        $this->app->db->save('orders_status_history', $sql_data_array);

        $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'includes/Module/Hooks/Shop/CheckoutProcess/';

        if (is_dir($source_folder)) {
          $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutProcess*');

          if (\is_array($files_get)) {
            foreach ($files_get as $value) {
              if (!empty($value['name'])) {
                $CLICSHOPPING_Hooks->call('CheckoutProcess', $value['name']);
              }
            }
          }
        }
      }
    }

    public function get_error()
    {
      return false;
    }

    public function check()
    {
      return \defined('CLICSHOPPING_APP_PAYPAL_EC_STATUS') && (trim(CLICSHOPPING_APP_PAYPAL_EC_STATUS) != '');
    }

    public function install()
    {
      $this->app->redirect('Configure&Install&module=EC');
    }

    public function remove()
    {
      $this->app->redirect('Configure&Uninstall&module=EC');
    }

    public function keys()
    {
      return array('CLICSHOPPING_APP_PAYPAL_EC_SORT_ORDER');
    }

    public function getProductType($id, $attributes)
    {
      foreach ($attributes as $a) {
        $Qcheck = $this->app->db->prepare('select pad.products_attributes_id
                                          from :table_products_attributes pa,
                                              :table_products_attributes_download pad
                                          where pa.products_id = :products_id
                                          and pa.options_values_id = :options_values_id
                                          and pa.products_attributes_id = pad.products_attributes_id
                                          limit 1
                                          ');

        $Qcheck->bindInt(':products_id', $id);
        $Qcheck->bindInt(':options_values_id', $a['value_id']);
        $Qcheck->execute();

        if ($Qcheck->fetch() !== false) {
          return 'Digital';
        }
      }

      return 'Physical';
    }
  }