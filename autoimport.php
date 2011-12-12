<?php

/**
 * @package Spolecznosci
 * @version 0.1
 */
/*
	Plugin Name: Blog Auto Importer
	Plugin URI: 
	Description: Automatyczne importowanie postów, stron, komentarzy, kategorii, tagów i mediów z innych istalacji WordPressa.
	Author: Spolecznosci.net
	Version: 0.1
	Author URI: http://spolecznosci.net
*/

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	define( 'WP_LOAD_IMPORTERS', true );
}

$cwd = getcwd();

wp_enqueue_script('jquery');

define('SPOLECZNOSCI_IMPORT_TEMP_DIR',$cwd.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'wp-content'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'spolecznosci-autoimport'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR);

function init_curl($url, $getCookie = false, $setCookie = false, $post = '') {
	
	$cookie_name = SPOLECZNOSCI_IMPORT_TEMP_DIR.'wp_cookie';
	
	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);

	curl_setopt($ch, CURLOPT_FAILONERROR, 0);
	//curl_setopt($ch, CURLOPT_MUTE, 0);
	curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);

	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 570);
	curl_setopt($ch, CURLOPT_TIMEOUT, 570);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

	curl_setopt($ch, CURLOPT_ENCODING, "");

	curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 0);

	curl_setopt($ch, CURLOPT_COOKIEFILE, 1);

	if($getCookie) {
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie_name);
	}

	if($setCookie) {
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie_name);
	}

	if(!empty($post)) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__)."/cacert.pem");

	return $ch;
}

function WPcurlLogin($domain,$login,$password) {

	$url = 'http://'.$domain.'/wp-login.php';
	$post = array(
		'log'=>$login,
		'pwd'=>$password,
		'wp-submit'=>'Log+In',
		//'redirect_to'=>'https%3A%2F%2F'.$domain.'%2Fwp-admin%2F',
		//'testcookie'=>'1'
	);

	$ch = init_curl($url, true, false, $post);
	$output = curl_exec($ch);
	$headers = curl_getinfo($ch);
	curl_close($ch);

}

function WPcurlGetExport($domain) {
	$url = 'http://'.$domain.'/wp-admin/export.php?download=true&content=all&cat=0&post_author=0&post_start_date=0&post_end_date=0&post_status=0&page_author=0&page_start_date=0&page_end_date=0&page_status=0&submit=Download+Export+File';
	
	$ch = init_curl($url, false, true);
	$output = curl_exec($ch);
	$headers = curl_getinfo($ch);
	curl_close($ch);
	
	if(strpos($headers['url'],'/wp-login.php') !== false) { ?>
		<div class="error settings-error"> 
		<p><strong>Niepoprawny login lub hasło.</strong></p></div>
	<?php }
	
	$pos = strpos($output,'<?xml');
	
	if($pos !== false) {
		$output = substr($output, $pos);

		file_put_contents(SPOLECZNOSCI_IMPORT_TEMP_DIR.'blog_export.xml', $output);
		
		return true;
	} else { ?>
		<div class="error settings-error"> 
		<p><strong>Nie udało się pobrać pliku Exportu. Sprawdź poprawność wprowadzonych danych.</strong></p></div>
	<?php }
	
	return false;
}

function WP_import($domain,$login,$password) {
	WPcurlLogin($domain,$login,$password);
	if(WPcurlGetExport($domain)) {
		require_once realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'spolecznosci-wp-importer.php';

		$wp_import = new Spolecznosci_WP_Import();
		$wp_import->fetch_attachments = true;
		$wp_import->id = 0;

		$file = SPOLECZNOSCI_IMPORT_TEMP_DIR.'blog_export.xml';

		$import_data = $wp_import->parse( $file );
		if ( is_wp_error( $import_data ) ) {
			echo '<p><strong>Błąd</strong><br />';
			echo esc_html( $import_data->get_error_message() ) . '</p>';
			return false;
		}

		if ($import_data['version'] > $wp_import->max_wxr_version ) {
			echo '<div class="error"><p><strong>';
			echo 'Zaimportowane dane są w wersji wyższej ('.$import_data['version'].') niż obsługiwana przez importer. Jeżeli importowanie nie wykonało się poprawnie skontaktuj się z administratorem społeczności.net (<a href="mailto:admin@spolecznosci.net">admin@spolecznosci.net</a>)';
			echo '</strong></p></div>';
		}

		return true;		
	}
	
	return false;
}

function WP_import_go() {
	require_once realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'spolecznosci-wp-importer.php';
	$wp_import = new Spolecznosci_WP_Import();
	$wp_import->fetch_attachments = true;
	$wp_import->id = 0;
	set_time_limit(0);
	$file = SPOLECZNOSCI_IMPORT_TEMP_DIR.'blog_export.xml';
	$wp_import->import($file);
}

add_action('admin_menu', 'spolecznosci_importplugin_menu');

function spolecznosci_importplugin_menu() {
	add_utility_page('Importuj Bloga', 'Importuj Bloga', 'manage_options', 'spolecznosci-import-options', 'spolecznosci_importplugin_options');
}

function spolecznosci_importplugin_options() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}
	
	if(!empty($_POST['import_go'])) {
		WP_import_go();
		return;
	}
	
	$wp_error = new WP_Error();
	
	$imported = false;
	
	if (!empty($_POST)) {
		if(empty($_POST['domain'])) {
			$wp_error->add('empty_domain', 'Podaj adres strony');
		} else {
			$domain = str_replace(array('http:','https:','/'),'',$_POST['domain']);
		}
		if(empty($_POST['login'])) {
			$wp_error->add('empty_domain', 'Podaj nazwę użytkownika');
		} else {
			$login = $_POST['login'];
		}
		if(empty($_POST['password'])) {
			$wp_error->add('empty_domain', 'Podaj hasło');
		} else {
			$password = $_POST['password'];
		}
		
		if(!$wp_error->get_error_code()) {
			switch($_POST['service']) {
				case 'wordpress':
					$imported = WP_import($domain,$login,$password);
					break;

				default:
					break;
			}
		} else {
			?>
			<div class="error settings-error"> 
			<p><?php
			foreach ( $wp_error->get_error_codes() as $code ) {
				$severity = $wp_error->get_error_data($code);
				foreach ( $wp_error->get_error_messages($code) as $error ) {
					echo $error . "<br />\n";
				}
			}
			?></p></div><?php
		}
	}

	
	?>
		<div class="wrap">

		<div class="icon32" id="icon-tools"><br></div>
		<h2>Automatycznie zaimportuj swojego bloga z innych serwisów</h2><br />
		
		<?php if($imported) { ?>
		<script type="text/javascript">
			jQuery.post('/wp-admin/admin.php?page=spolecznosci-import-options',{import_go:true});
		</script>
		<p>
			Twój blog jest właśnie importowany, może to potrwać kilka minut.
			<br /><a href="/">Przejdź do swojej strony</a>.
		</p>
		<?php } else { ?>
		<script type="text/javascript">
			function showAjax() {
				document.getElementById('importForms').style.display = 'none';
				document.getElementById('importAjax').style.display = 'block';
			}
		</script>
		<div id="importAjax" style="display:none">
			<p>
				<strong><img alt="" style="visibility:visible" class="ajax-loading" src="/wp-admin/images/wpspin_light.gif" /></strong>
			</p>
		</div>
		<div id="importForms">
			<form action="" method="post" onsubmit="showAjax()">
				<table>
					<tr>
						<td colspan="2">
							<strong>Zaimportuj z innego WordPressa:</strong>
						</td>
					</tr>
					<tr>
						<td>
							<label for="domain">Adres strony: </label>
						</td>
						<td>
							<input id="domain" name="domain" value="" type="text" />
						</td>
					</tr>
					<tr>
						<td>
							<label for="login">Nazwa użytkownika*: </label>
						</td>
						<td>
							<input id="login" name="login" value="" type="text" />
						</td>
					</tr>
					<tr>
						<td>
							<label for="password">Hasło*: </label>
						</td>
						<td>
							<input id="password" name="password" value="" type="password" />
						</td>
					</tr>
					<tr>
						<td>
							&nbsp;
						</td>
						<td>
							<input name="service" value="wordpress" type="hidden" />
							<input class="button-primary" value="Importuj" type="submit" />
						</td>
					</tr>
					<tr>
						<td colspan="2">
							* wymagane jest konto admintratora
						</td>
					</tr>
				</table>
			</form>
		</div>
		<?php } ?>
	</div>
	<?php
}

