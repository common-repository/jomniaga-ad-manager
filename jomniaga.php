<?php
/*
  Plugin Name: Ashadee Ad Manager
  Plugin URI: http://www.ashadee.com
  Description: The Ashadee plugin for WordPress allows Malaysian bloggers to automatically add related ads from the Ashadee affiliate network. Based on the keywords you use in your content, ads are highly targeted and varies according to the page or post.
  Version: 1.0.12
  Author: Ashadee
  Author URI: http://www.ashadee.com
  License: GPL2
 */

$jom = new WPJomniaga();

class WPJomniaga {

    var $jom_data = array();
    var $convert_limit_perpage = 1;
    var $keyword_limit_perpage = 1;
    var $convert_limit_percomment = 1;
    var $keyword_limit_percomment = 1;
    var $affiliate = '';
    var $tid = '';

    function WPJomniaga()
    {
        register_activation_hook(__FILE__, array($this, 'jom_install'));
        if (is_admin()) { // admin actions
            wp_enqueue_script('jquery');
            wp_enqueue_script('jomniaga-js', plugins_url('jomniaga/js/script.js'));

            add_action('admin_init', array($this, 'jom_register_settings'));
            add_action('admin_menu', array($this, 'jom_action_admin_menu'));
        } else {
            add_filter('the_content', array($this, 'the_content'));
            add_filter('comment_text', array($this, 'comment_text'));
            add_filter('the_excerpt', array($this, 'the_excerpt'));
            $this->load_data();
        }
    }

    function jom_register_settings()
    {
        register_setting('jom-settings-group', 'wpjomniaga_username');
        register_setting('jom-settings-group', 'wpjomniaga_tracking_code');
        register_setting('jom-settings-group', 'wpjomniaga_category');

        register_setting('jom-settings-group', 'wpjomniaga_related_show');
        register_setting('jom-settings-group', 'wpjomniaga_related_title');
        register_setting('jom-settings-group', 'wpjomniaga_related_number');

        register_setting('jom-settings-group', 'wpjomniaga_convert_home');
        register_setting('jom-settings-group', 'wpjomniaga_convert_single_post');
        register_setting('jom-settings-group', 'wpjomniaga_convert_single_page');
        register_setting('jom-settings-group', 'wpjomniaga_convert_comment');
        register_setting('jom-settings-group', 'wpjomniaga_convert_archive');

        register_setting('jom-settings-group', 'wpjomniaga_convert_limit_perpage');
        register_setting('jom-settings-group', 'wpjomniaga_keyword_limit_perpage');

        register_setting('jom-settings-group', 'wpjomniaga_convert_limit_percomment');
        register_setting('jom-settings-group', 'wpjomniaga_keyword_limit_percomment');

        register_setting('jom-settings-group', 'wpjomniaga_link_new_window');
        register_setting('jom-settings-group', 'wpjomniaga_link_no_follow');
    }

    function jom_action_admin_menu()
    {
        add_options_page("Ashadee Plugin Settings", "Ashadee", 1, "ashadee", array($this, "jom_settings"));
    }

    function jom_install()
    {
        //set initial value
        update_option('wpjomniaga_username', '');
        update_option('wpjomniaga_tracking_code', '');
        update_option('wpjomniaga_category', '');

        update_option('wpjomniaga_related_show', '0');
        update_option('wpjomniaga_related_title', 'Related Sites');
        update_option('wpjomniaga_related_number', '3');

        update_option('wpjomniaga_convert_home', '1');
        update_option('wpjomniaga_convert_single_post', '1');
        update_option('wpjomniaga_convert_single_page', '1');
        update_option('wpjomniaga_convert_comment', '1');
        update_option('wpjomniaga_convert_archive', '1');

        update_option('wpjomniaga_convert_limit_perpage', '3');
        update_option('wpjomniaga_keyword_limit_perpage', '3');

        update_option('wpjomniaga_convert_limit_percomment', '2');
        update_option('wpjomniaga_keyword_limit_percomment', '1');

        update_option('wpjomniaga_link_new_window', '1');
        update_option('wpjomniaga_link_no_follow', '1');
    }

    function load_data()
    {
        $dir = plugin_dir_path(__FILE__);
        $filename = $dir . 'data.json';
        $handle = fopen($filename, "r");
        $contents = fread($handle, filesize($filename));
        fclose($handle);
        $this->jom_data = json_decode($contents, true);

        //filter data for selected category


        if (get_option('wpjomniaga_category') > 0) {
            $this->jom_data = $this->jom_data[get_option('wpjomniaga_category')];
        } else {
            $new = array();
            foreach ($this->jom_data as $row) {
                $new = array_merge($new, $row);
            }
            $this->jom_data = $new;
        }

        $this->affiliate = get_option('wpjomniaga_username');
        $this->tid = (trim(get_option('wpjomniaga_tracking_code')) != '') ? '?tid=' . get_option('wpjomniaga_tracking_code') : '';
        $this->convert_limit_perpage = get_option('wpjomniaga_convert_limit_perpage');
        $this->keyword_limit_perpage = get_option('wpjomniaga_keyword_limit_perpage');

        $this->convert_limit_percomment = (int) get_option('wpjomniaga_convert_limit_percomment');
        $this->keyword_limit_percomment = (int) get_option('wpjomniaga_keyword_limit_percomment');
    }

    function search_sub_array(Array $array, $key, $value)
    {
        foreach ($array as $subarray) {
            if (isset($subarray[$key]) && trim($subarray[$key]) == $value) {
                $result[] = $subarray;
            }
        }
        return $result;
    }

    function update_start($link_matches, $start, $length)
    {
        foreach ($link_matches as $key => $value) {
            if ($value['start'] > $start) {
                $link_matches[$key]['start'] = $value['start'] + $length;
            }
        }
        return $link_matches;
    }

    function no_overlap($value, $link_matches = array())
    {
        foreach ($link_matches as $val) {
            if (($value >= $val['start']) AND ($value <= ($val['start'] + strlen($val['keyword'])))) {
                return false;
                break;
            }
        }
        return true;
    }

    function is_valid($value, $skip_filters = array())
    {
        foreach ($skip_filters as $val) {
            if (($value > $val['start']) AND ($value < $val['end'])) {
                return false;
                break;
            }
        }
        return true;
    }

    function skip_filters($content, $open_tag, $close_tag)
    {
        $i = 0;
        $open_tag_pos = true;
        $result = array();
        while ($open_tag_pos) {
            $open_tag_pos = stripos($content, $open_tag, $i);
            if ($open_tag_pos) {
                $close_tag_pos = stripos($content, $close_tag, $open_tag_pos + 1);
                $i = $close_tag_pos + 1;
                $result[] = array('start' => $open_tag_pos, 'end' => $close_tag_pos + strlen($close_tag));
            }
        }

        return $result;
    }

    function jom_settings()
    {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>

        <div class="wrap">
            <form id="jomniagaform" method="post" action="options.php">
        <?php settings_fields('jom-settings-group'); ?>
                <table width="800" cellspacing="0" cellpadding="5">
                    <tr>
                        <td valign="top">
                            <h1>Ashadee Plugin Settings</h1>
                            <p>The Ashadee plugin for WordPress plugin allows you to automatically display related affiliate offers from Ashadee.com. If you don't have a Ashadee account yet you can <a href="http://www.ashadee.com" target="_blank">signup free here</a>. </p>
                            <h3>Account Settings</h3>
                            <p>Enter your Ashadee affiliate username here to earn commissions. Enter a tracking code to identify sales that are generated from this site. Select a specific category to only display ads from that category</p>
                            <table width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="23%"><strong>Ashadee Username:</strong></td>
                                    <td >
                                        <input type="text" name="wpjomniaga_username" id="wpjomniaga_username" value="<?php echo get_option('wpjomniaga_username'); ?>"/>
                                        (Example: myname)
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Tracking Code:</strong></td>
                                    <td>
                                        <input maxlength="8" name="wpjomniaga_tracking_code" type="text" id="wpjomniaga_tracking_code" value="<?php echo get_option('wpjomniaga_tracking_code'); ?>" />
                                        (Max 8 characters, alphabets and numbers only)
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Products Category:</strong></td>
                                    <td>
                                        <select name="wpjomniaga_category" class="select tooltip-south" title="Select the niche for your site. All displayed products and links will be from this niche only." id="wpjomniaga_category">
                                            <option <?php echo (get_option('wpjomniaga_category') == '0') ? 'selected="selected"' : ''; ?>   value="0">Any Category</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '1') ? 'selected="selected"' : ''; ?>   value="1">Beauty & Health</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '2') ? 'selected="selected"' : ''; ?>   value="2">Clothing & Fashion</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '3') ? 'selected="selected"' : ''; ?>   value="3">Love & Relationships</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '4') ? 'selected="selected"' : ''; ?>   value="4">Children & Family</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '5') ? 'selected="selected"' : ''; ?>   value="5">Cooking & Food</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '6') ? 'selected="selected"' : ''; ?>   value="6">Education & Employment</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '7') ? 'selected="selected"' : ''; ?>   value="7">Language & Learning</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '8') ? 'selected="selected"' : ''; ?>   value="8">Music & Arts</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '9') ? 'selected="selected"' : ''; ?>   value="9">Computers & Gadgets</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '10') ? 'selected="selected"' : ''; ?>   value="10">Cars & Automobiles</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '11') ? 'selected="selected"' : ''; ?>   value="11">Business & Finance</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '12') ? 'selected="selected"' : ''; ?>   value="12">E-commerce & Online Marketing</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '13') ? 'selected="selected"' : ''; ?>   value="13">Religion & Spirituality</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '14') ? 'selected="selected"' : ''; ?>   value="14">Crafts & Hobbies</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '15') ? 'selected="selected"' : ''; ?>   value="15">Sports & Recreation</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '16') ? 'selected="selected"' : ''; ?>   value="16">General e-Shopping Mall</option>
                                            <option <?php echo (get_option('wpjomniaga_category') == '17') ? 'selected="selected"' : ''; ?>   value="17">Others</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <h3>Related Site Settings</h3>
                            <p>You can automatically display a list of &quot;Related Sites&quot; under each blog post that promotes products from Ashadee.com. Select the category to display more accurate recommendations.</p>
                            <table width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="23%"><strong>Related Sites:</strong></td>
                                    <td >
                                        <input type="checkbox" name="wpjomniaga_related_show" id="wpjomniaga_related_show" value="1" <?php echo (get_option('wpjomniaga_related_show') == '1') ? 'checked="checked"' : ''; ?>  />
                                        <label for="wpjomniaga_related_show">
                                            Display &quot;Related Sites&quot; automatically after each blog post.
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <td><strong>Related Sites Title:</strong></td>
                                    <td><input name="wpjomniaga_related_title" type="text" id="wpjomniaga_related_title" value="<?php echo get_option('wpjomniaga_related_title'); ?>" /></td>
                                </tr>
                                <tr>
                                    <td><strong>Number of Sites:</strong></td>
                                    <td>
                                        <input type="radio" name="wpjomniaga_related_number" id="rbNumberOfSites1" value="3"  <?php echo (get_option('wpjomniaga_related_number') == '3') ? 'checked="checked"' : ''; ?> />
                                        <label for="rbNumberOfSites1">
                                            3 Sites
                                        </label>
                                        <input type="radio" name="wpjomniaga_related_number" id="rbNumberOfSites2" value="5" <?php echo (get_option('wpjomniaga_related_number') == '5') ? 'checked="checked"' : ''; ?> />
                                        <label for="rbNumberOfSites2">
                                            5 Sites
                                        </label>
                                        <input type="radio" name="wpjomniaga_related_number" id="rbNumberOfSites3" value="-1" <?php echo (get_option('wpjomniaga_related_number') == '-1') ? 'checked="checked"' : ''; ?> />
                                        <label for="rbNumberOfSites3">
                                            Maximum
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <h3>Keyword  Settings</h3>
                            <p>Select which content type you want to use our keyword conversion feature on, as well as the maximum links per page. You can also set the limit for each keyword and auto-assign a campaign.</p>
                            <table width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="23%"><strong>Auto Conversion:</strong></td>
                                    <td>
                                        <input id="wpjomniaga_convert_home" value="1" name="wpjomniaga_convert_home" <?php echo (get_option('wpjomniaga_convert_home') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_convert_home">Home page</label>
                                        <input id="wpjomniaga_convert_single_post" value="1" name="wpjomniaga_convert_single_post" <?php echo (get_option('wpjomniaga_convert_single_post') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_convert_single_post">Single post</label>
                                        <input id="wpjomniaga_convert_single_page" value="1" name="wpjomniaga_convert_single_page" <?php echo (get_option('wpjomniaga_convert_single_page') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_convert_single_page">Single page</label>
                                        <input id="wpjomniaga_convert_comment" value="1" name="wpjomniaga_convert_comment" <?php echo (get_option('wpjomniaga_convert_comment') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_convert_comment">Comments</label>
                                        <input id="wpjomniaga_convert_archive" value="1" name="wpjomniaga_convert_archive" <?php echo (get_option('wpjomniaga_convert_archive') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_convert_archive">Archives</label>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Post / Page Limit:</strong></td>
                                    <td>
                                        Maximum of
                                        <input name="wpjomniaga_convert_limit_perpage" type="text" id="wpjomniaga_convert_limit_perpage" value="<?php echo get_option('wpjomniaga_convert_limit_perpage'); ?>" size="3" />
                                        links per page and <input name="wpjomniaga_keyword_limit_perpage" type="text" id="wpjomniaga_keyword_limit_perpage" value="<?php echo get_option('wpjomniaga_keyword_limit_perpage'); ?>" size="3" />
                                        links per keyword
                                    </td>
                                </tr>

                                <tr>
                                    <td><strong>Comment Limit:</strong></td>
                                    <td>
                                        Maximum of
                                        <input name="wpjomniaga_convert_limit_percomment" type="text" id="wpjomniaga_convert_limit_percomment" value="<?php echo get_option('wpjomniaga_convert_limit_percomment'); ?>" size="3" />
                                        links per comment and <input name="wpjomniaga_keyword_limit_percomment" type="text" id="wpjomniaga_keyword_limit_percomment" value="<?php echo get_option('wpjomniaga_keyword_limit_percomment'); ?>" size="3" />
                                        links per keyword
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Link Settings:</strong></td>

                                    <td>
                                        <input id="wpjomniaga_link_new_window" value="1" name="wpjomniaga_link_new_window" <?php echo (get_option('wpjomniaga_link_new_window') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_link_new_window">Open links in new window </label>
                                        <input id="wpjomniaga_link_no_follow" value="1" name="wpjomniaga_link_no_follow" <?php echo (get_option('wpjomniaga_link_no_follow') == '1') ? 'checked="checked"' : ''; ?> type="checkbox" />
                                        <label for="wpjomniaga_link_no_follow">Add no-follow to links</label>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <input type="submit" class="button-secondary action" name="btnSubmit" id="btnSubmit" value="<?php _e('Save Changes') ?>" />
                            </p>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }

    function the_content($content)
    {
        $related_page = '';
        if ((is_home() AND get_option('wpjomniaga_convert_home')) || ( is_archive() AND get_option('wpjomniaga_convert_archive')) || (is_single() AND get_option('wpjomniaga_convert_single_post')) || (is_page() AND get_option('wpjomniaga_convert_single_page'))) {

            $this->convert_limit_perpage = (int) get_option('wpjomniaga_convert_limit_perpage');

            if (count($this->jom_data) > 0) {


                shuffle($this->jom_data);

                $skip_filters = array();
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<a', '</a>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<h1', '</h1>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<h2', '</h2>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<h3', '</h3>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<h4', '</h4>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<h5', '</h5>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<code', '</code>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<object', '</object>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<script', '</script>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<embed', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<img', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '[tube]', '[/tube]'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<iframe', '</iframe>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($content, '<input', '>'));


                //search keyword in post
                //make sure keywords is unique
                $keywords = array();
                foreach ($this->jom_data as $val) {
                    if (trim($val['keyword']) != '') {
                        $keywords[] = trim($val['keyword']);
                    }
                }
                $keywords = array_unique($keywords);

                //set keyword data
                $kw_data = array();
                $i = 0;
                foreach ($keywords as $key) {
                    $kw_data[$i]['data'] = $this->search_sub_array($this->jom_data, 'keyword', $key);
                    $kw_data[$i]['keyword'] = $key;
                    $i++;
                }
                $keywords = $kw_data;

                $keyword_matches = array();
                $related_matches = array();
                foreach ($keywords as $keyword) {
                    if ($keyword['keyword'] != '') {
                        $pattern = '%\b' . $keyword['keyword'] . '\b%i';
                        $matches = array();
                        $matches_final = array();
                        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches[0]) > 0) {
                            $i = 0;
                            foreach ($matches[0] as $match) {
                                //check if matches is widthin skip_filters
                                if ($this->is_valid($match[1], $skip_filters)) {
                                    //check if keyword postion already in the list to prevent overlap keyword
                                    if ($this->no_overlap($match[1], $keyword_matches)) {
                                        $match_data = array();
                                        $match_data['start'] = $match[1];
                                        $match_data['keyword'] = $match[0];
                                        $total_jomniaga_url = count($keyword['data']);
                                        $match_data['jomniaga_url'] = $keyword['data'][$i]['jomniaga_url'];
                                        $matches_final[] = $match_data;
                                        $i++;
                                        if ($i > $total_jomniaga_url - 1) {
                                            $i = 0;
                                        }
                                    }
                                }

                                //Process related page
                                $result = $this->search_sub_array($related_matches, 'keyword', $keyword['keyword']);
                                if (count($result) == 0) {
                                    $related_matches = array_merge($related_matches, array($keyword));
                                }
                            }



                            //shuffle keyword matches to make random choices
                            shuffle($matches_final);

                            //Filter keyword limit per page
                            array_splice($matches_final, $this->keyword_limit_perpage);

                            //combine keyword
                            $keyword_matches = array_merge($keyword_matches, $matches_final);
                            shuffle($keyword_matches);
                        }
                    }
                }

                $open_new_window = get_option('wpjomniaga_link_new_window') ? ' target="_blank" ' : '';
                $no_follow = get_option('wpjomniaga_link_no_follow') ? ' rel="nofollow" ' : '';
                foreach ($keyword_matches as $key => $value) {
                    $link = '<a ' . $open_new_window . $no_follow . ' href="' . $value['jomniaga_url'] . $this->affiliate . $this->tid . '">' . $value['keyword'] . '</a>';
                    $content = substr_replace($content, $link, $keyword_matches[$key]['start'], strlen($value['keyword']));
                    $keyword_matches = $this->update_start($keyword_matches, $keyword_matches[$key]['start'], strlen($link) - strlen($value['keyword']));
                    $this->convert_limit_perpage--;
                    if ($this->convert_limit_perpage <= 0)
                        break;
                }
            }




            //related site

            if (get_option('wpjomniaga_related_show') AND ( is_archive() || is_single() || is_page() )) {


                foreach ($related_matches as $related) {
                    //$related_pages = $related['data'];
                    foreach ($related['data'] as $rd) {
                        $related_pages[] = array(
                            'site_name' => $rd['site_name'],
                            'jomniaga_url' => $rd['jomniaga_url']
                        );
                    }
                }
                if (count($related_pages) > 0) {
                    $related_pages = array_map("unserialize", array_unique(array_map("serialize", $related_pages)));
                    shuffle($related_pages);
                    $related_page = '<div id="jomniaga_related_site">';
                    $related_page .= '<h2>' . get_option('wpjomniaga_related_title') . '</h2>';
                    $related_page .= '<ul>';
                    $i = 1;
                    foreach ($related_pages as $row) {
                        $related_page .= '<li><a href="' . $row['jomniaga_url'] . $this->affiliate . $this->tid . '" target="_blank">' . $row['site_name'] . '</a></li>';
                        if (((int) get_option('wpjomniaga_related_number') != -1) && ((int) get_option('wpjomniaga_related_number') <= $i)) {
                            break;
                        }
                        $i++;
                    }
                    $related_page .= '</ul>';
                    $related_page .= '</div>';
                }
            }
        }
        return $content . $related_page;
    }

    function the_excerpt($excerpt)
    {
        if ((is_home() AND get_option('wpjomniaga_convert_home')) || ( is_archive() AND get_option('wpjomniaga_convert_archive')) || (is_single() AND get_option('wpjomniaga_convert_single_post')) || (is_page() AND get_option('wpjomniaga_convert_single_page'))) {

            $this->convert_limit_perpage = (int) get_option('wpjomniaga_convert_limit_perpage');

            if (count($this->jom_data) > 0) {


                shuffle($this->jom_data);

                $skip_filters = array();
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<a', '</a>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<h1', '</h1>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<h2', '</h2>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<h3', '</h3>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<h4', '</h4>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<h5', '</h5>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<code', '</code>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<object', '</object>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<script', '</script>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<embed', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<img', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '[tube]', '[/tube]'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<iframe', '</iframe>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($excerpt, '<input', '>'));


                //search keyword in post
                //make sure keywords is unique
                $keywords = array();
                foreach ($this->jom_data as $val) {
                    if (trim($val['keyword']) != '') {
                        $keywords[] = trim($val['keyword']);
                    }
                }
                $keywords = array_unique($keywords);

                //set keyword data
                $kw_data = array();
                $i = 0;
                foreach ($keywords as $key) {
                    $kw_data[$i]['data'] = $this->search_sub_array($this->jom_data, 'keyword', $key);
                    $kw_data[$i]['keyword'] = $key;
                    $i++;
                }
                $keywords = $kw_data;

                $keyword_matches = array();
                foreach ($keywords as $keyword) {
                    if ($keyword['keyword'] != '') {
                        $pattern = '%\b' . $keyword['keyword'] . '\b%i';
                        $matches = array();
                        $matches_final = array();
                        preg_match_all($pattern, $excerpt, $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches[0]) > 0) {
                            $i = 0;
                            foreach ($matches[0] as $match) {
                                //check if matches is widthin skip_filters
                                if ($this->is_valid($match[1], $skip_filters)) {
                                    //check if keyword postion already in the list to prevent overlap keyword
                                    if ($this->no_overlap($match[1], $keyword_matches)) {
                                        $match_data = array();
                                        $match_data['start'] = $match[1];
                                        $match_data['keyword'] = $match[0];
                                        $total_jomniaga_url = count($keyword['data']);
                                        $match_data['jomniaga_url'] = $keyword['data'][$i]['jomniaga_url'];
                                        $matches_final[] = $match_data;
                                        $i++;
                                        if ($i > $total_jomniaga_url - 1) {
                                            $i = 0;
                                        }
                                    }
                                }
                            }



                            //shuffle keyword matches to make random choices
                            shuffle($matches_final);

                            //Filter keyword limit per page
                            array_splice($matches_final, $this->keyword_limit_perpage);

                            //combine keyword
                            $keyword_matches = array_merge($keyword_matches, $matches_final);
                            shuffle($keyword_matches);
                        }
                    }
                }

                $open_new_window = get_option('wpjomniaga_link_new_window') ? ' target="_blank" ' : '';
                $no_follow = get_option('wpjomniaga_link_no_follow') ? ' rel="nofollow" ' : '';
                foreach ($keyword_matches as $key => $value) {
                    $link = '<a ' . $open_new_window . $no_follow . ' href="' . $value['jomniaga_url'] . $this->affiliate . $this->tid . '">' . $value['keyword'] . '</a>';
                    $excerpt = substr_replace($excerpt, $link, $keyword_matches[$key]['start'], strlen($value['keyword']));
                    $keyword_matches = $this->update_start($keyword_matches, $keyword_matches[$key]['start'], strlen($link) - strlen($value['keyword']));
                    $this->convert_limit_perpage--;
                    if ($this->convert_limit_perpage <= 0)
                        break;
                }
            }
        }
        return $excerpt;
    }

    function comment_text($comments)
    {
        if (get_option('wpjomniaga_convert_comment') AND ((is_home() AND get_option('wpjomniaga_convert_home')) || ( is_archive() AND get_option('wpjomniaga_convert_archive')) || (is_single() AND get_option('wpjomniaga_convert_single_post')) || (is_page() AND get_option('wpjomniaga_convert_single_page')))) {



            if (count($this->jom_data) > 0) {


                shuffle($this->jom_data);

                $skip_filters = array();
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<a', '</a>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<h1', '</h1>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<h2', '</h2>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<h3', '</h3>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<h4', '</h4>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<h5', '</h5>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<code', '</code>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<object', '</object>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<script', '</script>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<embed', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<img', '>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '[tube]', '[/tube]'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<iframe', '</iframe>'));
                $skip_filters = array_merge($skip_filters, $this->skip_filters($comments, '<input', '>'));


                //search keyword in post
                //make sure keywords is unique
                $keywords = array();
                foreach ($this->jom_data as $val) {
                    if (trim($val['keyword']) != '') {
                        $keywords[] = trim($val['keyword']);
                    }
                }
                $keywords = array_unique($keywords);

                //set keyword data
                $kw_data = array();
                $i = 0;
                foreach ($keywords as $key) {
                    $kw_data[$i]['data'] = $this->search_sub_array($this->jom_data, 'keyword', $key);
                    $kw_data[$i]['keyword'] = $key;
                    $i++;
                }
                $keywords = $kw_data;

                $keyword_matches = array();
                foreach ($keywords as $keyword) {
                    if ($keyword['keyword'] != '') {
                        $pattern = '%\b' . $keyword['keyword'] . '\b%i';
                        $matches = array();
                        $matches_final = array();
                        preg_match_all($pattern, $comments, $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches[0]) > 0) {
                            $i = 0;
                            foreach ($matches[0] as $match) {
                                //check if matches is widthin skip_filters
                                if ($this->is_valid($match[1], $skip_filters)) {
                                    //check if keyword postion already in the list to prevent overlap keyword
                                    if ($this->no_overlap($match[1], $keyword_matches)) {
                                        $match_data = array();
                                        $match_data['start'] = $match[1];
                                        $match_data['keyword'] = $match[0];
                                        $total_jomniaga_url = count($keyword['data']);
                                        $match_data['jomniaga_url'] = $keyword['data'][$i]['jomniaga_url'];
                                        $matches_final[] = $match_data;
                                        $i++;
                                        if ($i > $total_jomniaga_url - 1) {
                                            $i = 0;
                                        }
                                    }
                                }
                            }



                            //shuffle keyword matches to make random choices
                            shuffle($matches_final);

                            //Filter keyword limit per page
                            array_splice($matches_final, $this->keyword_limit_percomment);

                            //combine keyword
                            $keyword_matches = array_merge($keyword_matches, $matches_final);
                            shuffle($keyword_matches);
                        }
                    }
                }

                $open_new_window = get_option('wpjomniaga_link_new_window') ? ' target="_blank" ' : '';
                $no_follow = get_option('wpjomniaga_link_no_follow') ? ' rel="nofollow" ' : '';
                foreach ($keyword_matches as $key => $value) {
                    $link = '<a ' . $open_new_window . $no_follow . ' href="' . $value['jomniaga_url'] . $this->affiliate . $this->tid . '">' . $value['keyword'] . '</a>';
                    $comments = substr_replace($comments, $link, $keyword_matches[$key]['start'], strlen($value['keyword']));
                    $keyword_matches = $this->update_start($keyword_matches, $keyword_matches[$key]['start'], strlen($link) - strlen($value['keyword']));
                    $this->convert_limit_percomment--;
                    if ($this->convert_limit_percomment <= 0)
                        break;
                }
            }
        }
        return $comments;
    }

}

