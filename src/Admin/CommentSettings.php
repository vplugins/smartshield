<?php
namespace SmartShield\Admin;

class CommentSettings {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'smart_shield_comment_group', 'ss_comment_enabled' );
        register_setting( 'smart_shield_comment_group', 'ss_comment_span_save_for_review' );
    }

    public function create_page() {
        ?>
        <div class="wrap">
            <h1>Comment Settings</h1>
            <p>Configure the comment spam protection settings.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'smart_shield_comment_group' ); ?>
                <?php do_settings_sections( 'smart_shield_comment_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enable Comment Spam Protection</th>
                        <td>
                            <input type="checkbox" name="ss_comment_enabled" value="1" <?php checked( get_option( 'ss_comment_enabled' ), 1 ); ?> />
                            <p class="description">Enable this to protect your site from comment spam.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Save Comments for Review</th>
                        <td>
                            <input type="checkbox" name="ss_comment_span_save_for_review" value="1" <?php checked( get_option( 'ss_comment_span_save_for_review' ), 1 ); ?> />
                            <p class="description">Enable this to save comments for review. Otherwise, comments will be automatically rejected.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
} 