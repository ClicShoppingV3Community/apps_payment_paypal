<?php
/**
 *
 *  @copyright 2008 - https://www.clicshopping.org
 *  @Brand : ClicShopping(Tm) at Inpi all right Reserved
 *  @Licence GPL 2 & MIT
 *  @licence MIT - Portion of osCommerce 2.4
 *  @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\ClicShopping;

  $CLICSHOPPING_Template = Registry::get('Template');
  $CLICSHOPPING_Page = Registry::get('Site')->getPage();

?>
<!DOCTYPE html>
<html <?php echo ClicShopping::getDef('html_params'); ?>>
<head>
<meta charset="<?php echo ClicShopping::getDef('charset'); ?>">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo HTML::outputProtected($CLICSHOPPING_Template->getTitle()); ?></title>
</head>
<body>
<form name="pe" action="<?php echo $CLICSHOPPING_Page->data['login_url']; ?>" method="post" target="_top">
  <?php echo HTML::hiddenField('email_address', $CLICSHOPPING_Page->data['email_address']); ?>
</form>
<script>
document.pe.submit();
</script>
</body>
</html>
