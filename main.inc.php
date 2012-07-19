<?php
/*
Plugin Name: Reset manual order
Version: auto
Description: In an album, reset manual order with the current automatic order
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: plg
Author URI: http://piwigo.wordpress.com
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

load_language('plugin.lang', PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');

add_event_handler('loc_begin_admin', 'reset_manual_order_add_link');
function reset_manual_order_add_link()
{
  global $template;

  // this is a trick: instead of directly filling $_SESSION['page_infos'] in
  // function reset_manual_order_process I had to use a temporary variable
  // $_SESSION['reset_manual_order'] because the message with a quote (in French)
  //  was not correctly registered in the database.
  if (isset($_SESSION['reset_manual_order']))
  {
    $_SESSION['page_infos'] = array(l10n('Images manual order was saved'));
    unset($_SESSION['reset_manual_order']);
  }

  $template->set_prefilter('element_set_ranks', 'reset_manual_order_add_link_prefilter');
}

function reset_manual_order_add_link_prefilter($content, &$smarty)
{
  $search = "#<legend>\{'Manual order'\|@translate\}</legend>#";
  $replacement = '<legend>{\'Manual order\'|@translate}</legend>
<a href="{$F_ACTION}&amp;reset_manual_order=1">{\'Reset manual order with current automatic order\'|@translate}</a>
';

  return preg_replace($search, $replacement, $content);
}

add_event_handler('loc_begin_admin_page', 'reset_manual_order_process');
function reset_manual_order_process()
{
  global $page, $template, $conf;
  
  if ('element_set_ranks' == $page['page'] and isset($_GET['reset_manual_order']))
  {
    $query = '
SELECT *
  FROM '.CATEGORIES_TABLE.'
  WHERE id = '.$_GET['cat_id'].'
;';
    $row = pwg_db_fetch_assoc(pwg_query($query));

    $order_by = $conf['order_by_inside_category'];
    if (!empty($row['image_order']) and 'rank' != $row['image_order'])
    {
      $order_by = ' ORDER BY '.$row['image_order'];
    }

    $query = '
SELECT
    image_id
  FROM '.IMAGE_CATEGORY_TABLE.'
    JOIN '.IMAGES_TABLE.' ON id = image_id
  WHERE category_id = '.$_GET['cat_id'].'
  '.$order_by.'
;';
    $result = pwg_query($query);

    $ordered_image_ids = array();
    while ($row = pwg_db_fetch_assoc($result))
    {
      $ordered_image_ids[] = $row['image_id'];
    }

    $current_rank = 0;
    $datas = array();
    foreach ($ordered_image_ids as $id)
    {
      array_push(
        $datas,
        array(
          'category_id' => $_GET['cat_id'],
          'image_id' => $id,
          'rank' => ++$current_rank,
          )
        );
    }

    $fields = array(
      'primary' => array('image_id', 'category_id'),
      'update' => array('rank')
      );

    mass_updates(IMAGE_CATEGORY_TABLE, $fields, $datas);

    $_SESSION['reset_manual_order'] = true;
    redirect(get_root_url().'admin.php?page=element_set_ranks&cat_id='.$_GET['cat_id']);
  }
}
?>
