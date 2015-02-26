<?php

/**
 * Place a notification in the admin pages in one of two cases: 
 * 1. User available but didn't complete registration
 * 2. User not created and didn't request to dismiss the message
 */
function vcita_wp_add_admin_notices() {
  $vcita_widget = (array) get_option(VCITA_WIDGET_KEY);
  
  if (!isset($_GET['page']) || !preg_match('/'.VCITA_WIDGET_UNIQUE_ID.'\//',$_GET['page'])) {
  
    $vcita_section_url = admin_url("plugins.php?page=".plugin_basename(__FILE__));
    $vcita_dismiss_url = admin_url("plugins.php?page=".plugin_basename(__FILE__)."&dismiss=true");
    $prefix = "<p><b>".VCITA_WIDGET_PLUGIN_NAME." - </b>";
    $suffix = "</p>";
    $class = "error";
    $user_available = isset($vcita_widget["uid"]) && !empty($vcita_widget["uid"]);
    
    if ($user_available && !$vcita_widget['confirmed'] && !empty($vcita_widget['confirmation_token'])) {
      echo "<div class='".$class."'>".$prefix." <a href='".$vcita_section_url."'>Click here to configure your contact preferences</a>".$suffix."</div>";
    } 
  }
}

/**
 *  Add the vCita widget to the "Settings" Side Menu
 */
function vcita_admin_actions() {
  if (function_exists('add_menu_page')) {
    add_menu_page(__(VCITA_WIDGET_MENU_NAME, 'vcita'),
        __(VCITA_WIDGET_MENU_NAME, 'vcita'), 'edit_posts',
        __FILE__, 'vcita_settings_menu',
        plugins_url(VCITA_WIDGET_UNIQUE_ID.'/images/settings.jpg'));
    add_action('admin_notices', 'vcita_wp_add_admin_notices');
  }
  if (function_exists('add_submenu_page') && !vcita_is_demo_user()) {
    add_submenu_page(__FILE__, __('CRM Settings', 'vcita'),
        __('CRM Settings', 'vcita'),
        'edit_posts',
        __FILE__);
    add_submenu_page(__FILE__, __('Contact Management', 'vcita'), __('Contact Management', 'vcita'), 'edit_posts', VCITA_WIDGET_UNIQUE_ID.'/vcita-contact-management.php');
    add_submenu_page(__FILE__, __('LiveSite Widget', 'vcita'), __('LiveSite Widget', 'vcita'), 'edit_posts', VCITA_WIDGET_UNIQUE_ID.'/vcita-livesite-widget-edit.php');
    add_submenu_page(__FILE__, __('Contact Form', 'vcita'), __('Contact Form', 'vcita'), 'edit_posts', VCITA_WIDGET_UNIQUE_ID.'/vcita-contact-form-edit.php');
  }
  add_submenu_page(null, '', '', 'edit_posts', VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php');
}

/**
 * Create the Main vCita Settings form content.
 *
 * The form is constructed from a list of input fields and a preview for the result
 */
 function vcita_settings_menu() {
  vcita_add_stylesheet();
  // Disconnect should change the widget values before the prepare settings method is called.
  if (isset($_POST) && isset($_POST['Submit']) && $_POST['Submit'] == 'Disconnect') {
    $vcita_widget = (array) get_option(VCITA_WIDGET_KEY);
    vcita_trash_current_page($vcita_widget);
    
    $vcita_widget = create_initial_parameters();
    $vcita_widget["dismiss"] = "true"; // Make sure the notification won't appear 
    update_option(VCITA_WIDGET_KEY, $vcita_widget);
  }

  extract(vcita_prepare_widget_settings("settings"));

  // Check the dedicated page flag - If it is on, make sure a page is available, if not - Trash the page
  if ($update_made) {
    if ($_POST['Submit'] == "Disable Page") {
      vcita_trash_current_page($vcita_widget);
        
      $vcita_widget['contact_page_active'] = 'false';
      update_option(VCITA_WIDGET_KEY, $vcita_widget);
            
    // Make sure page is live if requested to or as default
    } else if ($_POST['Submit'] == "Activate Page" || $vcita_widget['contact_page_active'] == 'true') {
      $vcita_widget = make_sure_page_published($vcita_widget);
    }
  }

  $vcita_dismissed = false;
  
  if (isset($_GET) && isset($_GET['dismiss']) && $_GET['dismiss'] == "true") {
    $vcita_widget["dismiss"] = true;
    $vcita_dismissed = true;
    update_option(VCITA_WIDGET_KEY, $vcita_widget);
  }
  
  ?>
    <script type='text/javascript'>
      jQuery(function ($) {  
        $('.widgets-holder .type')
          .hover(function(){
            var currObject = $(this);
            var info = $('#widget-info');
            
            info
              .removeClass(info.data('type'))
              .data('curr_type', currObject.data('type'))
              .addClass(currObject.data('type'));
            
            window.setTimeout(function(){
              info
                .addClass('show');
            }, 1);
          }, function() {
            $('#widget-info')
              .attr('class', ' ');
          });
      
        $('#active-engage-switch')
          .change(function(){
            if (vcita_prevent()) return;
            toggleSettingsAjax($(this), "vcita_ajax_toggle_ae");
          });
          
        $('#contact-form-switch')
          .change(function(){
            if (vcita_prevent()) return;
            toggleSettingsAjax($(this), "vcita_ajax_toggle_contact");
          });

        $('.prevent-link').click(function(e){
          if (vcita_prevent()) e.preventDefault();
        });

        var toggleSettingsAjax = function(currObject, action) {  
          $.post(ajaxurl, {action: action, activate: currObject.is(':checked')}, function(response) { });
        };
        
         $('.shortcode')
          .click(function(){
            if (vcita_prevent()) return;
            showContent($('#shortcode-template').html());  
          });
        
        $('#close-floating, #floating')
          .click(function(){
            hideContent();  
          });
        
        $('#content-holder')  
          .click(function(e){
            e.stopImmediatePropagation();
          });
        
        var showContent = function(contentToShow){
          if (contentToShow) {
            $('#content').html(contentToShow);  
            
            var contentHolder = $('#content-holder');
            var marginTop = ($(window).height() - contentHolder.outerHeight(true)) / 2;

            contentHolder.css({ 'margin-top' : marginTop });
            $('#floating').addClass('visible');
            $('#floating-holder').css({'opacity':1});
            $('#content-holder').css({'display':'block'});
          }
        };
        
        var hideContent = function(){
          $('#content').html(" ");  
            
          $('#floating').removeClass('visible');
          $('#floating-holder').css({'opacity':0});
          $('#content-holder').css({'display':'none'});
        };                  

        $('#start-login')
          .click(function(){
            var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
            var emailInput = $('#vcita-email');
            var email = $('#vcita-email').val();
            if (email == emailInput.data('watermark')) {
              email = "";
            }
            var new_location = "http://" + "<?php echo VCITA_LOGIN_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"+"&email=" + email; 
            window.location = new_location;
          });
        
        $('#switch-email')
          .click(function(){
            var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
            var new_location = "http://" + "<?php echo VCITA_CHANGE_EMAIL_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"; 
            window.location = new_location;
           });      

        $('#scheduling-settings')
          .click(function(e){
            if (vcita_prevent()) { e.preventDefault(); return; }
            var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
            var new_location = "http://" + "<?php echo VCITA_SCHEDULING_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"; 
            window.open(new_location);
          });

        $('#test-drive')
          .click(function(){
            var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
            if ($(this).data().demo) {
              var new_location = "http://" + "<?php echo VCITA_SCHEDULING_TEST_DRIVE_DEMO_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"; 
            }
            else {
              var new_location = "http://" + "<?php echo VCITA_SCHEDULING_TEST_DRIVE_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"; 
            }

            window.open(new_location, '', 'height=740, width=1024');
          });

        $('#switch-account')
          .click(function(ev){
            ev.preventDefault();
            var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
            var new_location = "http://" + "<?php echo VCITA_LOGIN_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"+"&login=true"; 
            window.location = new_location;
          });          
        
        $('#vcita-email')
          .keypress(function(e){
            if (e.keyCode == 13) {
              $('#start-login').click();
            }
          });
          
        $('a.preview')
          .bind('click', function(e){
             var link = $(e.currentTarget);
             var height = link.data().height ? link.data().height : 600;
             var width = link.data().width ? link.data().width : 600;
             var specs = 'directories=0, height=' + height + ', width=' + width + ', location=0, menubar=0, scrollbars=0, status=0, titlebar=0, toolbar=0';
             window.open(link.attr('href'), '_blank', specs);
             e.preventDefault();
           });

          function popupCenter(url, width, height, name) {
            var left = (screen.width/2)-(width/2);
            var top = (screen.height/2)-(height/2);
            return window.open(url, name, "location=0,resizable=1,scrollbars=1,width="+width+",height="+height+",left="+left+",top="+top);
          }

          jQuery("a.show-in-popup").click(function(e ){
            popupCenter(jQuery(this).attr('href'), 1100, 650, jQuery(this).data().popup_window);
            e.stopPropagation();
            e.preventDefault();
          });
                      
        var handleWatermark = function(input){
          if(input.val().trim() != "") {
            input.removeClass('vcita-watermark');            
          } else {
            input.val(input.data('watermark'));
            input.addClass('vcita-watermark');
          }
        };  

        window.vcita_prevent = function(){
          <?php if ($first_time): ?>
            showContent('Please first connect vCita with your Wordpress account by providing an Email address.');  
            return true;
          <?php else: # not first time ?>
            return false;
          <?php endif; ?>
        };
         
        $('input.watermark')
          .focus(function(){
            var input = $(this);
            if (input.data('watermark') == input.val()) {
              input.val("");
              input.removeClass('vcita-watermark');
            }
           })
           .each(function(){
             handleWatermark($(this));
           })
           .blur(function(){
             handleWatermark($(this));
           });        
      <?php 
        if (vcita_is_demo_user()) { ?>
        
        $('.gray-button-style.edit, .widgets-holder .type')
          .click(function(e){
            showContent($('#must-logged-in').html());  
            e.preventDefault();
            return false;
          });
        <?php if(get_option(VCITA_WIDGET_KEY.'init')) { ?>
        $('.vcita-wrap').append($('#settings-iframe').html())
        
        <?php 
          update_option(VCITA_WIDGET_KEY.'init', false);
        } ?>
      <?php } ?>
      });
      
    </script>
    <div class="wrap vcita-new-wrap">
      <h2 class="vcita-admin-title">
        Client management and CRM by vCita
        <a target="_blank" href="http://www.vcita.com?invite=wp-v-crm&o=wp-crm" class="vcita-logo"></a>
      </h2>

      <?php echo vcita_create_user_message($vcita_widget, $update_made); ?>
      <?php if ($vcita_dismissed) { ?>
        <div class='updated below-h2' ><p>vCita Meeting Scheduler notification has been dismissed</p></div>      
      <?php } ?>

      <div class="vcita-box vcita-settings">
        <div class="vcita-box-title">
          <p>1. Connect WordPress with vCita</p>
        </div>
        <div class="vcita-box-content">
          <p>Create a free vCita account or connect your existing account:</p>
          <?php if ($first_time):
            $user = wp_get_current_user();
            $prepop_email = $user->data->user_email;
            ?>
            <input id="vcita-email" type="text" value="<?php echo esc_attr($prepop_email); ?>" class="watermark" data-watermark="Enter Your Email"/>
            <a href="javascript:void(0)" class="button button-primary" id="start-login">Connect</a>
          <?php else: # not first time ?>
            <label class="checked" for="user-email"></label>
            <input id="vcita-email" type="text" disabled="disabled" value="<?php echo($vcita_widget["email"]) ?>"/>
            <a class="vcita-switch-account" id="switch-account" href="#">switch account</a>
          <?php endif; ?>
          <div class="clear"></div>
        </div>
      </div>

      <div class="vcita-box capture-more-leads">
        <div class="vcita-box-title">
          <p>2. Add lead capturing to your site</p>
        </div>
        <div class="vcita-box-content">
          <p>Every lead captured on your site through vCita is automatically added to your CRM</p>
          <div class="vcita-row">
            <label for="active-engage-switch" class="vcita-checkbox">
              <input type="checkbox" id="active-engage-switch" name="livesite_active" value="1"<?php echo (@$vcita_widget['engage_active'] == 'true') ? ' checked' : ''; ?> />
              LiveSite widget
            </label>
            <span class="vcita-preview">
              <?php if ($first_time): ?>
                <a class="preview" href="http://www.vcita.com/integrations/wordpress/active_engage_preview?uid=252a0a71f9a46f20&ver=2">Preview &amp; Edit</a>
              <?php else: ?>
                <a href="admin.php?page=<?php echo VCITA_WIDGET_UNIQUE_ID; ?>/vcita-livesite-widget-edit.php">Preview &amp; Edit</a>
              <?php endif; ?>
            </span>
          </div>
          <div class="vcita-row">
            <label for="contact-form-switch" class="vcita-checkbox">
              <input type="checkbox" id="contact-form-switch" name="contact_page_active" value="1"<?php echo is_page_available($vcita_widget) ? ' checked' : ''; ?> />
              Contact us page
            </label>
            <span class="vcita-preview">
              <?php if ($first_time): ?>
                <a class="preview" href="http://www.vcita.com/contact_form?v=252a0a71f9a46f20&ver=2" target="_blank">Preview &amp; Edit</a>
              <?php else: ?>
                <a href="admin.php?page=<?php echo VCITA_WIDGET_UNIQUE_ID; ?>/vcita-contact-form-edit.php">Preview &amp; Edit</a>
              <?php endif; ?>
            </span>
            <span class="vcita-grab-shortcode">
              <a class="shortcode" target="_blank">Grab Shortcode</a>
            </span>
          </div>
        </div>
      </div>

      <div class="vcita-box manage-your-clients">
        <div class="vcita-box-title">
          <p>3. Manage Clients &ndash; CRM</p>
        </div>
        <div class="vcita-box-content">
          <div class="vcita-access-client-records">
            <a href="admin.php?page=<?php echo VCITA_WIDGET_UNIQUE_ID; ?>/vcita-contact-management.php" class="button button-primary prevent-link" style="font-weight:bold;">Access Client Records</a>
          </div>
        </div>
      </div>

      <div class="vcita-left-box">
        <div class="vcita-left-box-item">
          <img class="help-icon" src="<?php echo plugins_url('images/gear.png', __FILE__); ?>" width="16" height="16" alt="" />
          <a id="scheduling-settings" class="prevent-link" href="#">Email and business settings</a>
        </div>
        <div class="vcita-left-box-item">
          <img class="help-icon" src="<?php echo plugins_url('images/gear.png', __FILE__); ?>" width="16" height="16" alt="" />
          <a href="https://www.vcita.com/my/widgets" class="prevent-link" target="_blank">Widget Settings</a>
        </div>
        <div class="vcita-left-box-item">
          <img class="help-icon" src="<?php echo plugins_url('images/gear.png', __FILE__); ?>" width="16" height="16" alt="" />
          <a class="prevent-link" href="https://www.vcita.com/settings/client_card_fields" target="_blank">CRM Settings</a>
        </div>
      </div>

      <div class="vcita-info-box">
        <a href="https://www.vcita.com/partners/web-professionals?o=WP-CRM&invite=WP-V-CRM" target="_blank" class="vcita-webpro">
          <img src="<?php echo plugins_url('/images/webpro.png', __FILE__); ?>" />
        </a>
        <!--
        <a href="#" target="_blank" class="vcita-rate-us">
          <img src="<?php echo plugins_url('/images/rate-us.png', __FILE__); ?>" />
        </a>
        -->
        <a href="http://support.vcita.com/" target="_blank" class="vcita-need-help last">
          <img src="<?php echo plugins_url('/images/need-help.png', __FILE__); ?>" />
        </a>
      </div>
    </div><!-- .wrap -->

    
    <div id="floating">
      <div id="floating-holder">
        <div id="content-holder">
          <a id="close-floating"></a>
          <div id="content">
          </div>
        </div>
      </div>
    </div>
    
    <script type="text/html" id="shortcode-template">
      <div class="short-code">
        <div>Contact Form:</div>
        <input readonly="" type="text" id="vcita_embed_widget_<?php echo $form_uid;?>" onclick="this.select();" value="[<?php echo VCITA_WIDGET_SHORTCODE; ?> type=contact width=500 height=450]">
        
        Note: Changing the height and width will affect the widgets on your website but will not affect the preview.
      </div>
    </script>
    
    <script type="text/html" id="must-logged-in">
      <div class="need-to-fill-email">
        In order to edit the widget, please fill in the email to which contact requests should be sent.
      </div>
    </script>
    
    <script type="text/html" id="vcita-video">
      <iframe allowfullscreen="true" type="text/html" frameborder="0" height="363" src="http://www.youtube.com/embed/rv-O7gxwLbk" width="600" />
    </script>
    
    <script type="text/html" id="vcita-video2">
      <iframe allowfullscreen="true" type="text/html" frameborder="0" height="363" src="http://www.youtube.com/embed/zcPpfiwE41Q" width="600" />
    </script>

    <script type="text/html" id="settings-iframe">
      <iframe src="http://<?php echo VCITA_SERVER_BASE ?>/integrations/wordpress/settings" class="hidden" width="0" height="0"/>
    </script>
  <?php 
}

/**
 * Create the vCita floatting widget Settings form content.
 *
 * This is based on Wordpress guidelines for creating a single widget.
 */
function vcita_widget_admin() {
  vcita_add_stylesheet();
  ?>
  <script type="text/javascript">
    jQuery(function ($) {  
       $('.start-login')
        .on('click', function(){
          var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
          var email = "";
          $('.vcita-email').each(function(){
            var tempMail = $(this).val();
            if (tempMail)
             email = tempMail;
            if (email == $(this).data('watermark')) {
              email = "";
            }
          });
          
          var new_location = "http://" + "<?php echo VCITA_LOGIN_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"+"&email=" + email; 
          window.location = new_location;
        });
        
      $('.switch-account')
        .on('click', function(){
          var callbackURL = "<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page='.VCITA_WIDGET_UNIQUE_ID.'/vcita-callback.php' ?>";
          var new_location = "http://" + "<?php echo VCITA_LOGIN_PATH.'?callback=' ?>" + encodeURIComponent(callbackURL) + "&invite="+"<?php echo VCITA_WIDGET_INVITE_CODE ?>"+"&lang="+"<?php echo get_locale() ?>"+"&login=true"; 
          window.location = new_location;
         });          
           
      $('.vcita-email')
        .on('keypress', function(e){
          if (e.keyCode == 13) {
            $('.start-login').click();
          }
        });
        
      $('a.preview')
        .bind('click', function(e){
           var link = $(e.currentTarget);
           var height = link.data().height ? link.data().height : 600;
           var width = link.data().width ? link.data().width : 600;
           var specs = 'directories=0, height=' + height + ', width=' + width + ', location=0, menubar=0, scrollbars=0, status=0, titlebar=0, toolbar=0';
           window.open(link.attr('href'), '_blank', specs);
           e.preventDefault();
         });
         
    });
  </script>
  <div id="vcita_config" dir="ltr">
    <?php if(vcita_is_demo_user()) {?>
      <h3>Contact requests will be sent to this email:</h3>
      <input class="vcita-email" type="text" value=""/>
      <a href="javascript:void(0)" class="gray-button-style account start-login"><span></span>OK</a>
    <?php } 
      else { 
      $vcita_widget = (array) get_option(VCITA_WIDGET_KEY);  
      ?>
      <h3>Contact requests will be sent to this email:</h3>
      <label class="checked" for="user-email"></label>
      <input class="vcita-email" type="text" disabled="disabled" value="<?php echo($vcita_widget["email"]) ?>"/>
      <br><br>
      <a href="javascript:void(0)" class="gray-button-style account switch-account" ><span></span>Change Email</a>
      <br><br>      
      <a class="gray-button-style edit" href="<?php echo $url = get_admin_url('', '', 'admin') . 'admin.php?page=' . VCITA_WIDGET_UNIQUE_ID . '/vcita-sidebar-edit.php' ?>"><span></span>Edit</a>
      <br><br>
      <a class="gray-button-style preview" href="http://<?php echo VCITA_SERVER_BASE ?>/contact_widget?v=<?php echo vcita_get_uid() ?>&ver=2" data-width="200" data-height="500"><span></span>Preview</a>
    <?php } ?>      
  </div>

  <?php
}

/**
 * Update the settings link to point to the correct location
 */
function vcita_add_settings_link($links, $file) {
  if ($file == plugin_basename(VCITA_WIDGET_UNIQUE_LOCATION)) {
    $settings_link = '<a href="' . admin_url("plugins.php?page=".plugin_basename(__FILE__)) . '">Settings</a>';
    array_unshift($links, $settings_link);
  }

  return $links;
 }
 
/**
 * Create the message which will be displayed to the user after performing an update to the widget settings.
 * The message is created according to if an error had happen and if the user had finished the registration or not.
 */
function vcita_create_user_message($vcita_widget, $update_made) {

  if (!empty($vcita_widget['uid'])) {

    // If update wasn't made, keep the message without info about the last change
    if ($update_made) {
      if ($_POST['Submit'] == "Save Settings") {
        $message .= "<div>Account <b>".$vcita_widget['email']."</b> Saved.</div><br> ";
      } else {
        $message = "<b>Changes saved</b>";
      }
    } else {
      $message = "";
    }

    $message_type = "updated below-h2"; // Wordpress classes for showing a notification box
    
    if (!$vcita_widget['confirmed']) {
      if ($update_made) {
        $message .= "<br>";
      }
      
      $message .= "<div style='overflow:hidden'>";
      $prefix = "";

      if (!empty($vcita_widget['confirmation_token'])) {
        $message .= "<div style='float:left;'>Please <b>".vcita_create_link('configure your contact and meeting preferences', 'users/confirmation', 'confirmation_token='.$vcita_widget['confirmation_token'], array('style' => 'text-decoration:underline;'))."</b> or </div>";
      } else {
        $prefix = "Please";
      }
      
      $message .= "<div style='float:left;display:block;'>".$prefix."&nbsp;follow instructions sent to your email.</div>";
      
      if (empty($vcita_widget['confirmation_token'])) {
        $message .= "&nbsp;".vcita_create_link("Send email again", 'user/send_confirmation', 'email='.$vcita_widget['email'], array('style' => 'font-weight:bold;'));
      }
      
      $message .= "</div>";
    }

  } elseif (!empty($vcita_widget['last_error'])) {
    $message = "<b>".$vcita_widget['last_error']."</b>";
    $message_type = "error below-h2";
  }

  if (empty($message)) {
    return "";
  } else {
    return "<div class='".$message_type."' style='padding:5px;text-align:left;'>".$message."</div>";
  }
}
