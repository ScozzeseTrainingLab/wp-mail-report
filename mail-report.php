<?php
/*
Plugin Name: Mail Report
Author: Michele Salvini
Description: Send email, store as a custom post, Track opened emails. - Use: replace "wp_mail()" with "MailReportPlugin::send()" in your code
Version: 1.0.0
*/


class MailReportPlugin
{

  static $post_type = 'mail_report';

  static $custom_field = 'mailreport';

  static function buildTrackingPixel ($ID, $address)
  {
    return site_url('/mail_report/api/trackingpixel/' . $ID . '/' . urlencode($address));
  }

  function __construct ()
  {

    add_filter('query_vars', array($this, 'add_query_vars'), 0);
    add_action('parse_request', array($this, 'sniff_requests'), 0);
    add_action('init', array($this, 'init'), 0);
    add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 0);

    register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
    register_activation_hook( __FILE__, array($this, 'activation') );

  }

  public function activation () {
    $this->init();

    add_rewrite_rule('^mail_report/api/?([a-z]+)?/?([0-9]+)?/?(.+)?/?','index.php?__mailreport=1&mr_action=$matches[1]&mr_id=$matches[2]&mr_payload=$matches[3]', 'top');
    flush_rewrite_rules();
  }

  public function init () {

    register_post_type(self::$post_type, array(
      'labels'             => array(
        'name'               => _x( 'Mail Reports', 'post type general name', 'mail-report' ),
        'singular_name'      => _x( 'Mail Report', 'post type singular name', 'mail-report' ),
        'menu_name'          => _x( 'Mail Reports', 'admin menu', 'mail-report' ),
        'name_admin_bar'     => _x( 'Mail Report', 'add new on admin bar', 'mail-report' ),
        'add_new'            => _x( 'Add New', 'Mail report', 'mail-report' ),
        'add_new_item'       => __( 'Add New Mail Report', 'mail-report' ),
        'new_item'           => __( 'New Mail Report', 'mail-report' ),
        'edit_item'          => __( 'Edit Mail Report', 'mail-report' ),
        'view_item'          => __( 'View Mail Report', 'mail-report' ),
        'all_items'          => __( 'All Mail Reports', 'mail-report' ),
        'search_items'       => __( 'Search Mail Reports', 'mail-report' ),
        'parent_item_colon'  => __( 'Parent Mail Reports:', 'mail-report' ),
        'not_found'          => __( 'No Mail Reports found.', 'mail-report' ),
        'not_found_in_trash' => __( 'No Mail Reports found in Trash.', 'mail-report' )
      ),
      'description'        => __( 'Every sent email has a backup post.', 'mail-report' ),
      'public'             => false,
      'publicly_queryable' => false,
      'show_ui'            => true,
      'show_in_menu'       => true,
      'query_var'          => true,
      'rewrite'            => false,
      'capability_type'    => 'post',
      'has_archive'        => false,
      'hierarchical'       => false,
      'menu_position'      => null,
      'menu_icon'          => 'dashicons-archive',
      'supports'           => array( 'title', 'editor' )
    ));

  }

  function add_meta_boxes () {

    add_meta_box(
      'mail-report',
      _x( 'Mail Report', 'post type singular name', 'mail-report' ),
      array($this, 'render_meta_box'),
      self::$post_type,
      'normal',
      'high',
      array()
    );

  }

  function render_meta_box ($post, $args) {
    $pixels = get_post_meta($post->ID, self::$custom_field);
    ?>
    <table cellspacing="10">
      <tbody>
          <tr>
            <th>#</th>
            <th>Recipient</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        <?php foreach ($pixels as $key => $pixel): $part = explode(' - ', $pixel); ?>
          <tr>
            <td><?php echo $key+1; ?></td>
            <td><?php echo (!empty($part[0]) ? $part[0] : 'error'); ?></td>
            <td>
              <?php if (empty($part[1])): ?>
                <span style="background:#ff0000;">unread</span>
              <?php else: ?>
                <span style="background:#00ff00;">opened: <?php echo $part[1]; ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (empty($part[1])): ?>
                <a href="<?php echo self::buildTrackingPixel($post->ID, $part[0]); ?>">set as "opened"</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
  }

  public function add_query_vars ($vars) {
    $vars[] = '__mailreport';
    $vars[] = 'mr_action';
    $vars[] = 'mr_id';
    $vars[] = 'mr_payload';

    return $vars;
  }

  public function sniff_requests () {
    global $wp;

    if (isset($wp->query_vars['__mailreport'])) {
      $this->handle_request($wp->query_vars['mr_action'], $wp->query_vars['mr_id'], $wp->query_vars['mr_payload']);
      exit;
    }
  }

  public function handle_request ($action = '', $ID = '', $payload = '') {
    switch ($action) {

      case 'trackingpixel':
        $address = urldecode($payload);
        update_post_meta($ID, self::$custom_field, $address . ' - ' . date('H:i:s j-m-Y'), $address);

        $im = imagecreatetruecolor(1, 1);

        header('Content-Type: image/jpeg');

        imagejpeg($im);
        imagedestroy($im);

        break;

    }

    exit;
  }

  static function send ($to, $subject, $message, $headers = '', $attachments = array()) {

    $post_id = wp_insert_post(array(
      'post_title'     => $subject . ' - ' . date('H:i:s j-m-Y'),
      'post_content'   => $message,
      'post_type'      => self::$post_type
    ), $wp_error);

    if ($wp_error)
      return false;

    if (!is_array($to)) {
      $to = array($to);
    }

    foreach ($to as $address) {

      $message = str_replace("</body>", '<img src="' . self::buildTrackingPixel($post_id, $address) . '" /></body>', $message);

      $out = wp_mail($address, $subject, $message, $headers, $attachments);

      if (!$out)
        return $out;

      add_post_meta($post_id, self::$custom_field, $address);
    }

    return true;

  }

}


new MailReportPlugin();

