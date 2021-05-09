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

  namespace ClicShopping\Apps\Payment\PayPal\Sites\Shop\Pages\EC;

  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Is;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;

  use ClicShopping\Sites\Shop\Tax;
  use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;

  use ClicShopping\Apps\Payment\PayPal\Module\Payment\EC as PaymentModuleEC;
  use ClicShopping\Sites\Shop\Shipping;

  class EC extends \ClicShopping\OM\PagesAbstract
  {
    protected $file = null;
    protected $use_site_template = false;
    protected $pm;
    protected $lang;

    protected function init()
    {
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Address = Registry::get('Address');

      $this->lang = Registry::get('Language');

      $this->pm = new PaymentModuleEC();


      if (!$this->pm->check() || !$this->pm->enabled) {
        CLICSHOPPING::redirect(null, 'Cart');
      }

      $this->lang->loadDefinitions('Shop/create_account');

      if (isset($_SESSION['customer_id'])) {
        $CLICSHOPPING_Customer->setData($_SESSION['customer_id']);
      }

      /*
            if ($CLICSHOPPING_Customer->getID()) {
              $CLICSHOPPING_Customer->setData($CLICSHOPPING_Customer->getID());
            }
      */
      if (!isset($_SESSION['sendto'])) {
        if ($CLICSHOPPING_Customer->getID() || isset($_SESSION['customer_id'])) {
          $_SESSION['sendto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
        } else {

          $country = $CLICSHOPPING_Address->getCountries(STORE_COUNTRY, true);

          $_SESSION['sendto'] = [
            'firstname' => null,
            'lastname' => null,
            'company' => null,
            'street_address' => 'none',
            'suburb' => null,
            'postcode' => null,
            'city' => null,
            'zone_id' => STORE_ZONE,
            'zone_name' => $CLICSHOPPING_Address->getZoneName(STORE_COUNTRY, STORE_ZONE, ''),
            'country_id' => STORE_COUNTRY,
            'country_name' => $country['countries_name'],
            'country_iso_code_2' => $country['countries_iso_code_2'],
            'country_iso_code_3' => $country['countries_iso_code_3'],
            'address_format_id' => $CLICSHOPPING_Address->getAddressFormatId(STORE_COUNTRY),
          ];
        }
      }

      if (!isset($_SESSION['billto'])) {
        $_SESSION['billto'] = $_SESSION['sendto'];
      }

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
      $_SESSION['cartID'] = $CLICSHOPPING_ShoppingCart->cartID;

      if (!isset($_GET['action'])) {
        $_GET['action'] = null;
      }

      switch ($_GET['action']) {
        case 'cancel':
          $this->doCancel();
          break;

        case 'callbackSet':
          $this->doCallbackSet();
          break;

        case 'retrieve':
          $this->doRetrieve();
          break;

        default:
          $this->doSet();
          break;
      }

      CLICSHOPPING::redirect(null, 'Cart');
    }

    protected function doCancel()
    {
      if (isset($_SESSION['appPayPalEcResult'])) {
        unset($_SESSION['appPayPalEcResult']);
      }

      if (isset($_SESSION['appPayPalEcSecret'])) {
        unset($_SESSION['appPayPalEcSecret']);
      }

      if (empty($_SESSION['sendto']['firstname']) && empty($_SESSION['sendto']['lastname']) && empty($_SESSION['sendto']['street_address'])) {
        unset($_SESSION['sendto']);
      }

      if (empty($_SESSION['billto']['firstname']) && empty($_SESSION['billto']['lastname']) && empty($_SESSION['billto']['street_address'])) {
        unset($_SESSION['billto']);
      }

      CLICSHOPPING::redirect(null, 'Cart');
    }

    protected function doCallbackSet()
    {
      $CLICSHOPPING_Currencies = Registry::get('Currencies');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');

      if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_INSTANT_UPDATE == '1')) {
        $log_sane = [];

        $counter = 0;

        if (isset($_POST['CURRENCYCODE']) && $CLICSHOPPING_Currencies->isSet($_POST['CURRENCYCODE']) && ($_SESSION['currency'] != $_POST['CURRENCYCODE'])) {
          $_SESSION['currency'] = $_POST['CURRENCYCODE'];

          $log_sane['CURRENCYCODE'] = $_POST['CURRENCYCODE'];
        }

        while (true) {
          if (isset($_POST['L_NUMBER' . $counter]) && isset($_POST['L_QTY' . $counter])) {
            $CLICSHOPPING_ShoppingCart->addCart($_POST['L_NUMBER' . $counter], $_POST['L_QTY' . $counter]);

            $log_sane['L_NUMBER' . $counter] = $_POST['L_NUMBER' . $counter];
            $log_sane['L_QTY' . $counter] = $_POST['L_QTY' . $counter];
          } else {
            break;
          }

          $counter++;
        }

// exit if there is nothing in the shopping cart
        if ($CLICSHOPPING_ShoppingCart->getCountContents() < 1) {
          return false;
        }

        $_SESSION['sendto'] = [
          'firstname' => null,
          'lastname' => null,
          'company' => null,
          'street_address' => $_POST['SHIPTOSTREET'],
          'suburb' => $_POST['SHIPTOSTREET2'],
          'postcode' => $_POST['SHIPTOZIP'],
          'city' => $_POST['SHIPTOCITY'],
          'zone_id' => null,
          'zone_name' => $_POST['SHIPTOSTATE'],
          'country_id' => null,
          'country_name' => $_POST['SHIPTOCOUNTRY'],
          'country_iso_code_2' => null,
          'country_iso_code_3' => null,
          'address_format_id' => null
        ];

        $log_sane['SHIPTOSTREET'] = $_POST['SHIPTOSTREET'];
        $log_sane['SHIPTOSTREET2'] = $_POST['SHIPTOSTREET2'];
        $log_sane['SHIPTOZIP'] = $_POST['SHIPTOZIP'];
        $log_sane['SHIPTOCITY'] = $_POST['SHIPTOCITY'];
        $log_sane['SHIPTOSTATE'] = $_POST['SHIPTOSTATE'];
        $log_sane['SHIPTOCOUNTRY'] = $_POST['SHIPTOCOUNTRY'];

        $Qcountry = $this->pm->app->db->get('countries', '*', [
          'countries_iso_code_2' => $_SESSION['sendto']['country_name']
        ],
          null,
          1
        );

        if ($Qcountry->fetch() !== false) {
          $_SESSION['sendto']['country_id'] = $Qcountry->valueInt('countries_id');
          $_SESSION['sendto']['country_name'] = $Qcountry->value('countries_name');
          $_SESSION['sendto']['country_iso_code_2'] = $Qcountry->value('countries_iso_code_2');
          $_SESSION['sendto']['country_iso_code_3'] = $Qcountry->value('countries_iso_code_3');
          $_SESSION['sendto']['address_format_id'] = $Qcountry->value('address_format_id');
        }

        if ($_SESSION['sendto']['country_id'] > 0) {

          $Qzone = $this->pm->app->db->prepare('select *
                                                    from :zones
                                                    where zone_country_id = :zone_country_id
                                                    and (zone_name = :zone_name or zone_code = :zone_code)
                                                    limit 1
                                                    ');
          $Qzone->bindInt(':zone_country_id', $_SESSION['sendto']['country_id']);
          $Qzone->bindValue(':zone_name', $_SESSION['sendto']['zone_name']);
          $Qzone->bindValue(':zone_code', $_SESSION['sendto']['zone_name']);
          $Qzone->execute();

          if ($Qzone->fetch() !== false) {
            $_SESSION['sendto']['zone_id'] = $Qzone->valueInt('zone_id');
            $_SESSION['sendto']['zone_name'] = $Qzone->value('zone_name');
          }
        }

        $_SESSION['billto'] = $_SESSION['sendto'];

        $quotes_array = [];

        $CLICSHOPPING_Order = Registry::get('Order');

        if ($CLICSHOPPING_ShoppingCart->get_content_type() != 'virtual') {
// load all enabled shipping modules
          if (!Registry::exists('Shipping')) {
            Registry::set('Shipping', new Shipping());
          }

          $CLICSHOPPING_Shipping = Registry::get('Shipping');

          $free_shipping = false;

          if (\defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
            $pass = false;

            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
              case 'national':
                if ($CLICSHOPPING_Order->delivery['country_id'] == STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'international':
                if ($CLICSHOPPING_Order->delivery['country_id'] != STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'both':
                $pass = true;
                break;
            }

            if (\defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER')) {
//                  if (($pass === true) && ($CLICSHOPPING_Order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {
              if (($pass == true) && ($CLICSHOPPING_Order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {

                $free_shipping = true;

                $this->lang->loadDefinitions('Shop/modules/order_total/ot_shipping');
              }
            }
          }

          if (($CLICSHOPPING_Shipping->geCountShippingModules() > 0) || ($free_shipping === true)) {
            if ($free_shipping === true) {
              $quotes_array[] = [
                'id' => 'free_free',
                'name' => CLICSHOPPING::getDef('free_shipping_title'),
                'label' => '',
                'cost' => '0',
                'tax' => '0'
              ];
            } else {
// get all available shipping quotes
              $quotes = $CLICSHOPPING_Shipping->getQuote();

              foreach ($quotes as $quote) {
                if (!isset($quote['error'])) {
                  foreach ($quote['methods'] as $rate) {
                    $quotes_array[] = [
                      'id' => $quote['id'] . '_' . $rate['id'],
                      'name' => $quote['module'],
                      'label' => $rate['title'],
                      'cost' => $rate['cost'],
                      'tax' => isset($quote['tax']) ? $quote['tax'] : '0'
                    ];
                  }
                }
              }
            }
          }
        } else {
          $quotes_array[] = [
            'id' => 'null',
            'name' => 'No Shipping',
            'label' => '',
            'cost' => '0',
            'tax' => '0'
          ];
        }

        $order_totals = $CLICSHOPPING_OrderTotal->process();

        $params = ['METHOD' => 'CallbackResponse',
          'CALLBACKVERSION' => $this->pm->api_version
        ];

        if (!empty($quotes_array)) {
          $params['CURRENCYCODE'] = $_SESSION['currency'];
          $params['OFFERINSURANCEOPTION'] = 'false';
          $params['L_SHIPPINGOPTIONISDEFAULT0'] = 'true';

          $counter = 0;

          foreach ($quotes_array as $quote) {
            $params['L_SHIPPINGOPTIONNAME' . $counter] = $quote['name'];
            $params['L_SHIPPINGOPTIONLABEL' . $counter] = $quote['label'];
            $params['L_SHIPPINGOPTIONAMOUNT' . $counter] = $this->pm->app->formatCurrencyRaw($quote['cost'] + Tax::calculate($quote['cost'], $quote['tax']));
            $params['L_SHIPPINGOPTIONISDEFAULT' . $counter] = 'false';

            if (DISPLAY_PRICE_WITH_TAX == 'false') {
              $params['L_TAXAMT' . $counter] = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['tax']);
            }

            $counter++;
          }
        } else {
          $params['NO_SHIPPING_OPTION_DETAILS'] = '1';
        }

        $post_string = '';

        foreach ($params as $key => $value) {
          $post_string .= $key . '=' . urlencode(utf8_encode(trim($value))) . '&';
        }

        $post_string = substr($post_string, 0, -1);

        $this->pm->app->log('EC', 'CallbackResponse', 1, $log_sane, $params);

        echo $post_string;
      }

      Registry::get('Session')->kill();
    }

    protected function doRetrieve()
    {
      $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Mail = Registry::get('Mail');
      $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');

      if (($CLICSHOPPING_ShoppingCart->getCountContents() < 1) || !isset($_GET['token']) || empty($_GET['token']) || !isset($_SESSION['appPayPalEcSecret'])) {
        CLICSHOPPING::redirect(null, 'Cart');
      }

      if (!isset($_SESSION['appPayPalEcResult']) || ($_SESSION['appPayPalEcResult']['TOKEN'] != $_GET['token'])) {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $_SESSION['appPayPalEcResult'] = $this->pm->app->getApiResult('EC', 'GetExpressCheckoutDetails', ['TOKEN' => $_GET['token']]);
        } else { // Payflow
          $_SESSION['appPayPalEcResult'] = $this->pm->app->getApiResult('EC', 'PayflowGetExpressCheckoutDetails', ['TOKEN' => $_GET['token']]);
        }
      }

      $pass = false;

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
        if (\in_array($_SESSION['appPayPalEcResult']['ACK'], [
          'Success',
          'SuccessWithWarning'
        ])) {
          $pass = true;
        }
      } else { // Payflow
        if ($_SESSION['appPayPalEcResult']['RESULT'] == '0') {
          $pass = true;
        }
      }

      if ($pass === true) {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          if ($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_CUSTOM'] != $_SESSION['appPayPalEcSecret']) {
            CLICSHOPPING::redirect(null, 'Cart');
          }
        } else { // Payflow
          if ($_SESSION['appPayPalEcResult']['CUSTOM'] != $_SESSION['appPayPalEcSecret']) {
            CLICSHOPPING::redirect(null, 'Cart');
          }
        }

        $_SESSION['payment'] = $this->pm->app->vendor . '\\' . $this->pm->app->code . '\\' . $this->pm->code;


        $force_login = false;

// check if e-mail address exists in database and login or create customer account
        if (!isset($_SESSION['customer_id']) || !$CLICSHOPPING_Customer->getID()) {
          $force_login = true;
          $force_redirect = false;

          $email_address = HTML::sanitize($_SESSION['appPayPalEcResult']['EMAIL']);

          if (!Is::EmailAddress($email_address)) {
            $force_redirect = true;
          } else {
            $Qcheck = $this->pm->app->db->get('customers', '*', [
              'customers_email_address' => $email_address
            ],
              null,
              1
            );

            if ($Qcheck->fetch() !== false) {
// Force the customer to log into their local account if payerstatus is unverified and a local password is set
              if (($_SESSION['appPayPalEcResult']['PAYERSTATUS'] == 'unverified') && !empty($Qcheck->value('customers_password'))) {
                $force_redirect = true;
              } else {
                $_SESSION['customer_id'] = $Qcheck->valueInt('customers_id');
                $_SESSION['customer_first_name'] = $customers_firstname = $Qcheck->value('customers_firstname');
                $_SESSION['customer_default_address_id'] = $Qcheck->valueInt('customers_default_address_id');
              }
            } else {
              $customers_firstname = HTML::sanitize($_SESSION['appPayPalEcResult']['FIRSTNAME']);
              $customers_lastname = HTML::sanitize($_SESSION['appPayPalEcResult']['LASTNAME']);

              $sql_data_array = [
                'customers_firstname' => $customers_firstname,
                'customers_lastname' => $customers_lastname,
                'customers_email_address' => $email_address,
                'customers_telephone' => null,
                'customers_fax' => null,
                'customers_newsletter' => '0',
                'customers_password' => 'none',
                'customers_gender' => null,
                'member_level' => '1'
              ];

              if (isset($_SESSION['appPayPalEcResult']['PHONENUM']) && !\is_null($_SESSION['appPayPalEcResult']['PHONENUM'])) {
                $customers_telephone = HTML::sanitize($_SESSION['appPayPalEcResult']['PHONENUM']);

                $sql_data_array['customers_telephone'] = $customers_telephone;
              }

              $this->pm->app->db->save('customers', $sql_data_array);

              $_SESSION['customer_id'] = $this->pm->app->db->lastInsertId();

              $this->pm->app->db->save('customers_info', [
                  'customers_info_id' => $_SESSION['customer_id'],
                  'customers_info_number_of_logons' => '0',
                  'customers_info_date_account_created' => 'now()'
                ]
              );

// Only generate a password and send an email if the Set Password Content Module is not enabled
              if (!\defined('MODULE_CONTENT_ACCOUNT_SET_PASSWORD_STATUS') || (MODULE_CONTENT_ACCOUNT_SET_PASSWORD_STATUS != 'True')) {
                $customer_password = Hash::getRandomString(max(ENTRY_PASSWORD_MIN_LENGTH, 8));

                $this->pm->app->db->save('customers', ['customers_password' => Hash::encrypt($customer_password)],
                  ['customers_id' => $_SESSION['customer_id']]
                );

// build the message content
                $name = $customers_lastname . ' ' . $customers_firstname;

                $email_text = CLICSHOPPING::getDef('email_greet_none_ec', ['name' => $name]) . "\n\n" .
                  CLICSHOPPING::getDef('text_email_welcome_ec', ['store_name' => STORE_NAME]) . "\n\n" .
                  $this->pm->app->getDef('module_ec_email_account_password', [
                      'email_address' => $email_address,
                      'password' => $customer_password
                    ]
                  ) . "\n\n";

                $email_text .= TemplateEmail::getTemplateEmailSignature() . "\n\n";
                $email_text .= TemplateEmail::getTemplateEmailTextFooter();

                $CLICSHOPPING_Mail->clicMail($name, $email_address, CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]), $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
              }
            }
          }

          if ($force_redirect === true) {
            $CLICSHOPPING_MessageStack->add('login', $this->pm->app->getDef('module_ec_error_local_login_required'), 'warning');

            $CLICSHOPPING_NavigationHistory->setSnapshot();

            $this->file = 'login_redirect.php';

            $this->data = [
              'login_url' => CLICSHOPPING::link(null, 'Account&LogIn'),
              'email_address' => $email_address
            ];

            return false;
          }

          Registry::get('Session')->recreate();
        }

// check if paypal shipping address exists in the address book
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $ship_firstname = HTML::sanitize(substr($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTONAME'], 0, strpos($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTONAME'], ' ')));
          $ship_lastname = HTML::sanitize(substr($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTONAME'], strpos($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTONAME'], ' ') + 1));
          $ship_address = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTREET']);

          if (isset($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTREET2'])) {
            $ship_suburb = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTREET2']);
          } else {
            $ship_suburb = '';
          }

          $ship_city = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOCITY']);
          $ship_zone = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTATE']);
          $ship_postcode = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOZIP']);
          $ship_country = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE']);
        } else { // Payflow
          $ship_firstname = HTML::sanitize(substr($_SESSION['appPayPalEcResult']['SHIPTONAME'], 0, strpos($_SESSION['appPayPalEcResult']['SHIPTONAME'], ' ')));
          $ship_lastname = HTML::sanitize(substr($_SESSION['appPayPalEcResult']['SHIPTONAME'], strpos($_SESSION['appPayPalEcResult']['SHIPTONAME'], ' ') + 1));
          $ship_address = HTML::sanitize($_SESSION['appPayPalEcResult']['SHIPTOSTREET']);

          if (isset($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTREET2'])) {
            $ship_suburb = HTML::sanitize($_SESSION['appPayPalEcResult']['PAYMENTREQUEST_0_SHIPTOSTREET2']);
          } else {
            $ship_suburb = '';
          }

          $ship_city = HTML::sanitize($_SESSION['appPayPalEcResult']['SHIPTOCITY']);
          $ship_zone = HTML::sanitize($_SESSION['appPayPalEcResult']['SHIPTOSTATE']);
          $ship_postcode = HTML::sanitize($_SESSION['appPayPalEcResult']['SHIPTOZIP']);
          $ship_country = HTML::sanitize($_SESSION['appPayPalEcResult']['SHIPTOCOUNTRY']);
        }

        $ship_zone_id = 0;
        $ship_country_id = 0;
        $ship_address_format_id = 1;

        $Qcountry = $this->pm->app->db->get('countries', [
          'countries_id',
          'address_format_id'
        ], [
          'countries_iso_code_2' => $ship_country
        ],
          null,
          1
        );

        if ($Qcountry->fetch() !== false) {
          $ship_country_id = $Qcountry->valueInt('countries_id');
          $ship_address_format_id = $Qcountry->valueInt('address_format_id');

          $Qzone = $this->pm->app->db->prepare('select zone_id
                                                    from :table_zones
                                                    where zone_country_id = :zone_country_id
                                                    and (zone_name = :zone_name or
                                                         zone_code = :zone_code
                                                   )
                                                   limit 1
                                                   ');
          $Qzone->bindInt(':zone_country_id', $ship_country_id);
          $Qzone->bindValue(':zone_name', $ship_zone);
          $Qzone->bindValue(':zone_code', $ship_zone);
          $Qzone->execute();

          if ($Qzone->fetch() !== false) {
            $ship_zone_id = $Qzone->valueInt('zone_id');
          }
        }

        $Qcheck = $this->pm->app->db->prepare('select address_book_id
                                            from :table_address_book
                                            where customers_id = :customers_id
                                            and entry_firstname = :entry_firstname
                                            and entry_lastname = :entry_lastname
                                            and entry_street_address = :entry_street_address
                                            and entry_suburb = :entry_suburb
                                            and entry_postcode = :entry_postcode
                                            and entry_city = :entry_city
                                            and (entry_state = :entry_state or
                                                 entry_zone_id = :entry_zone_id)
                                            and entry_country_id = :entry_country_id
                                            limit 1
                                            ');

        $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qcheck->bindValue(':entry_firstname', $ship_firstname);
        $Qcheck->bindValue(':entry_lastname', $ship_lastname);
        $Qcheck->bindValue(':entry_street_address', $ship_address);
        $Qcheck->bindValue(':entry_suburb', $ship_suburb);
        $Qcheck->bindValue(':entry_postcode', $ship_postcode);
        $Qcheck->bindValue(':entry_city', $ship_city);
        $Qcheck->bindValue(':entry_state', $ship_zone);
        $Qcheck->bindInt(':entry_zone_id', $ship_zone_id);
        $Qcheck->bindInt(':entry_country_id', $ship_country_id);
        $Qcheck->execute();

        if ($Qcheck->fetch() !== false) {
          $_SESSION['sendto'] = $Qcheck->valueInt('address_book_id');
        } else {
          $sql_data_array = [
            'customers_id' => $CLICSHOPPING_Customer->getID(),
            'entry_firstname' => $ship_firstname,
            'entry_lastname' => $ship_lastname,
            'entry_street_address' => $ship_address,
            'entry_suburb' => $ship_suburb,
            'entry_postcode' => $ship_postcode,
            'entry_city' => $ship_city,
            'entry_country_id' => $ship_country_id,
            'entry_gender' => ''
          ];

          if (ACCOUNT_STATE == 'true') {
            if ($ship_zone_id > 0) {
              $sql_data_array['entry_zone_id'] = $ship_zone_id;
              $sql_data_array['entry_state'] = '';
            } else {
              $sql_data_array['entry_zone_id'] = '0';
              $sql_data_array['entry_state'] = $ship_zone;
            }
          }

          $this->pm->app->db->save('address_book', $sql_data_array);

          $address_id = $this->pm->app->db->lastInsertId();

          $_SESSION['sendto'] = $address_id;

          if (!isset($_SESSION['customer_default_address_id']) || !$CLICSHOPPING_Customer->getDefaultAddressID()) {
            $this->pm->app->db->save('customers', ['customers_default_address_id' => (int)$_SESSION['sendto']],
              ['customers_id' => (int)$_SESSION['customer_id']]
            );

            $_SESSION['customer_default_address_id'] = $_SESSION['sendto'];
          }
        }

        $_SESSION['billto'] = $_SESSION['sendto'];

        if ($force_login === true) {
          $_SESSION['customer_country_id'] = $ship_country_id;
          $_SESSION['customer_zone_id'] = $ship_zone_id;
        }

        $CLICSHOPPING_Order = Registry::get('Order');

        if ($CLICSHOPPING_ShoppingCart->get_content_type() != 'virtual') {
// load all enabled shipping modules
          Registry::set('Shipping', new Shipping());
          $CLICSHOPPING_Shipping = Registry::get('Shipping');

          $free_shipping = false;

          if (\defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
            $pass = false;

            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
              case 'national':
                if ($CLICSHOPPING_Order->delivery['country_id'] == STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'international':
                if ($CLICSHOPPING_Order->delivery['country_id'] != STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'both':
                $pass = true;
                break;
            }

            if (($pass === true) && ($CLICSHOPPING_Order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {
              $free_shipping = true;

              $this->lang->loadDefinitions('Shop/modules/order_total/ot_shipping');
            }
          }

          $_SESSION['shipping'] = false;

          if (($CLICSHOPPING_Shipping->geCountShippingModules() > 0) || ($free_shipping === true)) {
            if ($free_shipping === true) {
              $_SESSION['shipping'] = 'free_free';
            } else {
// get all available shipping quotes
              $quotes = $CLICSHOPPING_Shipping->getQuote();

              $shipping_set = false;

              if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_INSTANT_UPDATE == '1') && ((CLICSHOPPING_APP_PAYPAL_EC_STATUS == '0') || ((CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') && (parse_url(CLICSHOPPING::getConfig('http_server'), PHP_URL_SCHEME) == 'https'))) && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '0')) { // Live server requires SSL to be enabled
// if available, set the selected shipping rate from PayPals order review page
                if (isset($_SESSION['appPayPalEcResult']['SHIPPINGOPTIONNAME']) && isset($_SESSION['appPayPalEcResult']['SHIPPINGOPTIONAMOUNT'])) {
                  foreach ($quotes as $quote) {
                    if (!isset($quote['error'])) {
                      foreach ($quote['methods'] as $rate) {
                        if ($_SESSION['appPayPalEcResult']['SHIPPINGOPTIONNAME'] == trim($quote['module'] . ' ' . $rate['title'])) {
                          $shipping_rate = $this->pm->app->formatCurrencyRaw($rate['cost'] + Tax::calculate($rate['cost'], $quote['tax']));

                          if ($_SESSION['appPayPalEcResult']['SHIPPINGOPTIONAMOUNT'] == $shipping_rate) {
                            $_SESSION['shipping'] = $quote['id'] . '_' . $rate['id'];
                            $shipping_set = true;
                            break 2;
                          }
                        }
                      }
                    }
                  }
                }
              }

              if ($shipping_set === false) {
                $_SESSION['shipping'] = $CLICSHOPPING_Shipping->getFirst();
                $_SESSION['shipping'] = $_SESSION['shipping']['id'];
              }
            }
          } else {
            if (\defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False')) {
              unset($_SESSION['shipping']);

              $CLICSHOPPING_MessageStack->add('index.php', 'Checkout&ShippingAddress&' . $this->pm->app->getDef('module_ec_error_no_shipping_available'), 'error');

              $_SESSION['appPayPalEcRightTurn'] = true;

              CLICSHOPPING::redirect(null, 'Checkout&ShippingAddress');
            }
          }

          $CLICSHOPPING_SM = null;


          if (str_contains($_SESSION['shipping'], '\\')) {
            [$vendor, $app, $module] = explode('\\', $_SESSION['shipping']);
            [$module, $method] = explode('_', $module);

            $module = $vendor . '\\' . $app . '\\' . $module;

            $code = 'Shipping_' . str_replace('\\', '_', $module);

            if (Registry::exists($code)) {
              $CLICSHOPPING_SM = Registry::get($code);
            }
          }

          if (isset($CLICSHOPPING_SM) || ($_SESSION['shipping'] == 'free_free')) {
            $quote = [];

            if ($_SESSION['shipping'] == 'free_free') {
              $quote[0]['methods'][0]['title'] = CLICSHOPPING::getDef('free_shipping_title');
              $quote[0]['methods'][0]['cost'] = '0';
            } else {
              $quote = $CLICSHOPPING_Shipping->getQuote($method, $module);
            }

            if (isset($quote['error'])) {
              unset($_SESSION['shipping']);

              CLICSHOPPING::redirect(null, 'Checkout&Shipping');
            } else {
              if ((isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost']))) {
                $_SESSION['shipping'] = [
                  'id' => $_SESSION['shipping'],
                  'title' => (($free_shipping === true) ? $quote[0]['methods'][0]['title'] : $quote[0]['module'] . (isset($quote[0]['methods'][0]['title']) && !empty($quote[0]['methods'][0]['title']) ? ' (' . $quote[0]['methods'][0]['title'] . ')' : '')),
                  'cost' => $quote[0]['methods'][0]['cost']
                ];
              }
            }
          }
        } else {
          $_SESSION['shipping'] = false;
          $_SESSION['sendto'] = false;
        }

        if (isset($_SESSION['shipping'])) {
          if (isset($_SESSION['customer_id'])) {
            $CLICSHOPPING_Customer->setData($_SESSION['customer_id']);
          }

// Sales condition, pass the verification
// bug the data is not sent
          if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
            $_POST['conditions'] = 1;
          }

          CLICSHOPPING::redirect(null, 'Checkout&Confirmation');
        } else {
          $_SESSION['appPayPalEcRightTurn'] = true;

          CLICSHOPPING::redirect(null, 'Checkout&Shipping');
        }
      } else {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $CLICSHOPPING_MessageStack->add('header', stripslashes($_SESSION['appPayPalEcResult']['L_LONGMESSAGE0']), 'error');
        } else { // Payflow
          $CLICSHOPPING_MessageStack->add('header', $_SESSION['appPayPalEcResult']['CLICSHOPPING_ERROR_MESSAGE'], 'error');
        }

        CLICSHOPPING::redirect(null, 'Cart');
      }
    }

    protected function doSet()
    {
      $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_Address = Registry::get('Address');

// if there is nothing in the customers cart, redirect them to the shopping cart page
      if ($CLICSHOPPING_ShoppingCart->getCountContents() < 1) {
        CLICSHOPPING::redirect(null, 'Cart');
      }

      if (CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') {
        if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '1')) {
          $paypal_url = 'https://www.paypal.com/checkoutnow?';
        } else {
          $paypal_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&';
        }
      } else {
        if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '1')) {
          $paypal_url = 'https://www.sandbox.paypal.com/checkoutnow?';
        } else {
          $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&';
        }
      }


      $params = [];

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
        $params['PAYMENTREQUEST_0_CURRENCYCODE'] = $CLICSHOPPING_Order->info['currency'];
        $params['ALLOWNOTE'] = '0';
      } else { // Payflow
        $params['CURRENCY'] = $CLICSHOPPING_Order->info['currency'];
        $params['EMAIL'] = $CLICSHOPPING_Order->customer['email_address'];
        $params['ALLOWNOTE'] = '0';

        $params['BILLTOFIRSTNAME'] = $CLICSHOPPING_Order->billing['firstname'];
        $params['BILLTOLASTNAME'] = $CLICSHOPPING_Order->billing['lastname'];
        $params['BILLTOSTREET'] = $CLICSHOPPING_Order->billing['street_address'];
        $params['BILLTOSTREET2'] = $CLICSHOPPING_Order->billing['suburb'];
        $params['BILLTOCITY'] = $CLICSHOPPING_Order->billing['city'];
        $params['BILLTOSTATE'] = $CLICSHOPPING_Address->getZoneCode($CLICSHOPPING_Order->billing['country']['id'], $CLICSHOPPING_Order->billing['zone_id'], $CLICSHOPPING_Order->billing['state']);
        $params['BILLTOCOUNTRY'] = $CLICSHOPPING_Order->billing['country']['iso_code_2'];
        $params['BILLTOZIP'] = $CLICSHOPPING_Order->billing['postcode'];
      }

// A billing address is required for digital orders so we use the shipping address PayPal provides
//        if ($order->content_type == 'virtual') {
//            $params['NOSHIPPING'] = '1';
//        }

      $item_params = [];

      $line_item_no = 0;

      foreach ($CLICSHOPPING_Order->products as $product) {
        if (DISPLAY_PRICE_WITH_TAX == 'true') {
          $product_price = $this->pm->app->formatCurrencyRaw($product['final_price'] + Tax::calculate($product['final_price'], $product['tax']));
        } else {
          $product_price = $this->pm->app->formatCurrencyRaw($product['final_price']);
        }

        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $item_params['L_PAYMENTREQUEST_0_NAME' . $line_item_no] = $product['name'];
          $item_params['L_PAYMENTREQUEST_0_AMT' . $line_item_no] = $product_price;
          $item_params['L_PAYMENTREQUEST_0_NUMBER' . $line_item_no] = $product['id'];
          $item_params['L_PAYMENTREQUEST_0_QTY' . $line_item_no] = $product['qty'];
          $item_params['L_PAYMENTREQUEST_0_ITEMURL' . $line_item_no] = CLICSHOPPING::link(null, 'Products&Description&products_id=' . $product['id'], false);

          if ((DOWNLOAD_ENABLED == 'true') && isset($product['attributes'])) {
            $item_params['L_PAYMENTREQUEST_0_ITEMCATEGORY' . $line_item_no] = $this->pm->getProductType($product['id'], $product['attributes']);
          } else {
            $item_params['L_PAYMENTREQUEST_0_ITEMCATEGORY' . $line_item_no] = 'Physical';
          }
        } else { // Payflow
          $item_params['L_NAME' . $line_item_no] = $product['name'];
          $item_params['L_COST' . $line_item_no] = $product_price;
          $item_params['L_QTY' . $line_item_no] = $product['qty'];
        }

        $line_item_no++;
      }

      if (!empty($CLICSHOPPING_Order->delivery['street_address']) || !\is_null($CLICSHOPPING_Order->delivery['street_address'])) {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $params['PAYMENTREQUEST_0_SHIPTONAME'] = $CLICSHOPPING_Order->delivery['firstname'] . ' ' . $CLICSHOPPING_Order->delivery['lastname'];
          $params['PAYMENTREQUEST_0_SHIPTOSTREET'] = $CLICSHOPPING_Order->delivery['street_address'];
          $params['PAYMENTREQUEST_0_SHIPTOSTREET2'] = $CLICSHOPPING_Order->delivery['suburb'];
          $params['PAYMENTREQUEST_0_SHIPTOCITY'] = $CLICSHOPPING_Order->delivery['city'];
          $params['PAYMENTREQUEST_0_SHIPTOSTATE'] = $CLICSHOPPING_Address->getZoneCode($CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'], $CLICSHOPPING_Order->delivery['state']);
          $params['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $CLICSHOPPING_Order->delivery['country']['iso_code_2'];
          $params['PAYMENTREQUEST_0_SHIPTOZIP'] = $CLICSHOPPING_Order->delivery['postcode'];
        } else { // Payflow
          $params['SHIPTONAME'] = $CLICSHOPPING_Order->delivery['firstname'] . ' ' . $CLICSHOPPING_Order->delivery['lastname'];
          $params['SHIPTOSTREET'] = $CLICSHOPPING_Order->delivery['street_address'];
          $params['SHIPTOSTREET2'] = $CLICSHOPPING_Order->delivery['suburb'];
          $params['SHIPTOCITY'] = $CLICSHOPPING_Order->delivery['city'];
          $params['SHIPTOSTATE'] = $CLICSHOPPING_Address->getZoneCode($CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'], $CLICSHOPPING_Order->delivery['state']);
          $params['SHIPTOCOUNTRY'] = $CLICSHOPPING_Order->delivery['country']['iso_code_2'];
          $params['SHIPTOZIP'] = $CLICSHOPPING_Order->delivery['postcode'];
        }
      }

      $paypal_item_total = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['subtotal']);

      if ((CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') && (CLICSHOPPING_APP_PAYPAL_EC_INSTANT_UPDATE == '1') && ((CLICSHOPPING_APP_PAYPAL_EC_STATUS == '0') || ((CLICSHOPPING_APP_PAYPAL_EC_STATUS == '1') && (parse_url(CLICSHOPPING::getConfig('http_server'), PHP_URL_SCHEME) == 'https'))) && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '0')) { // Live server requires SSL to be enabled
        $quotes_array = [];


        if ($CLICSHOPPING_ShoppingCart->get_content_type() != 'virtual') {
// load all enabled shipping modules
          Registry::set('Shipping', new Shipping());
          $CLICSHOPPING_Shipping = Registry::get('Shipping');

          $free_shipping = false;

          if (\defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
            $pass = false;

            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
              case 'national':
                if ($CLICSHOPPING_Order->delivery['country_id'] == STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'international':
                if ($CLICSHOPPING_Order->delivery['country_id'] != STORE_COUNTRY) {
                  $pass = true;
                }
                break;

              case 'both':
                $pass = true;
                break;
            }

            if (($pass === true) && ($CLICSHOPPING_Order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {
              $free_shipping = true;

              $this->lang->loadDefinitions('Shop/modules/order_total/ot_shipping');
            }
          }

          if (($CLICSHOPPING_Shipping->geCountShippingModules() > 0) || ($free_shipping === true)) {
            if ($free_shipping === true) {
              $quotes_array[] = [
                'id' => 'free_free',
                'name' => CLICSHOPPING::getDef('free_shipping_title'),
                'label' => '',
                'cost' => '0.00',
                'tax' => '0'
              ];
            } else {
// get all available shipping quotes
              $quotes = $CLICSHOPPING_Shipping->getQuote();

              foreach ($quotes as $quote) {
                if (!isset($quote['error'])) {
                  foreach ($quote['methods'] as $rate) {
                    $quotes_array[] = [
                      'id' => $quote['id'] . '_' . $rate['id'],
                      'name' => $quote['module'],
                      'label' => $rate['title'],
                      'cost' => $rate['cost'],
                      'tax' => (isset($quote['tax']) ? $quote['tax'] : null)
                    ];
                  }
                }
              }
            }
          } else {
            if (\defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False')) {
              unset($_SESSION['shipping']);

              $CLICSHOPPING_MessageStack->add('index.php', 'Checkout&ShippingAddress&' . $this->pm->app->getDef('module_ec_error_no_shipping_available'), 'error');

              CLICSHOPPING::redirect(null, 'Checkout&ShippingAddress');
            }
          }
        }

        $counter = 0;
        $expensive_rate = 0;
        $default_shipping = null;

        foreach ($quotes_array as $quote) {
          $shipping_rate = $this->pm->app->formatCurrencyRaw($quote['cost'] + Tax::calculate($quote['cost'], $quote['tax']));
          $item_params['L_SHIPPINGOPTIONNAME' . $counter] = trim($quote['name'] . ' ' . $quote['label']);
          $item_params['L_SHIPPINGOPTIONAMOUNT' . $counter] = $shipping_rate;
          $item_params['L_SHIPPINGOPTIONISDEFAULT' . $counter] = 'false';

          if ($shipping_rate > $expensive_rate) {
            $expensive_rate = $shipping_rate;
          }

          if (isset($_SESSION['shipping']) && ($_SESSION['shipping']['id'] == $quote['id'])) {
            $default_shipping = $counter;
          }

          $counter++;
        }

        if (!isset($default_shipping) && !empty($quotes_array)) {
          $_SESSION['shipping'] = [
            'id' => $quotes_array[0]['id'],
            'title' => $item_params['L_SHIPPINGOPTIONNAME0'],
            'cost' => $this->pm->app->formatCurrencyRaw($quotes_array[0]['cost'])
          ];

          $default_shipping = 0;
        }

        if (!isset($default_shipping)) {
          $_SESSION['shipping'] = false;
        } else {
          $item_params['PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED'] = 'false';
          $item_params['L_SHIPPINGOPTIONISDEFAULT' . $default_shipping] = 'true';

// Instant Update
          $item_params['CALLBACK'] = CLICSHOPPING::link(null, 'order&callback&paypal&ec&action=callbackSet', false, false);
          $item_params['CALLBACKTIMEOUT'] = '6';
          $item_params['CALLBACKVERSION'] = $this->pm->api_version;

// set shipping for order total calculations; shipping in $item_params includes taxes
          $CLICSHOPPING_Order->info['shipping_method'] = $item_params['L_SHIPPINGOPTIONNAME' . $default_shipping];
          $CLICSHOPPING_Order->info['shipping_cost'] = $item_params['L_SHIPPINGOPTIONAMOUNT' . $default_shipping];

          $CLICSHOPPING_Order->info['total'] = $CLICSHOPPING_Order->info['subtotal'] + $CLICSHOPPING_Order->info['shipping_cost'];

          if (DISPLAY_PRICE_WITH_TAX == 'false') {
            $CLICSHOPPING_Order->info['total'] += $CLICSHOPPING_Order->info['tax'];
          }
        }

        $order_totals = $CLICSHOPPING_OrderTotal->process();

// Remove shipping tax from total that was added again in ot_shipping
        if (isset($default_shipping)) {
          if (DISPLAY_PRICE_WITH_TAX == 'true') {
            $CLICSHOPPING_Order->info['shipping_cost'] = $CLICSHOPPING_Order->info['shipping_cost'] / (1.0 + ($quotes_array[$default_shipping]['tax'] / 100));
          }

          $module = substr($_SESSION['shipping']['id'], 0, strpos($_SESSION['shipping']['id'], '_'));

          $CLICSHOPPING_Order->info['tax'] -= Tax::calculate($CLICSHOPPING_Order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);

          if (!isset($CLICSHOPPING_Order->info['tax_groups'][Tax::getTaxRateDescription($GLOBALS[$module]->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'])])) {
            $CLICSHOPPING_Order->info['tax_groups'][Tax::getTaxRateDescription($GLOBALS[$module]->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'])] = 0;
          }

          $CLICSHOPPING_Order->info['tax_groups'][Tax::getTaxRateDescription($GLOBALS[$module]->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id'])] -= osc_calculate_tax($CLICSHOPPING_Order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);

          $CLICSHOPPING_Order->info['total'] -= Tax::calculate($CLICSHOPPING_Order->info['shipping_cost'], $quotes_array[$default_shipping]['tax']);
        }

        $items_total = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['subtotal']);

        foreach ($order_totals as $ot) {
          if (!\in_array($ot['code'], [
            'ot_subtotal',
            'ot_shipping',
            'ot_tax',
            'ot_total',
            'ST',
            'SH',
            'TX',
            'TO'
          ])) {
            $item_params['L_PAYMENTREQUEST_0_NAME' . $line_item_no] = $ot['title'];
            $item_params['L_PAYMENTREQUEST_0_AMT' . $line_item_no] = $this->pm->app->formatCurrencyRaw($ot['value']);

            $items_total += $this->pm->app->formatCurrencyRaw($ot['value']);

            $line_item_no++;
          }
        }

        $params['PAYMENTREQUEST_0_AMT'] = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['total']);

        $item_params['MAXAMT'] = $this->pm->app->formatCurrencyRaw($params['PAYMENTREQUEST_0_AMT'] + $expensive_rate + 100, null, 1); // safely pad higher for dynamic shipping rates (eg, USPS express)
        $item_params['PAYMENTREQUEST_0_ITEMAMT'] = $items_total;
        $item_params['PAYMENTREQUEST_0_SHIPPINGAMT'] = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['shipping_cost']);

        $paypal_item_total = $item_params['PAYMENTREQUEST_0_ITEMAMT'] + $item_params['PAYMENTREQUEST_0_SHIPPINGAMT'];

        if (DISPLAY_PRICE_WITH_TAX == 'false') {
          $item_params['PAYMENTREQUEST_0_TAXAMT'] = $this->pm->app->formatCurrencyRaw($CLICSHOPPING_Order->info['tax']);

          $paypal_item_total += $item_params['PAYMENTREQUEST_0_TAXAMT'];
        }
      } else {
        if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
          $params['PAYMENTREQUEST_0_AMT'] = $paypal_item_total;
        } else { // Payflow
          $params['AMT'] = $paypal_item_total;
        }
      }

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
        if ($this->pm->app->formatCurrencyRaw($paypal_item_total) == $params['PAYMENTREQUEST_0_AMT']) {
          $params = array_merge($params, $item_params);
        }
      } else { // Payflow
        if ($this->pm->app->formatCurrencyRaw($paypal_item_total) == $params['AMT']) {
          $params = array_merge($params, $item_params);
        }
      }

//        if (!\is_null(CLICSHOPPING_APP_PAYPAL_EC_PAGE_STYLE) && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '0')) {
      if (!empty(CLICSHOPPING_APP_PAYPAL_EC_PAGE_STYLE) && (CLICSHOPPING_APP_PAYPAL_EC_CHECKOUT_FLOW == '0')) {
        $params['PAGESTYLE'] = CLICSHOPPING_APP_PAYPAL_EC_PAGE_STYLE;
      }

      $_SESSION['appPayPalEcSecret'] = Hash::getRandomString(16, 'digits');

      if (CLICSHOPPING_APP_PAYPAL_GATEWAY == '1') { // PayPal
        $params['PAYMENTREQUEST_0_CUSTOM'] = $_SESSION['appPayPalEcSecret'];

// Log In with PayPal token for seamless checkout
        if (isset($_SESSION['paypal_login_access_token'])) {
          $params['IDENTITYACCESSTOKEN'] = $_SESSION['paypal_login_access_token'];
        }

        $response_array = $this->pm->app->getApiResult('EC', 'SetExpressCheckout', $params);

        if (\in_array($response_array['ACK'], [
          'Success',
          'SuccessWithWarning'
        ])) {
          if (isset($_GET['format']) && ($_GET['format'] == 'json')) {
            $result = [
              'token' => $response_array['TOKEN']
            ];

            echo json_encode($result);
            exit;
          }

          HTTP::redirect($paypal_url . 'token=' . $response_array['TOKEN']);
        } else {
          CLICSHOPPING::redirect(null, 'Cart&error_message=' . stripslashes($response_array['L_LONGMESSAGE0']));
        }
      } else { // Payflow
        $params['CUSTOM'] = $_SESSION['appPayPalEcSecret'];

        $params['_headers'] = [
          'X-VPS-REQUEST-ID: ' . md5($_SESSION['cartID'] . session_id() . $this->pm->app->formatCurrencyRaw($paypal_item_total)),
          'X-VPS-CLIENT-TIMEOUT: 45',
          'X-VPS-VIT-INTEGRATION-PRODUCT: CLICSHOPPING',
          'X-VPS-VIT-INTEGRATION-VERSION: ' . CLICSHOPPING::getVersion()
        ];

        $response_array = $this->pm->app->getApiResult('EC', 'PayflowSetExpressCheckout', $params);

        if ($response_array['RESULT'] == '0') {
          HTTP::redirect($paypal_url . 'token=' . $response_array['TOKEN']);
        } else {
          CLICSHOPPING::redirect(null, 'Cart&error_message=' . urlencode($response_array['CLICSHOPPING_ERROR_MESSAGE']));
        }
      }
    }
  }
