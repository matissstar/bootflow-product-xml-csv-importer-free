<?php
/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    Bfpi
 * @subpackage Bfpi/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define the internationalization functionality.
 * Supports admin language override via user meta.
 */
class Bfpi_i18n {

    /**
     * Supported locales with their native names.
     *
     * @var array
     */
    private static $supported_locales = array(
        'en_US' => 'English',
        'lv'    => 'Latviešu',
        'es_ES' => 'Español',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'pt_BR' => 'Português (BR)',
        'ja'    => '日本語',
        'it_IT' => 'Italiano',
        'nl_NL' => 'Nederlands',
        'ru_RU' => 'Русский',
        'zh_CN' => '简体中文',
        'pl_PL' => 'Polski',
        'tr_TR' => 'Türkçe',
        'sv_SE' => 'Svenska',
        'id_ID' => 'Indonesia',
        'ar'    => 'العربية',
    );

    /**
     * Get supported locales.
     *
     * @return array
     */
    public static function get_supported_locales() {
        return self::$supported_locales;
    }

    /**
     * Get the active locale for admin UI.
     * Priority: user override > WP locale > en_US
     *
     * @return string
     */
    public static function get_admin_locale() {
        $user_locale = get_user_meta(get_current_user_id(), 'bfpi_admin_language', true);
        
        if ($user_locale && $user_locale !== 'auto' && array_key_exists($user_locale, self::$supported_locales)) {
            return $user_locale;
        }
        
        // Use WP locale
        $wp_locale = get_locale();
        if (array_key_exists($wp_locale, self::$supported_locales)) {
            return $wp_locale;
        }
        
        // Fallback to English
        return 'en_US';
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        // WP.org compliance: text domain must match plugin slug
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for development/non-WP.org translation loading
        load_plugin_textdomain(
            'bootflow-product-xml-csv-importer',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    /**
     * Reload textdomain with user's preferred locale.
     * Called on admin_init when user is already authenticated.
     *
     * @since    1.0.0
     */
    public function reload_textdomain_for_user() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $admin_locale = self::get_admin_locale();
        if (!$admin_locale || $admin_locale === 'en_US') {
            // English = no .mo needed, unload any loaded translation
            unload_textdomain('bootflow-product-xml-csv-importer');
            return;
        }

        // Check if the desired locale differs from what WP loaded
        $wp_locale = determine_locale();
        if ($admin_locale === $wp_locale) {
            return; // Already correct
        }

        // Unload current and load the user's preferred .mo
        unload_textdomain('bootflow-product-xml-csv-importer');
        $mofile = BFPI_PLUGIN_DIR . 'languages/bootflow-product-xml-csv-importer-' . $admin_locale . '.mo';
        if (file_exists($mofile)) {
            load_textdomain('bootflow-product-xml-csv-importer', $mofile);
        }
    }

    /**
     * Handle AJAX language switch.
     */
    public static function ajax_switch_language() {
        check_ajax_referer('bfpi_switch_language', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $locale = sanitize_text_field(wp_unslash($_POST['locale'] ?? ''));
        
        if ($locale !== 'auto' && !array_key_exists($locale, self::$supported_locales)) {
            wp_send_json_error('Invalid locale');
        }
        
        update_user_meta(get_current_user_id(), 'bfpi_admin_language', $locale);
        wp_send_json_success(array('locale' => $locale));
    }

    /**
     * Localize a date string — translate English month names to admin locale.
     * Use this instead of date_i18n() for plugin pages when override locale is active.
     *
     * @param string $format    PHP date format
     * @param int    $timestamp Unix timestamp
     * @return string Localized date string
     */
    public static function localize_date($format, $timestamp) {
        $date_str = date_i18n($format, $timestamp);
        
        $admin_locale = self::get_admin_locale();
        if (!$admin_locale || $admin_locale === 'en_US') {
            return $date_str;
        }

        // Month translations per locale
        $months = array(
            'lv' => array(
                'January' => 'Janvāris', 'February' => 'Februāris', 'March' => 'Marts',
                'April' => 'Aprīlis', 'May' => 'Maijs', 'June' => 'Jūnijs',
                'July' => 'Jūlijs', 'August' => 'Augusts', 'September' => 'Septembris',
                'October' => 'Oktobris', 'November' => 'Novembris', 'December' => 'Decembris',
                'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
                'Jun' => 'Jūn', 'Jul' => 'Jūl', 'Aug' => 'Aug', 'Sep' => 'Sep',
                'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Dec',
            ),
            'es_ES' => array(
                'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre',
            ),
            'de_DE' => array(
                'January' => 'Januar', 'February' => 'Februar', 'March' => 'März',
                'April' => 'April', 'May' => 'Mai', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'August', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'Dezember',
            ),
            'fr_FR' => array(
                'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
                'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
                'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
                'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre',
            ),
            'ru_RU' => array(
                'January' => 'Январь', 'February' => 'Февраль', 'March' => 'Март',
                'April' => 'Апрель', 'May' => 'Май', 'June' => 'Июнь',
                'July' => 'Июль', 'August' => 'Август', 'September' => 'Сентябрь',
                'October' => 'Октябрь', 'November' => 'Ноябрь', 'December' => 'Декабрь',
            ),
            'ja' => array(
                'January' => '1月', 'February' => '2月', 'March' => '3月',
                'April' => '4月', 'May' => '5月', 'June' => '6月',
                'July' => '7月', 'August' => '8月', 'September' => '9月',
                'October' => '10月', 'November' => '11月', 'December' => '12月',
            ),
            'it_IT' => array(
                'January' => 'Gennaio', 'February' => 'Febbraio', 'March' => 'Marzo',
                'April' => 'Aprile', 'May' => 'Maggio', 'June' => 'Giugno',
                'July' => 'Luglio', 'August' => 'Agosto', 'September' => 'Settembre',
                'October' => 'Ottobre', 'November' => 'Novembre', 'December' => 'Dicembre',
            ),
            'pt_BR' => array(
                'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro',
            ),
            'zh_CN' => array(
                'January' => '1月', 'February' => '2月', 'March' => '3月',
                'April' => '4月', 'May' => '5月', 'June' => '6月',
                'July' => '7月', 'August' => '8月', 'September' => '9月',
                'October' => '10月', 'November' => '11月', 'December' => '12月',
            ),
            'pl_PL' => array(
                'January' => 'Styczeń', 'February' => 'Luty', 'March' => 'Marzec',
                'April' => 'Kwiecień', 'May' => 'Maj', 'June' => 'Czerwiec',
                'July' => 'Lipiec', 'August' => 'Sierpień', 'September' => 'Wrzesień',
                'October' => 'Październik', 'November' => 'Listopad', 'December' => 'Grudzień',
            ),
            'nl_NL' => array(
                'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maart',
                'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'Augustus', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'December',
            ),
            'tr_TR' => array(
                'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık',
            ),
            'sv_SE' => array(
                'January' => 'Januari', 'February' => 'Februari', 'March' => 'Mars',
                'April' => 'April', 'May' => 'Maj', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'Augusti', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'December',
            ),
            'id_ID' => array(
                'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
                'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
            ),
            'ar' => array(
                'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
                'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
                'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
                'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر',
            ),
        );

        if (isset($months[$admin_locale])) {
            $date_str = str_replace(
                array_keys($months[$admin_locale]),
                array_values($months[$admin_locale]),
                $date_str
            );
        }

        return $date_str;
    }
}