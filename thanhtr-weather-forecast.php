<?php defined( 'ABSPATH' ) or die( 'You shall not pass' );
/*
Plugin Name: thanhtr Weather Forecast
Plugin URI:  myurl.here
Description: Just some plugin
Version:     1.0.1
Author:      Me
Author URI:  me.allme
License:     GPL2
*/

class WeatherForecast {
    private $wpdb, $table_current_weather, $table_forecast_weather;


    function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_current_weather = $wpdb->prefix . "thanhtrwf_current";
        $this->table_forecast_weather = $wpdb->prefix . "thanhtrwf_forecast";
        add_action( 'admin_init', array($this, 'storeWeatherData'));
        add_action( 'admin_menu', array($this, 'createMenu'));
    }

    function getWeatherData() {
        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20weather.forecast%20where%20woeid%20in%20(select%20woeid%20from%20geo.places(1)%20where%20text%3D%22helsinki%22)&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys");
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_HEADER, 0);
        if(curl_error($request)) {
            echo 'curl error: ' . curl_error($request);
            curl_close($request);
            return 'request_fail';
        } else {
            $result = curl_exec($request);
            curl_close($request);
            return json_decode($result);
        }
    }

    function extractWeatherData() {
        $table_current_weather = $this->table_current_weather;
        $table_forecast_weather = $this->table_forecast_weather;
        $today = $this->wpdb->get_row( "SELECT * FROM $table_current_weather");
        $forecast = $this->wpdb->get_results("SELECT * FROM $table_forecast_weather");
        echo '<p> Time: '.$today->date.'</p>';
        echo '<p> Temp: '.$today->temp.'</p>';
        echo '<p> Detail: '.$today->text.'</p>';
        echo '<p><span>Full url: </span><a target="_blank" href="'.$today->url.'">'.$today->url.'</a></p>';
        echo '<table style="width:100%">';
        for ($i = 0; $i <= 6; $i++) {
            $current = $forecast[$i];
            echo '<td>';
            echo '<p> Date: '.$current->date.'</p>';
            echo '<p> High: '.$current->high.'</p>';
            echo '<p> Low: '.$current->low.'</p>';
            echo '<p> Detail: '.$current->text.'</p>';
            echo '</td>';
        }
    }
    function storeWeatherData() {
        $table_current_weather = $this->table_current_weather;
        $table_forecast_weather = $this->table_forecast_weather;
        $charset_collate = $this->wpdb->get_charset_collate();
        $result = $this->wpdb->get_row("SELECT * FROM $table_current_weather");
        $weatherJson = $this->getWeatherData();
        if($weatherJson != 'request_fail') {
            if($this->wpdb->get_var("SHOW TABLES LIKE '$table_current_weather'") != $table_current_weather) {
                $sql_current = "CREATE TABLE $table_current_weather (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                date datetime DEFAULT '0000-00-00 00:00' NOT NULL,
                temp INTEGER NOT NULL,
                text text NOT NULL,
                url text NOT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql_current );
                echo 'create table';
            } else {
                $delete = $this->wpdb->query("TRUNCATE TABLE ".$table_current_weather);
            }
            if($this->wpdb->get_var("SHOW TABLES LIKE '$table_forecast_weather'") != $table_forecast_weather) {
                $sql_forecast = "CREATE TABLE $table_forecast_weather (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                date datetime DEFAULT '0000-00-00' NOT NULL,
                high INTEGER NOT NULL,
                low INTEGER NOT NULL,
                text text NOT NULL,
                PRIMARY KEY  (id)
                ) $charset_collate;";
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql_forecast );
                echo 'create table';
            } else {
                $delete = $this->wpdb->query("TRUNCATE TABLE ".$table_forecast_weather);
            }
            $info = $weatherJson->query->results->channel->item;
            $today = $info->condition;
            $forecast = $info->forecast;
            $queryDate = DateTime::createFromFormat('D, j M Y h:i A T',$today->date);
            $this->wpdb->insert($table_current_weather, array(
                'date' => date_format($queryDate, 'Y-m-d H:i'),
                'temp' => $today->temp,
                'text' => $today->text,
                'url' => $info->link
            ));
            for ($i = 1; $i <= 7; $i++) {
                $current = $forecast[$i];
                $insertDate = DateTime::createFromFormat('j M Y',$current->date);
                $this->wpdb->insert($table_forecast_weather, array(
                    'date' => date_format($insertDate, 'Y-m-d'),
                    'high' => $current->high,
                    'low' => $current->low,
                    'text' => $current->text
                ));
            }
        }
    }

    function createMenu() {
        add_menu_page('Tung Plugin', 'Tung Plugin', 'administrator', __FILE__, array($this, 'extractWeatherData'));
    }
}

$forecast = new WeatherForecast();
