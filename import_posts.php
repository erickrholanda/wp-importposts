<?php
/**
 * Plugin Name: Import Posts
 * Description: Importar posts para o projeto
 * Version: 1.0
 * Author: Erick Reis Holanda
 * Author URI: http://www.twitter.com/erickrholanda
 * License: GPL12
 */

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\IOFactory;
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ImportPost {
    public static $file;
    public static $plugin_url;
    public static $reset;
    public static $header;
    public static $post_type = 'property';
    public static $post_status;
    public static $messages = array();
    public static $columns = array();
    public static $extensions = array();
    public static $loadSpreadshet = array();
    public static $dependencies = array();
    
    const PHP_MIN_VERSION = '5.6.0';
    const ADMIN_TEMPLATE_FOLDER = "admin/";
    const ADMIN_URL = "importposts.php";
    
    
    public static function init() {
        add_action( 'admin_menu', 'ImportPost::admin_menu' );
        self::$extensions[] = 'csv';
        self::$plugin_url = admin_url('admin.php?page=' . self::ADMIN_URL);
        self::$dependencies = array(
            'PHP extension XML' => extension_loaded('xml'),
            'PHP extension xmlwriter' => extension_loaded('xmlwriter'),
            'PHP extension mbstring' => extension_loaded('mbstring'),
            'PHP extension ZipArchive' => extension_loaded('zip'),
            'PHP extension GD (optional)' => extension_loaded('gd'),
            'PHP extension dom (optional)' => extension_loaded('dom')
        );
    }
    
    public static function load_spreadsheet_lib() {
        self::$loadSpreadshet = true;
        
        if (version_compare(PHP_VERSION, self::PHP_MIN_VERSION, '<') ) {
            self::$loadSpreadshet = false;
            self::$messages[] = 'Versão do PHP não atende: ' . phpversion() . " . Versão mínima: " . self::PHP_MIN_VERSION;
        }
        foreach( self::$dependencies as $label => $result) {
            if (!$result) {
                self::$loadSpreadshet = false;
                self::$messages[] = $label;
            }
        }

        if (self::$loadSpreadshet) {
            self::$extensions[] = 'xls';
            self::$extensions[] = 'xlsx';
        }
        else {
            array_unshift(self::$messages, 'Classe SpreadSheet não foi carregada.');
        }
    }

    public static function admin_menu() {
        add_menu_page("Import Posts",
            "Importar Posts",
            'edit_others_posts',
            'importposts.php',
            'ImportPost::admin_page',
            'dashicons-upload',
            6
        );
    }
    
    public static function save_upload_file($file) {
        if (defined( 'ALLOW_UNFILTERED_UPLOADS') === false) {
            define( 'ALLOW_UNFILTERED_UPLOADS', true );
        }
        $upload_overrides = array( 'test_form' => false );
        
        $movefile = wp_handle_upload( $file, $upload_overrides );
        
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            return $movefile;
        } else {
            self::$messages[] = $movefile['error'];
        }
    }

    public static function validate_form_update_submit() {
        $error = count(self::$messages);
        if (self::$file) {
           $extension = self::get_file_extension();
            if (!in_array(strtolower($extension), self::$extensions )) {
                self::$messages[] = "Tipo de arquivo({$extension}) não suportado. Extensões permitidas: ". implode(', ', self::$extensions);
            }
        }
        else {
            self::$messages[] = 'Arquivo obrigatório.';
        }
        
        if(!self::$post_type) {
            self::$messages[] = 'O tipo de post é obrigatório.';
        }

        // Se o numero de mensagens for o mesmo, não houve erro.
        return $error == count(self::$messages);
    }

    public static function display_messages() {
        if (!empty(self::$messages)):
        ?>
        <div class="updated error is-dismissible">
            <?php foreach(self::$messages as $message): ?>
                <p><?php print $message; ?></p>
            <?php endforeach; ?>
        </div>
        <?php
        endif;
    }

    public static function admin_page() {
        self::load_spreadsheet_lib();
        if (!empty($_FILES)) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }

            self::$file = self::save_upload_file($_FILES['file']);
            self::$reset = isset($_POST['reset'])?$_POST['reset'] : false;
            self::$header = isset($_POST['header'])?$_POST['header'] : false;
            self::$post_type = isset($_POST['post_type'])?$_POST['post_type'] : false;
            self::$post_status = isset($_POST['post_status'])?$_POST['post_status'] : false;

            if (self::validate_form_update_submit()) {

                self::import_file_content();
                
                // require self::ADMIN_TEMPLATE_FOLDER . 'import-form.php';
                // return;
            }
        }
        else if (isset($_POST['file']) && isset($_POST['url']) && isset($_POST['type'])) {
            self::$file = array(
                'file' => $_POST['file'],
                'url' => $_POST['url'],
                'type' => $_POST['type'],
            );
            self::$reset = isset($_POST['reset'])?$_POST['reset'] : false;
            self::$post_type = isset($_POST['post_type'])?$_POST['post_type'] : false;
        }
        
        require self::ADMIN_TEMPLATE_FOLDER . 'upload-form.php';
    }

    public static function import_file_content() {
        $content = self::get_file_content();
        
        $error = 0;
        $success = 0;
        if (!empty($content)) {
            if (self::$reset) {
                global $wpdb;
                $sql = "UPDATE {$wpdb->prefix}posts
                SET post_status = 'archived'
                WHERE post_status <> 'archived' AND post_type = %s";
                $wpdb->query( $wpdb->prepare($sql, self::$post_type) );
            }

            foreach($content as $line => $values) {
                if (self::$header && $line == 1) continue;
                $values = array_values($values);
                $data = $values[4];
                if ($data) {
                    if (strpos($data, '-') !== false) {
                        $data = explode('-', $data);
                        if (count($data) == 2) {
                            $dia = str_pad($data[0], 2, '0', STR_PAD_LEFT);
                            $mes = self::convert_mes($data[1]);
                            $data = "{$dia} de {$mes}";
                        }
                        else {
                            $data = implode('-', $data);
                        }
                    }
                }
                if ($values[2] == '') continue;
                $location = self::get_term_or_create('location', $values[0]);
                $locations = array();
                if ($location) {
                    $locations[] = $location->term_id;
                }
                $post_values = array(
                    'post_title' => $values[2],
                    'post_content' => $values[2],
                    'post_status' => self::$post_status,
                    'post_type' => self::$post_type,
                    'meta_input' => array(
                        'price' => array(
                            $values[5]
                        ),
                        'pprice' => array(
                            $values[5],
                            '$'
                            ),
                            'essentialinformation' => array(
                                array(
                                'essentialtitle' => 'Data do leilão',
                                'essentialvalue' => $data
                                )
                            ),
                        ),
                    'tax_input' => array(
                            'location' => $locations
                        )
                );
               
                $return = self::create_post($post_values);
                if (is_wp_error($return)) {
                    $error++;
                    self::$messages[] = 'Linha #' .$line . ': ' . $return->get_error_message;
                }
                else {
                    $success++;
                }
            }
            self::$messages[] = "Importação concluída. Sucesso: {$success}. Error: {$error}";
        }
        else {
            self::$messages[] = 'Arquivo vazio.';
        }
    }

    public static function get_file_extension() {
        if (self::$file['file']) {
            $extension = explode('.', self::$file['file']);
            return $extension[count($extension) -1];
        }
    }

    public static function get_file_content() {
        if (self::$file) {
            if (!isset(self::$file['content']) || empty(self::$file['content'])) {
                $method = 'get_file_content_' . self::get_file_extension();

                if (method_exists('ImportPost', $method)) {
                    self::$file['content'] = self::$method();
                }
                else {
                    self::$messages[] = "Não existe metodo para importar este formato. ({$method})";
                }
            }

            return self::$file['content'];
        }
    }

    public static function get_file_content_csv() {
        // self::$messages[] = 'get_file_content_csv()';
        $inputFileName = self::$file['file'];
        $reader = new Csv();
        $spreadsheet = $reader->load($inputFileName);

        // $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        // return $sheetData;
    }
    
    public static function get_file_content_xlsx() {
        // self::$messages[] = 'get_file_content_xlsx()';
        // var_dump(self::$file);
        $inputFileName = self::$file['file'];
        $reader = new Xlsx();
        $spreadsheet = $reader->load($inputFileName);

        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        return $sheetData;
    }
    
    public static function get_file_content_xls() {
        $inputFileName = self::$file['file'];
        $reader = new Xls();
        $spreadsheet = $reader->load($inputFileName);

        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        return $sheetData;
    }

    public static function create_post($post_values) {
        /**
         * wp_insert_post()
         * 
         * https://developer.wordpress.org/reference/functions/wp_insert_post/
         * 
         * post_title
         * post_content
         * post_status
         * post_type
         * 
         * meta_input
         * 
         * tax_input
         */

        return wp_insert_post($post_values, true); 
    }

    public static function convert_mes($mes, $type = 'nome') {
        $mesList = array(
            01 => 'Janeiro',
            02 => 'Fevereiro',
            03 => 'Março',
            04 => 'Abril',
            05 => 'Maio',
            06 => 'Junho',
            07 => 'Julho',
            08 => 'Agosto',
            09 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        );

        foreach($mesList as $key => $value) {
            if(substr($value, 0 ,strlen($mes)) == $mes) {
                return $type == "nome"? $value:$key;
            }
        }
    }

    public static function get_term_or_create($taxonomy, $term) {
        $item = get_term_by( 'name', $term, $taxonomy);
        if (!$item) {
            $item = wp_insert_term( $term, $taxonomy); 
        }
        return is_wp_error($item) ? false : $item;
    }

    public static function pre($value) {
        print "<pre>";
        var_dump($value);
        print "</pre>";
    }
}

add_action( 'init', 'ImportPost::init' );